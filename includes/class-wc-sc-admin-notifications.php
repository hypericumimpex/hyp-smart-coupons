<?php
/**
 * Smart Coupons Admin Notifications
 *
 * @author      StoreApps
 * @since       4.0.0
 * @version     1.0
 *
 * @package     woocommerce-smart-coupons/includes/
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_SC_Admin_Notifications' ) ) {

	/**
	 * Class for handling admin pages of Smart Coupons
	 */
	class WC_SC_Admin_Notifications {

		/**
		 * Variable to hold instance of WC_SC_Admin_Notifications
		 *
		 * @var $instance
		 */
		private static $instance = null;

		/**
		 * Constructor
		 */
		public function __construct() {

			add_filter( 'plugin_action_links_' . plugin_basename( WC_SC_PLUGIN_FILE ), array( $this, 'plugin_action_links' ) );

			add_action( 'wp_ajax_wc_sc_review_notice_action', array( $this, 'wc_sc_review_notice_action' ) );
			add_action( 'wp_ajax_wc_sc_40_notice_action', array( $this, 'wc_sc_40_notice_action' ) );
			add_action( 'admin_notices', array( $this, 'show_plugin_notice' ) );

			// To update footer text on SC screens.
			add_filter( 'admin_footer_text', array( $this, 'wc_sc_footer_text' ) );
			add_filter( 'update_footer', array( $this, 'wc_sc_update_footer_text' ), 99 );

			// To show 'Connect your store' notice of WC Helper on SC pages.
			add_filter( 'woocommerce_screen_ids', array( $this, 'add_wc_connect_store_notice_on_sc_pages' ) );

		}

		/**
		 * Get single instance of WC_SC_Admin_Pages
		 *
		 * @return WC_SC_Admin_Pages Singleton object of WC_SC_Admin_Pages
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
		 * Function to add more action on plugins page
		 *
		 * @param array $links Existing links.
		 * @return array $links
		 */
		public function plugin_action_links( $links ) {
			$action_links = array(
				'settings' => '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=wc-smart-coupons' ) ) . '">' . esc_html__( 'Settings', 'woocommerce-smart-coupons' ) . '</a>',
				'faqs'     => '<a href="' . esc_url( admin_url( 'admin.php?page=sc-faqs' ) ) . '">' . esc_html__( 'FAQ\'s', 'woocommerce-smart-coupons' ) . '</a>',
			);

			return array_merge( $action_links, $links );
		}

		/**
		 * Handle Smart Coupons review notice action
		 */
		public function wc_sc_review_notice_action() {

			check_ajax_referer( 'wc-sc-review-notice-action', 'security' );

			$post_do = ( ! empty( $_POST['do'] ) ) ? wc_clean( wp_unslash( $_POST['do'] ) ) : ''; // phpcs:ignore

			$option = strtotime( '+1 month' );
			if ( 'remove' === $post_do ) {
				$option = 'no';
			}

			update_option( 'wc_sc_is_show_review_notice', $option, 'no' );

			wp_send_json( array( 'success' => 'yes' ) );

		}

		/**
		 * Handle Smart Coupons version 4.0.0 notice action
		 */
		public function wc_sc_40_notice_action() {

			check_ajax_referer( 'wc-sc-40-notice-action', 'security' );

			update_option( 'wc_sc_is_show_40_notice', 'no', 'no' );

			wp_send_json( array( 'success' => 'yes' ) );

		}

		/**
		 * Show plugin review notice
		 */
		public function show_plugin_notice() {

			global $pagenow, $post;

			$valid_post_types      = array( 'shop_coupon', 'shop_order', 'product' );
			$valid_pagenow         = array( 'edit.php', 'post.php', 'plugins.php' );
			$is_show_review_notice = get_option( 'wc_sc_is_show_review_notice' );
			$is_show_380_notice    = get_option( 'wc_sc_is_show_380_notice', 'yes' );
			$is_show_40_notice     = get_option( 'wc_sc_is_show_40_notice', 'yes' );
			$is_coupon_enabled     = get_option( 'woocommerce_enable_coupons' );
			$get_post_type         = ( ! empty( $post->post_type ) ) ? $post->post_type : '';
			$get_page              = ( ! empty( $_GET['page'] ) ) ? wc_clean( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore
			$get_tab               = ( ! empty( $_GET['tab'] ) ) ? wc_clean( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore

			$is_page = ( in_array( $pagenow, $valid_pagenow, true ) || in_array( $get_post_type, $valid_post_types, true ) || ( 'admin.php' === $pagenow && ( 'wc-smart-coupons' === $get_page || 'wc-smart-coupons' === $get_tab ) ) );

			if ( $is_page && 'yes' !== $is_coupon_enabled ) {
				?>
				<div id="wc_sc_coupon_disabled" class="updated fade error">
					<p>
						<?php
						echo '<strong>' . esc_html__( 'Important', 'woocommerce-smart-coupons' ) . ':</strong> ' . esc_html__( 'Setting "Enable the use of coupon codes" is disabled.', 'woocommerce-smart-coupons' ) . ' ' . sprintf(
							'<a href="%s">%s</a>',
							esc_url(
								add_query_arg(
									array(
										'page' => 'wc-settings',
										'tab'  => 'general',
									),
									admin_url( 'admin.php' )
								)
							),
							esc_html__( 'Enable', 'woocommerce-smart-coupons' )
						) . ' ' . esc_html__( 'it to use', 'woocommerce-smart-coupons' ) . ' <strong>' . esc_html__( 'WooCommerce Smart Coupons', 'woocommerce-smart-coupons' ) . '</strong> ' . esc_html__( 'features.', 'woocommerce-smart-coupons' );
						?>
					</p>
				</div>
				<?php
			}

			// Review Notice.
			if ( $is_page && ! empty( $is_show_review_notice ) && 'no' !== $is_show_review_notice && time() >= absint( $is_show_review_notice ) ) {
				if ( ! wp_script_is( 'jquery' ) ) {
					wp_enqueue_script( 'jquery' );
				}
				?>
				<style type="text/css" media="screen">
					#wc_sc_review_notice .wc_sc_review_notice_action {
						float: right;
						padding: 0.5em 0;
						text-align: right;
					}
				</style>
				<script type="text/javascript">
					jQuery(function(){
						jQuery('body').on('click', '#wc_sc_review_notice .wc_sc_review_notice_action a.wc_sc_review_notice_remind', function( e ){
							jQuery.ajax({
								url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
								type: 'post',
								dataType: 'json',
								data: {
									action: 'wc_sc_review_notice_action',
									security: '<?php echo esc_html( wp_create_nonce( 'wc-sc-review-notice-action' ) ); ?>',
									do: 'remind'
								},
								success: function( response ){
									if ( response.success != undefined && response.success != '' && response.success == 'yes' ) {
										jQuery('#wc_sc_review_notice').fadeOut(500, function(){ jQuery('#wc_sc_review_notice').remove(); });
									}
								}
							});
							return false;
						});
						jQuery('body').on('click', '#wc_sc_review_notice .wc_sc_review_notice_action a.wc_sc_review_notice_remove', function(){
							jQuery.ajax({
								url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
								type: 'post',
								dataType: 'json',
								data: {
									action: 'wc_sc_review_notice_action',
									security: '<?php echo esc_html( wp_create_nonce( 'wc-sc-review-notice-action' ) ); ?>',
									do: 'remove'
								},
								success: function( response ){
									if ( response.success != undefined && response.success != '' && response.success == 'yes' ) {
										jQuery('#wc_sc_review_notice').fadeOut(500, function(){ jQuery('#wc_sc_review_notice').remove(); });
									}
								}
							});
							return false;
						});
					});
				</script>
				<div id="wc_sc_review_notice" class="updated fade">
					<div class="wc_sc_review_notice_action">
						<a href="javascript:void(0)" class="wc_sc_review_notice_remind"><?php echo esc_html__( 'Remind me after a month', 'woocommerce-smart-coupons' ); ?></a><br>
						<a href="javascript:void(0)" class="wc_sc_review_notice_remove"><?php echo esc_html__( 'Never show again', 'woocommerce-smart-coupons' ); ?></a>
					</div>
					<p>
						<?php echo esc_html__( 'Awesome, you successfully auto-generated a coupon! Are you having a great experience with', 'woocommerce-smart-coupons' ) . ' <strong>' . esc_html__( 'WooCommerce Smart Coupons', 'woocommerce-smart-coupons' ) . '</strong> ' . esc_html__( 'so far?', 'woocommerce-smart-coupons' ) . '<br>' . esc_html__( 'Please consider', 'woocommerce-smart-coupons' ) . ' <a href="' . esc_url( 'https://woocommerce.com/products/smart-coupons/#reviews-start' ) . '">' . esc_html__( 'leaving a review', 'woocommerce-smart-coupons' ) . '</a> ' . esc_html__( '! If things aren\'t going quite as expected, we\'re happy to help -- please reach out to', 'woocommerce-smart-coupons' ) . ' <a href="' . esc_url( 'https://woocommerce.com/my-account/create-a-ticket/' ) . '">' . esc_html__( 'our support team', 'woocommerce-smart-coupons' ) . '</a>.'; ?>
					</p>
				</div>
				<?php
			}

			// What's new notice in SC 4.0.
			if ( $is_page && 'yes' === $is_show_40_notice ) {
				if ( ! wp_script_is( 'jquery' ) ) {
					wp_enqueue_script( 'jquery' );
				}
				?>
				<style type="text/css" media="screen">
					#wc_sc_40_notice .wc_sc_40_notice_action {
						float: right;
						padding: 0.5em 0;
						text-align: right;
					}
					#wc_sc_40_notice .wc_sc_40_notice_action.bottom {
						margin-top: -3em;
					}
					#wc_sc_40_notice .dashicons.dashicons-yes {
						color: #46b450;
					}
				</style>
				<script type="text/javascript">
					jQuery(function(){
						jQuery('body').on('click', '#wc_sc_40_notice .wc_sc_40_notice_action a.wc_sc_40_notice_remove', function( e ){
							jQuery.ajax({
								url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
								type: 'post',
								dataType: 'json',
								data: {
									action: 'wc_sc_40_notice_action',
									security: '<?php echo esc_html( wp_create_nonce( 'wc-sc-40-notice-action' ) ); ?>'
								},
								success: function( response ){
									if ( response.success != undefined && response.success != '' && response.success == 'yes' ) {
										jQuery('#wc_sc_40_notice').fadeOut(500, function(){ jQuery('#wc_sc_40_notice').remove(); });
									}
								}
							});
							return false;
						});
						jQuery( '#wc_sc_40_notice a.wc-sc-rating-link' ).click( function() {
							jQuery( this ).parent().text( jQuery( this ).data( 'rated' ) );
						});
					});
				</script>
				<div class="notice notice-info" id="wc_sc_40_notice">
					<div class="wc_sc_40_notice_action">
						<a href="javascript:void(0)" class="wc_sc_40_notice_remove" title="<?php echo esc_attr__( 'Dismiss', 'woocommerce-smart-coupons' ); ?>"><?php echo esc_html__( 'I have seen these features, now hide it', 'woocommerce-smart-coupons' ); ?></a>
					</div>
					<div class="wc_sc_40_notice_content">
						<h1><?php echo esc_html__( 'Welcome to Smart Coupons 4.0', 'woocommerce-smart-coupons' ); ?></h1>
						<h3><?php echo esc_html__( 'Here\'s what\'s new:', 'woocommerce-smart-coupons' ); ?></h3>
						<ul style="list-style-type: none; padding-left: 2em;">
							<li><span class="dashicons dashicons-yes"></span>&nbsp;<?php echo '<strong>' . esc_html__( 'Restrict Coupons by Location', 'woocommerce-smart-coupons' ) . '</strong> &mdash; ' . esc_html__( 'Supports Country, City, State, Zip code.', 'woocommerce-smart-coupons' ); ?></li>
							<li><span class="dashicons dashicons-yes"></span>&nbsp;<?php echo '<strong>' . esc_html__( 'Coupon action - Display Message', 'woocommerce-smart-coupons' ) . '</strong> &mdash; ' . esc_html__( 'Show Custom Messages on cart/checkout pages on successful coupon application.', 'woocommerce-smart-coupons' ); ?></li>
							<?php if ( 'yes' === $is_show_380_notice ) { ?>
								<li><span class="dashicons dashicons-yes"></span>&nbsp;<?php echo '<strong>' . esc_html__( 'Coupon action - Add products with/without discount', 'woocommerce-smart-coupons' ) . '</strong> &mdash; ' . esc_html__( 'Giveaway a product on application of a coupon.', 'woocommerce-smart-coupons' ); ?> <small><i><a href="https://docs.woocommerce.com/document/smart-coupons/#section-19" target="sa_wc_smart_coupons_docs"><?php echo esc_html__( '[Know more]', 'woocommerce-smart-coupons' ); ?></a></i></small></li>
								<li><span class="dashicons dashicons-yes"></span>&nbsp;<?php echo '<strong>' . esc_html__( 'Coupon for new users', 'woocommerce-smart-coupons' ) . '</strong> &mdash; ' . esc_html__( 'Create special coupons that only first time shoppers can use.', 'woocommerce-smart-coupons' ); ?> <small><i><a href="https://docs.woocommerce.com/document/smart-coupons/#section-18" target="sa_wc_smart_coupons_docs"><?php echo esc_html__( '[Know more]', 'woocommerce-smart-coupons' ); ?></a></i></small></li>
								<li><span class="dashicons dashicons-yes"></span>&nbsp;<?php echo '<strong>' . esc_html__( 'Customize coupon designs', 'woocommerce-smart-coupons' ) . '</strong> &mdash; ' . esc_html__( 'Choose from six readymade coupon designs.', 'woocommerce-smart-coupons' ); ?> <small><i><a href="https://docs.woocommerce.com/document/smart-coupons/#section-17" target="sa_wc_smart_coupons_docs"><?php echo esc_html__( '[Know more]', 'woocommerce-smart-coupons' ); ?></a></i></small></li>
								<li><span class="dashicons dashicons-yes"></span>&nbsp;<?php echo '<strong>' . esc_html__( 'Customize coupon code length', 'woocommerce-smart-coupons' ) . '</strong> &mdash; ' . esc_html__( 'Control the length of auto generated coupon code.', 'woocommerce-smart-coupons' ); ?> <small><i><a href="https://docs.woocommerce.com/document/smart-coupons/#section-21" target="sa_wc_smart_coupons_docs"><?php echo esc_html__( '[Know more]', 'woocommerce-smart-coupons' ); ?></a></i></small></li>
								<li><span class="dashicons dashicons-yes"></span>&nbsp;<?php echo '<strong>' . esc_html__( 'Rename the label - "Store Credit / Gift Certificate"', 'woocommerce-smart-coupons' ) . '</strong> &mdash; ' . esc_html__( 'Change "Store Credit / Gift Certificate" to gift cards/bonus card etc in entire store.', 'woocommerce-smart-coupons' ); ?> <small><i><a href="https://docs.woocommerce.com/document/smart-coupons/#section-20" target="sa_wc_smart_coupons_docs"><?php echo esc_html__( '[Know more]', 'woocommerce-smart-coupons' ); ?></a></i></small></li>
							<?php } ?>
						</ul>
						<hr>
						<p>
							<?php
								/* translators: %s: link to submit idea for Smart Coupons on WooCommerce idea board */
								echo sprintf( esc_html__( 'Hope you enjoy these new features! If you have any other feature request, %s.', 'woocommerce-smart-coupons' ), '<a href="' . esc_url( 'http://ideas.woocommerce.com/forums/133476-woocommerce?category_id=163716' ) . '" target="_blank">let us know here</a>' );
							?>
						</p>
					</div>
					<div class="wc_sc_40_notice_action bottom">
						<?php echo esc_html__( 'Rate', 'woocommerce-smart-coupons' ); ?>&nbsp;<a href="https://woocommerce.com/products/smart-coupons/#reviews-start" target="_blank" class="wc-sc-rating-link" data-rated="<?php echo esc_attr__( 'Thanks :)', 'woocommerce-smart-coupons' ); ?>" title="<?php echo esc_attr__( 'Rate WooCommerce Smart Coupons', 'woocommerce-smart-coupons' ); ?>">&#9733;&#9733;&#9733;&#9733;&#9733;</a>
					</div>
				</div>
				<?php
			}

		}

		/**
		 * Function to 'Connect your store' notice on Smart Coupons pages in admin
		 *
		 * @param  string $sc_rating_text Text in footer (left).
		 * @return string $sc_rating_text
		 */
		public function wc_sc_footer_text( $sc_rating_text ) {

			global $post, $pagenow;

			if ( ! empty( $pagenow ) ) {
				$get_post_type = ( ! empty( $post->post_type ) ) ? $post->post_type : '';
	  			$get_page      = ( ! empty( $_GET['page'] ) ) ? wc_clean( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore
				$get_tab       = ( ! empty( $_GET['tab'] ) ) ? wc_clean( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore
				$sc_pages      = array( 'wc-smart-coupons', 'sc-about', 'sc-faqs' );

				if ( in_array( $get_page, $sc_pages, true ) || 'shop_coupon' === $get_post_type || 'wc-smart-coupons' === $get_tab ) {
					/* translators: %s: link to review WooCommerce Smart Coupons */
					$sc_rating_text = wp_kses_post( sprintf( __( 'If you are liking WooCommerce Smart Coupons, can you do us a favor? <strong>Please consider leaving us a %s</strong>. A huge thanks in advance from WooCommerce & StoreApps!', 'woocommerce-smart-coupons' ), '<a target="_blank" href="' . esc_url( 'https://woocommerce.com/products/smart-coupons/#reviews-start' ) . '">5-star rating here</a>' ) );
				}
			}

			return $sc_rating_text;

		}

		/**
		 * Function to 'Connect your store' notice on Smart Coupons pages in admin
		 *
		 * @param  string $sc_text Text in footer (right).
		 * @return string $sc_text
		 */
		public function wc_sc_update_footer_text( $sc_text ) {

			global $post, $pagenow;

			if ( ! empty( $pagenow ) ) {
				$get_post_type = ( ! empty( $post->post_type ) ) ? $post->post_type : '';
	  			$get_page      = ( ! empty( $_GET['page'] ) ) ? wc_clean( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore
	  			$get_tab       = ( ! empty( $_GET['tab'] ) ) ? wc_clean( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore
				$sc_pages      = array( 'wc-smart-coupons', 'sc-about', 'sc-faqs' );

				if ( in_array( $get_page, $sc_pages, true ) || 'shop_coupon' === $get_post_type || 'wc-smart-coupons' === $get_tab ) {
					/* translators: %s: link to submit idea for Smart Coupons on WooCommerce idea board */
					$sc_text = sprintf( __( '<strong>Have a feature request? Submit your request from our %s.</strong>', 'woocommerce-smart-coupons' ), '<a href="' . esc_url( 'http://ideas.woocommerce.com/forums/133476-woocommerce?category_id=163716' ) . '" target="_blank">idea board</a>' );
				}
			}

			return $sc_text;

		}

		/**
		 * Function to 'Connect your store' notice on Smart Coupons pages in admin
		 *
		 * @param  array $screen_ids List of existing screen ids.
		 * @return array $screen_ids
		 */
		public function add_wc_connect_store_notice_on_sc_pages( $screen_ids ) {

			array_push( $screen_ids, 'woocommerce_page_wc-smart-coupons' );

			return $screen_ids;
		}

	}

}

WC_SC_Admin_Notifications::get_instance();
