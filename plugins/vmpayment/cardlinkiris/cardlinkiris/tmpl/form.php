<?php
/**
 *
 * Eurobank payment plugin
 */
defined('_JEXEC') or die();

$post = $viewData["form_params"];
$url = $viewData["form_url"];
$logos = $viewData["logos"];

$logos=str_replace('<img ','<img onclick="document.paycardCardlink.submit();" ',$logos);
$logoURL=JURI::root(true).'/plugins/vmpayment/cardlink/cardlink/images/cardlink.png';
$totalAmount=$post['orderAmount'];

?>
<div id="cardlink" class="cardlink paymentgateway" style="margin:0 auto;text-align:center;">
	<img onclick="document.paycardCardlink.submit();" src="<?php echo $logoURL;?>" border="0" id="cardlinklogo" style="width:350px;cursor:pointer;" />
	<form id="vmPaymentForm" name="paycardCardlink" method="post" action="<?php echo $url;?>" accept-charset="UTF-8">
		<?php foreach($post as $name=>$value) echo '<input type="hidden" id="'.$name.'" name="'.$name.'" value="'.$value.'" />'."\n"; ?>
	</form>
	<button class="btn btn-primary paynow" onclick="document.paycardCardlink.submit();"><?php echo vmText::_('VMPAYMENT_CARDLINK_REDIRECT_MESSAGE');?></button>
	<script>//setTimeout(function(){document.paycardCardlink.submit();},5000);</script>
</div>