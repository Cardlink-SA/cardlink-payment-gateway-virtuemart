(function ($) {
	'use strict';

	function deletePaymentCard() {
		$("body").on("click", ".payment-cards .remove", function (e) {
			e.preventDefault();
			var selected_card_id = $(this).parent().children('input').attr('id');
			var selected_card = '#' + selected_card_id;
			var selected_card_value = $(this).parent().children('input').val();

			jQuery.ajax({
				url: 'index.php?option=com_virtuemart&view=plugin&type=vmpayment&name=cardlinkcard&task=deleteToken',
				data: {
					selected_card_value: selected_card_value
				},
				type: "post",
				success: function (response) {
					$(selected_card).parent().hide();
				},
				error: function (response) {
					console.log('error on delete card');
				}
			});
		});
	}

	function check_order_status(orderId) {
		var polling = setInterval(function () {
			var $iframe = $('#payment_iframe');
			if ($iframe.attr('src') != 'about:blank') {
				$.ajax({
					url: 'index.php?option=com_virtuemart&view=plugin&type=vmpayment&name=cardlinkcard&task=checkOrderStatus',
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
		deletePaymentCard();

		var $storedCardRadioOption = $('input[type="radio"][name="selected_card"]');

		if ($storedCardRadioOption.length > 0) {
			$storedCardRadioOption.on('change', function (e) {
				var checkedValue = $('input[type="radio"][name="selected_card"]:checked').val();
				var $newCardStoreField = $('div.payment-cards-new-card.payment-cards__field');
				if (checkedValue == 'new') {
					$newCardStoreField.show();
					$newCardStoreField.find('input[type="checkbox"]').attr('disabled', false);
				} else {
					$newCardStoreField.hide();
					$newCardStoreField.find('input[type="checkbox"]').attr('disabled', true);
				}
			});
			$storedCardRadioOption.trigger('change');
		}

		var $iframe = $('#payment_iframe');
		if ($iframe.length > 0) {
			modalPayment($iframe);
		}
	});

})(jQuery);
