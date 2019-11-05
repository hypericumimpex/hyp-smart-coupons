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

if ( ! class_exists( 'WC_SC_Email' ) ) {
	/**
	 * The Smart Copuons Email class
	 *
	 * @extends \WC_Email
	 */
	class WC_SC_Email extends WC_Email {

		/**
		 * Email args defaults
		 *
		 * @var array
		 */
		public $email_args = array(
			'email'                         => '',
			'coupon'                        => array(),
			'discount_type'                 => 'smart_coupon',
			'smart_coupon_type'             => '',
			'receiver_name'                 => '',
			'message_from_sender'           => '',
			'gift_certificate_sender_name'  => '',
			'gift_certificate_sender_email' => '',
			'from'                          => '',
			'sender'                        => '',
			'is_gift'                       => false,
		);

		/**
		 * Get shop page url
		 *
		 * @return string $url Shop page url
		 */
		public function get_url() {

			global $woocommerce_smart_coupon;

			if ( $woocommerce_smart_coupon->is_wc_gte_30() ) {
				$page_id = wc_get_page_id( 'shop' );
			} else {
				$page_id = woocommerce_get_page_id( 'shop' );
			}

			$url = ( get_option( 'permalink_structure' ) ) ? get_permalink( $page_id ) : get_post_type_archive_link( 'product' );

			return $url;
		}

		/**
		 * Function to get from sender text.
		 *
		 * @return string $from_sender_name From sender name text string.
		 */
		public function get_from_sender_name() {

			$sender_name = $this->get_sender_name();

			if ( ! empty( $sender_name ) ) {
				$from = ' ' . __( 'from', 'woocommerce-smart-coupons' ) . ' ';
			} else {
				$from = '';
			}

			$from_sender_name = ( ! empty( $sender_name ) ) ? $from . $sender_name : '';

			return $from_sender_name;
		}

		/**
		 * Function to get sender name.
		 *
		 * @return string $sender_name Sender name.
		 */
		public function get_sender_name() {

			$sender_name = isset( $this->email_args['gift_certificate_sender_name'] ) ? $this->email_args['gift_certificate_sender_name'] : '';

			return $sender_name;
		}

		/**
		 * Function to get sender email.
		 *
		 * @return string $sender_email Sender email.
		 */
		public function get_sender_email() {

			$sender_email = isset( $this->email_args['gift_certificate_sender_email'] ) ? $this->email_args['gift_certificate_sender_email'] : '';

			return $sender_email;
		}

		/**
		 * Initialise Settings Form Fields
		 */
		public function init_form_fields() {

			/* translators: %s: list of placeholders */
			$placeholder_text = sprintf( __( 'Available placeholders: %s', 'woocommerce' ), '<code>' . implode( '</code>, <code>', array_keys( $this->placeholders ) ) . '</code>' );

			$this->form_fields = array(
				'enabled'    => array(
					'title'   => __( 'Enable/Disable', 'woocommerce' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable this email notification', 'woocommerce' ),
					'default' => 'yes',
				),
				'email_type' => array(
					'title'       => __( 'Email type', 'woocommerce' ),
					'type'        => 'select',
					'description' => __( 'Choose which format of email to send.', 'woocommerce' ),
					'default'     => 'html',
					'class'       => 'email_type wc-enhanced-select',
					'options'     => $this->get_email_type_options(),
					'desc_tip'    => true,
				),
				'subject'    => array(
					'title'       => __( 'Subject', 'woocommerce' ),
					'type'        => 'text',
					'desc_tip'    => true,
					'description' => $placeholder_text,
					'placeholder' => $this->get_default_subject(),
					'default'     => '',
				),
				'heading'    => array(
					'title'       => __( 'Email heading', 'woocommerce' ),
					'type'        => 'text',
					'desc_tip'    => true,
					'description' => $placeholder_text,
					'placeholder' => $this->get_default_heading(),
					'default'     => '',
				),
			);
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
