jQuery( function( $ ) {
	'use strict';
	
	window.rocketr_payment_checker = {
		checkOrderStatus: function(callback) {
			$.ajax({
				url: 'https://api.rocketr.net/orders/' + this.orderId + '/status',
				headers: {
					'Application-ID': this.applicationId
				},
				method: 'GET',
				dataType: 'json',
				crossOrigin: true,
				success: function(data) {
					if(data.status == null)
						return;
					console.log(data.status)
					if(data.status > 0)
						return callback(true);
				},
				error: function (error) { 
					console.log(error);
				}
			});
			return false;
		}
	};
	
	window.rocketr_payment_checker.applicationId = wc_rocketr_passed_data.rocketr_api_application_id;
	window.rocketr_payment_checker.orderId = wc_rocketr_passed_data.rocketr_order_id;
	
	window.setInterval(function() {
		window.rocketr_payment_checker.checkOrderStatus(function(result){
			if(result === true) {
				window.location.href = wc_rocketr_passed_data.redirect_url; 
			}
		});
	}, 30000);
	
});