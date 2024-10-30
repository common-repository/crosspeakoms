<?php
/**
 *
 * @link              https://www.crosspeakoms.com/
 * @since             1.0.0
 * @package           WC_CrossPeak_OMS
 *
 * @wordpress-plugin
 * Plugin Name:       CrossPeak OMS for WooCommerce
 * Plugin URI:        https://www.crosspeakoms.com/
 * Description:       Integrates WooCommerce with CrossPeak OMS v2+
 * Version:           2.0.1
 * Author:            CrossPeak OMS
 * Author URI:        https://www.crosspeakoms.com/
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       crosspeakoms
 * Requires PHP: 7.1
 * WC tested up to:   8.0.3
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Make sure WP_CLI class exists and load CLI commands.
if ( class_exists( 'WP_CLI_Command' ) ) {
	require_once dirname( __FILE__ ) . '/crosspeakoms-cli.php';
}

class CrossPeak_OMS {
	/**
	 * Instance of this class.
	 *
	 * @since    1.0.0
	 *
	 * @var     object
	 */
	protected static $instance = null;

	/**
	 * Plugin Name
	 *
	 * @since    1.0.0
	 *
	 * @var     string
	 */
	public $plugin_name;

	/**
	 * Version
	 *
	 * @since    1.0.0
	 *
	 * @var     string
	 */
	public $version;

	/**
	 * Options
	 *
	 * @since    1.0.0
	 *
	 * @var     array
	 */
	private $options;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks.
	 *
	 * @since   1.0.0
	 */
	public function __construct() {

		$this->plugin_name = 'crosspeakoms';
		$this->version     = '1.4.1';

		// Checks if WooCommerce is installed.
		if ( class_exists( 'WC_Integration' ) && defined( 'WOOCOMMERCE_VERSION' ) && version_compare( WOOCOMMERCE_VERSION, '2.1', '>=' ) ) {
			require_once dirname( __FILE__ ) . '/crosspeakoms-admin.php';
			// Register the integration.
			add_filter( 'woocommerce_integrations', array( $this, 'add_integration' ) );
		}

		// Order created.
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'process_order_update' ) );
		// do_action( 'woocommerce_new_order', $order->get_id() );
		add_action( 'woocommerce_new_order', array( $this, 'process_order_update' ) );

		// Order updated.
		// do_action( 'woocommerce_update_order', $order->get_id() );
		add_action( 'woocommerce_update_order', array( $this, 'process_order_update' ) );
		add_action( 'woocommerce_order_edit_status', array( $this, 'process_order_update' ) );
		add_action( 'woocommerce_order_status_changed', array( $this, 'process_order_update' ) );
		// do_action( 'woocommerce_process_shop_order_meta', $post_id, $post );
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'process_order_update' ) );

		// Order deleted.
		add_action( 'wp_trash_post', array( $this, 'process_order_update' ) );

		// Order Created by API.
		add_action( 'woocommerce_rest_pre_insert_shop_order_object', array( $this, 'api_add_crosspeak_order_id' ), 10, 3 );

		// Post save.
		add_action( 'save_post', array( $this, 'send_posts' ), 9999, 3 );

		// Save order notes to pending.
		add_action( 'wp_insert_comment', array( $this, 'add_order_note_to_pending' ), 10, 2 );

		// Modify the order note before it is created.
		add_action( 'woocommerce_rest_insert_order_note', array( $this, 'api_add_crosspeak_order_note' ), 20, 3 );

		// Product stock.
		add_action( 'woocommerce_product_set_stock', array( $this, 'send_product_stock' ) );

		// Tracking cookie.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_tracking_scripts' ) );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_tracking_data' ), 10, 1 );

		// Enable connection testing.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_test_connection' ) );
		add_action( 'wp_ajax_test_connection', array( $this, 'test_connection' ) );

		// CrossPeak overrides for cart shipping and discounts.
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'apply_cart_discount' ) );
		add_filter( 'woocommerce_package_rates', array( $this, 'apply_shipping_override' ), 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_custom_product_prices' ) );

		add_action( 'woocommerce_email_order_details', array( $this, 'email_shipping_details' ), 5, 3 );

		add_filter( 'woocommerce_my_account_my_orders_columns', array( $this, 'my_orders_columns' ), 10, 1 );
		add_action( 'woocommerce_my_account_my_orders_column_crosspeak-tracking', array( $this, 'my_orders_tracking' ), 10, 1 );

		add_action( 'woocommerce_order_details_before_order_table', array( $this, 'order_details_tracking' ), 10, 1 );

		// Extend the REST API for CrossPeak.
		add_action( 'rest_api_init', array( $this, 'rest_routes' ) );

		// Action Scheduler Commands.
		add_action( 'crosspeak_product_update_task', array( $this, 'product_update' ), 10, 1 );
		add_action( 'crosspeak_coupon_update_task', array( $this, 'coupon_update' ), 10, 1 );
		add_action( 'crosspeak_order_update_task', array( $this, 'order_update' ), 10, 1 );
		add_action( 'crosspeak_product_stock_update_task', array( $this, 'stock_update' ), 10, 1 );
	}

	/**
	 * Return a list of all pending updates.
	 *
	 * @since     1.0.0
	 *
	 * @return   object  A JSON object of DB values.
	 */
	public function get_pending_updates() {

		global $wpdb;

		$results = $wpdb->get_results(
			"SELECT *
			FROM {$wpdb->prefix}crosspeak_pending_updates LIMIT 100"
		);

		return $results;
	}

	/**
	 * Get Customer by email
	 *
	 * @param WP_REST_Request $request  Request object.
	 * @return   object  A JSON object of DB values.
	 */
	public function get_customer( $request ) {
		$user_data = WP_User::get_data_by( 'email', $request->get_param( 'email' ) );
		if ( empty( $user_data ) ) {
			return array( 'error' => 'Email not found' );
		}
		return $user_data;
	}

	/**
	 * Add an object to the pending list.
	 *
	 * @since     1.0.0
	 *
	 * @param string $object_type Object type.
	 * @param int    $object_id Object ID.
	 * @param int    $object_parent Parent Object ID.
	 *
	 * @return   object  A JSON object of DB values.
	 */
	public function add_to_pending( $object_type, $object_id, $object_parent ) {

		global $wpdb;

		// Both object_type and object_id are required.
		if ( empty( $object_type ) || empty( $object_id ) ) {
			return false;
		}

		$results = $wpdb->insert(
			"{$wpdb->prefix}crosspeak_pending_updates",
			array(
				'object_type'   => $object_type,
				'object_id'     => $object_id,
				'object_parent' => $object_parent,
				'created_date'  => current_time( 'mysql' ),
			),
			array(
				'%s',
				'%d',
				'%d',
				'%s',
			)
		);

		return $results;
	}

	/**
	 * Remove an object to the pending list.
	 *
	 * @since     1.0.0
	 *
	 * @param int $id A row ID.
	 *
	 * @return   object  A JSON object of DB values.
	 */
	public function remove_from_pending( $id ) {

		global $wpdb;

		// $id is required.
		if ( empty( $id ) ) {
			return false;
		}

		$results = $wpdb->delete(
			"{$wpdb->prefix}crosspeak_pending_updates",
			array(
				'id' => $id,
			)
		);

		return $results;
	}

	/**
	 * Callback handler for removing an item from the Pending list.
	 *
	 * @since     1.0.0
	 *
	 * @param WP_REST_Request $request  Request object.
	 */
	public function remove_from_pending_endpoint( $request ) {

		// ID is required.
		if ( empty( $request['id'] ) ) {
			return false;
		}

		$results = $this->remove_from_pending( $request['id'] );

		return $results;
	}

	/**
	 * Callback handler for removing an item from the Pending list.
	 *
	 * @since     1.0.0
	 *
	 * @param WP_REST_Request $request  Request object.
	 */
	public function calculate_cart_totals( $request ) {

		// Init Woo Data if we don't have it.
		// Taken from init in wp-content/plugins/woocommerce/includes/class-woocommerce.php.
		if ( ! WC()->session ) {
			// Session class, handles session data for users - can be overwritten if custom handler is needed.
			$session_class = apply_filters( 'woocommerce_session_handler', 'WC_Session_Handler' );
			WC()->session  = new $session_class();
			WC()->session->init();
		}
		if ( ! WC()->customer ) {
			WC()->customer = new WC_Customer( 0, true );
		}
		if ( ! WC()->cart ) {
			WC()->cart = new WC_Cart();
		}

		WC()->frontend_includes();

		WC()->shipping()->reset_shipping();

		// Remove any existing cart sessions for this connection.
		// Maybe this needs to be more dynamic?
		if ( WC()->session && method_exists( WC()->session, '__unset' ) ) {
			WC()->session->__unset( 'shipping_for_package_0' );
		}

		// Get the params.
		$line_items = $request->get_param( 'lineitems' );
		$addresses  = $request->get_param( 'addresses' );
		$promo      = $request->get_param( 'promo' );
		$discount   = $request->get_param( 'discount' );
		$shipping   = $request->get_param( 'shipping' );

		// Get all countries/state key/names in an array.
		$countries_obj        = new WC_Countries();
		$countries_array      = $countries_obj->get_countries();
		$country_states_array = $countries_obj->get_states();

		// Set the address values.
		$country  = $addresses['shipping']['country'];
		$state    = $addresses['shipping']['state'];
		$postcode = $addresses['shipping']['postal_code'];
		$city     = $addresses['shipping']['city'];

		// If country/state codes are not given, look them up.
		if ( strlen( $country ) > 2 ) {
			$country = array_search( $country, $countries_array, true );
		}

		if ( strlen( $state ) > 2 ) {
			$state = array_search( $state, $country_states_array[ $country ], true );
		}

		// Update the cart based on the customers location for getting local taxes.
		if ( $country ) {
			WC()->customer->set_location( $country, $state, $postcode, $city );
			WC()->customer->set_shipping_location( $country, $state, $postcode, $city );
		} else {
			WC()->customer->set_billing_address_to_base();
			WC()->customer->set_shipping_address_to_base();
		}

		// Just in case the cart is not empty, do it now.
		if ( ! WC()->cart->is_empty() ) {
			WC()->cart->empty_cart();
		}

		// Add each product to the cart.
		if ( ! empty( $line_items ) ) {

			foreach ( $line_items as $line_item ) {

				$id      = $line_item['external_product_id'];
				$product = wc_get_product( $id );
				$qty     = $line_item['qty'];

				// If the product is sold individually, but more than one is added to the cart, fallback to the CrossPeak defaults.
				if ( $product->is_sold_individually() && $qty > 1 ) {
					return false;
				}

				$cart_item_data = apply_filters( 'crosspeak_add_to_cart_cart_item_data', array(), $line_item );

				$cart_item_key = WC()->cart->add_to_cart( $id, (int) $qty, 0, array(), $cart_item_data );

				// An object in the CrossPeak Cart does not exist in WooCommerce. Fallback to the CrossPeak calculations.
				if ( ! $cart_item_key ) {
					return false;
				}
			}
		}

		WC()->cart->calculate_shipping();

		// Apply coupons.
		if ( ! empty( $promo['code'] ) ) {
			$applied = WC()->cart->apply_coupon( $promo['code'] );
		}

		// Get the cart totals.
		$totals = WC()->cart->get_totals();

		// Deduct the taxes from deposit items, if nessesary.
		// Based on the WooCommerce Deposits plugin.
		if ( class_exists( 'WC_Deposits_Cart_Manager' ) ) {
			$wc_deposits_cart_manager = WC_Deposits_Cart_Manager::get_instance();
			$deferred_tax             = $wc_deposits_cart_manager->calculate_deferred_tax_from_cart( false );
			$totals['subtotal_tax']   = $totals['subtotal_tax'] - $deferred_tax;
			$totals['total_tax']      = $totals['total_tax'] - $deferred_tax;
			$totals['deferred_tax']   = $deferred_tax;
		}
		$calculate_tax_args  = array(
			'country'  => strtoupper( wc_clean( $country ) ),
			'state'    => strtoupper( wc_clean( $state ) ),
			'postcode' => strtoupper( wc_clean( $postcode ) ),
			'city'     => strtoupper( wc_clean( $city ) ),
		);
		$totals['tax_rates'] = WC_Tax::find_rates( $calculate_tax_args );

		// Exit as a failure if there are any error notices.
		// TODO: Someday parse the errors to determine how best to handle them.
		// Maybe a filter would be good here for site specific needs.
		$notices = wc_get_notices();
		wc_clear_notices();
		if ( ! empty( $notices['error'] ) ) {
			return false;
		}

		// Empty the cart again, just in case.
		WC()->cart->empty_cart();

		return $totals;
	}

	/**
	 * Callback handler for removing an item from the Pending list.
	 *
	 * @since     1.0.0
	 *
	 * @param WP_REST_Request $request  Request object.
	 */
	public function silent_update_order( $request ) {

		// Get the params.
		$order_id         = $request->get_param( 'order_id' );
		$tracking         = $request->get_param( 'tracking' );
		$shipping_carrier = $request->get_param( 'shipping_carrier' );
		$shipping_method  = $request->get_param( 'shipping_method' );
		$status           = $request->get_param( 'status' );

		$order = wc_get_order( $order_id );
		if ( empty( $order ) ) {
			return array(
				'order'    => $order_id,
				'messages' => 'Order not found',
			);
		}

		$messages = array();

		global $wpdb;

		if ( ! empty( $tracking ) ) {
			update_post_meta( $order_id, 'crosspeak_shipping_tracking_number', $tracking );
			update_post_meta( $order_id, 'crosspeak_shipping_carrier', $shipping_carrier );
			update_post_meta( $order_id, 'crosspeak_shipping_method', $shipping_method );
		}

		$woo_order_status = $order->get_status();

		if ( in_array( $woo_order_status, array( 'processing', 'on-hold' ), true ) && 'completed' === $status ) {
			$messages[] = 'Updating to completed';
			$messages[] = $wpdb->update( $wpdb->posts, array( 'post_status' => 'wc-completed' ), array( 'ID' => $order_id ) );
		} elseif ( in_array( $woo_order_status, array( 'processing', 'on-hold' ), true ) && 'cancelled' === $status ) {
			$messages[] = 'Updating to cancelled from ' . $woo_order_status;
			$messages[] = $wpdb->update( $wpdb->posts, array( 'post_status' => 'wc-cancelled' ), array( 'ID' => $order_id ) );
		} elseif ( 'processing' === $woo_order_status && 'on-hold' === $status ) {
			$messages[] = 'Updating to On Hold from Processing';
			$messages[] = $wpdb->update( $wpdb->posts, array( 'post_status' => 'wc-on-hold' ), array( 'ID' => $order_id ) );
		} elseif ( $woo_order_status !== $status ) {
			$messages[] = $order->get_status() . ' !== ' . $status;
		}

		return array(
			'order'    => $order_id,
			'messages' => $messages,
		);
	}

	/**
	 * Apply cart shipping overrides.
	 *
	 * @since     1.0.0
	 *
	 * @param object $cart_object wc_cart object.
	 */
	public function apply_custom_product_prices( $cart_object ) {
		if ( $this->is_request_from_crosspeak() ) {
			// Get the request contents.
			$request = json_decode( file_get_contents( 'php://input' ) );

			$i = 0;

			// Loop through the Woo cart.
			foreach ( $cart_object->cart_contents as $key => $value ) {

				// If the product from CrossPeak is the same as the one in the cart, add the CrossPeak product cost.
				if ( $value['product_id'] == $request->lineitems[ $i ]->external_product_id ) {

					if ( $request->lineitems[ $i ]->price_each != $request->lineitems[ $i ]->price_msrp ) {
						$value['data']->set_price( $request->lineitems[ $i ]->price_each / 100 );
					}
					$i++;

					// Loop through all available lineitems because the IDs did not match as expected.
				} else {
					foreach ( $request->lineitems as $key => $cp_lineitem ) {

						if ( $value['product_id'] == $cp_lineitem->external_product_id ) {

							if ( $request->lineitems[ $key ]->price_each != $request->lineitems[ $key ]->price_msrp ) {
								$value['data']->set_price( $request->lineitems[ $key ]->price_each / 100 );
							}

							// Reset the count based on the location of the missing product.
							$i = $key + 1;
						}
					}
				}
			}
		}
	}

	/**
	 * Apply cart shipping overrides.
	 *
	 * @since     1.0.0
	 *
	 * @param array $rates The cost of a package.
	 * @param array $package Package of cart items.
	 */
	public function apply_shipping_override( $rates, $package ) {
		if ( $this->is_request_from_crosspeak() ) {

			// Get the request contents.
			$request = json_decode( file_get_contents( 'php://input' ) );

			if ( isset( $request->shipping ) && is_numeric( $request->shipping ) ) {

				$shipping_cost = $request->shipping / 100;

				foreach ( $rates as $key => $rate ) {

					$rate_cost = (float) $rate->get_cost();
					$rate_tax  = $rate->get_shipping_tax();
					if ( 0 === $rate_tax || 0 === $rate_cost ) {
						$tax_percent = 0;
					} else {
						$tax_percent = ( $rate_tax / $rate_cost );
					}

					$rate->set_cost( $shipping_cost );
					$rate->set_taxes( array( $tax_percent * $shipping_cost ) );
				}
			}
		}

		return $rates;
	}

	/**
	 * Apply cart discount overrides as a fee.
	 *
	 * @since     1.0.0
	 */
	public function apply_cart_discount() {
		global $woocommerce;

		if ( $this->is_request_from_crosspeak() ) {

			// Get the request contents.
			$request = json_decode( file_get_contents( 'php://input' ) );

			if ( ! empty( $request->discount ) ) {
				$totals = $woocommerce->cart->get_totals();

				// If using the deposits plugin, modify the totals calculation to ignore the future payment.
				if ( class_exists( 'WC_Deposits_Cart_Manager' ) ) {
					$wc_deposits_cart_manager = WC_Deposits_Cart_Manager::get_instance();
					$discount_amount          = $wc_deposits_cart_manager->get_future_payments_amount();
					$totals['subtotal']       = $totals['subtotal'] - $discount_amount;
				}

				$discount_total = $totals['subtotal'] * $request->discount;
				$woocommerce->cart->add_fee( 'discount', -$discount_total, true );
			}
		}
	}

	/**
	 * Return an instance of this class.
	 *
	 * @since     1.0.0
	 *
	 * @return   object  A single instance of this class.
	 */
	public static function get_instance() {
		// If the single instance hasn't been set, set it now.
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Sets the options from an array
	 *
	 * @since     1.0.0
	 *
	 * @param array $options Array of options to be set.
	 */
	public function set_options( $options ) {
		$this->options = $options;
	}

	/**
	 * Log a message either to WP Cli output or to
	 *
	 * @param string $message Message to log.
	 * @param string $type Type of message this is.
	 * @return void
	 */
	public function log_message( $message, $type ) {
		if ( class_exists( 'WP_CLI' ) ) {
			if ( 'success' === $type ) {
				WP_CLI::success( $message );
			} elseif ( 'error' === $type ) {
				WP_CLI::error( $message );
			}
		}
	}

	/**
	 * When an order is created by an API request, mark it as such.
	 *
	 * @since     1.0.0
	 *
	 * @param WC_Data         $order    Object object.
	 * @param WP_REST_Request $request  Request object.
	 * @param bool            $creating If is creating a new object.
	 */
	public function api_add_crosspeak_order_id( $order, $request, $creating ) {

		if ( ! empty( $request['order_meta']['crosspeak_order_id'] ) ) {
			$order->update_meta_data( 'crosspeak_order_id', $request['order_meta']['crosspeak_order_id'] );

			// Add this flag to prevent other functions from pushing this back to CrossPeak immediatly.
			$order->update_meta_data( 'created_recently', true );

			$order->save();
		}

		return $order;
	}

	/**
	 * Process the order update for sending to update.
	 *
	 * @since     1.0.0
	 *
	 * @param int $order_id Order ID.
	 */
	public function process_order_update( $order_id ) {

		if ( 'shop_order' !== get_post_type( $order_id ) ) {
			return;
		}

		if ( $this->is_request_from_crosspeak() ) {
			return;
		}

		$this->queue_action(
			'crosspeak_order_update_task',
			array(
				'order_id' => apply_filters( 'crosspeak_order_update', $order_id ),
			)
		);
	}

	/**
	 * Send the product to CrossPeak OMS on save_post
	 *
	 * @since     1.0.0
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post Object.
	 * @param boolean $update If this is an update.
	 */
	public function send_posts( $post_id, $post, $update ) {

		// Sanity check for settings.
		if ( empty( $this->options['url'] ) || empty( $this->options['api_token'] ) ) {
			return;
		}

		if ( $this->is_request_from_crosspeak() ) {
			return;
		}

		// Send Products.
		if ( in_array( $post->post_type, array( 'product', 'product_variation' ) ) ) {
			$this->queue_action(
				'crosspeak_product_update_task',
				array(
					'product_id' => apply_filters( 'crosspeak_product_update', $post_id ),
				)
			);
		}

		// Send Coupons.
		if ( 'shop_coupon' === $post->post_type ) {
			$this->queue_action(
				'crosspeak_coupon_update_task',
				array(
					'coupon_id' => apply_filters( 'crosspeak_coupon_update', $post_id ),
				)
			);
		}
	}

	/**
	 * Send the product to CrossPeak OMS for update
	 *
	 * @param int $product_id The Product ID.
	 */
	public function product_update( $product_id ) {
		$data = $this->post_api_data( 'api/v1/product', apply_filters( 'crosspeak_product_update_post', $product_id ) );

		do_action( 'crosspeak_product_sent', $product_id, $data );
	}

	/**
	 * Send the coupon to CrossPeak OMS for update
	 *
	 * @param int $coupon_id The Coupon ID.
	 */
	public function coupon_update( $coupon_id ) {
		$data = $this->post_api_data( 'api/v1/coupon', apply_filters( 'crosspeak_coupon_update_post', $coupon_id ) );

		do_action( 'crosspeak_coupon_sent', $coupon_id, $data );
	}

	/**
	 * Send the order to CrossPeak OMS for update
	 *
	 * @param int $order_id The Order ID.
	 */
	public function order_update( $order_id ) {
		$data = $this->post_api_data( 'api/v1/order', apply_filters( 'crosspeak_order_update_post', $order_id ) );

		do_action( 'crosspeak_order_sent', $order_id, $data );
	}

	/**
	 * Schedule the next action
	 * Make sure if the action is already scheduled to not schedule it twice.
	 *
	 * @return void
	 */
	private function queue_action( $action, $args ) {
		$next_scheduled_date = WC()->queue()->get_next( $action, $args, 'crosspeak' );

		if ( is_null( $next_scheduled_date ) ) {
			WC()->queue()->add( $action, $args, 'crosspeak' );
		}
	}

	/**
	 * Save order notes to the pending list.
	 *
	 * @since     1.0.0
	 *
	 * @param int        $comment_id  The Comment ID.
	 * @param WC_Comment $comment_object Comment Object.
	 */
	public function add_order_note_to_pending( $comment_id, $comment_object ) {

		if ( 'order_note' === $comment_object->comment_type && get_post_type( $comment_object->comment_post_ID ) === 'shop_order' ) {
			$this->add_to_pending( 'order_note', $comment_id, $comment_object->comment_post_ID );
		}
	}

	/**
	 * Save order notes to the pending list.
	 *
	 * @since     1.0.0
	 *
	 * @param WP_Comment      $note      New order note object.
	 * @param WP_REST_Request $request   Request object.
	 * @param boolean         $creating  True when creating item, false when updating.
	 */
	public function api_add_crosspeak_order_note( $note, $request, $creating ) {

		global $wpdb;

		// Flag the comment as being generated by CrossPeak.
		add_comment_meta( $note->comment_ID, 'api_origin', 'crosspeakoms' );

		// Remove the comment from the pending list, because it was added in wp_insert_comment.
		// Ideally, the comment would not be written to the pending list at all,
		// but there does not appear to be a great place to flag the note before the creation.
		$results = $wpdb->delete(
			"{$wpdb->prefix}crosspeak_pending_updates",
			array(
				'object_id' => $note->comment_ID,
			)
		);
	}

	/**
	 * Queue product stock to be sent.
	 *
	 * @since     1.0.0
	 *
	 * @param WC_Product $product Product Object.
	 */
	public function send_product_stock( $product ) {

		// Sanity check for settings.
		if ( empty( $this->options['url'] ) || empty( $this->options['api_token'] ) ) {
			return;
		}

		$this->queue_action(
			'crosspeak_product_stock_update_task',
			apply_filters(
				'crosspeak_stock_update',
				array(
					'product_id' => $product->get_id(),
					'stock'      => $product->get_stock_quantity( 'edit' ),
				)
			)
		);

		$data = $this->post_api_data(
			'api/v1/product/stock',
			apply_filters(
				'crosspeak_stock_update',
				array(
					'product_id' => $product->get_id(),
					'stock'      => $product->get_stock_quantity( 'edit' ),
				)
			)
		);

		if ( false !== $data ) {

			do_action( 'crosspeak_product_stock_sent', $product->get_id(), $data );

		}
	}

	/**
	 * Send the product stock to CrossPeak OMS
	 *
	 * @param int $product_id The Product ID.
	 * @param int $stock The stock value.
	 */
	public function stock_update( $product_id, $stock ) {
		$data = $this->post_api_data(
			'api/v1/product/stock',
			apply_filters(
				'crosspeak_stock_update_post',
				array(
					'product_id' => $product_id,
					'stock'      => $stock,
				)
			)
		);

		if ( false !== $data ) {
			do_action( 'crosspeak_product_stock_sent', $product_id, $data );
		}
	}

	/**
	 * Add a new integration to WooCommerce.
	 *
	 * @since     1.0.0
	 *
	 * @param  array $integrations WooCommerce integrations.
	 *
	 * @return array
	 */
	public function add_integration( $integrations ) {
		$integrations[] = 'CrossPeak_OMS_Admin';
		return $integrations;
	}


	/**
	 * Saturday Shipping
	 *
	 * @since     1.1.0
	 *
	 * @param  string $postal_code Postal Code.
	 *
	 * @return boolean If Saturday shipping is available for the address.
	 */
	public function saturday_shipping( $postal_code ) {

		// Sanity check for settings.
		if ( empty( $this->options['url'] ) || empty( $this->options['api_token'] ) ) {
			return true;
		}

		$data = array(
			'context'     => 'saturday_shipping',
			'postal_code' => $postal_code,
		);

		$result = $this->post_api_data( 'api/v1/shipping/saturday', apply_filters( 'crosspeak_shipping_rates', $data ) );

		if ( empty( $result ) ) {
			return false;
		} elseif ( false === $result['success'] ) {
			return false;
		} else {
			return $result['saturday'];
		}

	}

	/**
	 * Shipping Rates
	 *
	 * @since     1.1.0
	 *
	 * @param  string $method Shipping Method.
	 * @param  string $postal_code Postal Code.
	 * @param  string $delivery_date Delivery Date.
	 *
	 * @return mixed Data returned from endpoint
	 */
	public function shipping_rates( $method, $postal_code, $delivery_date = '' ) {

		// Sanity check for settings.
		if ( empty( $this->options['url'] ) || empty( $this->options['api_token'] ) ) {
			return;
		}

		$data = array(
			'context'       => 'shipping_rates',
			'method'        => $method,
			'postal_code'   => $postal_code,
			'delivery_date' => $delivery_date,
		);

		return $this->post_api_data( 'api/v1/shipping/rates', apply_filters( 'crosspeak_shipping_rates', $data ) );
	}

	/**
	 * Validate Address with CrossPeak OMS
	 *
	 * @since     1.1.0
	 *
	 * @param  array $data Data to send, values should be
	 *                     company_name
	 *                     address_1
	 *                     address_2
	 *                     city
	 *                     state
	 *                     postcode.
	 *
	 * @return array Data returned from endpoint.
	 */
	public function validate_address( $data ) {

		// Sanity check for settings.
		if ( empty( $this->options['url'] ) || empty( $this->options['api_token'] ) || empty( $this->options['validate_addresses'] ) ) {
			return array( 'status' => true ); // Return true because if we aren't validating it should always validate success.
		}

		return $this->post_api_data( 'api/v1/address/validate', apply_filters( 'crosspeak_verify_address', $data ) );
	}

	/**
	 * Send API call to CrossPeak OMS using GET.
	 *
	 * @since     1.4.0
	 *
	 * @param  string $url API endpoint.
	 * @param  array  $data Data to send.
	 *
	 * @return mixed Data returned from endpoint
	 */
	private function get_api_data( $url, $data ) {
		return $this->send_api_data( $url, $data, 'GET' );
	}

	/**
	 * Send API call to CrossPeak OMS using POST.
	 *
	 * @since     1.4.0
	 *
	 * @param  string $url API endpoint.
	 * @param  array  $data Data to send.
	 *
	 * @return mixed Data returned from endpoint
	 */
	private function post_api_data( $url, $data ) {
		return $this->send_api_data( $url, $data, 'POST' );
	}

	/**
	 * Send API call to CrossPeak OMS
	 *
	 * @since     1.0.0
	 *
	 * @param string $url API endpoint.
	 * @param array  $data Data to send.
	 * @param string $method The HTTP method.
	 *
	 * @return mixed Data returned from endpoint
	 */
	private function send_api_data( $url, $data, $method = 'GET' ) {

		// Sanity check for settings.
		if ( empty( $this->options['url'] ) || empty( $this->options['api_token'] ) ) {
			return;
		}

		$args = array(
			'method'      => $method,
			'timeout'     => 45, // Time in seconds until a request times out.
			'httpversion' => '1.0',
			'body'        => array(
				'api_token' => $this->options['api_token'],
				'source'    => get_site_url(),
				'data'      => $data,
			),
		);

		// If debug is enabled, allow for unverified SSL certs. This helps local testing.
		if ( defined( 'WP_DEBUG' ) && true === WP_DEBUG ) {
			$args['sslverify'] = false;
		}

		$response = wp_remote_post(
			$this->options['url'] . $url,
			$args
		);

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			return "Something went wrong: $error_message";
		} else {
			return json_decode( $response['body'], true );
		}
	}

	/**
	 * Enqueue tracking script
	 *
	 * @since 1.3.0
	 */
	public function enqueue_tracking_scripts() {
		if ( ! empty( $this->options['enable_tracking'] ) ) {
			$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
			wp_enqueue_script( 'crosspeakoms', plugins_url( "/js/crosspeak$min.js", __FILE__ ), array( 'js-cookie' ), $this->version, false );
		}
	}

	/**
	 * On checkout, update the order meta with the values from the CrossPeak tracking cookie.
	 *
	 * @since 1.3.0
	 *
	 * @param int $order_id Order ID.
	 */
	public function save_tracking_data( $order_id ) {
		// Check if the cookie exists.
		if ( isset( $_COOKIE['crosspeaksession'] ) ) {
			update_post_meta( $order_id, 'crosspeakoms_tracking', sanitize_text_field( $_COOKIE['crosspeaksession'] ) );
		}
	}

	/**
	 * Enqueue test connection script
	 *
	 * @since 1.3.2
	 */
	public function enqueue_test_connection() {
		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		wp_enqueue_script( 'crosspeak-test-connection', plugins_url( "/js/test-connection$min.js", __FILE__ ), array( 'jquery' ), $this->version, false );
	}

	/**
	 * Ping CrossPeak
	 *
	 * @since 1.3.2
	 */
	public function test_connection() {

		if ( empty( $this->options['url'] ) ) {
			$result = array(
				'status' => false,
				'error'  => 'CrossPeak OMS URL is empty.',
			);
		} elseif ( empty( $this->options['api_token'] ) ) {
			$result = array(
				'status' => false,
				'error'  => 'API Token is empty.',
			);
		}

		if ( ! isset( $result ) ) {
			$result = $this->get_api_data( 'api/v1', array() );
		}

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			echo wp_json_encode( $result );
			exit;
		} else {
			return $result;
		}
	}

	/**
	 * Include the tracking details in the email templates.
	 *
	 * @param WC_Order $order Current order.
	 * @param bool     $sent_to_admin Send to admin (default: false).
	 * @param bool     $plain_text    Plain text email (default: false).
	 */
	public function email_shipping_details( $order, $sent_to_admin, $plain_text ) {
		$crosspeak_shipping_tracking_number = get_post_meta( $order->get_id(), 'crosspeak_shipping_tracking_number', true );
		if ( empty( $crosspeak_shipping_tracking_number ) ) {
			return;
		}

		$crosspeak_shipping_carrier = get_post_meta( $order->get_id(), 'crosspeak_shipping_carrier', true );

		if ( $plain_text ) {
			echo "\n" . esc_html( wc_strtoupper( esc_html__( 'Shipping details', 'crosspeakoms' ) ) ) . "\n\n";
			echo esc_html__( 'Click here to track your shipment:', 'crosspeakoms' );
			echo ' ';
			echo esc_html( $crosspeak_shipping_tracking_number ) . "\n";
			echo esc_html( $this->get_tracking_link( $crosspeak_shipping_carrier, $crosspeak_shipping_tracking_number ) ) . "\n";
		} else {
			$text_align                 = is_rtl() ? 'right' : 'left';
			$crosspeak_shipping_carrier = get_post_meta( $order->get_id(), 'crosspeak_shipping_carrier', true );

			?>
			<h2><?php echo wp_kses_post( __( 'Shipping details', 'crosspeakoms' ) ); ?></h2>

			<table id="shipping_details" cellspacing="0" cellpadding="0" style="width: 100%; vertical-align: top; margin-bottom: 40px; padding:0;" border="0">
				<tr>
					<td style="text-align:<?php echo esc_attr( $text_align ); ?>; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; border:0; padding:0;" valign="top" width="50%">
					<?php echo wp_kses_post( __( 'Click here to track your shipment:', 'crosspeakoms' ) ); ?></h2>
						<a href="<?php echo esc_attr( $this->get_tracking_link( $crosspeak_shipping_carrier, $crosspeak_shipping_tracking_number ) ); ?>" target="_blank">
							<?php echo esc_html( $crosspeak_shipping_tracking_number ); ?>
						</a>
					</td>
				</tr>
			</table>
			<?php
		}
	}

	/**
	 * Get the tracking link based on carrier and tracking number.
	 *
	 * @param string $carrier The Shipping Carrier
	 * @param string $tracking_number The Tracking Number
	 * @return string
	 */
	public function get_tracking_link( $carrier, $tracking_number ) {

		$tracking_urls = array(
			'UPS'   => 'https://wwwapps.ups.com/WebTracking?TypeOfInquiryNumber=T&InquiryNumber1=',
			'USPS'  => 'https://tools.usps.com/go/TrackConfirmAction?qtc_tLabels1=',
			'FEDEX' => 'https://www.fedex.com/Tracking?language=english&cntry_code=us&tracknumbers=',
			'DHL'   => 'https://www.dhl.com/content/g0/en/express/tracking.shtml?brand=DHL&AWB=',
		);

		$carrier = strtoupper( $carrier );

		if ( isset( $tracking_urls[ $carrier ] ) ) {
			return $tracking_urls[ $carrier ] . $tracking_number;
		}

		if ( empty( $carrier ) ) {
			$php_tracking_regex = array(
				array(
					'regex'   => '/\b(1Z ?[0-9A-Z]{3} ?[0-9A-Z]{3} ?[0-9A-Z]{2} ?[0-9A-Z]{4} ?[0-9A-Z]{3} ?[0-9A-Z]|[\dT]\d\d\d ?\d\d\d\d ?\d\d\d)\b/i',
					'carrier' => 'UPS',
				),
				array(
					'regex'   => '/\b((420 ?\d\d\d\d\d ?)?(91|94|01|03|04|70|23|13)\d\d ?\d\d\d\d ?\d\d\d\d ?\d\d\d\d ?\d\d\d\d( ?\d\d)?)\b/i',
					'carrier' => 'USPS',
				),
				array(
					'regex'   => '/\b((M|P[A-Z]?|D[C-Z]|LK|EA|V[A-Z]|R[A-Z]|CP|CJ|LC|LJ) ?\d\d\d ?\d\d\d ?\d\d\d ?[A-Z]?[A-Z]?)\b/i',
					'carrier' => 'USPS',
				),
				array(
					'regex'   => '/\b((96\d\d\d\d\d ?\d\d\d\d|96\d\d|\d\d\d\d) ?\d\d\d\d ?\d\d\d\d( ?\d\d\d)?)\b/i',
					'carrier' => 'FEDEX',
				),
				array(
					'regex'   => '/\b(\d\d\d\d ?\d\d\d\d ?\d\d)\b/i',
					'carrier' => 'DHL',
				),
			);

			foreach ( $php_tracking_regex as $item ) {
				if ( preg_match( $item['regex'], $tracking_number ) ) {
					return $this->get_tracking_link( $item['carrier'], $tracking_number );
				}
			}
		}

		// Fallback to Google search for the number if we can't match it.
		return 'https://www.google.com/search?q=' . $tracking_number;
	}

	/**
	 * Add a Tracking Column to the order list.
	 *
	 * @param array $columns The Current Columns
	 * @return array
	 */
	public function my_orders_columns( $columns ) {
		$inserted    = false;
		$new_columns = array();
		foreach ( $columns as $key => $value ) {
			if ( ! $inserted && 'order-status' === $key ) {
				$new_columns[ $key ]               = $value;
				$new_columns['crosspeak-tracking'] = esc_html__( 'Tracking', 'crosspeakoms' );
				$inserted                          = true;
			} elseif ( ! $inserted && 'order-total' === $key ) {
				$new_columns['crosspeak-tracking'] = esc_html__( 'Tracking', 'crosspeakoms' );
				$new_columns[ $key ]               = $value;
				$inserted                          = true;
			} else {
				$new_columns[ $key ] = $value;
			}
		}
		if ( ! $inserted ) {
			$new_columns['crosspeak-tracking'] = esc_html__( 'Tracking', 'crosspeakoms' );
		}

		return $new_columns;
	}

	/**
	 * Put the tracking link on the My Orders Page
	 *
	 * @param WC_Order $order The Order in question.
	 * @return void
	 */
	public function my_orders_tracking( $order ) {

		$tracking_number = get_post_meta( $order->get_id(), 'crosspeak_shipping_tracking_number', true );
		if ( empty( $tracking_number ) ) {
			return;
		}

		$carrier = get_post_meta( $order->get_id(), 'crosspeak_shipping_carrier', true );
		?>
		<a href="<?php echo esc_attr( $this->get_tracking_link( $carrier, $tracking_number ) ); ?>" target="_blank">
			<?php echo esc_html( $tracking_number ); ?>
		</a>
		<?php
	}


	/**
	 * Put the tracking link on the Order Details Page
	 *
	 * @param WC_Order $order The Order in question.
	 * @return void
	 */
	public function order_details_tracking( $order ) {
		$tracking_number = get_post_meta( $order->get_id(), 'crosspeak_shipping_tracking_number', true );
		if ( empty( $tracking_number ) ) {
			return;
		}

		$carrier = get_post_meta( $order->get_id(), 'crosspeak_shipping_carrier', true );
		?>
		<p>
			<b>Track your order:</b>
			<a href="<?php echo esc_attr( $this->get_tracking_link( $carrier, $tracking_number ) ); ?>" target="_blank">
				<?php echo esc_html( $tracking_number ); ?>
			</a>
		</p>
		<?php
	}

	/**
	 * Define CrossPeak REST API routes.
	 */
	public function rest_routes() {
		register_rest_route(
			'wc-crosspeak/v1',
			'/customer',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_customer' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_others_posts' );
				},
			)
		);

		register_rest_route(
			'wc-crosspeak/v1',
			'/pending',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_pending_updates' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_others_posts' );
				},
			)
		);

		register_rest_route(
			'wc-crosspeak/v1',
			'/test-connection',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => function() {
					return 'Crosspeak Connection Successful';
				},
				'permission_callback' => function () {
					return current_user_can( 'edit_others_posts' );
				},
			)
		);

		register_rest_route(
			'wc-crosspeak/v1',
			'/calculate_cart_totals',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'calculate_cart_totals' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'wc-crosspeak/v1',
			'/silent_update_order',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'silent_update_order' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'wc-crosspeak/v1',
			'/pending/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'remove_from_pending_endpoint' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_others_posts' );
				},
			)
		);
	}

	/**
	 * Is this an API request from CrossPeak.
	 *
	 * @return boolean
	 */
	public function is_request_from_crosspeak() {
		return ( defined( 'REST_REQUEST' ) && REST_REQUEST && isset( $_SERVER['HTTP_USER_AGENT'] ) && strpos( $_SERVER['HTTP_USER_AGENT'], 'CrossPeak OMS' ) === 0 );
	}
}

/**
 * Begins execution of the plugin.
 *
 * @since    1.0.0
 */
add_action( 'plugins_loaded', array( 'CrossPeak_OMS', 'get_instance' ) );

/**
 * Activation and deactivation hook.
 *
 * @since    1.0.0
 */
register_activation_hook( __FILE__, 'create_crosspeak_pending_updates' );

/**
 * Create a database table to house pending updates.
 *
 * @since 1.0.0
 * @return type returns and Error or Success message.
 */
function create_crosspeak_pending_updates() {

	global $wpdb;
	$sql = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}crosspeak_pending_updates`(
			`id` int(11) NOT NULL auto_increment,
			`object_type` varchar(40) DEFAULT NULL,
			`object_id` int(11) DEFAULT NULL,
			`object_parent` int(11) DEFAULT NULL,
			`created_date` datetime DEFAULT NULL,
			PRIMARY KEY ( `id`)
		)";
	if ( $wpdb->query( $sql ) === false ) {
		return 'Error creating crosspeak_pending_updates database';
	} else {
		return 'Successfully created crosspeak_pending_updates database, if it didn\'t exist';
	}
}
