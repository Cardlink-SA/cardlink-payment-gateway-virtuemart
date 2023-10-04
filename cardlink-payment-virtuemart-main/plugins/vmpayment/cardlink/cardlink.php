<?php
/**
 *
 * Cardlink payment plugin
 *
 */

defined('_JEXEC') or die('Restricted access');
if (!class_exists('vmPSPlugin')) require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
if (!class_exists('vRequest')) { class vRequest extends JRequest{} }
if (!class_exists('CurrencyDisplay')) require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'currencydisplay.php');
if (!class_exists('PaymentCardlinkHelper')) require(JPATH_PLUGINS.DS.'vmpayment'.DS.'cardlink'.DS.'cardlink'.DS.'compatibility'.DS.'vmpsplugin.php');
if (!class_exists('vmText')) require(JPATH_PLUGINS.DS.'vmpayment'.DS.'cardlink'.DS.'cardlink'.DS.'compatibility'.DS.'vmtext.php');
//ini_set('display_errors',1);
//error_reporting(E_ALL);
class plgVmPaymentCardlink extends PaymentCardlinkHelper
{

	protected $gatewayName = 'cardlink';
	
	function __construct(& $subject, $config) {
		parent::__construct($subject, $config);
		jimport( 'joomla.filesystem.file' );

		$this->_loggable = TRUE;
		$this->tableFields = array_keys($this->getTableSQLFields());
		$this->_tablepkey = 'id'; //virtuemart_cardlink_id';
		$this->_tableId = 'id'; //'virtuemart_cardlink_id';
		$varsToPush = array(
			'acquirer' => array(0, 'int'),
			'mid' => array('', 'char'),
			'secretkey' => array('', 'char'),
			'demoaccount' => array(0, 'int'),
			'allowinstallments' => array(1, 'int'),
			'max_installments' => array(0, 'int'),
			'installments'  => array('', 'char'),
			'payment_currency' => array('', 'int'),
			'payment_logos' => array('', 'char'),
			'paymeth' => array('auto', 'char'),
			'referenceid' => array('order_number', 'char'),			
			'paytype' => array(1, 'int'),
			'tokenization' => array(0, 'int'),
			'iframe' => array(0, 'int'),
			'css_url' => array('', 'char'),
			'version' => array(1, 'int'),
			'debug' => array(0, 'int'),
			'log' => array(0, 'int'),
			'jspos' => array(1, 'int'),
			
			'status_pending' => array('P', 'char'),
			'status_success' => array('C', 'char'),
			'status_canceled' => array('X', 'char'),
			'status_expired' => array('X', 'char'),
			'status_capture' => array('C', 'char'),
			'status_refunded' => array('R', 'char'),
			'status_partial_refunded' => array('R', 'char'),
			'no_shipping' => array('', 'int'),
			
			//Restrictions
			'countries' => array('', 'char'),
			'min_amount' => array('', 'float'),
			'max_amount' => array('', 'float'),
			'publishup' => array('', 'char'),
			'publishdown' => array('', 'char'),

			//discount
			'cost_per_transaction' => array('', 'float'),
			'cost_percent_total' => array('', 'char'),
			'tax_id' => array(0, 'int'),
		);
		$this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
	}
	
	public function getVmPluginCreateTableSQL()
	{
		return $this->createTableSQL('Cardlink Table');
	}

	function getTableSQLFields()
	{
		$SQLfields = array(
			'id' => 'int(11) UNSIGNED NOT NULL AUTO_INCREMENT',
			'virtuemart_order_id' => 'int(1) UNSIGNED',
			'order_number' => 'char(64)',
			'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
			'payment_name' => 'varchar(5000)',
			'payment_order_total' => 'decimal(15,5) NOT NULL',
			'payment_currency' => 'smallint(1)',
			'cost_per_transaction' => 'decimal(10,2)',
			'cost_percent_total' => 'decimal(10,2)',
			'tax_id' => 'smallint(1)',
			'installments' => 'int(3) UNSIGNED',
			'cardlink_orderid' => 'varchar(50)',
			'cardlink_paymenttotal' => 'decimal(10,2)',
			'cardlink_message' => 'varchar(128)',
			'cardlink_txid' => 'int(15) UNSIGNED',
			'cardlink_paymentref' =>  'int(15) UNSIGNED',
			'cardlink_riskscore' => 'int(3) UNSIGNED',
			'cardlink_status' => 'varchar(30)',
			'cardlink_fullresponse' => 'text',
		);
		return $SQLfields;
	}
	
