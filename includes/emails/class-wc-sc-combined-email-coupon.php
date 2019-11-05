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

if ( ! class_exists( 'WC_SC_Combined_Email_Coupon' ) ) {
	/**
	 * The Smart Copuons Combined Email class
	 *
	 * @extends \WC_SC_Email
	 */
	class WC_SC_Combined_Email_Coupon extends WC_SC_Email {

		/**
		 * Set email defaults
		 */
		public function __construct() {

			$this->id = 'wc_sc_combined_email_coupon';

			// Not necessarily a customer email.
			$this->customer_email = false;

			// Set email title and description.
			$this->title       = __( 'Smart Coupons Combined Emails', 'woocommerce-smart-coupons' );
			$this->description = __( 'Send only one email instead of multiple emails when multiple coupons are generated for same recipient.', 'woocommerce-smart-coupons' );

			// Use our plugin templates directory as the template base.
			$this->template_base = dirname( WC_SC_PLUGIN_FILE ) . '/templates/';

			// Email template location.
			$this->template_html  = 'combined-email.php';
			$this->template_plain = 'plain/combined-email.php';

			$this->placeholders = array(
				'{sender_name}'      => '',
				'{from_sender_name}' => '',
			);

			// Trigger for this email.
			add_action( 'wc_sc_combined_email_coupon_notification', array( $this, 'trigger' ) );

			// Call parent constructor to load any other defaults not explicity defined here.
			parent::__construct();
		}

		/**
		 * Get default email subject.
		 *
		 * @return string Default email subject
		 */
		public function get_default_subject() {
			return __( '{site_title}: Congratulations! You\'ve received coupons{from_sender_name}', 'woocommerce-smart-coupons' );
		}

		/**
		 * Get default email heading.
		 *
		 * @return string Default email heading
		 */
		public function get_default_heading() {
			return __( 'You have received coupons.', 'woocommerce-smart-coupons' );
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

			$receiver_details = isset( $this->email_args['receiver_details'] ) ? $this->email_args['receiver_details'] : '';

			$design           = get_option( 'wc_sc_setting_coupon_design', 'round-dashed' );
			$background_color = get_option( 'wc_sc_setting_coupon_background_color', '#39cccc' );
			$foreground_color = get_option( 'wc_sc_setting_coupon_foreground_color', '#30050b' );
			$coupon_styles    = $woocommerce_smart_coupon->get_coupon_styles( $design );

			ob_start();

			wc_get_template(
				$this->template_html,
				array(
					'email_heading'    => $email_heading,
					'order'            => $order,
					'url'              => $url,
					'from'             => $from,
					'background_color' => $background_color,
					'foreground_color' => $foreground_color,
					'coupon_styles'    => $coupon_styles,
					'sender'           => $sender,
					'receiver_details' => $receiver_details,
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

			$receiver_details = isset( $this->email_args['receiver_details'] ) ? $this->email_args['receiver_details'] : '';

			ob_start();

			wc_get_template(
				$this->template_plain,
				array(
					'email_heading'    => $email_heading,
					'order'            => $order,
					'url'              => $url,
					'from'             => $from,
					'sender'           => $sender,
					'receiver_details' => $receiver_details,
				),
				'',
				$this->template_base
			);

			return ob_get_clean();
		}

		/**
		 * Function to update SC admin email settings when WC email settings get updated
		 */
		public function process_admin_options() {
			// Save regular options.
			parent::process_admin_options();

			$is_email_enabled = $this->get_field_value( 'enabled', $this->form_fields['enabled'] );

			if ( ! empty( $is_email_enabled ) ) {
				update_site_option( 'smart_coupons_combine_emails', $is_email_enabled );
			}
		}

	}
}
