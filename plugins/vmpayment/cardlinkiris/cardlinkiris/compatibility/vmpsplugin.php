<?php
if(!defined( '_JEXEC' ) ) die( 'Direct Access is not allowed.' );

class PaymentCardlinkIrisHelper extends vmPSPlugin {
	protected $totalOrder = 0;
	
	function __construct (& $subject, $config)
	{
		parent::__construct ($subject, $config);
	}
		
	protected function renderPluginName($activeMethod)
	{
		$plugin_name = $this->_psType . '_name';
		$plugin_desc = $this->_psType . '_desc';
		$description = '';
		$logos = $this->getLogos($activeMethod);
		$pluginName = $logos . '<span class="' . $this->_type . '_name">' . $activeMethod->$plugin_name . '</span>';
		if (!empty($activeMethod->$plugin_desc))
		{
			$pluginName .= '<span class="' . $this->_type . '_description">' . $activeMethod->$plugin_desc . '</span>';
		}
		
		return $pluginName;
	}
	
	protected function getPluginHtml($plugin, $selectedPlugin, $pluginSalesPrice){
		static $results=array();
		
		$pluginmethod_id = $this->_idName;
		$pluginName = $this->_psType . '_name';
		$checked = ($selectedPlugin == $plugin->$pluginmethod_id)? 'checked="checked"':'';
		
		$hashKey=$this->_idName.$plugin->$pluginmethod_id;
		if(isset($results[$hashKey])) return $results[$hashKey];
		
		if (!class_exists ('CurrencyDisplay')) {
			require(VMPATH_ADMIN . DS . 'helpers' . DS . 'currencydisplay.php');
		}
		$currency = CurrencyDisplay::getInstance ();
		$costDisplay = "";
		if ($pluginSalesPrice) {
			$costDisplay = $currency->priceDisplay( $pluginSalesPrice );
			$t = vmText::_( 'COM_VIRTUEMART_PLUGIN_COST_DISPLAY' );
			if(strpos($t,'/',$t!==FALSE)){
				list($discount, $fee) = explode( '/', vmText::_( 'COM_VIRTUEMART_PLUGIN_COST_DISPLAY' ) );
				if($pluginSalesPrice>=0) {
					$costDisplay = '<span class="'.$this->_type.'_cost fee"> ('.$fee.' +'.$costDisplay.")</span>";
				} else if($pluginSalesPrice<0) {
					$costDisplay = '<span class="'.$this->_type.'_cost discount"> ('.$discount.' -'.$costDisplay.")</span>";
				}
			} else {
				$costDisplay = '<span class="'.$this->_type.'_cost fee"> ('.$t.' +'.$costDisplay.")</span>";
			}
		}
		$dynUpdate='';
		if( VmConfig::get('oncheckout_ajax',false)) {
			//$url = JRoute::_('index.php?option=com_virtuemart&view=cart&task=updatecart&'. $this->_idName. '='.$plugin->$pluginmethod_id );
			$dynUpdate=' data-dynamic-update="1" ';
		}
		

		$html = '<input type="radio"'.$dynUpdate.' name="' . $pluginmethod_id . '" id="' . $this->_psType . '_id_' . $plugin->$pluginmethod_id . '"   value="' . $plugin->$pluginmethod_id . '" ' . $checked . ">\n"
			. '<label for="' . $this->_psType . '_id_' . $plugin->$pluginmethod_id . '">' . '<span class="' . $this->_type . '">' . $plugin->$pluginName . $costDisplay . "</span></label>\n";

		$results[$hashKey]=$html;
		return $html;
	}


	public function getVmPluginCreateTableSQL()
	{
		return $this->createTableSQL('Payment Table');
	}
	
	protected function getOrderByID ($virtuemart_order_id) {
		if(method_exists($this, 'getDatasByOrderId')) return $this->getDatasByOrderId($virtuemart_order_id);
		$db = JFactory::getDBO ();
		$q = 'SELECT * FROM `' . $this->_tablename . '` '
			. 'WHERE `virtuemart_order_id` = "' . $virtuemart_order_id. '" '
			. 'ORDER BY `id` ASC';
		$db->setQuery ($q);
		return $db->loadObjectList();
	}
	