	public function plgVmDisplayListFEPayment (VirtueMartCart $cart, $selected = 0, &$htmlIn)
	{
		return parent::plgVmDisplayListFEPayment($cart,$selected,$htmlIn);
	}
	
	function plgVmConfirmedOrder($cart, $order)
	{

		if (!($this->_currentMethod = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($this->_currentMethod->payment_element))
		{
			return FALSE;
		}

		if (!class_exists('VirtueMartModelOrders'))
		{
			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
		}
		
		$session = JFactory::getSession ();
		$app = JFactory::getApplication();
		$this->getPaymentCurrency($this->_currentMethod);
		$currency = $this->getEmailCurrency($this->_currentMethod);
		
		if((int)$this->_currentMethod->payment_currency==0) $this->_currentMethod->payment_currency=$order['details']['BT']->order_currency;
		$paymentCurrency = CurrencyDisplay::getInstance($this->_currentMethod->payment_currency);
		
		if(method_exists('vmPSPlugin','getAmountValueInCurrency')){
			$this->totalOrder = vmPSPlugin::getAmountValueInCurrency($order['details']['BT']->order_total, $this->_currentMethod->payment_currency);
		}else{
			$this->totalOrder =round($paymentCurrency->convertCurrencyTo($this->_currentMethod->payment_currency, $order['details']['BT']->order_total, FALSE), 2);
		}
		
		$acquirer = $this->_currentMethod->acquirer;
		$demoaccount = $this->_currentMethod->demoaccount;

		$this->doseis=0;
		$this->installmentOptions=array();
		if(isset($this->_currentMethod->allowinstallments) && $this->_currentMethod->allowinstallments && !empty($this->_currentMethod->installments))
		{
			$this->installmentOptions=$this->findInstallments($this->_currentMethod->installments,$this->totalOrder);
			$this->doseis=count($this->installmentOptions)?max($this->installmentOptions):0;
		}

		$payid=$this->_currentMethod->virtuemart_paymentmethod_id;
		$installments=isset($_POST['installments'][$payid])?(int)$_POST['installments'][$payid]:(int)$session->get('vmpayinstallments'.$payid,0);
		if($this->doseis>0 && $installments>0)
		{
			$installments=($installments>$this->doseis)?$this->doseis:$installments;
		}else{
			$installments=0;
		}
		
		$virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order['details']['BT']->order_number);
		$address = ((isset($order['details']['ST'])) ? $order['details']['ST'] : $order['details']['BT']);

		$refID=$this->_currentMethod->referenceid=='order_number'?$order['details']['BT']->order_number:$virtuemart_order_id;
		
		// Prepare data that should be stored in the database
		$dbValues['order_status'] = $this->_currentMethod->status_pending;
		$dbValues['order_number'] = $order['details']['BT']->order_number;
		$dbValues['payment_name'] = $this->renderPluginName($this->_currentMethod);
		$dbValues['virtuemart_paymentmethod_id'] = $cart->virtuemart_paymentmethod_id;
		$dbValues['cost_per_transaction'] = $this->_currentMethod->cost_per_transaction;
		$dbValues['cost_percent_total'] = $this->_currentMethod->cost_percent_total;
		$dbValues['payment_currency'] = $this->_currentMethod->payment_currency;
		$dbValues['payment_order_total'] =$this->totalOrder;
		$dbValues['tax_id'] = $this->_currentMethod->tax_id;
		$dbValues['installments'] = $installments;	
		$this->storePSPluginInternalData($dbValues);
		
		jimport('joomla.environment.browser');
		$browser = JBrowser::getInstance();
		if(method_exists('VmConfig','loadJLang')) VmConfig::loadJLang('com_virtuemart_orders',TRUE);
		$lang = explode('-',JFactory::getLanguage()->getTag());
		$billCountry=ShopFunctions::getCountryByID($order['details']['BT']->virtuemart_country_id, 'country_2_code');
		$shipCountry=ShopFunctions::getCountryByID($address->virtuemart_country_id, 'country_2_code');
		$post=array(
			'version'=>2,
			'mid'=>JString::trim($this->_currentMethod->mid),
			'lang'=>$lang[0]=='el'?'el':'en',
			'orderid'=>str_replace(array('_','-'),'',$refID),
			'orderDesc'=>'Order: '.$refID,
			'orderAmount'=> number_format($this->totalOrder,2,".",""),
			'currency'=> ShopFunctions::getCurrencyByID($this->_currentMethod->payment_currency,'currency_code_3'),
			'payerEmail'=> $order['details']['BT']->email,
			'billCountry'=> $billCountry,
			'billState'=> isset($order['details']['BT']->virtuemart_state_id) ? ShopFunctions::getStateByID($order['details']['BT']->virtuemart_state_id, 'state_name') : '',
			'billZip'=>str_replace(' ','',$order['details']['BT']->zip),
			'billCity'=>JString::trim($order['details']['BT']->city),
			'billAddress'=>!empty($order['details']['BT']->address_1)?JString::trim($order['details']['BT']->address_1):JString::trim($order['details']['BT']->address_2),
			'trType'=>(int)$this->_currentMethod->paytype,
		);

		
		if($billCountry=='GR' || empty($post['billState'])) unset($post['billState']);
		
		if($installments>0)
		{
			$post['extInstallmentoffset']=0;
			$post['extInstallmentperiod']=$installments;
		}

		if($this->_currentMethod->css_url){
			$post['cssUrl'] = $this->_currentMethod->css_url;
		}
		
		$post['confirmUrl']=JURI::root().'?com=vm&b=cardlink&pmode=ok&pm='.$order['details']['BT']->virtuemart_paymentmethod_id.'&on='.$refID;
		$post['cancelUrl']=JURI::root().'?com=vm&b=cardlink&pmode=cancel&pm='.$order['details']['BT']->virtuemart_paymentmethod_id.'&on='.$refID;
		
		$urlParams=array('Itemid'=>vRequest::getInt('Itemid',(int)$app->getMenu()->getActive()->id),'lang'=>vRequest::getCmd('lang',$lang[0]));
		foreach($urlParams as $key=>$val)
		{
			if($val=='' || $val===0) continue;
			$post['confirmUrl'].='&'.$key.'='.urlencode($val);
			$post['cancelUrl'].='&'.$key.'='.urlencode($val);
		}


		/* $tokenization = isset($_POST['tokenization'])?(int)$_POST['tokenization']:(int)$session->get('tokenization');
		if ( $tokenization ) {
			$post['extTokenOptions'] = 100;
		}  */

		$tokenization = isset($_POST['tokenization'])?(int)$_POST['tokenization']:(int)$session->get('tokenization');
		$selected_card = isset($_POST['cardlink-card'])?(int)$_POST['cardlink-card']:(int)$session->get('cardlink-card');
		if ( $tokenization ) {
			$post['extTokenOptions'] = 100;
		} else {
			if ( $selected_card ) {
				$post['extTokenOptions'] = 110;
				$post['extToken']        = $selected_card;
			}
		}

		//var_dump($post);
		//return;

		$form_secret = $this->_currentMethod->secretkey;
		$form_data   = iconv( 'utf-8', 'utf-8//IGNORE', implode( "", $post ) ) . $form_secret;
		$post['digest'] 	 = base64_encode( hash( 'sha256', ( $form_data ), true ) );
		
		$url = '';
		if ( $demoaccount ) {
			switch ( $acquirer ) {
				case 0 :
					$url = "https://ecommerce-test.cardlink.gr/vpos/shophandlermpi";
					break;
				case 1 :
					$url = "https://alphaecommerce-test.cardlink.gr/vpos/shophandlermpi";
					break;
				case 2 :
					$url = "https://eurocommerce-test.cardlink.gr/vpos/shophandlermpi";
					break;
			}
		} else {
			switch ( $acquirer ) {
				case 0 :
					$url = "https://ecommerce.cardlink.gr/vpos/shophandlermpi";
					break;
				case 1 :
					$url = "https://www.alphaecommerce.gr/vpos/shophandlermpi";
					break;
				case 2 :
					$url = "https://vpos.eurocommerce.gr/vpos/shophandlermpi";
					break;
			}
		}

		$cart->_confirmDone = FALSE;
		$cart->_dataValidated = FALSE;
		$cart->setCartIntoSession();
	
		
		if( $use_iframe = $this->_currentMethod->iframe ){
			$html = $this->renderByLayout('form_iframe', array(
				"form_params" => $post,
				"form_url" => $url,		
				"logos" => $this->getLogos($this->_currentMethod),
				"params" => $this->_currentMethod,
			));
			$app->input->set('html', $html);
			vRequest::setVar('html', $html);

		}else{
			$html = $this->renderByLayout('form', array(
				"form_params" => $post,
				"form_url" => $url,							
				"logos" => $this->getLogos($this->_currentMethod),
				"params" => $this->_currentMethod,
			));
			$app->input->set('html', $html);
			vRequest::setVar('html', $html);
		}

		$this->log('plgVmConfirmedOrder: Form Fields',$post);
		$this->log('plgVmConfirmedOrder: Order',$order);
		$this->log('plgVmConfirmedOrder: HTML Form',$html);
	}

