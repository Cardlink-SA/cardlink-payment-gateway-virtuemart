<?php

defined('_JEXEC') or die();

use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Factory;

// Get the application instance
$app = Factory::getApplication();

// Disable caching
$app->set('caching', 0);

$logos = $viewData["logos"];
$logos = str_replace('<img ', '<img', $logos);
$logoURL = Uri::root(true) . '/plugins/vmpayment/cardlinkiris/assets/images/iris.png';

?>
<div id="cardlinkiris" class="cardlinkiris paymentgateway" style="margin:0 auto;text-align:center;">
	<img src="<?php echo $logoURL; ?>" border="0" id="cardlinkIrisLogo" style="width:350px;cursor:pointer;" />

	<form id="vmPaymentForm" name="payCardlinkIris" method="post" action="about:blank" target="payment_iframe"
		accept-charset="UTF-8" data-date="<?php echo date("Y-m-d H:i:s"); ?>">
	</form>

	<script>
		jQuery(document).ready(function ($) {
			$.ajax({
				url: '<?php echo Uri::root(true); ?>/index.php?option=com_ajax&plugin=cardlinkiris&group=vmpayment&format=json&rnd=' + (Math.random() * 100),
				type: 'POST',
				data: {
					order_id: '<?php echo $viewData['order_id']; ?>',
					order_ref_id: '<?php echo $viewData['order_ref_id']; ?>',
					'<?php echo JSession::getFormToken(); ?>': 1
				},
				success: function (response) {
					if (response.success) {
						if (response.data.url) {
							let $form = $('form#vmPaymentForm');
							$form.attr('action', response.data.url);
							$.each(response.data.post_data, function (k, v) {
								$('<input>').attr({ type: 'hidden', id: k, name: k, value: v }).appendTo($form);
							});
							$('<button class="btn btn-primary paynow">'
								+ '<?php echo vmText::_('VMPAYMENT_CARDLINKIRIS_REDIRECT_MESSAGE'); ?>'
								+ '</button>')
								.on('click', function () {
									document.payCardlinkIris.submit();
									document.getElementById('modal').style.display = 'block';
								})
								.appendTo($form);

							document.getElementById('modal').style.display = 'block';
							document.payCardlinkIris.submit();
						}
					} else {
						console.error('Error:', response.message);
					}
				}
			});
		});
	</script>
</div>
<div id="modal" class="modal" style="display:none">
	<iframe name="payment_iframe" id="payment_iframe" data-order-id="<?php echo $order_id; ?>" src="about:blank"
		frameBorder="0"></iframe>
</div>