	protected function getDataOrderNumber($order_number){
		if(is_callable('parent::getDataByOrderNumber')) return parent::getDataByOrderNumber($order_number);
		$db = JFactory::getDBO();
		$db->setQuery('SELECT * FROM `'.$this->_tablename.'` WHERE `order_number`="'.$db->escape($order_number).'"');
		return $db->loadObjectList();
	}
	
	function getEmailCurrency (&$method) {
		if(is_callable('parent::getEmailCurrency')) return parent::getEmailCurrency($method);
		if (!isset($method->email_currency)  or $method->email_currency=='vendor') {
			$vendorId = 1; //VirtueMartModelVendor::getLoggedVendor();
			$db = JFactory::getDBO ();
			$q = 'SELECT   `vendor_currency` FROM `#__virtuemart_vendors` WHERE `virtuemart_vendor_id`=' . $vendorId;
			$db->setQuery ($q);
			return $db->loadResult ();
		}
		return $method->payment_currency; // either the vendor currency, either same currency as payment
	}
	
	function getCartAmount($cart_prices)
	{
		if(method_exists($this, 'getCartAmount')) return parent::getCartAmount($cart_prices);
		if(!isset($cart_prices['salesPrice']) || empty($cart_prices['salesPrice'])) $cart_prices['salesPrice'] = 0.0;
		$cartPrice = isset($cart_prices['withTax']) && !empty($cart_prices['withTax'])? $cart_prices['withTax']:$cart_prices['salesPrice'];
		if(!isset($cart_prices['salesPriceShipment']) || empty($cart_prices['salesPriceShipment'])) $cart_prices['salesPriceShipment'] = 0.0;
		if(!isset($cart_prices['salesPriceCoupon']) ||  empty($cart_prices['salesPriceCoupon'])) $cart_prices['salesPriceCoupon'] = 0.0;
		$amount= $cartPrice + $cart_prices['salesPriceShipment'] + $cart_prices['salesPriceCoupon'] ;
		if ($amount <= 0) $amount=0;
		return $amount;
	}
	
	function convert_condition_amount (&$method) {
		if(is_callable('parent::convert_condition_amount')){
			parent::convert_condition_amount($method);
		}else{
			$method->min_amount = (float)str_replace(',','.',$method->min_amount);
			$method->max_amount = (float)str_replace(',','.',$method->max_amount);
		}
	}
	
	function plgVmOnStoreInstallPaymentPluginTable ($jplugin_id) {
        return $this->onStoreInstallPluginTable ($jplugin_id);
    }
	
	/**
	 * @param   int $virtuemart_order_id
	 * @param string $order_number
	 * @return mixed|string
	 */
	protected function _getGatewayInternalData($virtuemart_order_id, $order_number = '') {
		if (empty($order_number)) {
			$orderModel = VmModel::getModel('orders');
			$order_number = $orderModel->getOrderNumber($virtuemart_order_id);
		}
		$db = JFactory::getDBO();
		$q = 'SELECT * FROM `' . $this->_tablename . '` WHERE `order_number` = '.$db->quote($db->escape($order_number), false);
		$db->setQuery($q);
		
		if (!($payments = $db->loadObjectList())) {
			$this->log('_getGatewayInternalData Error:',$db->getErrorMsg());
			return array();
		}
		$this->log('_getGatewayInternalData:',$payments);
		return $payments;
	}
	
	/**
	 * @param $product
	 * @param $productDisplay
	 * @return bool
	 */
	function plgVmOnProductDisplayPayment($product, &$productDisplay) {
		return;
	}

	/**
	 * @param VirtuemartViewUser $user
	 * @param                    $html
	 * @param bool               $from_cart
	 * @return bool|null
	 */
	/*function plgVmDisplayLogin(VirtuemartViewUser $user, &$html, $from_cart = FALSE) {

		// only to display it in the cart, not in list orders view
		if (!$from_cart) {
			return NULL;
		}

		$vendorId = 1;
		if (!class_exists('VirtueMartCart')) {
			require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
		}

		$cart = VirtueMartCart::getCart();
		if ($this->getPluginMethods($cart->vendorId) === 0) {
			return FALSE;
		}
		$cart->prepareCartData();
		if (isset($cart->pricesUnformatted['salesPrice']) AND $cart->pricesUnformatted['salesPrice'] <= 0.0) {
			return FALSE;
		}
		if (!($this->_currentMethod = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id))) {
			return NULL;
		}

		$html .= $this->getExpressCheckoutHtml($this->_currentMethod, $cart);
	}*/
	