	/**
	 * @param $html
	 * @return bool|null|string
	 */
	function plgVmOnPaymentResponseReceived(&$html) {

		if (!class_exists('VirtueMartCart')) {
			require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
		}
		if (!class_exists('shopFunctionsF')) {
			require(JPATH_VM_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php');
		}
		if (!class_exists('VirtueMartModelOrders')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
		}
		
		$app = JFactory::getApplication();
		$virtuemart_paymentmethod_id=$app->input->getInt('pm',0);
		$orderReference=$app->input->get('on',$app->input->get('orderid'));
		
		$debug='virtuemart_paymentmethod_id='.$virtuemart_paymentmethod_id."\n";
		$debug.='orderReference='.$orderReference."\n";
		$debug.='$_POST='.print_r($_POST,true)."\n";
		//mail('info@cardlink.gr','cardlink',$debug);
		
		$vendorId = 1;
		if(!($this->_currentMethod = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			//return NULL; // Another method was selected, do nothing
		}	
		
		if(count($_POST)>0 && !empty($orderReference))
		{
			$this->log('plgVmOnPaymentResponseReceived: _POST',$_POST);
			$rsp=$this->plgVmOnPaymentNotification();
		}
		
		if (!$this->selectedThisElement($this->_currentMethod->payment_element)) {
			return NULL;
		}
		
		if($this->_currentMethod->referenceid=='order_number')
		{
			if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($orderReference))) {
				return NULL;
			}
			$order_number=$orderReference;
		}
		else
		{
			$virtuemart_order_id=(int)$orderReference;
		}
		
	
		if (!($payments = $this->getDatasByOrderId ($virtuemart_order_id)))
		{
			return NULL;
		}
		
		$payment = is_array($payments)?end($payments):$payments;
		$order_number=$payment->order_number;
		$payment_name = $this->renderPluginName($this->_currentMethod);

		$orderModel = VmModel::getModel('orders');
		$order = $orderModel->getOrder($virtuemart_order_id);

		$currency = CurrencyDisplay::getInstance('',$order['details']['BT']->virtuemart_vendor_id);
		
		$success=true;
		$ResponseData=new stdclass;
		if(count($_POST) && !empty($virtuemart_order_id) && $this->validateDigest())
		{
			$ResponseData=json_decode(json_encode($_POST));
		}
		
		$total=(!isset($payment->payment_order_total) || !$payment->payment_order_total)?$api->paymentTotal:$payment->payment_order_total;
		$total=(!isset($payment->cardlink_paymenttotal) || !$payment->cardlink_paymenttotal)?$total:$payment->cardlink_paymenttotal;

		if( $_POST['extToken'] ){
			$this->saveToken($_POST);
		}

		VmConfig::loadJLang('com_virtuemart_orders',TRUE);
		$html = $this->renderByLayout('response', array("success" => $success,
		                                                "payment_name" => $payment_name,
		                                                "response" => $ResponseData,
														"payment" => $payment,
		                                                "order" => $order,
		                                                "currency" => $currency,
														"total" => (float)$total,
														"params" => $this->_currentMethod,
													));
		$cart = VirtueMartCart::getCart();
		$cart->emptyCart();
		return TRUE;
	}

