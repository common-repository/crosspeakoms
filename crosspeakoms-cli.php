<?php
/**
 * CrossPeak WP CLI class
 */
class CrossPeakOMS_CLI extends WP_CLI_Command {

	/**
	 * Push a list of orders into CrossPeak.
	 * May require setting the SSL cert in the request;
	 * ex: `wp crosspeakoms send_orders --url=https://example.com`.
	 *
	 * @return void
	 */
	public function send_orders() {
		$crosspeak = CrossPeak_OMS::get_instance();
		$crosspeak->send_orders();
	}

	/**
	 * WP CLI function to test your connection.
	 * ex: `wp crosspeakoms test_connection --url=https://example.com`.
	 *
	 * @return void
	 */
	public function test_connection() {
		$crosspeak = CrossPeak_OMS::get_instance();

		$resp = $crosspeak->test_connection();

		if ( is_array( $resp ) ) {
			if ( $resp['status'] == true ) {
				WP_CLI::success( "Lookin' good!" );
			} else {
				WP_CLI::error( $resp['error'] );
			}
		} else {
			WP_CLI::error( $resp );
		}
	}
}
WP_CLI::add_command( 'crosspeakoms', 'CrossPeakOMS_CLI' );
