<?php
/**
 * Main class for Smart Coupons Email
 *
 * @author      StoreApps
 * @since       4.4.1
 * @version     1.0.0
 *
 * @package     woocommerce-smart-coupons/includes/emails/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WC_SC_Email_Coupon' ) ) {
	/**
	 * The Smart Copuons Email class
	 *
	 * @extends \WC_SC_Email
	 */
	class WC_SC_Email_Coupon extends WC_SC_Email {

		/**
		 * Set email defaults
		 */
		public function __construct() {

			$this->id = 'wc_sc_email_coupon';

			// Not necessarily a customer email.
			$this->customer_email = false;

			// Set email title and description.
			$this->title       = __( 'Smart Coupons Email', 'woocommerce-smart-coupons' );
			$this->description = __( 'Smart Coupons coupon emails.', 'woocommerce-smart-coupons' );

			// Use our plugin templates directory as the template base.
			$this->template_base = dirname( WC_SC_PLUGIN_FILE ) . '/templates/';

			// Email template location.
			$this->template_html  = 'email.php';
			$this->template_plain = 'plain/email.php';

			$this->placeholders = array(
				'{coupon_code}'      => '',
				'{coupon_type}'      => '',
				'{coupon_value}'     => '',
				'{sender_name}'      => '',
				'{from_sender_name}' => '',
			);

			// Trigger for this email.
			add_action( 'wc_sc_email_coupon_notification', array( $this, 'trigger' ) );

			// Call parent constructor to load any other defaults not explicity defined here.
			parent::__construct();
		}

		/**
		 * Get default email subject.
		 *
		 * @return string Default email subject
		 */
		public function get_default_subject() {
			return __( '{site_title}: Congratulations! You\'ve received a {coupon_type}{from_sender_name}', 'woocommerce-smart-coupons' );
		}

		/**
		 * Get default email heading.
		 *
		 * @return string Default email heading
		 */
		public function get_default_heading() {
			return __( 'You have received a {coupon_type} {coupon_value}', 'woocommerce-smart-coupons' );
		}

		/**
		 * Determine if the email should actually be sent and setup email merge variables
		 *
		 * @param array $args Email arguements.
		 */
		public function trigger( $args = array() ) {

			$this->email_args = wp_parse_args( $args, $this->email_args );

			if ( ! isset( $this->email_args['email'] ) || empty( $this->email_args['email'] ) ) {
				return;
			}

			$this->setup_locale();

			$receiver_email  = $this->email_args['email'];
			$this->recipient = $receiver_email;

			$sender_email = $this->get_sender_email();

			// If sender and receiver are same, then it is a customer email.
			if ( $receiver_email === $sender_email ) {
				$this->customer_email = true;
			}

			$order_id = isset( $this->email_args['order_id'] ) ? $this->email_args['order_id'] : 0;

			// Get order object.
			if ( ! empty( $order_id ) && 0 !== $order_id ) {
				$order = wc_get_order( $order_id );
				if ( is_a( $order, 'WC_Order' ) ) {
					$this->object = $order;
				}
			}

			$this->placeholders['{coupon_code}']      = $this->get_coupon_code();
			$this->placeholders['{coupon_type}']      = $this->get_coupon_type();
			$this->placeholders['{coupon_value}']     = $this->get_coupon_value();
			$this->placeholders['{sender_name}']      = $this->get_sender_name();
			$this->placeholders['{from_sender_name}'] = $this->get_from_sender_name();

			// Send email.
			if ( $this->is_enabled() && $this->get_recipient() ) {
				$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			}

			$this->restore_locale();
		}

		/**
		 * Function to load email html content
		 *
		 * @return string Email content html
		 */
		public function get_content_html() {

			global $woocommerce_smart_coupon;

			$order         = $this->object;
			$url           = $this->get_url();
			$email_heading = $this->get_heading();

			$sender = '';
			$from   = '';

			$is_gift = isset( $this->email_args['is_gift'] ) ? $this->email_args['is_gift'] : '';

			if ( 'yes' === $is_gift ) {
				$sender_name  = $this->get_sender_name();
				$sender_email = $this->get_sender_email();
				if ( ! empty( $sender_name ) && ! empty( $sender_email ) ) {
					$sender = $sender_name . ' (' . $sender_email . ') ';
					$from   = ' ' . __( 'from', 'woocommerce-smart-coupons' ) . ' ';
				}
			}

			$message_from_sender = isset( $this->email_args['message_from_sender'] ) ? $this->email_args['message_from_sender'] : '';
			$coupon_code         = isset( $this->email_args['coupon']['code'] ) ? $this->email_args['coupon']['code'] : '';

			$design           = get_option( 'wc_sc_setting_coupon_design', 'round-dashed' );
			$background_color = get_option( 'wc_sc_setting_coupon_background_color', '#39cccc' );
			$foreground_color = get_option( 'wc_sc_setting_coupon_foreground_color', '#30050b' );
			$coupon_styles    = $woocommerce_smart_coupon->get_coupon_styles( $design );

			ob_start();

			wc_get_template(
				$this->template_html,
				array(
					'email_heading'       => $email_heading,
					'order'               => $order,
					'url'                 => $url,
					'message_from_sender' => $message_from_sender,
					'from'                => $from,
					'coupon_code'         => $coupon_code,
					'background_color'    => $background_color,
					'foreground_color'    => $foreground_color,
					'coupon_styles'       => $coupon_styles,
					'sender'              => $sender,
				),
				'',
				$this->template_base
			);

			return ob_get_clean();
		}

		/**
		 * Function to load email plain content
		 *
		 * @return string Email plain content
		 */
		public function get_content_plain() {

			global $woocommerce_smart_coupon;

			$order         = $this->object;
			$url           = $this->get_url();
			$email_heading = $this->get_heading();

			$sender = '';
			$from   = '';

			$is_gift = isset( $this->email_args['is_gift'] ) ? $this->email_args['is_gift'] : '';

			if ( 'yes' === $is_gift ) {
				$sender_name  = $this->get_sender_name();
				$sender_email = $this->get_sender_email();
				if ( ! empty( $sender_name ) && ! empty( $sender_email ) ) {
					$sender = $sender_name . ' (' . $sender_email . ') ';
					$from   = ' ' . __( 'from', 'woocommerce-smart-coupons' ) . ' ';
				}
			}

			$message_from_sender = isset( $this->email_args['message_from_sender'] ) ? $this->email_args['message_from_sender'] : '';
			$coupon_code         = isset( $this->email_args['coupon']['code'] ) ? $this->email_args['coupon']['code'] : '';

			ob_start();

			wc_get_template(
				$this->template_plain,
				array(
					'email_heading'       => $email_heading,
					'order'               => $order,
					'url'                 => $url,
					'message_from_sender' => $message_from_sender,
					'from'                => $from,
					'coupon_code'         => $coupon_code,
					'sender'              => $sender,
				),
				'',
				$this->template_base
			);

			return ob_get_clean();
		}



		/**
		 * Get coupon code
		 *
		 * @return string
		 */
		public function get_coupon_code() {

			$coupon_code = isset( $this->email_args['coupon']['code'] ) ? $this->email_args['coupon']['code'] : '';

			return $coupon_code;
		}

		/**
		 * Get coupon value
		 *
		 * @return string
		 */
		public function get_coupon_value() {

			global $woocommerce_smart_coupon, $store_credit_label;

			$coupon = isset( $this->email_args['coupon'] ) ? $this->email_args['coupon'] : '';

			if ( empty( $coupon ) ) {
				return '';
			}

			$discount_type = isset( $this->email_args['discount_type'] ) ? $this->email_args['discount_type'] : '';
			$is_gift       = isset( $this->email_args['is_gift'] ) ? $this->email_args['is_gift'] : '';

			// Get smart coupon type  string.
			if ( 'smart_coupon' === $discount_type && 'yes' === $is_gift ) {
				$smart_coupon_type = __( 'Gift Card', 'woocommerce-smart-coupons' );
			} else {
				$smart_coupon_type = __( 'Store Credit', 'woocommerce-smart-coupons' );
			}

			if ( ! empty( $store_credit_label['singular'] ) ) {
				$smart_coupon_type = ucwords( $store_credit_label['singular'] );
			}

			$amount      = $coupon['amount'];
			$coupon_code = $coupon['code'];

			// Get coupon types.
			$all_discount_types = wc_get_coupon_types();

			$_coupon = new WC_Coupon( $coupon_code );

			if ( $woocommerce_smart_coupon->is_wc_gte_30() ) {
				$_coupon_id                   = ( is_object( $_coupon ) && is_callable( array( $_coupon, 'get_id' ) ) ) ? $_coupon->get_id() : 0;
				$_is_free_shipping            = ( $_coupon->get_free_shipping() ) ? 'yes' : 'no';
				$_discount_type               = $_coupon->get_discount_type();
				$_product_ids                 = $_coupon->get_product_ids();
				$_excluded_product_ids        = $_coupon->get_excluded_product_ids();
				$_product_categories          = $_coupon->get_product_categories();
				$_excluded_product_categories = $_coupon->get_excluded_product_categories();
			} else {
				$_coupon_id                   = ( ! empty( $_coupon->id ) ) ? $_coupon->id : 0;
				$_is_free_shipping            = ( ! empty( $_coupon->free_shipping ) ) ? $_coupon->free_shipping : '';
				$_discount_type               = ( ! empty( $_coupon->discount_type ) ) ? $_coupon->discount_type : '';
				$_product_ids                 = ( ! empty( $_coupon->product_ids ) ) ? $_coupon->product_ids : array();
				$_excluded_product_ids        = ( ! empty( $_coupon->exclude_product_ids ) ) ? $_coupon->exclude_product_ids : array();
				$_product_categories          = ( ! empty( $_coupon->product_categories ) ) ? $_coupon->product_categories : array();
				$_excluded_product_categories = ( ! empty( $_coupon->exclude_product_categories ) ) ? $_coupon->exclude_product_categories : array();
			}

			switch ( $discount_type ) {

				case 'smart_coupon':
					/* translators: %s coupon amount */
					$coupon_value = sprintf( __( 'worth %2$s ', 'woocommerce-smart-coupons' ), $smart_coupon_type, wc_price( $amount ) );
					break;

				case 'fixed_cart':
					/* translators: %s: coupon amount */
					$coupon_value = sprintf( __( 'worth %s (for entire purchase) ', 'woocommerce-smart-coupons' ), wc_price( $amount ) );
					break;

				case 'fixed_product':
					if ( ! empty( $_product_ids ) || ! empty( $_excluded_product_ids ) || ! empty( $_product_categories ) || ! empty( $_excluded_product_categories ) ) {
						$_discount_for_text = __( 'for some products', 'woocommerce-smart-coupons' );
					} else {
						$_discount_for_text = __( 'for all products', 'woocommerce-smart-coupons' );
					}

					/* translators: 1: coupon amount 2: discount for text */
					$coupon_value = sprintf( __( 'worth %1$s (%2$s) ', 'woocommerce-smart-coupons' ), wc_price( $amount ), $_discount_for_text );
					break;

				case 'percent_product':
					if ( ! empty( $_product_ids ) || ! empty( $_excluded_product_ids ) || ! empty( $_product_categories ) || ! empty( $_excluded_product_categories ) ) {
						$_discount_for_text = __( 'for some products', 'woocommerce-smart-coupons' );
					} else {
						$_discount_for_text = __( 'for all products', 'woocommerce-smart-coupons' );
					}

					/* translators: 1: coupon amount 2: discount for text */
					$coupon_value = sprintf( __( 'worth %1$s%% (%2$s) ', 'woocommerce-smart-coupons' ), $amount, $_discount_for_text );
					break;

				case 'percent':
					if ( ! empty( $_product_ids ) || ! empty( $_excluded_product_ids ) || ! empty( $_product_categories ) || ! empty( $_excluded_product_categories ) ) {
						$_discount_for_text = __( 'for some products', 'woocommerce-smart-coupons' );
					} else {
						$_discount_for_text = __( 'for entire purchase', 'woocommerce-smart-coupons' );
					}

					$max_discount_text = '';
					$max_discount      = get_post_meta( $_coupon_id, 'wc_sc_max_discount', true );
					if ( ! empty( $max_discount ) && is_numeric( $max_discount ) ) {
						/* translators: %s: Maximum coupon discount amount */
						$max_discount_text = sprintf( __( ' upto %s', 'woocommerce-smart-coupons' ), wc_price( $max_discount ) );
					}

					/* translators: 1: coupon amount 2: max discount text 3: discount for text */
					$coupon_value = sprintf( __( 'worth %1$s%% %2$s (%3$s) ', 'woocommerce-smart-coupons' ), $amount, $max_discount_text, $_discount_for_text );
					break;

				default:
					$default_coupon_type = ( ! empty( $all_discount_types[ $discount_type ] ) ) ? $all_discount_types[ $discount_type ] : ucwords( str_replace( array( '_', '-' ), ' ', $discount_type ) );
					$coupon_type         = apply_filters( 'wc_sc_coupon_type', $default_coupon_type, $_coupon, $all_discount_types );
					$coupon_amount       = apply_filters( 'wc_sc_coupon_amount', $amount, $_coupon );

					/* translators: 1: coupon type 2: coupon amount */
					$coupon_value = sprintf( __( '%1$s coupon of %2$s', 'woocommerce-smart-coupons' ), $coupon_type, $coupon_amount );
					$coupon_value = apply_filters( 'wc_sc_email_heading', $coupon_value, $_coupon );
					break;

			}

			if ( 'yes' === $_is_free_shipping && in_array( $_discount_type, array( 'fixed_cart', 'fixed_product', 'percent_product', 'percent' ), true ) ) {
				/* translators: 1: email heading 2: suffix */
				$coupon_value = sprintf( __( '%1$s Free Shipping%2$s', 'woocommerce-smart-coupons' ), ( ( ! empty( $amount ) ) ? $coupon_value . __( '&', 'woocommerce-smart-coupons' ) : __( 'You have received a', 'woocommerce-smart-coupons' ) ), ( ( empty( $amount ) ) ? __( ' coupon', 'woocommerce-smart-coupons' ) : '' ) );
			}

			return $coupon_value;
		}

		/**
		 * Function to get coupon type for current coupon being sent.
		 *
		 * @return string $coupon_type Coupon type.
		 */
		public function get_coupon_type() {

			global $store_credit_label;

			$discount_type = isset( $this->email_args['discount_type'] ) ? $this->email_args['discount_type'] : '';
			$is_gift       = isset( $this->email_args['is_gift'] ) ? $this->email_args['is_gift'] : '';

			if ( 'smart_coupon' === $discount_type && 'yes' === $is_gift ) {
				$smart_coupon_type = __( 'Gift Card', 'woocommerce-smart-coupons' );
			} else {
				$smart_coupon_type = __( 'Store Credit', 'woocommerce-smart-coupons' );
			}

			if ( ! empty( $store_credit_label['singular'] ) ) {
				$smart_coupon_type = ucwords( $store_credit_label['singular'] );
			}

			$coupon_type = ( 'smart_coupon' === $discount_type && ! empty( $smart_coupon_type ) ) ? $smart_coupon_type : __( 'coupon', 'woocommerce-smart-coupons' );

			return $coupon_type;
		}

		/**
		 * Function to update SC admin email settings when WC email settings get updated
		 */
		public function process_admin_options() {
			// Save regular options.
			parent::process_admin_options();

			$is_email_enabled = $this->get_field_value( 'enabled', $this->form_fields['enabled'] );

			if ( ! empty( $is_email_enabled ) ) {
				update_site_option( 'smart_coupons_is_send_email', $is_email_enabled );
			}
		}

	}
}
