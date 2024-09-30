<?php

defined('_JEXEC') or die();

use Joomla\CMS\Router\Route;

$success = $viewData["success"];
$payment_name = $viewData["payment_name"];
$payment = $viewData["payment"];
$response = $viewData["response"];
$order = $viewData["order"];
$currency = $viewData["currency"];
$total = $viewData["total"];
$paymentID = (!isset($payment->cardlink_txid) || empty($payment->cardlink_txid)) ? $response->txId : $payment->cardlink_txid;
$orderURL = Route::_('index.php?option=com_virtuemart&view=orders&layout=details&order_number=' . $order['details']['BT']->order_number . '&order_pass=' . $order['details']['BT']->order_pass, false);
$refID = $viewData["params"]->referenceid;
?>
<table>
	<tr>
		<td width="150">
			<?php echo vmText::_('VMPAYMENT_CARDLINKIRIS_PAYMENT_NAME'); ?>
		</td>
		<td>
			<?php echo $payment_name; ?>
		</td>
	</tr>

	<tr>
		<td width="150">
			<?php echo vmText::_('COM_VIRTUEMART_ORDER_NUMBER'); ?>
		</td>
		<td>
			<?php echo $order['details']['BT']->{$refID}; ?>
		</td>
	</tr>
	<tr>
		<td width="150">
			<?php echo vmText::_('VMPAYMENT_CARDLINKIRIS_PAYMENT_STATUS'); ?>
		</td>
		<td>
			<?php echo ($success) ? vmText::_('VMPAYMENT_CARDLINKIRIS_PAYMENT_SUCCESS') : vmText::_('VMPAYMENT_CARDLINKIRIS_PAYMENT_FAILED'); ?>
		</td>
	</tr>
	<?php if ($success) { ?>
		<?php if ($total) { ?>
			<tr>
				<td width="150">
					<?php echo vmText::_('VMPAYMENT_CARDLINKIRIS_AMOUNT'); ?>
				</td>
				<td>
					<?php echo $currency->priceDisplay($total, $payment->payment_currency); ?>
				</td>
			</tr>
		<?php } ?>
		<?php if (!empty($paymentID)) { ?>
			<tr>
				<td width="150">
					<?php echo vmText::_('VMPAYMENT_CARDLINKIRIS_TRANSACTION_ID'); ?>
				</td>
				<td>
					<?php echo $paymentID; ?>
				</td>
			</tr>
		<?php } ?>
	<?php } ?>
</table>
<?php if ($success) { ?>
	<br />
	<a class="btn btn-success vm-button-correct" href="<?php echo $orderURL; ?>">
		<?php echo vmText::_('COM_VIRTUEMART_ORDER_VIEW_ORDER'); ?>
	</a>
	<a class="btn btn-info vm-button-correct"
		href="javascript:void window.open('<?php echo $orderURL; ?>&tmpl=component', 'win2', 'status=no,toolbar=no,scrollbars=yes,titlebar=no,menubar=no,resizable=yes,width=640,height=480,directories=no,location=no');">
		<?php echo vmText::_('COM_VIRTUEMART_PRINT'); ?>
	</a>
<?php } ?>