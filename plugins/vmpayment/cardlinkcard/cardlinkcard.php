<?php
/**
 *
 * Cardlink payment plugin
 *
 */

defined('_JEXEC') or die('Restricted access');

if (!class_exists('vmDefines')) {
	require (JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_virtuemart' . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'vmdefines.php');
	vmDefines::defines('site');
}
if (!class_exists('vmPlugin')) {
	require (JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_virtuemart' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'vmplugin.php');
}
if (!class_exists('vmPSPlugin')) {
	require (JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_virtuemart' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'vmpsplugin.php');
}

use Joomla\Database\DatabaseInterface;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\File;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;

class plgVmPaymentCardlinkCard extends vmPSPlugin
{
	public const OrderId_SuffixLength = 5;
	protected $installments = 0;
	protected $installmentOptions = array();
	protected $tokenizationOption = 0;
	protected $totalOrder = 0;
	protected $gatewayName = 'cardlinkcard';

	function __construct(&$subject, $config)
	{
		$this->loadVirtueMartFiles();

		parent::__construct($subject, $config);

		$this->_loggable = TRUE;
		$this->tableFields = array_keys($this->getTableSQLFields());
		$this->_tablepkey = 'id'; //virtuemart_cardlink_id';
		$this->_tableId = 'id'; //'virtuemart_cardlink_id';
		$varsToPush = array(
			'acquirer' => array(0, 'int'),
			'mid' => array('', 'char'),
			'secretkey' => array('', 'char'),
			'demoaccount' => array(0, 'int'),
			'allow_installments' => array(0, 'int'),
			'max_installments' => array(0, 'int'),
			'installments_variations' => array('', 'char'),
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

	protected function loadVirtueMartFiles()
	{
		// Load VirtueMart configuration
		if (!class_exists('VmConfig')) {
			require (JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_virtuemart' . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'config.php');
		}
		vmDefines::loadJoomlaCms();
		VmConfig::loadConfig();
	}

	public function getVmPluginCreateTableSQL()
	{
		return $this->createTableSQL('Cardlink Card Payment Method Table');
	}

	function getTableSQLFields()
	{
		$SQLfields = array(
			'id' => 'int(11) UNSIGNED NOT NULL AUTO_INCREMENT',
			'virtuemart_order_id' => 'int(11) UNSIGNED',
			'order_number' => 'char(64)',
			'virtuemart_paymentmethod_id' => 'mediumint(5) UNSIGNED',
			'payment_name' => 'varchar(500)',
			'payment_order_total' => 'decimal(15,5) NOT NULL',
			'payment_currency' => 'int(5) UNSIGNED',
			'cost_per_transaction' => 'decimal(10,2)',
			'cost_percent_total' => 'decimal(10,2)',
			'tax_id' => 'smallint(1)',
			'installments' => 'int(3) UNSIGNED',
			'tokenize' => 'smallint(1)',
			'token_id' => 'int(11)',
			'cardlink_orderid' => 'varchar(50)',
			'cardlink_paymenttotal' => 'decimal(10,2)',
			'cardlink_message' => 'varchar(128)',
			'cardlink_txid' => 'varchar(24)',
			'cardlink_paymentref' => 'varchar(24)',
			'cardlink_riskscore' => 'varchar(5)',
			'cardlink_paymethod' => 'varchar(20)',
			'cardlink_status' => 'varchar(30)',
			'cardlink_fullresponse' => 'text',
		);
		return $SQLfields;
	}

	/**
	 * This event is fired after the payment method has been selected.
	 * It can be used to store additional payment info in the cart.
	 * @param VirtueMartCart $cart
	 * @param $msg
	 * @return bool|null
	 */
	public function plgVmOnSelectCheckPayment(VirtueMartCart $cart, &$msg)
	{
		return $this->OnSelectCheck($cart);
	}

	public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn)
	{
		$cartPrices = isset($cart->cartPrices) ? $cart->cartPrices : $cart->cart_prices;
		$this->totalOrder = $this->getCartAmount($cartPrices);
		vmdebug('plgVmOnSelectedCalculatePricePayment totalOrder', $this->totalOrder);
		return $this->displayListFE($cart, $selected, $htmlIn);
	}

	//Calculate the price (value, tax_id) of the selected method, It is called by the calculator
	//This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
	public function plgVmOnSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name)
	{
		if ($this->OnSelectCheck($cart)) {
			$payid = vRequest::getInt('virtuemart_paymentmethod_id', 0);
			if ($payid > 0 && isset($_POST['installments'][$payid])) {
				Factory::getApplication()->getSession()->set('vmpayinstallments' . $payid, (int) $_POST['installments'][$payid]);
			}
			return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
		}
		return NULL;
	}

	// Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
	// The plugin must check first if it is the correct type
	public function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter)
	{
		return $this->onCheckAutomaticSelected($cart, $cart_prices, $paymentCounter);
	}

	// This method is fired when showing the order details in the frontend.
	// It displays the method-specific data.
	public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name)
	{
		// Check if the order contains payment data for this plugin
		if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return null; // Another method was selected, do nothing
		}

		if (!$this->selectedThisElement($method->payment_element)) {
			return false; // Another method was selected, do nothing
		}

		// Load the payment data from the order
		$paymentTable = $this->getDataByOrderId($virtuemart_order_id);

		if (empty($paymentTable)) {
			return false; // No payment data found, do nothing
		}

		$paymentModel = VmModel::getModel('paymentmethod');
		$paymentmethod = $paymentModel->getPayment($virtuemart_paymentmethod_id);
		$paymentName = $paymentmethod->payment_name;

		// Add custom payment information to the order details view
		$html = '<u>' . $paymentName . '</u><br />';
		$html .= '<table class="small">';

		if ($paymentTable->installments > 1) {
			$html .= $this->getHtmlRowBE('CARDLINKCARD_INSTALLMENTS_TITLE', $paymentTable->installments);
		}

		$html .= $this->getHtmlRowBE('CARDLINKCARD_PAYMENT_METHOD', strtoupper($paymentTable->cardlink_paymethod));
		$html .= $this->getHtmlRowBE('CARDLINKCARD_PAYMENT_STATUS', $paymentTable->cardlink_status);
		$html .= $this->getHtmlRowBE('CARDLINKCARD_TRANSACTION_ID', $paymentTable->cardlink_txid);
		$html .= $this->getHtmlRowBE('CARDLINKCARD_PAYMENT_REFERENCE', $paymentTable->cardlink_paymentref);
		$html .= '</table>';
		$payment_name = $html;
	}

	function plgVmonShowOrderPrintPayment($order_number, $method_id)
	{
		return $this->onShowOrderPrint($order_number, $method_id);
	}

	function plgVmSetOnTablePluginParamsPayment($name, $id, &$table)
	{
		return $this->setOnTablePluginParams($name, $id, $table);
	}

	function plgVmDeclarePluginParamsPayment($name, $id, &$data)
	{
		return $this->declarePluginParams('payment', $name, $id, $data);
	}

	function plgVmDeclarePluginParamsPaymentVM3(&$data)
	{
		return $this->declarePluginParams('payment', $data);
	}

	protected function _getRandomStringHash($length = 3)
	{
		// Define the characters to be used in the random string
		$characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charactersLength = strlen($characters);
		$randomString = '';

		// Loop through and append a random character from the characters string
		for ($i = 0; $i < $length; $i++) {
			$randomString .= $characters[random_int(0, $charactersLength - 1)];
		}

		return $randomString;
	}

	function plgVmConfirmedOrder($cart, $order)
	{
		if (!($this->_currentMethod = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($this->_currentMethod->payment_element)) {
			return FALSE;
		}

		if (!class_exists('VirtueMartModelOrders')) {
			require (JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
		}

		$app = Factory::getApplication();
		$app->allowCache(false);
		$app->set('caching', 0);

		$session = $app->getSession();
		$this->getPaymentCurrency($this->_currentMethod);

		if ((int) $this->_currentMethod->payment_currency == 0) {
			$this->_currentMethod->payment_currency = $order['details']['BT']->order_currency;
		}
		$paymentCurrency = CurrencyDisplay::getInstance($this->_currentMethod->payment_currency);

		if (method_exists('vmPSPlugin', 'getAmountValueInCurrency')) {
			$this->totalOrder = vmPSPlugin::getAmountValueInCurrency($order['details']['BT']->order_total, $this->_currentMethod->payment_currency);
		} else {
			$this->totalOrder = round($paymentCurrency->convertCurrencyTo($this->_currentMethod->payment_currency, $order['details']['BT']->order_total, FALSE), 2);
		}

		$this->installments = 0;
		$this->installmentOptions = array();

		if (isset($this->_currentMethod->allow_installments) && $this->_currentMethod->allow_installments) {
			if (!empty($this->_currentMethod->installments_variations)) {
				$this->installmentOptions = $this->findInstallments($this->_currentMethod->installments_variations, $this->totalOrder);
				$this->installments = count($this->installmentOptions) ? max($this->installmentOptions) : 0;
			} else if ($this->_currentMethod->max_installments > 1) {
				$this->installmentOptions = [];
				for ($i = 2; $i <= $this->_currentMethod->max_installments; $i++) {
					$this->installmentOptions[] = $i;
				}
				$this->installments = $this->_currentMethod->max_installments;
			}
		}

		$payid = $this->_currentMethod->virtuemart_paymentmethod_id;

		$installments = isset($_POST['installments'][$payid]) ? (int) $_POST['installments'][$payid] : (int) $session->get('vmpayinstallments' . $payid, 0);
		if ($this->installments > 1 && $installments > 1) {
			$installments = ($installments > $this->installments) ? $this->installments : $installments;
		} else {
			$installments = 0;
		}

		$virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order['details']['BT']->order_number);
		$refID = $this->_currentMethod->referenceid == 'order_number' ? $order['details']['BT']->order_number : $virtuemart_order_id;

		$tokenization = isset($_POST['tokenization']) ? (int) $_POST['tokenization'] : (int) $session->get('tokenization');
		$stored_card_id = isset($_POST['stored_card_id']) ? (int) $_POST['stored_card_id'] : (int) $session->get('stored_card_id');

		// Prepare data that should be stored in the database
		$dbValues['order_status'] = $this->_currentMethod->status_pending;
		$dbValues['order_number'] = $order['details']['BT']->order_number;
		$dbValues['payment_name'] = $this->_currentMethod->payment_name;
		$dbValues['virtuemart_order_id'] = $virtuemart_order_id;
		$dbValues['virtuemart_paymentmethod_id'] = $payid;
		$dbValues['cost_per_transaction'] = $this->_currentMethod->cost_per_transaction;
		$dbValues['cost_percent_total'] = $this->_currentMethod->cost_percent_total;
		$dbValues['payment_currency'] = $this->_currentMethod->payment_currency;
		$dbValues['payment_order_total'] = $this->totalOrder;
		$dbValues['tax_id'] = $this->_currentMethod->tax_id;
		$dbValues['installments'] = $installments;
		$dbValues['tokenize'] = $tokenization;
		$dbValues['token_id'] = $stored_card_id;
		$this->storePSPluginInternalData($dbValues, 'virtuemart_order_id', true);

		$cart->_confirmDone = FALSE;
		$cart->_dataValidated = FALSE;
		$cart->setCartIntoSession();

		$html = $this->renderByLayout(
			$this->_currentMethod->iframe ? 'form_iframe' : 'form',
			array(
				'order_id' => $virtuemart_order_id,
				'order_ref_id' => $refID,
				'logos' => $this->getLogos($this->_currentMethod),
				'params' => $this->_currentMethod
			)
		);

		$app->input->set('html', $html);
		vRequest::setVar('html', $html);

		$this->log('plgVmConfirmedOrder: Form Fields', $post);
		$this->log('plgVmConfirmedOrder: Order', $order);
		$this->log('plgVmConfirmedOrder: HTML Form', $html);
	}

	/**
	 * @param $html
	 * @return bool|null|string
	 */
	function plgVmOnPaymentResponseReceived(&$html)
	{
		if (!class_exists('VirtueMartCart')) {
			require (JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
		}
		if (!class_exists('shopFunctionsF')) {
			require (JPATH_VM_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php');
		}
		if (!class_exists('VirtueMartModelOrders')) {
			require (JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
		}

		$app = Factory::getApplication();
		$virtuemart_paymentmethod_id = $app->input->getInt('pm', 0);
		$orderReference = $app->input->get('on', $app->input->get('orderid'));

		$debug = 'virtuemart_paymentmethod_id=' . $virtuemart_paymentmethod_id . "\n";
		$debug .= 'orderReference=' . $orderReference . "\n";
		$debug .= '$_POST=' . print_r($_POST, true) . "\n";

		if (!($this->_currentMethod = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}

		if (count($_POST) > 0 && !empty($orderReference)) {
			$this->log('plgVmOnPaymentResponseReceived: _POST', $_POST);
			$rsp = $this->plgVmOnPaymentNotification();
		}

		if (!$this->selectedThisElement($this->_currentMethod->payment_element)) {
			return NULL;
		}

		if ($this->_currentMethod->referenceid == 'order_number') {
			if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($orderReference))) {
				return NULL;
			}
			$order_number = $orderReference;
		} else {
			$virtuemart_order_id = (int) $orderReference;
		}

		if (!($payments = $this->getDatasByOrderId($virtuemart_order_id))) {
			return NULL;
		}

		$payment = is_array($payments) ? end($payments) : $payments;

		$orderModel = VmModel::getModel('orders');
		$order = $orderModel->getOrder($virtuemart_order_id);

		$currency = CurrencyDisplay::getInstance('', $order['details']['BT']->virtuemart_vendor_id);

		$responseData = new stdclass;

		if (count($_POST) && !empty($virtuemart_order_id) && $this->validateDigest()) {
			$responseData = json_decode(json_encode($_POST));

			$total = (!isset($payment->payment_order_total) || !$payment->payment_order_total) ? $api->paymentTotal : $payment->payment_order_total;
			$total = (!isset($payment->cardlink_paymenttotal) || !$payment->cardlink_paymenttotal) ? $total : $payment->cardlink_paymenttotal;

			if ($_POST['extToken']) {
				$this->saveToken($_POST, $order);
			}

			VmConfig::loadJLang('com_virtuemart_orders', TRUE);
			$html = $this->renderByLayout(
				'response',
				array(
					"success" => true,
					"response" => $responseData,
					"payment" => $payment,
					"order" => $order,
					"currency" => $currency,
					"total" => (float) $total,
					"params" => $this->_currentMethod,
				)
			);
			$cart = VirtueMartCart::getCart();
			$cart->emptyCart();
		}
		return TRUE;
	}

	function plgVmOnUserPaymentCancel()
	{
		if (!class_exists('VirtueMartModelOrders')) {
			require (JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
		}

		$app = Factory::getApplication();
		$virtuemart_paymentmethod_id = $app->input->getInt('pm', 0);
		$orderReference = $app->input->get('on', $app->input->get('orderid'));

		$orderid = $response['orderid'];
		$order_model = VmModel::getModel('orders');
		//$myorder = $order_model->getOrder($orderid);

		if (empty($orderReference) or empty($virtuemart_paymentmethod_id) or !$this->selectedThisByMethodId($virtuemart_paymentmethod_id)) {
			return NULL;
		}

		if (!($this->_currentMethod = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}

		if ($this->_currentMethod->referenceid == 'order_number') {
			if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($orderReference))) {
				return NULL;
			}

			$order_number = $orderReference;
			if (method_exists($this, 'getDataOrderNumber') && !($payments = $this->getDataOrderNumber($order_number))) {
				return NULL;
			}
		} else {
			if (!($payments = $this->getDatasByOrderId($orderReference))) {
				return NULL;
			}
			$virtuemart_order_id = (int) $orderReference;
		}

		$this->log('plgVmOnUserPaymentCancel: _POST', $_POST);

		if ($this->validateDigest()) {
			$this->handlePaymentUserCancel($virtuemart_order_id);
			Factory::getApplication()->enqueueMessage(vmText::_('VMPAYMENT_CARDLINKCARD_FAILED_TRYAGAIN'), 'error');
			if (isset($_POST['message']))
				vmWarn($_POST['message']);
		}
		return TRUE;
	}

	function plgVmOnPaymentNotification()
	{
		if (!class_exists('VirtueMartModelOrders')) {
			require (JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
		}

		$cardlink_data = (array) $_POST;
		$app = Factory::getApplication();
		$virtuemart_paymentmethod_id = $app->input->getInt('pm', 0);
		$orderReference = $app->input->get('on', $app->input->get('orderid'));

		if (!($this->_currentMethod = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}

		if ($this->_currentMethod->referenceid == 'order_number') {
			if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($orderReference))) {
				return FALSE;
			}

			$order_number = $orderReference;
			if (!($payments = $this->getDatasByOrderId($virtuemart_order_id))) {
				return NULL;
			}
		} else {
			if (!($payments = $this->getDatasByOrderId($orderReference))) {
				return NULL;
			}
			$virtuemart_order_id = (int) $orderReference;
		}

		$this->_currentMethod = $this->getVmPluginMethod($payments[0]->virtuemart_paymentmethod_id);
		if (!$this->selectedThisElement($this->_currentMethod->payment_element)) {
			return FALSE;
		}

		if ($virtuemart_paymentmethod_id != $payments[0]->virtuemart_paymentmethod_id) {
			return FALSE;
		}

		if (!$this->validateDigest()) {
			return FALSE;
		}

		$orderModel = VmModel::getModel('orders');
		$order = $orderModel->getOrder($virtuemart_order_id);
		$refID = $this->_currentMethod->referenceid == 'order_number' ? $order['details']['BT']->order_number : $virtuemart_order_id;

		$transactionStatus = $app->input->get('status', '');

		$order_history = array(
			'customer_notified' => 1,
			'order_status' => $transactionStatus == 'CAPTURED' ? $this->_currentMethod->status_success : $this->_currentMethod->status_authorization,
			'comments' => Text::sprintf('VMPAYMENT_CARDLINKCARD_SUCCESS_COMMENT', $refID, $cardlink_data['txId'], $transactionStatus),
		);

		$db = Factory::getContainer()->get(DatabaseInterface::class);
		$query = 'SELECT COUNT(id) FROM `' . $this->_tablename . '` WHERE `cardlink_txid`=' . $db->quote($cardlink_data['txId']);
		$db->setQuery($query);
		$exists = (int) $db->loadResult();

		$this->log('plgVmOnPaymentNotification: order_history', array_merge($order_history, array('exists' => $exists)));

		//Order Exists
		if ($exists > 0) {
			return FALSE;
		}

		$this->_storeCardlinkInternalData($cardlink_data, $virtuemart_order_id, $payments[0]->virtuemart_paymentmethod_id, $order_number);
		$orderModel->updateStatusForOneOrder($virtuemart_order_id, $order_history, TRUE);

		return TRUE;
	}

	/**
	 * Display stored payment data for an order
	 *
	 * @see components/com_virtuemart/helpers/vmPSPlugin::plgVmOnShowOrderBEPayment()
	 */
	function plgVmOnShowOrderBEPayment($virtuemart_order_id, $payment_method_id)
	{
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

		$html = '<table class="adminlist table uk-first-column" width="50%">' . "\n";

		foreach ($payments as $payment) {
			if (isset($payment->cardlink_fullresponse) and !empty($payment->cardlink_fullresponse)) {
				$html .= $this->getHtmlHeaderBE();
				$html .= $this->getHtmlRowBE('COM_VIRTUEMART_PAYMENT_NAME', $payment->payment_name);
				$html .= $this->getHtmlRowBE('VMPAYMENT_CARDLINKCARD_DATE', $payment->created_on);
				$html .= $this->getHtmlRowBE('CARDLINKCARD_PAYMENT_METHOD', strtoupper($payment->cardlink_paymethod));
				$html .= $this->getHtmlRowBE('CARDLINKCARD_PAYMENT_STATUS', $payment->cardlink_status);

				if ($payment->installments > 1) {
					$html .= $this->getHtmlRowBE('CARDLINKCARD_INSTALLMENTS_TITLE', $payment->installments);
				}

				$html .= $this->getHtmlRowBE('CARDLINKCARD_TRANSACTION_ID', $payment->cardlink_txid);
				$html .= $this->getHtmlRowBE('CARDLINKCARD_PAYMENT_REFERENCE', $payment->cardlink_paymentref);

				if ($payment->payment_order_total and $payment->payment_order_total != 0.00) {
					$total = vmPSPlugin::getAmountValueInCurrency($payment->cardlink_paymenttotal, $payment->payment_currency);
					$html .= $this->getHtmlRowBE('COM_VIRTUEMART_TOTAL', $total . " " . shopFunctions::getCurrencyByID($payment->payment_currency, 'currency_code_3'));
				}

				$cardlink_data = json_decode($payment->cardlink_fullresponse);
				$html .= '<tr><td></td><td>  <a href="#" class="VMLogOpener" rel="' . $payment->id . '" ><div style="background-color: white; z-index: 100; right:0; display: none; border:solid 2px; padding:10px;" class="vm-absolute" id="TranslLog_' . $payment->id . '">';
				foreach ($cardlink_data as $key => $value) {
					$html .= ' <b>' . $key . '</b>:&nbsp;' . $value . '<br />';
				}
				$html .= '</div><span class="icon-nofloat vmicon vmicon-16-xml"></span>&nbsp;';
				$html .= vmText::_('VMPAYMENT_CARDLINKCARD_VIEW_TRANSACTION_LOG');
				$html .= '</a>';
				$html .= '</td></tr>';
			} else {
				$html .= '<!-- CARDLINK PAYMENT -->';
			}
		}
		$html .= '</table>' . "\n";

		$doc = Factory::getApplication()->getDocument();
		$wam = $doc->getWebAssetManager();
		$js = "jQuery().ready(function($) {
			$('.VMLogOpener').click(function() {
				var logId = $(this).attr('rel');
				$('#TranslLog_'+logId).toggle();
				return false;
			});
		});";
		$wam->addInlineScript($js);
		return $html;
	}

	function plgVmOnSelfCallFE($type, $name, &$render)
	{
		if ($type != $this->_type) {
			return;
		}
		if ($name != $this->_name) {
			return;
		}

		$task = vRequest::getCmd('task', false);

		$response = null;

		switch ($task) {
			case 'deleteToken':
				$stored_card_id_value = $_POST['stored_card_id_value'];

				if ($stored_card_id_value) {
					$user_id = Factory::getApplication()->getIdentity()->id;
					$db = Factory::getContainer()->get(DatabaseInterface::class);

					$query = $db->getQuery(true);
					$query->delete($db->quoteName('#__virtuemart_payment_plg_' . $this->pluginName . '_tokens'));
					$query->where([
						$db->quoteName('token_id') . ' = :token_id',
						$db->quoteName('user_id') . ' = :user_id'
					]);

					$query
						->bind(':token_id', $stored_card_id_value)
						->bind(':user_id', $user_id, Joomla\Database\ParameterType::INTEGER);

					$db->setQuery($query);
					$status = $db->execute();
					$response = ['status' => $status];
				}
				break;

			case 'checkOrderStatus':
				$order_id = $_POST['order_id'];
				$order = $this->getOrderByID($order_id)[0];
				if (!$order) {
					return false;
				}

				$confirmUrl = Uri::root() . '?com=cardlink&type=card&status=ok&pm=' . $order->virtuemart_paymentmethod_id . '&on=' . $order->order_number;
				$cancelUrl = uri::root() . '?com=cardlink&type=card&status=cancel&pm=' . $order->virtuemart_paymentmethod_id . '&on=' . $order->order_number;

				if ($order->order_status == 'P') {
					$redirected = true;
				}

				$response = [
					'redirect_url' => false,
					'redirected' => $redirected,
				];

				if ($response['redirected'] !== '1') {
					if ($order->order_status == 'C') {
						$response['redirect_url'] = $confirmUrl;
					} else {
						$response['redirect_url'] = $cancelUrl;
					}
				}
		}

		$app = Factory::getApplication();
		// Set the response content type to JSON
		$app->setHeader('Content-Type', 'application/json', true);

		echo json_encode($response);

		$app->close();
	}

	function plgVmOnStoreInstallPaymentPluginTable($jplugin_id)
	{
		return $this->onStoreInstallPluginTable($jplugin_id);
	}

	/**
	 * @param $virtuemart_paymentmethod_id
	 * @param $paymentCurrencyId
	 * @return bool|null
	 */
	function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId)
	{

		if (!($this->_currentMethod = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($this->_currentMethod->payment_element)) {
			return FALSE;
		}
		$this->getPaymentCurrency($this->_currentMethod);
		$paymentCurrencyId = $this->_currentMethod->payment_currency;
	}

	function plgVmgetEmailCurrency($virtuemart_paymentmethod_id, $virtuemart_order_id, &$emailCurrencyId)
	{
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
			$db = Factory::getContainer()->get(DatabaseInterface::class);
			$q = 'SELECT   `vendor_currency` FROM `#__virtuemart_vendors` WHERE `virtuemart_vendor_id`=' . $vendorId;
			$db->setQuery($q);
			$emailCurrencyId = $db->loadResult();
		} else {
			$emailCurrencyId = $payments[0]->email_currency;
		}
	}

	function saveToken($response, $orderDetails = null)
	{
		$token = $response['extToken'];
		$last4 = $response['extTokenPanEnd'];
		$payMethod = $response['payMethod'];
		$extTokenExp = $response['extTokenExp'];
		$extTokenExpYear = substr($extTokenExp, 0, 4);
		$extTokenExpMonth = substr($extTokenExp, 4, 2);

		if ($orderDetails == null) {
			$orderReference = $response['orderid'];
			$orderReference = substr($orderReference, 0, strlen($orderReference) - self::OrderId_SuffixLength);
			$order_model = VmModel::getModel('orders');
			$myorderid = $order_model->getOrderIdByOrderNumber($orderReference);
			$orderDetails = $order_model->getOrder($myorderid);
		}

		$user_id = $orderDetails['details']['BT']->virtuemart_user_id;

		if ($user_id != null) {
			$db = Factory::getContainer()->get(DatabaseInterface::class);
			$query = 'SELECT * FROM `' . $db->getPrefix() . 'virtuemart_payment_plg_' . $this->gatewayName . '_tokens` WHERE `user_id`=' . $user_id;
			$db->setQuery($query);
			$user_tokens = $db->loadObjectList();
			$token_exists = 0;

			if (!empty($user_tokens)) {
				foreach ($user_tokens as $key => $row) {
					if ($row->card_type == $payMethod && $row->last4 == $last4 && $row->expiry_year == $extTokenExpYear && $row->expiry_month == $extTokenExpMonth) {
						$token_exists = 1;
					}
				}
			}

			if (!$token_exists) {
				$db = Factory::getContainer()->get(DatabaseInterface::class);
				$db->setQuery("INSERT INTO `#__virtuemart_payment_plg_cardlinkcard_tokens` (token, user_id, type, last4, expiry_year, expiry_month, card_type) VALUES ('" . $token . "', '" . $user_id . "', 'CC', '" . $last4 . "', '" . $extTokenExpYear . "', '" . $extTokenExpMonth . "', '" . $payMethod . "')");
				$result = $db->execute();
			}
		}
	}

	/*********************/
	/* Private functions */
	/*********************/

	private function _storeCardlinkInternalData($cardlink_data, $virtuemart_order_id, $virtuemart_paymentmethod_id, $order_number)
	{
		$db = Factory::getContainer()->get(DatabaseInterface::class);
		$columns = $db->getTableColumns($this->_tablename);

		// Create a new query object
		$query = $db->getQuery(true);

		// Select all fields from the custom table where column_name equals the given value
		$query->select('*')
			->from($db->quoteName($this->_tablename))
			->where($db->quoteName('virtuemart_order_id') . ' = ' . (int) $virtuemart_order_id);

		// Set the query and load the result
		$db->setQuery($query);

		$response_fields = $db->loadAssoc();
		$response_fields['order_number'] = $order_number;
		$response_fields['virtuemart_order_id'] = $virtuemart_order_id;
		$response_fields['virtuemart_paymentmethod_id'] = $virtuemart_paymentmethod_id;
		$response_fields['payment_name'] = $this->_currentMethod->payment_name;

		if (count($cardlink_data)) {
			foreach ($cardlink_data as $key => $value) {
				$dbkey = 'cardlink_' . strtolower($key);
				if (array_key_exists($dbkey, $columns)) {
					$response_fields[$dbkey] = $value;
				}
			}
			unset($cardlink_data['digest']);
			if (array_key_exists('extToken', $cardlink_data)) {
				unset($cardlink_data['extToken']);
			}

			$response_fields['cardlink_fullresponse'] = json_encode($cardlink_data);
			if (isset($response_fields['cardlink_paymenttotal']))
				$response_fields['payment_order_total'] = $response_fields['cardlink_paymenttotal'];
		}

		$this->log('_storeCardlinkInternalData:', $response_fields);
		return $this->storePSPluginInternalData($response_fields, 'virtuemart_order_id', true);
	}

	/**
	 * Check if the payment conditions are fulfilled for this payment method
	 * @param VirtueMartCart $cart
	 * @param object $activeMethod
	 * @param array $cart_prices
	 * @return bool
	 */
	protected function checkConditions($cart, $activeMethod, $cart_prices)
	{
		//Check method publication start
		if (isset($activeMethod->publishup) && $activeMethod->publishup) {
			$nowDate = Factory::getDate();
			$publish_up = Factory::getDate($activeMethod->publishup);
			if ($publish_up->toUnix() > $nowDate->toUnix()) {
				return FALSE;
			}
		}

		if (isset($activeMethod->publishdown) && $activeMethod->publishdown) {
			$nowDate = Factory::getDate();
			$publish_down = Factory::getDate($activeMethod->publishdown);
			if ($publish_down->toUnix() <= $nowDate->toUnix()) {
				return FALSE;
			}
		}
		$this->convert_condition_amount($activeMethod);

		$address = (($cart->ST == 0) ? $cart->BT : $cart->ST);

		$this->totalOrder = $amount = $this->getCartAmount($cart_prices);
		vmdebug('checkConditions totalOrder', $this->totalOrder);

		$amount_cond = ($amount >= $activeMethod->min_amount and $amount <= $activeMethod->max_amount or ($activeMethod->min_amount <= $amount and ($activeMethod->max_amount == 0)));

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
		return ($amount_cond && (in_array($address['virtuemart_country_id'], $countries) || count($countries) == 0)) ? true : false;
	}

	protected function log($title = '', $data)
	{
		if ($this->_currentMethod->debug) {
			vmdebug('CARDLINK: ' . $title, $data);
		}

		if (!$this->_currentMethod->log) {
			return false;
		}

		if (is_array($data) || is_object($data)) {
			$data = print_r($data, true);
		}

		$n = PHP_EOL; //new line
		$data = $n . $title . $n . "=============================" . $n . $data;

		if (version_compare(JVERSION, '3.2', 'ge')) {
			$logPath = Factory::getApplication()->get('log_path', JPATH_SITE . '/logs');
		} else {
			$logPath = Factory::getApplication()->getConfig()->get('log_path', JPATH_SITE . '/logs');
		}

		if (!is_dir($logPath)) {
			Folder::create($logPath);
		}

		$logFile = $logPath . '/' . $this->gatewayName . '.log';
		if (file_exists($logFile) && filesize($logFile) > 2000000) {
			File::delete($logFile);
		}
		return File::write($logFile, $data, false, true);
	}

	//Check Respone from Bank
	private function validateDigest()
	{
		$post_DIGEST = $_POST['digest'];
		$post_DIGEST_BONUS = $_POST['xlsbonusdigest'];
		//unset($_POST['digest']);
		$digestString = '';
		$digestStringBonus = '';
		foreach ($_POST as $k => $val) {
			if (!in_array($k, array('_charset_', 'digest', 'submitButton', 'xlsbonusadjamt', 'xlsbonustxid', 'xlsbonusstatus', 'xlsbonusdetails', 'xlsbonusdigest'))) {
				$digestString .= filter_var($_POST[$k], FILTER_SANITIZE_STRING);
			}
			if (in_array($k, array('xlsbonusadjamt', 'xlsbonustxid', 'xlsbonusstatus', 'xlsbonusdetails'))) {
				$digestStringBonus .= filter_var($_POST[$k], FILTER_SANITIZE_STRING);
			}
		}

		$secret = $this->_currentMethod->secretkey;
		$form_data = iconv('utf-8', 'utf-8//IGNORE', $digestString) . $secret;
		$computed_digest = base64_encode(hash('sha256', ($form_data), true));
		$form_data_bonus = iconv('utf-8', 'utf-8//IGNORE', $digestStringBonus) . $secret;
		$computed_digest_bonus = base64_encode(hash('sha256', ($form_data_bonus), true));

		$result = false;
		if ($post_DIGEST == $computed_digest) {
			$result = true;
		}
		if ($post_DIGEST_BONUS != '') {
			if ($post_DIGEST_BONUS == $computed_digest_bonus) {
				$result = true;
			} else {
				$result = false;
			}
		}
		$this->log('Signature', $result ? 'Valid' : 'Invalid');
		//JLog::add($result, JLog::WARNING, 'result');

		return $result;
	}

	public function onBeforeCompileHead()
	{
		$app = Factory::getApplication();
		$document = $app->getDocument();
		$wam = $document->getWebAssetManager();

		$isFrontEnd = $app->isClient('site');

		if ($isFrontEnd) {
			$wam->registerAndUseScript($this->gatewayName . '-frontend-script', Uri::root() . 'plugins/vmpayment/' . $this->gatewayName . '/assets/js/scripts-frontend.js');
			$wam->registerAndUseStyle($this->gatewayName . '-frontend-styles', Uri::root() . 'plugins/vmpayment/' . $this->gatewayName . '/assets/css/styles-frontend.css');
		} else {
			$wam->registerAndUseScript($this->gatewayName . '-backend-script', Uri::root() . 'plugins/vmpayment/' . $this->gatewayName . '/assets/js/scripts-backend.js');
			$wam->registerAndUseStyle($this->gatewayName . '-backend-styles', Uri::root() . 'plugins/vmpayment/' . $this->gatewayName . '/assets/css/styles-backend.css');
		}
	}

	protected function renderPluginName($activeMethod, $displayInstallmentsTip = true)
	{
		$app = Factory::getApplication();
		$session = $app->getSession();

		$plugin_name = $this->_psType . '_name';
		$plugin_desc = $this->_psType . '_desc';
		$description = '';
		$logos = $this->getLogos($activeMethod);
		$pluginName = $logos . '<span class="' . $this->_type . '_name">' . $activeMethod->$plugin_name . '</span>';
		if (!empty($activeMethod->$plugin_desc)) {
			$pluginName .= '<span class="' . $this->_type . '_description">' . $activeMethod->$plugin_desc . '</span>';
		}

		$this->installments = 0;
		$this->installmentOptions = array();

		if (isset($activeMethod->allow_installments) && $activeMethod->allow_installments) {
			if (!empty($activeMethod->installments_variations)) {
				$this->installmentOptions = $this->findInstallments($activeMethod->installments_variations, $this->totalOrder);
				$this->installments = count($this->installmentOptions) ? max($this->installmentOptions) : 0;
			} else if ($activeMethod->max_installments > 1) {
				$this->installmentOptions = [];
				for ($i = 2; $i <= $activeMethod->max_installments; $i++) {
					$this->installmentOptions[] = $i;
				}
				$this->installments = $activeMethod->max_installments;
			}
		}

		if ($this->installments > 0) {
			$installs = (int) $session->get('vmpayinstallments' . $activeMethod->virtuemart_paymentmethod_id, 0);
			if ($installs > $this->installments) {
				$installs = $this->installments;
				$session->set('vmpayinstallments' . $activeMethod->virtuemart_paymentmethod_id, $installs);
			}
			$gateway = strtoupper($this->gatewayName);

			if ($displayInstallmentsTip || $installs > 1) {
				$pluginName .= ' <span class="vmpayment_description vminstallments">';
				$pluginName .= ($installs < 2)
					? '<span class="no-print">' . Text::sprintf('VMPAYMENT_' . $gateway . '_MAXINSTALLMENTS', $this->installments) . '</span>'
					: Text::sprintf('VMPAYMENT_' . $gateway . '_SELECTEDINSTALLMENTS', $installs);
				$pluginName .= '</span>';
			}
		}

		if (isset($activeMethod->tokenization)) {
			$this->tokenizationOption = $activeMethod->tokenization;
		}

		return $pluginName;
	}

	protected function getPluginHtml($plugin, $selectedPlugin, $pluginSalesPrice)
	{
		static $results = array();
		$pluginmethod_id = $this->_idName;
		$pluginName = $this->_psType . '_name';

		$isSelectedPlugin = $selectedPlugin == $plugin->$pluginmethod_id;
		$checked = $isSelectedPlugin ? 'checked="checked"' : '';

		$hashKey = $this->_idName . $plugin->$pluginmethod_id;
		if (isset($results[$hashKey]))
			return $results[$hashKey];

		if (!class_exists('CurrencyDisplay')) {
			require (VMPATH_ADMIN . DS . 'helpers' . DS . 'currencydisplay.php');
		}
		$currency = CurrencyDisplay::getInstance();
		$costDisplay = "";
		if ($pluginSalesPrice) {
			$costDisplay = $currency->priceDisplay($pluginSalesPrice);
			$t = vmText::_('COM_VIRTUEMART_PLUGIN_COST_DISPLAY');
			if (strpos($t, '/', $t !== FALSE)) {
				list($discount, $fee) = explode('/', vmText::_('COM_VIRTUEMART_PLUGIN_COST_DISPLAY'));
				if ($pluginSalesPrice >= 0) {
					$costDisplay = '<span class="' . $this->_type . '_cost fee"> (' . $fee . ' +' . $costDisplay . ")</span>";
				} else if ($pluginSalesPrice < 0) {
					$costDisplay = '<span class="' . $this->_type . '_cost discount"> (' . $discount . ' -' . $costDisplay . ")</span>";
				}
			} else {
				$costDisplay = '<span class="' . $this->_type . '_cost fee"> (' . $t . ' +' . $costDisplay . ")</span>";
			}
		}
		$dynUpdate = '';
		if (VmConfig::get('oncheckout_ajax', false)) {
			//$url = Route::_('index.php?option=com_virtuemart&view=cart&task=updatecart&'. $this->_idName. '='.$plugin->$pluginmethod_id );
			$dynUpdate = ' data-dynamic-update="1" ';
		}

		$installmentsHTML = $isSelectedPlugin && method_exists($this, 'installmentsHTML') ? $this->installmentsHTML($plugin, $this->installmentOptions) : '';

		$tokensHTML = $isSelectedPlugin && method_exists($this, 'tokenizationHTML') ? $this->tokenizationHTML($plugin, $this->tokenizationOption) : '';

		$html = '<input type="radio"' . $dynUpdate . ' name="' . $pluginmethod_id . '" id="' . $this->_psType . '_id_' . $plugin->$pluginmethod_id . '"   value="' . $plugin->$pluginmethod_id . '" ' . $checked . ' />' . PHP_EOL
			. '<label for="' . $this->_psType . '_id_' . $plugin->$pluginmethod_id . '">'
			. '<span class="' . $this->_type . ' ' . $this->gatewayName . '">' . $plugin->$pluginName . $costDisplay . '</span>'
			. $installmentsHTML
			. $tokensHTML
			. '</label>'
			. PHP_EOL;

		$results[$hashKey] = $html;
		return $html;
	}

	protected function tokenizationHTML($plugin, $tokenizationOption)
	{
		if (!$tokenizationOption) {
			return '';
		}
		$html = '';

		$app = Factory::getApplication();
		$session = $app->getSession();
		$tokenization_enabled = boolval(isset($_POST['tokenization']) ? (int) $_POST['tokenization'] : (int) $session->get('tokenization'));
		$stored_card_id = isset($_POST['stored_card_id']) ? $_POST['stored_card_id'] : $session->get('stored_card_id');
		$user_id = Factory::getApplication()->getIdentity()->id;

		if ($user_id > 0) {
			if ($stored_card_id != 'new') {
				$tokenization_enabled = 0;
			}

			$html .= $this->get_user_tokens_html($tokenization_enabled, $stored_card_id, $user_id);
		}
		return $html;
	}

	protected function get_user_tokens_html($tokenization_enabled, $stored_card_id, $user_id)
	{
		$db = Factory::getContainer()->get(DatabaseInterface::class);
		$db->setQuery('SELECT * FROM `#__virtuemart_payment_plg_cardlinkcard_tokens` WHERE `user_id`=' . $user_id);
		$user_tokens = $db->loadObjectList();

		$html = '';
		$tokens_html = '';

		$dynUpdate = '';
		if (VmConfig::get('oncheckout_ajax', false)) {
			$dynUpdate = ' data-dynamic-update="1" ';
		}

		$html .= '<div class="payment-cards">';
		if (!empty($user_tokens)) {
			foreach ($user_tokens as $key => $row) {
				if ($row->card_type == 'mastercard') {
					$icon = '<img src="' . Uri::root() . '/plugins/vmpayment/' . $this->gatewayName . '/assets/images/mastercard.svg" alt="mastercard">';
				} elseif ($row->card_type == 'visa') {
					$icon = '<img src="' . Uri::root() . '/plugins/vmpayment/' . $this->gatewayName . '/assets/images/visa.svg" alt="visa">';
				} elseif ($row->card_type == 'amex') {
					$icon = '<img src="' . Uri::root() . '/plugins/vmpayment/' . $this->gatewayName . '/assets/images/amex.svg" alt="visa">';
				} elseif ($row->card_type == 'diners') {
					$icon = '<img src="' . Uri::root() . '/plugins/vmpayment/' . $this->gatewayName . '/assets/images/dinersclub.svg" alt="visa">';
				} elseif ($row->card_type == 'discover') {
					$icon = '<img src="' . Uri::root() . '/plugins/vmpayment/' . $this->gatewayName . '/assets/images/discover.svg" alt="visa">';
				} else {
					$icon = $row->card_type;
				}
				$tokens_html .= '<div class="payment-cards__field">';
				$tokens_html .= '<label for="card-' . $key . '">
									<input type="radio" ' . $dynUpdate . ' id="card-' . $key . '" name="stored_card_id" value="' . $row->token_id . '" ' . ($stored_card_id == $row->token_id ? ' checked' : '') . '> <span>' .
					$icon . ' ************' . $row->last4 . ' ' . $row->expiry_month . '/' . $row->expiry_year .
					'</span><a href="#" title="' . vmText::_('VMPAYMENT_CARDLINKCARD_TOKENIZATION_REMOVE_CARD') . '" class="remove" aria-label="' . vmText::_('VMPAYMENT_CARDLINKCARD_TOKENIZATION_REMOVE_CARD') . '">x</a>' .
					'</label>';
				$tokens_html .= '</div>';
			}
		}

		if ($tokens_html !== "") {
			$html .= '<div class="payment-cards__fields">';
			$html .= $tokens_html;
			$html .= '<div class="payment-cards__field">';
			$html .= '<label for="new-card"><input type="radio" ' . $dynUpdate . ' id="new-card" name="stored_card_id" value="new" ' . ($stored_card_id == 'new' ? ' checked' : '') . '> <span>' . vmText::_('VMPAYMENT_CARDLINKCARD_USE_OTHER_CARD') . '</span></label>';
			$html .= '</div>';
			$html .= '</div>';
			$html .= '<div class="payment-cards-new-card payment-cards__field" ' . ($stored_card_id != 'new' ? 'style="display:none"' : '') . ' >';
		} else {
			$html .= '<div class="payment-cards-new-card payment-cards__field">';
			$html .= '<input type="hidden" name="stored_card_id" value="new" />';
		}

		$html .= '<label for="tokenization">
					<input type="checkbox" ' . $dynUpdate . ' id="tokenization" name="tokenization" value="1" ' . ($tokenization_enabled ? 'checked' : '') . '>
					<span>' . vmText::_('VMPAYMENT_CARDLINKCARD_TOKENIZATION_STORE') . '</span>
				 </label>';
		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}

	protected function installmentsHTML($plugin, $installmentOptions = array())
	{
		if (!count($installmentOptions))
			return '';
		asort($installmentOptions);
		$maxInstall = max($installmentOptions);
		if ($maxInstall <= 1)
			return '';

		$payid = $plugin->virtuemart_paymentmethod_id;
		$app = Factory::getApplication();
		$session = $app->getSession();
		$gateway = strtoupper($this->gatewayName);
		$selected = isset($_POST['installments'][$payid]) ? (int) $_POST['installments'][$payid] : (int) $session->get('vmpayinstallments' . $payid, 0);

		$html = '<div class="payment-installments"><select class="installments" id="installments' . $payid . '" name="installments[' . $payid . ']">';
		if (!(int) $plugin->nooption) {
			$html .= '<option value="0"' . (!$selected ? ' selected="selected"' : '') . '>' . JText::_('VMPAYMENT_' . $gateway . '_NO_INSTALLMENTS') . '</option>';
		}
		for ($i = 2; $i <= $maxInstall; $i++) {
			$html .= '<option value="' . $i . '"' . ($i == $selected ? ' selected="selected"' : '') . '>' . JText::sprintf('VMPAYMENT_' . $gateway . '_INSTALLMENTS', $i) . '</option>';
		}
		$html .= '</select>
		</div>';
		$script = '<script>
			jQuery(document).ready(function($) {
				$("body").on("change","select#installments' . $payid . '", function() {
					selectedPaymentMethod=$("input[name=\'virtuemart_paymentmethod_id\']:checked");
					var paymethid=selectedPaymentMethod.val();
					if($(this).prop("id")=="installments"+selectedPaymentMethod.val()){
						var installments=$(this).val();
						$.ajaxSetup({ beforeSend: function(jqXHR, settings) { settings.data+="&installments["+paymethid+"]="+installments; } });
						selectedPaymentMethod.click();
					}
				});
		});
		</script>';

		if ((int) $plugin->jspos) {
			$html .= $script;
		} else {
			Factory::getApplication()->getDocument()->addCustomTag($script);
		}

		//remove Chosen or SelectBoxIt
		Factory::getApplication()->getDocument()->addStyleDeclaration('select.installments{display: block !important;} div[id^=installments].chzn-container, span[id^=installments].selectboxit-container{display: none !important;}');
		$session->set('vmpayinstallments' . $payid, $selected);
		return $html;
	}

	protected function findInstallments($installs, $total)
	{
		if ($total <= 0)
			return array();

		$cart = VirtueMartCart::getCart(false);
		$minProductInstall = 1;
		$maxProductInstall = 0;
		$installMode = '';

		foreach ($cart->products as $product) {
			if (empty($product->customfields))
				continue;
			foreach ($product->customfields as $custom) {
				if ($custom->custom_element == 'vmweinstallments' && !empty($custom->installs) && $custom->installs > $maxProductInstall) {
					$maxProductInstall = $custom->installs;
					$minProductInstall = $custom->installsfrom;
					$installMode = $custom->installmode;
					break;
				}
			}
		}

		$installments = array();
		if ($installMode == 'exact' && $maxProductInstall) {
			$installments = array($maxProductInstall);
		} else if ($installMode == 'range' && $maxProductInstall) {
			for ($i = $minProductInstall; $i <= $maxProductInstall; $i++) {
				$installments[] = $i;
			}
		} else {
			$tmp = @explode(",", trim($installs));
			if (empty($installs) || !count($tmp))
				return array();

			foreach ($tmp as $inst) {
				$vars = explode(":", $inst, 2);
				$amount = (float) trim($vars[0]);
				$install = (int) trim($vars[1]);
				if ($amount >= 0 && $total >= $amount && (!$maxProductInstall || $maxProductInstall >= $install)) {
					$installments[] = $install;
				}
			}
		}

		return $installments;
	}

	protected function getOrderByID($virtuemart_order_id)
	{
		if (method_exists($this, 'getDatasByOrderId')) {
			return $this->getDatasByOrderId($virtuemart_order_id);
		}
		$db = Factory::getContainer()->get(DatabaseInterface::class);
		$q = 'SELECT * FROM `' . $this->_tablename . '` '
			. 'WHERE `virtuemart_order_id` = "' . $virtuemart_order_id . '" '
			. 'ORDER BY `id` ASC';
		$db->setQuery($q);
		return $db->loadObjectList();
	}

	protected function getDataOrderNumber($order_number)
	{
		if (is_callable('parent::getDataByOrderNumber'))
			return parent::getDataByOrderNumber($order_number);
		$db = Factory::getContainer()->get(DatabaseInterface::class);
		$db->setQuery('SELECT * FROM `' . $this->_tablename . '` WHERE `order_number`="' . $db->escape($order_number) . '"');
		return $db->loadObjectList();
	}

	function getEmailCurrency(&$method)
	{
		if (is_callable('parent::getEmailCurrency'))
			return parent::getEmailCurrency($method);
		if (!isset($method->email_currency) or $method->email_currency == 'vendor') {
			$vendorId = 1; //VirtueMartModelVendor::getLoggedVendor();
			$db = Factory::getContainer()->get(DatabaseInterface::class);
			$q = 'SELECT   `vendor_currency` FROM `#__virtuemart_vendors` WHERE `virtuemart_vendor_id`=' . $vendorId;
			$db->setQuery($q);
			return $db->loadResult();
		}
		return $method->payment_currency; // either the vendor currency, either same currency as payment
	}

	function getCartAmount($cart_prices, $forShipment = false)
	{
		if (method_exists($this, 'getCartAmount'))
			return parent::getCartAmount($cart_prices);
		if (!isset($cart_prices['salesPrice']) || empty($cart_prices['salesPrice']))
			$cart_prices['salesPrice'] = 0.0;
		$cartPrice = isset($cart_prices['withTax']) && !empty($cart_prices['withTax']) ? $cart_prices['withTax'] : $cart_prices['salesPrice'];
		if (!isset($cart_prices['salesPriceShipment']) || empty($cart_prices['salesPriceShipment']))
			$cart_prices['salesPriceShipment'] = 0.0;
		if (!isset($cart_prices['salesPriceCoupon']) || empty($cart_prices['salesPriceCoupon']))
			$cart_prices['salesPriceCoupon'] = 0.0;
		$amount = $cartPrice + $cart_prices['salesPriceShipment'] + $cart_prices['salesPriceCoupon'];
		if ($amount <= 0)
			$amount = 0;
		return $amount;
	}

	function convert_condition_amount(&$method)
	{
		if (is_callable('parent::convert_condition_amount')) {
			parent::convert_condition_amount($method);
		} else {
			$method->min_amount = (float) str_replace(',', '.', $method->min_amount);
			$method->max_amount = (float) str_replace(',', '.', $method->max_amount);
		}
	}

	/**
	 * @param   int $virtuemart_order_id
	 * @param string $order_number
	 * @return mixed|string
	 */
	protected function _getGatewayInternalData($virtuemart_order_id, $order_number = '')
	{
		if (empty($order_number)) {
			$orderModel = VmModel::getModel('orders');
			$order_number = $orderModel->getOrderNumber($virtuemart_order_id);
		}
		$db = Factory::getContainer()->get(DatabaseInterface::class);
		$q = 'SELECT * FROM `' . $this->_tablename . '` WHERE `order_number` = ' . $db->quote($db->escape($order_number), false);
		$db->setQuery($q);

		if (!($payments = $db->loadObjectList())) {
			$this->log('_getGatewayInternalData Error:', $db->errorMsg);
			return array();
		}
		$this->log('_getGatewayInternalData:', $payments);
		return $payments;
	}

	// /**
	//  * @param $product
	//  * @param $productDisplay
	//  * @return bool
	//  */
	// function plgVmOnProductDisplayPayment($product, &$productDisplay)
	// {
	// 	return;
	// }

	/**
	 * @param null $msg
	 */
	function redirectToCart($msg = NULL)
	{
		if (!$msg) {
			$msg = vmText::_('VMPAYMENT_CARDLINKCARD_ERROR_TRY_AGAIN');
		}
		$app = Factory::getApplication();
		$app->redirect(Route::_('index.php?option=com_virtuemart&view=cart&Itemid=' . vRequest::getInt('Itemid'), false), $msg);
	}

	//Get Logos HTML
	protected function getLogos($activeMethod)
	{
		$logosFieldName = $this->_psType . '_logos';
		$logos = $activeMethod->$logosFieldName;
		$returnLogo = '';
		if (!empty($logos) && $logos != '-1' && $logos != '' && $logos != 'default') {
			$returnLogo = $this->displayLogos($logos) . ' ';
		}
		return $returnLogo;
	}

	protected function getPaymentMethodFromOrder($orderDetails)
	{
		$paymentMethodId = $orderDetails['details']['BT']->virtuemart_paymentmethod_id;

		// Load the VirtueMart payment method model
		if (!class_exists('VirtueMartModelPaymentmethod')) {
			require (JPATH_VM_ADMINISTRATOR . '/models/paymentmethod.php');
		}

		$paymentMethodModel = VmModel::getModel('paymentmethod');
		$paymentMethod = $paymentMethodModel->getPayment($paymentMethodId);

		return $paymentMethod;
	}

	public function onAjaxCardlinkcard()
	{
		// Check for a valid token to prevent CSRF
		Session::checkToken('post') or jexit('Invalid Token');

		$app = Factory::getApplication();

		// Set the response content type to JSON
		$app->setHeader('Content-Type', 'application/json', true);
		header('Content-Type: application/json');

		// Get the order ID from the request
		$orderId = $app->input->getInt('order_id');
		$refID = $app->input->get('order_ref_id');

		if (!$orderId || !$refID) {
			echo new JsonResponse(null, JText::_('PLG_VMPAYMENT_ORDERINFO_NO_ORDER_ID'), true);
			$app->close();
			return;
		}

		// Load the order information
		$orderModel = VmModel::getModel('orders');
		$order = $orderModel->getOrder($orderId);

		if (!$order) {
			echo new JsonResponse(null, JText::_('PLG_VMPAYMENT_ORDERINFO_ORDER_NOT_FOUND'), true);
			$app->close();
			return;
		}

		if ($order['details']['BT']->order_status_code != 'P' && $order['details']['BT']->order_status_code != 'X') {
			echo new JsonResponse(null, JText::_('PLG_VMPAYMENT_ORDERINFO_ORDER_NOT_PENDING'), true);
			$app->close();
			return;
		}

		$currentMethod = $this->getPaymentMethodFromOrder($order);

		if (method_exists('VmConfig', 'loadJLang')) {
			VmConfig::loadJLang('com_virtuemart_orders', TRUE);
		}
		$lang = explode('-', $app->getLanguage()->getTag());

		$paymentData = array_pop($this->_getGatewayInternalData($orderId, $refID));
		$billCountry = ShopFunctions::getCountryByID($order['details']['BT']->virtuemart_country_id, 'country_2_code');
		$shipCountry = ShopFunctions::getCountryByID($order['details']['ST']->virtuemart_country_id, 'country_2_code');

		$post = array(
			'version' => 2,
			'mid' => trim($currentMethod->mid),
			'lang' => $lang[0] == 'el' ? 'el' : 'en',
			'orderid' => str_replace(array('_', '-'), '', $refID) . 'x' . self::_getRandomStringHash(self::OrderId_SuffixLength - 1),
			'orderDesc' => 'Order: ' . $refID,
			'orderAmount' => number_format($order['details']['BT']->order_total, 2, ".", ""),
			'currency' => ShopFunctions::getCurrencyByID($order['details']['BT']->payment_currency_id, 'currency_code_3'),
			'payerEmail' => $order['details']['BT']->email,
			'payerPhone' => $order['details']['BT']->phone_1,
			'billCountry' => $billCountry,
			'billState' => isset($order['details']['BT']->virtuemart_state_id) ? ShopFunctions::getStateByID($order['details']['BT']->virtuemart_state_id, 'state_name') : '',
			'billZip' => str_replace(' ', '', $order['details']['BT']->zip),
			'billCity' => trim($order['details']['BT']->city),
			'billAddress' => !empty($order['details']['BT']->address_1) ? trim($order['details']['BT']->address_1) : trim($order['details']['BT']->address_2),
			'shipCountry' => $shipCountry,
			'shipState' => isset($order['details']['BT']->virtuemart_state_id) ? ShopFunctions::getStateByID($order['details']['BT']->virtuemart_state_id, 'state_name') : '',
			'shipZip' => str_replace(' ', '', $order['details']['ST']->zip),
			'shipCity' => trim($order['details']['ST']->city),
			'shipAddress' => !empty($order['details']['ST']->address_1) ? trim($order['details']['ST']->address_1) : trim($order['details']['ST']->address_2),
			'trType' => (int) $currentMethod->paytype,
		);

		if ($billCountry == 'GR' || empty($post['billState'])) {
			unset($post['billState']);
			unset($post['shipState']);
		}

		$installments = $paymentData->installments;

		if ($installments > 1) {
			$post['extInstallmentoffset'] = 0;
			$post['extInstallmentperiod'] = $installments;
		}

		if ($currentMethod->css_url) {
			$post['cssUrl'] = $currentMethod->css_url;
		}

		$post['confirmUrl'] = Uri::root() . '?com=cardlink&type=card&status=ok&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id . '&on=' . $refID;
		$post['cancelUrl'] = Uri::root() . '?com=cardlink&type=card&status=cancel&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id . '&on=' . $refID;

		$urlParams = array('Itemid' => vRequest::getInt('Itemid', (int) $app->getMenu()->getActive()->id), 'lang' => vRequest::getCmd('lang', $lang[0]));
		foreach ($urlParams as $key => $val) {
			if ($val == '' || $val === 0)
				continue;
			$post['confirmUrl'] .= '&' . $key . '=' . urlencode($val);
			$post['cancelUrl'] .= '&' . $key . '=' . urlencode($val);
		}

		if (!JFactory::getUser()->guest) {
			$user_id = $app->getIdentity()->id;
			$stored_card_id = $paymentData->token_id;

			if ($paymentData->tokenize) {
				$post['extTokenOptions'] = 100;
			} else if ($stored_card_id) {
				$db = Factory::getContainer()->get(DatabaseInterface::class);
				$db->setQuery('SELECT * FROM `#__virtuemart_payment_plg_cardlinkcard_tokens` WHERE `user_id`=' . $user_id . ' LIMIT 1');
				$user_tokens = $db->loadObjectList();

				if (count($user_tokens) == 1) {
					$post['extTokenOptions'] = 110;
					$post['extToken'] = $user_tokens[0]->token;
				}
			}
		}

		$post['var1'] = $refID;

		$form_secret = $currentMethod->secretkey;
		$form_data = iconv('utf-8', 'utf-8//IGNORE', implode("", $post)) . $form_secret;
		$post['digest'] = base64_encode(hash('sha256', ($form_data), true));

		$acquirer = $currentMethod->acquirer;
		$demoaccount = $currentMethod->demoaccount;

		$url = '';
		if ($demoaccount) {
			switch ($acquirer) {
				case 0:
					$url = "https://ecommerce-test.cardlink.gr/vpos/shophandlermpi";
					break;
				case 1:
					$url = "https://alphaecommerce-test.cardlink.gr/vpos/shophandlermpi";
					break;
				case 2:
					$url = "https://eurocommerce-test.cardlink.gr/vpos/shophandlermpi";
					break;
			}
		} else {
			switch ($acquirer) {
				case 0:
					$url = "https://ecommerce.cardlink.gr/vpos/shophandlermpi";
					break;
				case 1:
					$url = "https://www.alphaecommerce.gr/vpos/shophandlermpi";
					break;
				case 2:
					$url = "https://vpos.eurocommerce.gr/vpos/shophandlermpi";
					break;
			}
		}

		// Return the order information
		echo new JsonResponse([
			'date' => date('Y-m-d H:i:s'),
			'url' => $url,
			'post_data' => $post
		], JText::_('PLG_VMPAYMENT_ORDERINFO_SUCCESS'), false, true);

		$app->close();
	}
}