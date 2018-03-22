jQuery( function( $ ) {
	'use strict';
	
	var rocketr_form = {
		init: function() {
			if ( $( 'form.woocommerce-checkout' ).length ) {
				this.woocommerce_form = $( 'form.woocommerce-checkout' );
			}
			$( 'form.woocommerce-checkout' ).on('checkout_place_order_rocketr', this.onSubmit);
		},
		onSubmit: function(e) {
			var payment_method = $('#rocketr-select-payment-method').val()
			rocketr_form.woocommerce_form.append( "<input type='hidden' class='rocketr-payment-method' name='rocketr-payment-method' value='" + payment_method + "'/>" );
			return true;
		}
	};
	rocketr_form.init();
});