	function saveToken($response)
	{
		$token = $response['extToken'];
		//$user_id = JFactory::getUser()->id;
		$last4 = $response['extTokenPanEnd'];
		$payMethod = $response['payMethod'];
		$extTokenExp = $response['extTokenExp'];
		$extTokenExpYear  = substr( $extTokenExp, 0, 4 );
		$extTokenExpMonth = substr( $extTokenExp, 4, 2 );

		$orderid = $response['orderid'];
		$order_model = VmModel::getModel('orders');
		$myorderid = $order_model->getOrderIdByOrderNumber($orderid);
        $myorder = $order_model->getOrder($myorderid);
        $user_id = $myorder['details']['BT']->virtuemart_user_id;
		
		$db = JFactory::getDBO();
		$db->setQuery ('SELECT * FROM `#__virtuemart_payment_plg_cardlink_tokens` WHERE `user_id`=' . $user_id);
		$user_tokens = $db->loadObjectList();
		$token_exist = 0;

		if ( !empty($user_tokens) ) {
			foreach ( $user_tokens as $key => $row ) {
				//if( $token == $row->token ){
				if ( $row->card_type == $payMethod && $row->last4 == $last4 && $row->expiry_year == $extTokenExpYear && $row->expiry_month == $extTokenExpMonth ) {
					$token_exist = 1;
				}
			}
		}

		if( !$token_exist  ){
			$db = JFactory::getDBO();
			$db->setQuery ("INSERT INTO #__virtuemart_payment_plg_cardlink_tokens(token, user_id, type, last4, expiry_year, expiry_month, card_type) VALUES ('". $token ."', '". $user_id ."', 'CC', '". $last4 ."', '". $extTokenExpYear ."', '". $extTokenExpMonth ."', '". $payMethod ."')");
			$db->execute();
			//for joomla 2.5 $db->query();
		}
	}

