<?php
/**
 * Integrates the settings into the WooCommerece Integrations section
 *
 * @link       http://www.crosspeakoms.com/
 * @since      1.0.0
 * @package    CrossPeak_OMS
 * @subpackage CrossPeak_OMS_Admin
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class with the functions for the WordPress Admin
 */
class CrossPeak_OMS_Admin extends WC_Integration {

	/**
	 * Instance of this class.
	 *
	 * @since    1.0.0
	 *
	 * @var     object
	 */
	protected static $instance = null;

	/**
	 * Plugin object
	 *
	 * @since    1.0.0
	 *
	 * @var     object
	 */
	public $plugin;

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
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks.
	 *
	 * @since   1.0.0
	 */
	public function __construct() {
		$this->plugin  = CrossPeak_OMS::get_instance();
		$this->id      = $this->plugin->plugin_name;
		$this->version = $this->plugin->version;

		$this->method_title       = __( 'CrossPeak OMS', 'crosspeakoms' );
		$this->method_description = __( 'Integrates your WooCommerce data with CrossPeak OMS.', 'crosspeakoms' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();
		$this->plugin->set_options( $this->init_options() );

		add_action( 'woocommerce_update_options_integration_' . $this->id, array( $this, 'process_admin_options' ) );

		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 30 );
	}

	/**
	 * Loads all of our options for this plugin
	 *
	 * @return array An array of options that can be passed to other classes
	 */
	public function init_options() {
		$options     = array(
			'url',
			'api_token',
			'validate_addresses',
			'enable_tracking',
		);
		$constructor = array();
		foreach ( $options as $option ) {
			$constructor[ $option ] = $this->$option = $this->get_option( $option );
		}
		return $constructor;
	}

	/**
	 * Tells WooCommerce which settings to display under the "integration" tab
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'url'                => array(
				'title'       => __( 'CrossPeak OMS URL', 'crosspeakoms' ),
				'description' => __( 'The url of the API you are connecting to ( e.g. https://example.crosspeakoms.com/ )', $this->id ),
				'type'        => 'text',
				'default'     => 'https://example.crosspeakoms.com/',
			),
			'api_token'          => array(
				'title'       => __( 'API Token', 'crosspeakoms' ),
				'description' => __( 'Your API Token that you retrieved from Settings -> More -> Web Stores &amp; API Access', 'crosspeakoms' ),
				'type'        => 'text',
				'default'     => '',
			),
			'validate_addresses' => array(
				'title'       => __( 'Validate Addresses', 'crosspeakoms' ),
				'description' => __( 'Validate addresses with CrossPeak OMS', 'crosspeakoms' ),
				'type'        => 'checkbox',
				'default'     => false,
			),
			'enable_tracking'    => array(
				'title'       => __( 'Enable Tracking', 'crosspeakoms' ),
				'description' => __( 'Save affiliate and UTM parameters with orders.', 'crosspeakoms' ),
				'type'        => 'checkbox',
				'default'     => true,
			),
			'test_connection'    => array(
				'title'       => __( 'Test Connection', 'crosspeakoms' ),
				'description' => __( 'Test the connection between WooCommerce and CrossPeak. Make sure you save your settings before testing.', $this->id ),
				'type'        => 'button',
				'default'     => 'Test Connection',
			),
		);
	}

	/**
	 * Add WC Meta boxes.
	 */
	public function add_meta_boxes() {
		add_meta_box( 'woocommerce-crosspeak-order', __( 'CrossPeak OMS', 'crosspeakoms' ), array( $this, 'order_meta' ), 'shop_order', 'side', 'default' );
	}

	/**
	 * Output the order metabox.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function order_meta( $post ) {

		$crosspeak_order_id                 = get_post_meta( $post->ID, 'crosspeak_order_id', true );
		$crosspeak_shipping_carrier         = get_post_meta( $post->ID, 'crosspeak_shipping_carrier', true );
		$crosspeak_shipping_tracking_number = get_post_meta( $post->ID, 'crosspeak_shipping_tracking_number', true );
		$crosspeak_shipping_method          = get_post_meta( $post->ID, 'crosspeak_shipping_method', true );

		if ( ! empty( $crosspeak_order_id ) ) :
			?>
			<p>
				<b><?php esc_html_e( 'CrossPeak Order:', 'crosspeakoms' ); ?></b> <a href="<?php echo esc_attr( $this->url . 'order/' . $crosspeak_order_id ); ?>" target="_blank"><?php echo esc_html( $crosspeak_order_id ); ?></a>
			</p>
			<?php
		endif;

		if ( ! empty( $crosspeak_shipping_tracking_number ) ) :
			?>
			<p>
				<b><?php esc_html_e( 'Shipping:', 'crosspeakoms' ); ?></b>
				<a href="<?php echo esc_attr( $this->plugin->get_tracking_link( $crosspeak_shipping_carrier, $crosspeak_shipping_tracking_number ) ); ?>" target="_blank">
					<?php echo esc_html( $crosspeak_shipping_tracking_number ); ?>
				</a>
			</p>
			<?php
		endif;

		if ( ! empty( $crosspeak_shipping_carrier ) ) :
			?>
			<p>
				<b><?php esc_html_e( 'Shipping Carrier:', 'crosspeakoms' ); ?></b>
				<?php echo esc_html( $crosspeak_shipping_carrier ); ?>
			</p>
			<?php
		endif;

		if ( ! empty( $crosspeak_shipping_method ) ) :
			?>
			<p>
				<b><?php esc_html_e( 'Shipping Method:', 'crosspeakoms' ); ?></b>
				<?php echo esc_html( $crosspeak_shipping_method ); ?>
			</p>
			<?php
		endif;
	}

}
