(function($){

	$( document ).ready(
		function() {

			$( '#woocommerce_crosspeakoms_test_connection' ).val( 'Test Connection' );

			$( '#woocommerce_crosspeakoms_test_connection' ).click(
				function() {
					$.ajax(
						{
							url : ajaxurl,
							type : 'GET',
							data : {
								action : 'test_connection',
							},
							dataType : 'json',
							success : function( response ) {

								console.log( response );

								if ( typeof response === 'undefined' || ! response ) {
									alert( "Unable to connect for an unknown reason. Double check the CrossPeak URL and API token." );
								} else if ( true === response.status ) {
									alert( "Lookin' good!" );
								} else if ( typeof response.error != 'undefined' ) {
									alert( response.error );
								} else {
									alert( response );
								}
							},
							error : function( ) {
								alert( "An error occurred performing the test" );
							}

						}
					);
				}
			);
		}
	);
})( jQuery );