	function plgVmOnUserPaymentCancel()
	{
		if (!class_exists('VirtueMartModelOrders')) require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
		
		$app = JFactory::getApplication();
		$virtuemart_paymentmethod_id=$app->input->getInt('pm',0);
		$orderReference=$app->input->get('on',$app->input->get('orderid'));

		$orderid = $response['orderid'];
		$order_model = VmModel::getModel('orders');
        $myorder = $order_model->getOrder($orderid);		
		
		if (empty($orderReference) or empty($virtuemart_paymentmethod_id) or !$this->selectedThisByMethodId($virtuemart_paymentmethod_id)) {
			return NULL;
		}
		if (!($this->_currentMethod = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		
		if($this->_currentMethod->referenceid=='order_number')
		{
			if(!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($orderReference))) {
				return NULL;
			}
			
			$order_number=$orderReference;
			if (method_exists($this,'getDataOrderNumber') && !($payments = $this->getDataOrderNumber($order_number)))
			{
				return NULL;
			}
		}
		else
		{
			if (!($payments = $this->getDatasByOrderId ($orderReference)))
			{
				return NULL;
			}
			$virtuemart_order_id=(int)$orderReference;
		}
		
		$this->log('plgVmOnUserPaymentCancel: _POST',$_POST);

		if($this->validateDigest())
		{
			$this->handlePaymentUserCancel($virtuemart_order_id);
			JFactory::getApplication()->enqueueMessage( vmText::_('VMPAYMENT_CARDLINK_FAILED_TRYAGAIN'),'error');
			if(isset($_POST['message'])) vmWarn($_POST['message']);
		}
		return TRUE;
	}
	
	function plgVmOnPaymentNotification() {
		if (!class_exists('VirtueMartModelOrders'))	require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');

		$cardlink_data = (array)$_POST;
		$app = JFactory::getApplication();
		$virtuemart_paymentmethod_id=$app->input->getInt('pm',0);
		$orderReference=$app->input->get('on',$app->input->get('orderid'));
		
		if (!($this->_currentMethod = $this->getVmPluginMethod($virtuemart_paymentmethod_id)))
		{
			return NULL; // Another method was selected, do nothing
		}
		
		if($this->_currentMethod->referenceid=='order_number')
		{
			if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($orderReference)))
			{
				return FALSE;
			}
			
			$order_number=$orderReference;
			if (!($payments = $this->getDatasByOrderId ($virtuemart_order_id)))
			{
				return NULL;
			}
		}
		else
		{
			if (!($payments = $this->getDatasByOrderId ($orderReference)))
			{
				return NULL;
			}
			$virtuemart_order_id=(int)$orderReference;
		}
		
