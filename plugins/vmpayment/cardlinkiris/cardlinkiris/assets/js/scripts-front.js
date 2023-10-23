(function( $ ) {
	'use strict';


	$(document).ready(function () {
		deletePaymentCard();
		
		var $iframe = $('#payment_iframe');
		if ($iframe.length > 0) {
			modalPayment($iframe);
		}
	});

	function deletePaymentCard() {
		$("body").on("click", ".payment-cards .remove", function (e) {
			e.preventDefault();
			var selected_card_id = $(this).parent().children('input').attr('id');
			var selected_card = '#'+selected_card_id;
			var selected_card_value = $(this).parent().children('input').val();

			
			jQuery.ajax({
				url: 'index.php?option=com_ajax&plugin=deleteCardlinkToken&format=json',
				data:{
					selected_card_value: selected_card_value
				},
				type: "post",
				success: function(response){
					$(selected_card).parent().hide();
				},
				error:function(response){
					console.log('error on delete card');
				}
			});
		});
	}

	function check_order_status(orderId) {
		var polling = setInterval(function () {
			$.ajax({
				url: 'index.php?option=com_ajax&plugin=checkOrderStatus&format=json',
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
		}, 1000);
	}

	function modalPayment($iframe) {
		var orderId = $iframe.data('order-id');
		check_order_status(orderId);
	}



})( jQuery );
