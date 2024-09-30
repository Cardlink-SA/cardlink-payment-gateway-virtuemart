(function ($) {
	'use strict';

	function check_order_status(orderId) {
		var polling = setInterval(function () {
			var $iframe = $('#payment_iframe');
			if ($iframe.attr('src') != 'about:blank') {
				$.ajax({
					url: 'index.php?option=com_virtuemart&view=plugin&type=vmpayment&name=cardlinkiris&task=checkOrderStatus',
					data: {
						order_id: orderId
					},
					type: 'post',
					dataType: 'json',
					success: function (response) {
						var redirectUrl = response.data[0].redirect_url;
						var redirected = response.data[0].redirected;
						if (!redirected && redirectUrl) {
							clearInterval(polling);
							window.location.href = redirectUrl;
						}
					},
					error: function (error) {
						clearInterval(polling);
						window.location.reload();
					}
				});
			}
		}, 1000);
	}

	function modalPayment($iframe) {
		var orderId = $iframe.data('order-id');
		check_order_status(orderId);
	}

	$(document).ready(function () {
		var $iframe = $('#payment_iframe');
		if ($iframe.length > 0) {
			modalPayment($iframe);
		}
	});

})(jQuery);