		$this->_currentMethod = $this->getVmPluginMethod($payments[0]->virtuemart_paymentmethod_id);
		if (!$this->selectedThisElement($this->_currentMethod->payment_element))
		{
			return FALSE;
		}
		
		if($virtuemart_paymentmethod_id!=$payments[0]->virtuemart_paymentmethod_id) return FALSE;

		if(!$this->validateDigest()) return FALSE;
		
		$orderModel = VmModel::getModel('orders');
		$order = $orderModel->getOrder($virtuemart_order_id);
		$refID=$this->_currentMethod->referenceid=='order_number'?$order['details']['BT']->order_number:$virtuemart_order_id;
		
		$order_history=array(
			'customer_notified' => 1,
			'order_status' => $this->_currentMethod->status_success,
			'comments'=> JText::sprintf('VMPAYMENT_CARDLINK_SUCCESS_COMMENT',$refID,$cardlink_data['txId']),
		);
		
		$db = JFactory::getDBO();
		$db->setQuery ('SELECT COUNT(id) FROM `'.$this->_tablename.'` WHERE `cardlink_txid`='.$db->quote($cardlink_data['txId']));
		$exists = (int)$db->loadResult();
		
		$this->log('plgVmOnPaymentNotification: order_history',array_merge($order_history,array('exists'=>$exists)));
		//Order Exists
	    if($exists>0) return FALSE;
		$this->_storeCardlinkInternalData( $cardlink_data, $virtuemart_order_id, $payments[0]->virtuemart_paymentmethod_id, $order_number);
		$orderModel->updateStatusForOneOrder($virtuemart_order_id, $order_history, TRUE);
		return TRUE;
	}

	/*********************/
	/* Private functions */
	/*********************/
	private function _storeCardlinkInternalData( $cardlink_data, $virtuemart_order_id, $virtuemart_paymentmethod_id, $order_number) {
		$db = JFactory::getDBO ();
		$columns = $db->getTableColumns($this->_tablename);
		
		$response_fields=array();
		$response_fields['order_number'] = $order_number;
		$response_fields['virtuemart_order_id'] = $virtuemart_order_id;
		$response_fields['virtuemart_paymentmethod_id'] = $virtuemart_paymentmethod_id;
		if (count($cardlink_data)){
			foreach ($cardlink_data as $key => $value) {
				$dbkey='cardlink_'.strtolower($key);
				if (array_key_exists($dbkey, $columns)) {
					$response_fields[$dbkey] = $value;
				}
			}
			$cardlink_data['digest']='***';
			$response_fields['cardlink_fullresponse']=json_encode($cardlink_data);
			if(isset($response_fields['cardlink_paymenttotal'])) $response_fields['payment_order_total']=$response_fields['cardlink_paymenttotal'];
		}
		$this->log('_storeCardlinkInternalData:',$response_fields);
		return $this->storePSPluginInternalData($response_fields, $this->_tablepkey, 0);
	}
	
	/**
	 * Display stored payment data for an order
	 *
	 * @see components/com_virtuemart/helpers/vmPSPlugin::plgVmOnShowOrderBEPayment()
	 */
	function plgVmOnShowOrderBEPayment($virtuemart_order_id, $payment_method_id) {

		if (!$this->selectedThisByMethodId($payment_method_id)) {
			return NULL; // Another method was selected, do nothing
		}
		if (!($this->_currentMethod = $this->getVmPluginMethod($payment_method_id))) {
			return FALSE;
		}

		if (!($payments = $this->_getGatewayInternalData($virtuemart_order_id))) {
			// JError::raiseWarning(500, $db->getErrorMsg());
			return '';
		}
		
		$refID=$this->_currentMethod->referenceid=='order_number'?$order['details']['BT']->order_number:$virtuemart_order_id;
		//$html = $this->renderByLayout('orderbepayment', array($payments, $this->_psType));
		$html = '<table class="adminlist" width="50%">' . "\n";
		$html .= $this->getHtmlHeaderBE();
		$code = "cardlink_response_";
		$first = TRUE;
		foreach ($payments as $payment) {
			$html .= '<tr class="row1"><td>' . vmText::_('VMPAYMENT_CREATEDON') . '</td><td align="left">' . $payment->created_on . '</td></tr>';
			// Now only the first entry has this data when creating the order
			if ($first) {
				$html .= $this->getHtmlRowBE('COM_VIRTUEMART_PAYMENT_NAME', $payment->payment_name);
				if ($payment->payment_order_total and  $payment->payment_order_total != 0.00) {
					$html .= $this->getHtmlRowBE('COM_VIRTUEMART_TOTAL', $payment->payment_order_total . " " . shopFunctions::getCurrencyByID($payment->payment_currency, 'currency_code_3'));
				}
				$first = FALSE;
			} else {
				if (isset($payment->cardlink_fullresponse) and !empty($payment->cardlink_fullresponse)) {
					$cardlink_data = json_decode($payment->cardlink_fullresponse);
					$html .= '<tr><td></td><td>  <a href="#" class="VMLogOpener" rel="'. $payment->id . '" ><div style="background-color: white; z-index: 100; right:0; display: none; border:solid 2px; padding:10px;" class="vm-absolute" id="TranslLog_' . $payment->id . '">';
					foreach ($cardlink_data as $key => $value) {
						$html .= ' <b>' . $key . '</b>:&nbsp;' . $value . '<br />';
					}
					$html .= '</div><span class="icon-nofloat vmicon vmicon-16-xml"></span>&nbsp;';
					$html .= vmText::_('VMPAYMENT_CARDLINK_VIEW_TRANSACTION_LOG');
					$html .= '</a>';
					$html .= '</td></tr>';
				} else {
					$html .='<!-- CARDLINK PAYMENT -->';
				}
			}
		}
		$html .= '</table>' . "\n";

		$doc = JFactory::getDocument();
		$js = "jQuery().ready(function($) {
			$('.VMLogOpener').click(function() {
				var logId = $(this).attr('rel');
				$('#TranslLog_'+logId).toggle();
				return false;
			});
		});";
		$doc->addScriptDeclaration($js);
		return $html;
	}
	
	/**
	 * Check if the payment conditions are fulfilled for this payment method
	 * @param VirtueMartCart $cart
	 * @param int $activeMethod
	 * @param array $cart_prices
	 * @return bool
	 */
	protected function checkConditions($cart, $activeMethod, $cart_prices)
	{
		$condition=parent::checkConditions($cart, $activeMethod, $cart_prices);
		return ($condition)?true:false; //TRUE IF IS OK
	}

	protected function log($title='',$data)
	{
		if($this->_currentMethod->debug) vmdebug('CARDLINK: '.$title,$data);
		if(!$this->_currentMethod->log) return false;
		if(is_array($data) || is_object($data)) $data=print_r($data,true);
		$n=PHP_EOL; //new line
		$data=$n.$title.$n."=============================".$n.$data;
		
		if(version_compare(JVERSION,'3.2','ge'))
			$logPath=JFactory::getApplication()->get('log_path',JPATH_SITE.'/logs');
		else
			$logPath=JFactory::getConfig()->get('log_path',JPATH_SITE.'/logs');
		
		jimport( 'joomla.filesystem.folder' );
		if(!JFolder::exists($logPath)) JFolder::create($logPath);
		
		$logFile=$logPath.'/'.$this->gatewayName.'.log';
		if(JFile::exists($logFile) && filesize($logFile)>2000000) JFile::delete($logFile);
		return JFile::append($logFile,$data);
	}
	
	//Check Respone from Bank
	private function validateDigest()
	{
		$post_DIGEST=$_POST['digest'];
		$post_DIGEST_BONUS=$_POST['xlsbonusdigest'];
		//unset($_POST['digest']);
		$digestString='';
		$digestStringBonus='';
		foreach($_POST as $k=>$val)
		{
			if ( ! in_array( $k, array( '_charset_', 'digest', 'submitButton', 'xlsbonusadjamt', 'xlsbonustxid', 'xlsbonusstatus', 'xlsbonusdetails', 'xlsbonusdigest' ) ) ) {
				$digestString .= filter_var( $_POST[ $k ], FILTER_SANITIZE_STRING );
			}
			if ( in_array( $k, array( 'xlsbonusadjamt', 'xlsbonustxid', 'xlsbonusstatus', 'xlsbonusdetails' ) ) ) {
				$digestStringBonus .= filter_var( $_POST[ $k ], FILTER_SANITIZE_STRING );
			}
		}
		
		//$digest=base64_encode(sha1($digestString.$this->_currentMethod->secretkey,true));

		$secret = $this->_currentMethod->secretkey;
		$form_data   = iconv( 'utf-8', 'utf-8//IGNORE', $digestString ) . $secret;
		$computed_digest 	 = base64_encode( hash( 'sha256', ( $form_data ), true ) );
		$form_data_bonus   = iconv( 'utf-8', 'utf-8//IGNORE', $digestStringBonus ) . $secret;
		$computed_digest_bonus 	 = base64_encode( hash( 'sha256', ( $form_data_bonus ), true ) );

		$result = false;
		if ( $post_DIGEST == $computed_digest ){
			$result = true;
		}
		if( $post_DIGEST_BONUS != '' ){
			if ( $post_DIGEST_BONUS == $computed_digest_bonus ){
				$result = true;
			}else{
				$result = false;
			}
		}
		$this->log('Signature',$result?'Valid':'Invalid');
		//JLog::add($result, JLog::WARNING, 'result');

		return $result;
	}

	public function onBeforeCompileHead(){
		$app = JFactory::getApplication();
		$document = JFactory::getDocument();
		if ( $app->isSite() ) {
			$document->addScript(JUri::root() . 'plugins/vmpayment/cardlink/cardlink/assets/js/scripts-front.js');
			$document->addStyleSheet(JUri::root() . 'plugins/vmpayment/cardlink/cardlink/assets/css//styles-front.css');
		}else{
			$document->addScript(JUri::root() . 'plugins/vmpayment/cardlink/cardlink/assets/js//scripts-back.js');
			$document->addStyleSheet(JUri::root() . 'plugins/vmpayment/cardlink/cardlink/assets/css//styles-back.css');
		}
	}
}