	/**
	 * @param $cart
	 * @param $payment_advertise
	 * @return bool|null
	 */
	/*function plgVmOnCheckoutAdvertise($cart, &$payment_advertise) {

		if ($this->getPluginMethods($cart->vendorId) === 0) {
			return FALSE;
		}
		if ($cart->pricesUnformatted['salesPrice'] <= 0.0) {
			return NULL;
		}
		if (!($this->_currentMethod = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id))) {
			return NULL;
		}
		$payment_advertise[] = $this->getExpressCheckoutHtml($this->_currentMethod, $cart);
	}*/

	/**
	 * @param $currentMethod
	 * @param $cart
	 * @return null|string
	 */
	/*function getExpressCheckoutHtml($currentMethod, $cart) {
		return NULL;
	}*/
	
	/**
	 * @param null $msg
	 */
	function redirectToCart ($msg = NULL) {
		//if (!$msg)	$msg = vmText::_('VMPAYMENT_CARDLINK_ERROR_TRY_AGAIN');
		$app = JFactory::getApplication();
		$app->redirect(JRoute::_('index.php?option=com_virtuemart&view=cart&Itemid=' . vRequest::getInt('Itemid'), false), $msg);
	}

	/**
	 * @param $virtuemart_paymentmethod_id
	 * @param $paymentCurrencyId
	 * @return bool|null
	 */
	function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId) {

		if (!($this->_currentMethod = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($this->_currentMethod->payment_element)) {
			return FALSE;
		}
		$this->getPaymentCurrency($this->_currentMethod);
		$paymentCurrencyId = $this->_currentMethod->payment_currency;
	}

	function plgVmgetEmailCurrency($virtuemart_paymentmethod_id, $virtuemart_order_id, &$emailCurrencyId) {

		if (!($this->_currentMethod = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($this->_currentMethod->payment_element)) {
			return FALSE;
		}
		if (!($payments = $this->_getGatewayInternalData($virtuemart_order_id))) {
			// JError::raiseWarning(500, $db->getErrorMsg());
			return '';
		}
		if (empty($payments[0]->email_currency)) {
			$vendorId = 1; //VirtueMartModelVendor::getLoggedVendor();
			$db = JFactory::getDBO();
			$q = 'SELECT   `vendor_currency` FROM `#__virtuemart_vendors` WHERE `virtuemart_vendor_id`=' . $vendorId;
			$db->setQuery($q);
			$emailCurrencyId = $db->loadResult();
		} else {
			$emailCurrencyId = $payments[0]->email_currency;
		}

	}
		
	//Get Logos HTML
	protected function getLogos($activeMethod){
		$logosFieldName = $this->_psType . '_logos';
		$logos = $activeMethod->$logosFieldName;
		$returnLogo='';
		if (!empty($logos) && $logos!='-1' && $logos!='' && $logos!='default'){
			$returnLogo = $this->displayLogos($logos) . ' ';
		}
		return $returnLogo;
	}
	
	/**
	 * Check if the payment conditions are fulfilled for this payment method
	 * @param VirtueMartCart $cart
	 * @param int $activeMethod
	 * @param array $cart_prices
	 * @return bool
	 */
	protected function checkConditions($cart, $activeMethod, $cart_prices) {
		
		//Check method publication start
		if(isset($activeMethod->publishup) && $activeMethod->publishup)
		{
			$nowDate = JFactory::getDate();
			$publish_up = JFactory::getDate($activeMethod->publishup);
			if ($publish_up->toUnix() > $nowDate->toUnix()) {
				return FALSE;
			}
		}
		
		if(isset($activeMethod->publishdown) && $activeMethod->publishdown) {
			$nowDate = JFactory::getDate();
			$publish_down = JFactory::getDate($activeMethod->publishdown);
			if ($publish_down->toUnix() <= $nowDate->toUnix()) {
				return FALSE;
			}
		}
		$this->convert_condition_amount($activeMethod);

		$address = (($cart->ST == 0) ? $cart->BT : $cart->ST);

		$this->totalOrder=$amount=$this->getCartAmount($cart_prices);
		vmdebug('checkConditions totalOrder',$this->totalOrder);
		
		$amount_cond =($amount >= $activeMethod->min_amount AND $amount <= $activeMethod->max_amount OR ($activeMethod->min_amount <= $amount AND ($activeMethod->max_amount == 0)));

		$countries = array();
		if (!empty($activeMethod->countries)) {
			if (!is_array($activeMethod->countries)) {
				$countries[0] = $activeMethod->countries;
			} else {
				$countries = $activeMethod->countries;
			}
		}
		
		// probably did not gave his BT:ST address
		if (!is_array($address)) {
			$address = array();
			$address['virtuemart_country_id'] = 0;
		}

		if (!isset($address['virtuemart_country_id'])) {
			$address['virtuemart_country_id'] = 0;
		}
		return($amount_cond && (in_array($address['virtuemart_country_id'], $countries) || count($countries) == 0))?true:false;
	}
	

	/**
	 *     * This event is fired after the payment method has been selected.
	 * It can be used to store additional payment info in the cart.
	 * @param VirtueMartCart $cart
	 * @param $msg
	 * @return bool|null
	 */
	public function plgVmOnSelectCheckPayment (VirtueMartCart $cart, &$msg) {
		return $this->OnSelectCheck($cart);
	}

	/**
	 *  Order status changed
	 * @param $order
	 * @param $old_order_status
	 * @return bool|null
	 */
	/*public function plgVmOnUpdateOrderPayment(&$order, $old_order_status) {

		//Load the method
		if (!($this->_currentMethod = $this->getVmPluginMethod($order->virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}

		if (!$this->selectedThisElement($this->_currentMethod ->payment_element)) {
			return NULL;
		}

		//Load only when updating status to shipped
		if ($order->order_status != $this->_currentMethod->status_capture AND $order->order_status != $this->_currentMethod->status_refunded) {
			 //return null;
		}
		//Load the payments
		if (!($payments = $this->_getGatewayInternalData($order->virtuemart_order_id))) {
			// JError::raiseWarning(500, $db->getErrorMsg());
			return null;
		}

		$payment = end($payments);
		return true;
	}*/

	function plgVmOnUpdateOrderLinePayment(&$order) {
		// $xx=1;
	}

	public function plgVmDisplayListFEPayment (VirtueMartCart $cart, $selected = 0, &$htmlIn)
	{
		$cartPrices=isset($cart->cartPrices)?$cart->cartPrices:$cart->cart_prices;
		$this->totalOrder=$this->getCartAmount($cartPrices);
		vmdebug('plgVmOnSelectedCalculatePricePayment totalOrder',$this->totalOrder);
		return $this->displayListFE($cart, $selected, $htmlIn);
	}

	/**
	 * Validate payment on checkout
	 * @param VirtueMartCart $cart
	 * @return bool|null
	 */
	/*function plgVmOnCheckoutCheckDataPayment(VirtueMartCart $cart) {

		if (!$this->selectedThisByMethodId($cart->virtuemart_paymentmethod_id)) {
			return NULL; // Another method was selected, do nothing
		}

		if (!($this->_currentMethod = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id))) {
			return FALSE;
		}
	}*/

	//Calculate the price (value, tax_id) of the selected method, It is called by the calculator
	//This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
	public function plgVmOnSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name)
	{
		$payid=vRequest::getInt('virtuemart_paymentmethod_id',0);
		if($payid>0 && isset($_POST['installments'][$payid]))
		{
			JFactory::getSession()->set('vmpayinstallments'.$payid,(int)$_POST['installments'][$payid]);
		}
		return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
	}

	// Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
	// The plugin must check first if it is the correct type
	function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter){
		return $this->onCheckAutomaticSelected($cart, $cart_prices, $paymentCounter);
	}

	// This method is fired when showing the order details in the frontend.
	// It displays the method-specific data.
	public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name)
	{
		$this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
	}

	function plgVmonShowOrderPrintPayment ($order_number, $method_id)
	{
		return $this->onShowOrderPrint ($order_number, $method_id);
	}

	function plgVmSetOnTablePluginParamsPayment ($name, $id, &$table)
	{
		return $this->setOnTablePluginParams ($name, $id, $table);
	}
	
	function plgVmDeclarePluginParamsPayment($name, $id, &$data)
	{
		return $this->declarePluginParams('payment', $name, $id, $data);
	}
	
	function plgVmDeclarePluginParamsPaymentVM3(&$data)
	{
		return $this->declarePluginParams('payment', $data);
	}	
}