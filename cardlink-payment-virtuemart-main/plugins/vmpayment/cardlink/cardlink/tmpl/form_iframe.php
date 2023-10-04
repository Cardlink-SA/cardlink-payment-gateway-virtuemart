<?php
/**
 *
 * Eurobank payment plugin
 */
defined('_JEXEC') or die();

$post = $viewData["form_params"];
$url = $viewData["form_url"];
$logos = $viewData["logos"];

$logos=str_replace('<img ','<img',$logos);
$logoURL=JURI::root(true).'/plugins/vmpayment/cardlink/cardlink/images/cardlink.png';
$totalAmount=$post['orderAmount'];
$order_id=$post['orderid'];
?>
<div id="cardlink" class="cardlink paymentgateway" style="margin:0 auto;text-align:center;">
	<img src="<?php echo $logoURL;?>" border="0" id="cardlinklogo" style="width:350px;cursor:pointer;" />
	<form id="vmPaymentForm" name="paycardCardlink" method="post" action="<?php echo $url;?>" target="payment_iframe"  accept-charset="UTF-8">
		<?php foreach($post as $name=>$value) echo '<input type="hidden" id="'.$name.'" name="'.$name.'" value="'.$value.'" />'."\n"; ?>
	</form>
	<button class="btn btn-primary paynow" onclick="document.paycardCardlink.submit(); document.getElementById('modal').style.display = 'block';"><?php echo vmText::_('VMPAYMENT_CARDLINK_REDIRECT_MESSAGE');?></button>
	<script>
		/* setTimeout(function(){
			document.paycardCardlink.submit(); 
			document.getElementById('modal').style.display = 'block';
		},5000); */
	</script>
</div>




<div id="modal" class="modal" style="display:none">
	<div class="modal_wrapper">
		<iframe name="payment_iframe" id="payment_iframe" data-order-id="<?php echo $order_id; ?>" src="" frameBorder="0" width="100%" height="700"></iframe>
	</div>
</div>