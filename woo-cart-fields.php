<?php
/**
 * Plugin Name: WooCommerce Cart Fields
 * Description: Add form fields to the cart page
 * Version: 1.0.0
 * Author: Liam Bailey (Webby Scots)
 * Author URI: http://webbyscots.com/
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */
class Woo_Cart_Fields {

	/**
	 * I18n textdomain for the plugin
	 *
	 * @var string $textdomain
	 */
	private $textdomain = 'woo-cart-fields';

	/**
	 * Plugins needed for this plugin to run properly
	 *
	 * @var array|null $required_plugins
	 */
	private $required_plugins = array();

	/**
	 * A holder for an instance of the plugin
	 *
	 * @var Woo_Cart_Fields|null $instance
	 */
	public static $instance;

	/**
	 * The key we store settings using in wp_options table
	 *
	 * @var string $settings_key
	 */
	private $settings_key = 'woo-cart-fields-settings';

	/**
	 * Holder for the nonce action
	 *
	 * @var string $nonce_action
	 */
	private $nonce_action = 'security-woo-cart-fiELds';

	/**
	 * Checks to make sure needed plugins are activated on the site
	 *
	 * @return bool
	 */
	function have_required_plugins() {
		if ( empty( $this->required_plugins ) ) {
			return true;
		}
		$active_plugins = ( array ) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
		}
		foreach ( $this->required_plugins as $key => $required ) {
			$required = ( !is_numeric( $key ) ) ? "{$key}/{$required}.php" : "{$required}/{$required}.php";
			if ( !in_array( $required, $active_plugins ) && !array_key_exists( $required, $active_plugins ) )
				return false;
		}
		return true;
	}

	/**
	 * __construct function for the class
	 *
	 */
	function __construct() {
		if ( !$this->have_required_plugins() ) {
			return;
		}
		$this->settings = get_option( $this->settings_key, array() );
		define( 'WCCF_TEXTDOMAIN', $this->textdomain );
		load_plugin_textdomain( WCCF_TEXTDOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		$this->init();
	}

	/**
	 * Init function mainly attaching hooks
	 *
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'settings_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'scripts_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'scripts_styles') );
		add_filter( 'woocommerce_cart_item_name', array( $this, 'display_fields' ), 30, 3 );
		add_filter( 'woocommerce_add_cart_item', array( $this, 'split_items'), 20, 2 );
		add_action( 'wp', array( $this, 'checkout_validation'),30);
		add_filter( 'woocommerce_is_sold_individually', array( $this, 'no_qty_edit') );
		add_filter( 'woocommerce_checkout_create_order_line_item_object', array( $this, 'final_save' ), 40, 4 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'get_additional_data' ), 200, 2 );
		add_action( 'wp_ajax_wccf_save_field', array( $this, 'save_field_ajax' ) );
		add_action( 'wp_ajax_nopriv_wccf_save_field', array( $this, 'save_field_ajax' ) );
	}

	/**
	 * Saves field content when it is changed
	 *
	 * @return void
	 */
	function save_field_ajax() {
		check_ajax_referer('secure_wccf_requests', 'security');
		$cart_item_key = $_POST['cart_item_key'];
		if (!isset(WC()->cart->cart_contents[$cart_item_key])) {
			wp_send_json( array( 'success' => false, 'message' => 'Cart item not found' ) );
		}
		$changed = false;
		$field = sanitize_text_field( wp_unslash( (string)$_POST['field'] ) );
		$orig_val = sanitize_text_field( $_POST['value'] );
		$value = apply_filters( 'wccf_cart_field_value', $orig_val, $field );
		$changed = $value . "::". $orig_val;
		if ($value != $orig_val) {
			$changed = true;
		}
		//$sanitize_function = strstr( $field, 'age' ) ? "intval" : "sanitize_text_field";
		WC()->cart->cart_contents[ $cart_item_key ][ $field ] = $value;
		WC()->session->cart = WC()->cart->get_cart_for_session();
		wp_send_json( array( 'success' => true, 'message' => 'Saved', 'changed' => $changed, 'val' => $value ) );
	}

	/**
	 * Gets saved card field data for displaying in the checkout
	 *
	 * @return array $data
	 */
	function get_additional_data( $data, $cart_item ) {
		if ( ! is_checkout() ) {
			return $data;
		}
		if ( ! isset( $this->settings ) ) {
			$this->settings = get_option( $this->settings_key, array() );
		}
		foreach ( $this->settings as $key => $setting ) {
			if ( isset( $cart_item[ $setting['key'] ] ) ) {
				$counter = count( $data );
				$data[ $counter ]['key'] = $setting['label'];
				$data[ $counter ]['value'] = $cart_item[ $setting['key'] ];
			}
		}
		return $data;
	}

	/**
	 * Stops users editing quantity in cart
	 *
	 * @param boolean $bool Original value if qty is editable or not.
	 * @return boolean $bool
	 */
	function no_qty_edit( $bool ) {
		if ( is_cart() && empty( $_POST ) )
			$bool = true;
		return $bool;
	}

	/**
	 * Splits multiple quantities into individual cart rows
	 *
	 * @param array $merged_data Merger of cart_item_data and other data stored as item is added to cart.
	 * @param string $cart_item_key - Unique identifier for cart item - forms array key in contents array.
	 * @return array $merged_data
	 */
	function split_items($merged_data, $cart_item_key) {
		if ($merged_data['quantity'] > 1) {
			while($merged_data['quantity'] > 1) {
				$merged_data['uniqid'] = bin2hex(random_bytes(5));
				WC()->cart->add_to_cart($merged_data['product_id'], 1, $merged_data['variation_id'], $merged_data['variation'], array('uniqid' => bin2hex(random_bytes(5)) ) );
				$merged_data['quantity']--;
			}
		}
		return $merged_data;
	}

	/**
	 * Takes cart item data and saves it as order item meta
	 *
	 * @param array|object $item Order item.
	 * @param string $cart_item_key Unique identifier for cart item - forms array key in contents array.
	 * @param array $values cart item details.
	 * @param object|integer $order - the order id or the order object.
	 *
	 * @return void
	 */
	function final_save($item, $cart_item_key, $values, $order) {
		if (!isset($this->settings)) {
				$this->settings = get_option($this->settings_key);
		}
		$save = false;
		foreach($this->settings as $s => $field) {
			if (isset($values[$field['key']])) {
				$item->update_meta_data($field['key'], $values[$field['key']]);
				$save = true;
			}
		}
		if ($save) {
			$item->save();
		}
		return $item;
	}

	/**
	 * Validate if required fields filled and if not redirect to cart page with notice
	 *
	 * @return void
	 */
	function checkout_validation() {
		if (is_page('checkout')) {
			$required_field_keys = array();
			$fail = false;
			foreach($this->settings as $key => $setting) {
				if ($setting['required'] !== "yes") {
					continue;
				}
				$required_field_keys[] = $setting['key'];
			}
			foreach(WC()->cart->cart_contents as $cik => &$ci) {
				foreach($required_field_keys as $key) {
					if (!isset($ci[$key]) || empty($ci[$key])) {
						$fail = true;
					}
				}
			}
			if ($fail === true) {
				$url = get_permalink( get_option( 'woocommerce_cart_page_id' ) ); 
				wc_add_notice("You need to fill in all required fields on cart page before proceeding to checkout", "error");
				wp_safe_redirect($url);
				exit;
			}
		}

	}

	/**
	 * Display input fields in cart table.
	 *
	 * @param string $name - Cart item name.
	 * @param array $cart_item the cart item.
	 * @param string $cart_item_key unique identifier and array key for the item
	 * @return boolean $bool
	 */
	function display_fields($name, $cart_item, $cart_item_key) {
		if (!is_cart())
			return $name;
		$fields = $this->settings;
		ob_start();
		$class = array( 'form-row', 'additional_fields' );
		foreach($fields as $key => $field) {
			if (apply_filters('wccf_hide_field', false, $field, $cart_item)) {
				continue;
			}
			if ($key % 2 == 0) {
				$class[2] = 'form-row-last';
			} else {
				$class[2] = 'form-row-first';
			}

			//echo $cart_item_key;
			$field_key = $cart_item_key . ':' . $field['key'];
			echo woocommerce_form_field($field_key, array(
						'label' => $field['label'],
						'type' => $field['type'],
						'required' => $field['required'] == "yes" ? true : false,
						'class' => $class,
					), $cart_item[$field['key']] );
		}
		$content = $name . "<div>" . ob_get_contents() . "</div>";
		ob_end_clean();
		return $content;
		
	}

	/**
	 * Enqueues scripts and styles for the plugin.
	 *
	 * @return void
	 */
	function scripts_styles() {
		if ( is_cart() ) {
			wp_enqueue_script( 'wccf_frontend', plugins_url( 'js/frontend.js', __FILE__ ), array( 'jquery' ), time() );
			wp_localize_script( 'wccf_frontend', 'wccfVars', array(
				'ajaxurl' => admin_url('admin-ajax.php'),
				'security' => wp_create_nonce('secure_wccf_requests')
			));
			return;
		} elseif ( is_admin() ) {
			$screen         = get_current_screen();
			$screen_id      = $screen ? $screen->id : '';
			if ( $screen_id === $this->page_id ) {
				wp_enqueue_script( 'wccf_javascript', plugins_url( 'js/script.js',__FILE__ ), array('jquery'), time() );
			}
		}
	}

	/**
	 * Sets up settings page to add the fields for the cart.
	 *
	 * @return void
	 */
	function settings_page() {
		$this->page_id = add_submenu_page( 'edit.php?post_type=product', 'WooCommerce Cart Fields', 'Cart Fields', 'manage_woocommerce', 'woo-cart-fields', array( $this, 'setup_page') );
	}

	/**
	 * Settings page display.
	 *
	 * @return void
	 */
	function setup_page() {
		if ( $_POST['wccf_fields_submit'] == "Save" ) {
			$check = $_REQUEST['_wpnonce'];
			if ( ! wp_verify_nonce( $check, $this->nonce_action ) ) {
				die( 'Security check' );
			}
			$settings = array();
			foreach( $_POST['wccf_fields'] as $counter => &$field ) {
				if ( $field['label'] == "" && $field['key'] == "" ) {
					continue;
				}
				if ( $field['key'] == '' ) {
					$field['key'] = str_replace( '-', '_', sanitize_title( $field['label'] ) );
				}
				else if ( $field['label'] == '' ) {
					$field['label'] = ucwords( sanitize_text_field( $field['key'] ) );
				}
				$settings[$counter] = array_map( 'sanitize_text_field', $field ) ;
			}
			$this->settings = $settings;
			update_option( $this->settings_key, $this->settings );
		}
		?><div class='wrap'>
			<h1 class='dashicons-before dashicons-admin-generic'><?php echo esc_html( get_admin_page_title() ); ?></h1>
				<form id="wccf_fields_form" method="post" action="<?php echo add_query_arg(array('post_type' => 'product', 'page' => 'woo-cart-fields' ) ); ?>">
					<?php wp_nonce_field( $this->nonce_action ); ?>
				<table>
					<thead>
						<tr>
							<th>Field</th>
							<th>Type</th>
							<th>Label</th>
							<th>Key</th>
							<th>Required</th>
							<th></th>
						</tr>
					</thead>
					<tbody><?php
						$key = 1;
						if ( ! empty( $this->settings ) ) {
							foreach( $this->settings as $key => $fieldset ) {
								include( 'templates/form-row.php' );
							}
							$key++;
						}

						$fieldset = '';
						include( 'templates/form-row.php' );
						?>
					</tbody>
				</table>
				<input type="submit" class="btn btn-primary" name="wccf_fields_submit" value="Save" />
			</form>
		</div>
		<?php
	}

	/**
	 * Gives access to class instance from anywhere via helper function
	 *
	 * @return Woo_Cart_Fields
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}

/* Use this function to call up the class from anywahere
like PB()->class_method();
 */
function WCCF() {
	return Woo_Cart_Fields::instance();
}

WCCF();