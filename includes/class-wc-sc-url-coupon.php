<?php
/**
 * Coupons via URL
 *
 * @author      StoreApps
 * @since       3.3.0
 * @version     1.0
 *
 * @package     woocommerce-smart-coupons/includes/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WC_SC_URL_Coupon' ) ) {

	/**
	 * Class for handling coupons applied via URL
	 */
	class WC_SC_URL_Coupon {

		/**
		 * Variable to hold instance of WC_SC_URL_Coupon
		 *
		 * @var $instance
		 */
		private static $instance = null;

		/**
		 * Variable to hold coupon notices
		 *
		 * @var $coupon_notices
		 */
		private $coupon_notices = array();

		/**
		 * Constructor
		 */
		public function __construct() {

			add_action( 'wp_loaded', array( $this, 'apply_coupon_from_url' ), 20 );
			add_action( 'wp_loaded', array( $this, 'apply_coupon_from_session' ), 20 );
			add_action( 'wp_loaded', array( $this, 'move_applied_coupon_from_cookies_to_account' ) );
			add_action( 'wp_head', array( $this, 'convert_sc_coupon_notices_to_wc_notices' ) );
			add_filter( 'the_content', array( $this, 'show_coupon_notices' ) );

		}

		/**
		 * Get single instance of WC_SC_URL_Coupon
		 *
		 * @return WC_SC_URL_Coupon Singleton object of WC_SC_URL_Coupon
		 */
		public static function get_instance() {
			// Check if instance is already exists.
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Handle call to functions which is not available in this class
		 *
		 * @param string $function_name The function name.
		 * @param array  $arguments Array of arguments passed while calling $function_name.
		 * @return result of function call
		 */
		public function __call( $function_name, $arguments = array() ) {

			global $woocommerce_smart_coupon;

			if ( ! is_callable( array( $woocommerce_smart_coupon, $function_name ) ) ) {
				return;
			}

			if ( ! empty( $arguments ) ) {
				return call_user_func_array( array( $woocommerce_smart_coupon, $function_name ), $arguments );
			} else {
				return call_user_func( array( $woocommerce_smart_coupon, $function_name ) );
			}

		}

		/**
		 * Apply coupon code if passed in url
		 */
		public function apply_coupon_from_url() {

			if ( empty( $_SERVER['QUERY_STRING'] ) ) {
				return;
			}

			parse_str( wp_unslash( $_SERVER['QUERY_STRING'] ), $coupon_args ); // phpcs:ignore
			$coupon_args = wc_clean( $coupon_args );

			if ( isset( $coupon_args['coupon-code'] ) && ! empty( $coupon_args['coupon-code'] ) ) {

				$coupon_args['coupon-code'] = urldecode( $coupon_args['coupon-code'] );

				$cart = ( is_object( WC() ) && isset( WC()->cart ) ) ? WC()->cart : null;

				if ( empty( $cart ) || WC()->cart->is_empty() ) {
					$this->hold_applied_coupon( $coupon_args );
				} else {

					if ( ! WC()->cart->has_discount( $coupon_args['coupon-code'] ) ) {
						WC()->cart->add_discount( trim( $coupon_args['coupon-code'] ) );
					}
				}

				if ( empty( $coupon_args['sc-page'] ) ) {
					return;
				}

				$redirect_url = '';

				if ( in_array( $coupon_args['sc-page'], array( 'shop', 'cart', 'checkout', 'myaccount' ), true ) ) {
					if ( $this->is_wc_gte_30() ) {
						$page_id = wc_get_page_id( $coupon_args['sc-page'] );
					} else {
						$page_id = woocommerce_get_page_id( $coupon_args['sc-page'] );
					}
					$redirect_url = get_permalink( $page_id );
				} else {
					$redirect_url = get_permalink( get_page_by_title( $coupon_args['sc-page'] ) );
				}

				if ( empty( $redirect_url ) ) {
					$redirect_url = home_url();
				}

				$redirect_url = $this->get_redirect_url_after_smart_coupons_process( $redirect_url );

				wp_safe_redirect( $redirect_url );

				exit;

			}

		}

		/**
		 * Apply coupon code from session, if any
		 */
		public function apply_coupon_from_session() {

			$cart = ( is_object( WC() ) && isset( WC()->cart ) ) ? WC()->cart : null;

			if ( empty( $cart ) || WC()->cart->is_empty() ) {
				return;
			}

			$user_id = get_current_user_id();

			if ( 0 === $user_id ) {
				$unique_id               = ( ! empty( $_COOKIE['sc_applied_coupon_profile_id'] ) ) ? wc_clean( wp_unslash( $_COOKIE['sc_applied_coupon_profile_id'] ) ) : ''; // phpcs:ignore
				$applied_coupon_from_url = ( ! empty( $unique_id ) ) ? get_option( 'sc_applied_coupon_profile_' . $unique_id, array() ) : array();
			} else {
				$applied_coupon_from_url = get_user_meta( $user_id, 'sc_applied_coupon_from_url', true );
			}

			if ( empty( $applied_coupon_from_url ) ) {
				return;
			}

			foreach ( $applied_coupon_from_url as $index => $coupon_code ) {
				WC()->cart->add_discount( trim( $coupon_code ) );
				unset( $applied_coupon_from_url[ $index ] );
			}

			if ( 0 === $user_id ) {
				update_option( 'sc_applied_coupon_profile_' . $unique_id, $applied_coupon_from_url, 'no' );
			} else {
				update_user_meta( $user_id, 'sc_applied_coupon_from_url', $applied_coupon_from_url );
			}

		}

		/**
		 * Apply coupon code from session, if any
		 *
		 * @param array $coupon_args The coupon arguments.
		 */
		public function hold_applied_coupon( $coupon_args ) {

			$user_id      = get_current_user_id();
			$saved_status = '';

			if ( 0 === $user_id ) {
				$saved_status = $this->save_applied_coupon_in_cookie( $coupon_args );
			} else {
				$saved_status = $this->save_applied_coupon_in_account( $coupon_args, $user_id );
			}

			if ( ! empty( $saved_status ) ) {
				if ( 'saved' === $saved_status ) {
					$notice = __( 'Coupon code applied successfully. Please add some products to the cart to see the discount.', 'woocommerce-smart-coupons' );
					$this->set_coupon_notices( $notice, 'success' );
				} elseif ( 'already_saved' === $saved_status ) {
					$notice = __( 'Coupon code already applied! Please add some products to the cart to see the discount.', 'woocommerce-smart-coupons' );
					$this->set_coupon_notices( $notice, 'error' );
				}
			}

		}

		/**
		 * Apply coupon code from session, if any
		 *
		 * @param array $coupon_args The coupon arguments.
		 * @return string $saved_status
		 */
		public function save_applied_coupon_in_cookie( $coupon_args ) {

			$saved_status = ''; // Variable to store whether coupon saved/already saved in cookie.

			if ( ! empty( $coupon_args['coupon-code'] ) ) {

				if ( empty( $_COOKIE['sc_applied_coupon_profile_id'] ) ) {
					$unique_id = $this->generate_unique_id();
				} else {
					$unique_id = wc_clean( wp_unslash( $_COOKIE['sc_applied_coupon_profile_id'] ) ); // phpcs:ignore
				}

				$applied_coupons = get_option( 'sc_applied_coupon_profile_' . $unique_id, array() );

				if ( ! in_array( $coupon_args['coupon-code'], $applied_coupons, true ) ) {
					$applied_coupons[] = $coupon_args['coupon-code'];
					$saved_status      = 'saved';
					update_option( 'sc_applied_coupon_profile_' . $unique_id, $applied_coupons, 'no' );
					wc_setcookie( 'sc_applied_coupon_profile_id', $unique_id, $this->get_cookie_life() );
				} else {
					$saved_status = 'already_saved';
				}
			}

			return $saved_status;

		}

		/**
		 * Apply coupon code from session, if any
		 *
		 * @param array $coupon_args The coupon arguments.
		 * @param int   $user_id The user id.
		 * @return string $saved_status
		 */
		public function save_applied_coupon_in_account( $coupon_args, $user_id ) {

			$saved_status = ''; // Variable to store whether coupon saved/already saved in user meta.

			if ( ! empty( $coupon_args['coupon-code'] ) ) {

				$applied_coupons = get_user_meta( $user_id, 'sc_applied_coupon_from_url', true );

				if ( empty( $applied_coupons ) ) {
					$applied_coupons = array();
				}

				if ( ! in_array( $coupon_args['coupon-code'], $applied_coupons, true ) ) {
					$applied_coupons[] = $coupon_args['coupon-code'];
					$saved_status      = 'saved';
					update_user_meta( $user_id, 'sc_applied_coupon_from_url', $applied_coupons );
				} else {
					$saved_status = 'already_saved';
				}
			}

			return $saved_status;

		}

		/**
		 * Apply coupon code from session, if any
		 */
		public function move_applied_coupon_from_cookies_to_account() {

			$user_id = get_current_user_id();

			if ( $user_id > 0 && ! empty( $_COOKIE['sc_applied_coupon_profile_id'] ) ) {

				$unique_id = wc_clean( wp_unslash( $_COOKIE['sc_applied_coupon_profile_id'] ) ); // phpcs:ignore

				$applied_coupons = get_option( 'sc_applied_coupon_profile_' . $unique_id );

				if ( false !== $applied_coupons && is_array( $applied_coupons ) && ! empty( $applied_coupons ) ) {

					$saved_coupons = get_user_meta( $user_id, 'sc_applied_coupon_from_url', true );
					if ( empty( $saved_coupons ) || ! is_array( $saved_coupons ) ) {
						$saved_coupons = array();
					}
					$saved_coupons = array_merge( $saved_coupons, $applied_coupons );
					update_user_meta( $user_id, 'sc_applied_coupon_from_url', $saved_coupons );
					wc_setcookie( 'sc_applied_coupon_profile_id', '' );
					delete_option( 'sc_applied_coupon_profile_' . $unique_id );

				}
			}

		}

		/**
		 * Function to get redirect URL after processing Smart Coupons params
		 *
		 * @param string $url The URL.
		 * @return string $url
		 */
		public function get_redirect_url_after_smart_coupons_process( $url = '' ) {

			if ( empty( $url ) ) {
				return $url;
			}

			$query_string = ( ! empty( $_SERVER['QUERY_STRING'] ) ) ? wc_clean( wp_unslash( $_SERVER['QUERY_STRING'] ) ) : array(); // phpcs:ignore

			parse_str( $query_string, $url_args );

			$sc_params  = array( 'coupon-code', 'sc-page' );
			$url_params = array_diff_key( $url_args, array_flip( $sc_params ) );

			return add_query_arg( $url_params, $url );
		}

		/**
		 * Function to convert sc coupon notices to wc notices
		 */
		public function convert_sc_coupon_notices_to_wc_notices() {
			$coupon_notices = $this->get_coupon_notices();
			// If we have coupon notices to be shown and we are on a woocommerce page then convert them to wc notices.
			if ( count( $coupon_notices ) > 0 && ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) ) {
				foreach ( $coupon_notices as $notice_type => $notices ) {
					if ( count( $notices ) > 0 ) {
						foreach ( $notices as $notice ) {
							wc_add_notice( $notice, $notice_type );
						}
					}
				}
				$this->remove_coupon_notices();
			}
		}

		/**
		 * Function to get sc coupon notices
		 */
		public function get_coupon_notices() {
			return apply_filters( 'wc_sc_coupon_notices', $this->coupon_notices );
		}

		/**
		 * Function to set sc coupon notices
		 *
		 * @param string $notice notice.
		 * @param string $type notice type.
		 */
		public function set_coupon_notices( $notice = '', $type = '' ) {
			if ( empty( $notice ) || empty( $type ) ) {
				return;
			}
			if ( empty( $this->coupon_notices[ $type ] ) || ! is_array( $this->coupon_notices[ $type ] ) ) {
				$this->coupon_notices[ $type ] = array();
			}
			$this->coupon_notices[ $type ][] = $notice;
		}

		/**
		 * Function to remove sc coupon notices
		 */
		public function remove_coupon_notices() {
			$this->coupon_notices = array();
		}

		/**
		 * Function to add coupon notices to wp content
		 *
		 * @param string $content page content.
		 * @return string $content page content
		 */
		public function show_coupon_notices( $content = '' ) {

			$coupon_notices = $this->get_coupon_notices();

			if ( count( $coupon_notices ) > 0 ) {

				// Buffer output.
				ob_start();

				foreach ( $coupon_notices as $notice_type => $notices ) {
					if ( count( $coupon_notices[ $notice_type ] ) > 0 ) {
						wc_get_template(
							"notices/{$notice_type}.php", array(
								'messages' => $coupon_notices[ $notice_type ],
							)
						);
					}
				}

				$notices = wc_kses_notice( ob_get_clean() );
				$content = $notices . $content;
				$this->remove_coupon_notices(); // Empty out notice data.
			}

			return $content;

		}

	}

}

WC_SC_URL_Coupon::get_instance();
