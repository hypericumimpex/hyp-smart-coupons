<?php
/**
 * Class to handle import of coupons in background
 *
 * @author      StoreApps
 * @since       3.8.6
 * @version     1.0.0
 *
 * @package     woocommerce-smart-coupons/includes/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WC_SC_Background_Process' ) ) {
	include_once 'class-wc-sc-background-process.php';
}

if ( ! class_exists( 'WC_SC_Background_Coupon_Importer' ) ) {

	/**
	 * WC_SC_Background_Coupon_Importer Class.
	 */
	class WC_SC_Background_Coupon_Importer extends WC_SC_Background_Process {

		/**
		 * Array for storing newly created global coupons
		 *
		 * @var $global_coupons_new
		 */
		public $global_coupons_new = array();

		/**
		 * Variable to hold instance of WC_SC_Background_Coupon_Importer
		 *
		 * @var $instance
		 */
		private static $instance = null;

		/**
		 * Initiate new background process.
		 */
		public function __construct() {
			// Uses unique prefix per blog so each blog has separate queue.
			$this->prefix = 'wp_' . get_current_blog_id();
			$this->action = 'wc_sc_coupon_importer';

			add_action( 'admin_notices', array( $this, 'coupon_background_progress' ) );
			add_action( 'admin_notices', array( $this, 'coupon_background_notice' ) );
			add_action( 'wp_ajax_wc_sc_coupon_background_progress', array( $this, 'ajax_coupon_background_progress' ) );

			parent::__construct();
		}

		/**
		 * Get single instance of WC_SC_Background_Coupon_Importer
		 *
		 * @return WC_SC_Background_Coupon_Importer Singleton object of WC_SC_Background_Coupon_Importer
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
		 * Get Identifier
		 *
		 * @return string The Identifier
		 */
		public function get_identifier() {
			return $this->identifier;
		}

		/**
		 * Show progress of background coupon process
		 */
		public function coupon_background_progress() {

			$show_progress = ( ! empty( $_GET['show_progress'] ) ) ? wc_clean( wp_unslash( $_GET['show_progress'] ) ) : ''; // WPCS: sanitization ok. CSRF ok, input var ok.

			if ( 'wc_sc_coupon_background_progress' !== $show_progress ) {
				return;
			}

			$start_time = get_site_option( 'start_time_' . $this->identifier, false );

			if ( false === $start_time ) {
				return;
			}

			if ( ! wp_script_is( 'jquery' ) ) {
				wp_enqueue_script( 'jquery' );
			}
			if ( ! wp_script_is( 'heartbeat' ) ) {
				wp_enqueue_script( 'heartbeat' );
			}
			?>
			<div id="wc_sc_coupon_background_progress" class="updated fade">
				<p>
					<?php
						$bulk_action = get_site_option( 'bulk_coupon_action_' . $this->identifier );

					switch ( $bulk_action ) {

						case 'import_email':
							$bulk_text = __( 'getting imported & are getting sent via email', 'woocommerce-smart-coupons' );
							break;

						case 'import':
							$bulk_text = __( 'importing', 'woocommerce-smart-coupons' );
							break;

						case 'generate_email':
							$bulk_text = __( 'getting generated & are getting sent via email', 'woocommerce-smart-coupons' );
							break;

						case 'generate':
						default:
							$bulk_text = __( 'generating', 'woocommerce-smart-coupons' );
							break;

					}

						echo esc_html__( 'Coupons are', 'woocommerce-smart-coupons' );
						echo '&nbsp;' . esc_html( $bulk_text ) . '&nbsp;';
						echo esc_html__( 'in the background. Estimated time remaining: ', 'woocommerce-smart-coupons' );
					?>
					<strong><span id="wc_sc_remaining_time"><?php echo esc_html( '--:--:--', 'woocommerce-smart-coupons' ); ?></span></strong>
					<?php echo wc_help_tip( __( 'Time may fluctuate depending on the delay in network & background processing', 'woocommerce-smart-coupons' ) ); // WPCS: XSS ok. ?>
				</p>
			</div>
			<script type="text/javascript">
				jQuery(function(){
					var current_interval = false;
					function wc_sc_start_coupon_background_progress_timer( total_seconds, target_dom ) {
						var timer = total_seconds, hours, minutes, seconds;
						var target_element = target_dom.find('#wc_sc_remaining_time');
						if ( false !== current_interval ) {
							clearInterval( current_interval );
						}
						current_interval = setInterval(function(){
							hours   = Math.floor(timer / 3600);
							timer   %= 3600;
							minutes = Math.floor(timer / 60);
							seconds = timer % 60;

							hours   = hours < 10 ? "0" + hours : hours;
							minutes = minutes < 10 ? "0" + minutes : minutes;
							seconds = seconds < 10 ? "0" + seconds : seconds;

							target_element.text(hours + ":" + minutes + ":" + seconds);

							if (--timer < 0) {
								timer = 0;
								location.reload();
							}

						}, 1000);
					}
					jQuery(document).on( 'ready heartbeat-tick', function( event, data, response ){
						jQuery.ajax({
							url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
							method: 'post',
							dataType: 'json',
							data: {
								action: 'wc_sc_coupon_background_progress',
								security: '<?php echo esc_attr( wp_create_nonce( 'wc-sc-background-coupon-progress' ) ); ?>'
							},
							success: function( response ) {
								var target_dom = jQuery('#wc_sc_coupon_background_progress');
								if ( response.total_seconds != undefined && response.total_seconds != '' ) {
									var total_seconds = response.total_seconds;
									target_dom.show();
									wc_sc_start_coupon_background_progress_timer( total_seconds, target_dom );
								} else {
									target_dom.hide();
								}
							}
						});
					});
				});
			</script>
			<?php
		}

		/**
		 * Display notice if a background process is already running
		 */
		public function coupon_background_notice() {
			global $pagenow;

			if ( 'admin.php' !== $pagenow ) {
				return;
			}

			if ( ! is_admin() ) {
				return;
			}

			$show_progress = ( ! empty( $_GET['show_progress'] ) ) ? wc_clean( wp_unslash( $_GET['show_progress'] ) ) : ''; // WPCS: sanitization ok. CSRF ok, input var ok.
			$page          = ( ! empty( $_GET['page'] ) ) ? wc_clean( wp_unslash( $_GET['page'] ) ) : ''; // WPCS: sanitization ok. CSRF ok, input var ok.
			$tab           = ( ! empty( $_GET['tab'] ) ? ( 'send-smart-coupons' === $_GET['tab'] ? 'send-smart-coupons' : 'import-smart-coupons' ) : 'generate_bulk_coupons' ); // WPCS: sanitization ok. CSRF ok, input var ok.

			if ( ( 'import-smart-coupons' !== $tab && 'generate_bulk_coupons' !== $tab ) || 'wc-smart-coupons' !== $page ) {
				return;
			}

			if ( $this->is_process_running() ) {
				echo '<div class="message error wc_sc_coupon_background_progress"><p><strong>' . esc_html__( 'Important', 'woocommerce-smart-coupons' ) . ':</strong> ' . esc_html__( 'A background process for coupon is already running. Wait for it to complete before generating or importing new coupons.', 'woocommerce-smart-coupons' ) . ( ( 'wc_sc_coupon_background_progress' !== $show_progress ) ? ' <a href="' . esc_url(
					add_query_arg(
						array(
							'page'          => 'wc-smart-coupons',
							'show_progress' => 'wc_sc_coupon_background_progress',
						),
						admin_url( 'admin.php' )
					)
				) . '" target="_blank">' . esc_html__( 'Check progress', 'woocommerce-smart-coupons' ) . '</a>' : '' ) . '</p></div>';
			}

		}

		/**
		 * Get coupon background progress via ajax
		 */
		public function ajax_coupon_background_progress() {

			check_ajax_referer( 'wc-sc-background-coupon-progress', 'security' );

			$response = array();

			$progress = $this->get_coupon_background_progress();

			if ( ! empty( $progress['remaining_seconds'] ) ) {
				$response['total_seconds'] = $progress['remaining_seconds'];
			}

			wp_send_json( $response );
		}

		/**
		 * Task
		 *
		 * Override this method to perform any actions required on each
		 * queue item. Return the modified item for further processing
		 * in the next pass through. Or, return false to remove the
		 * item from the queue.
		 *
		 * @param array $callback Update callback function.
		 * @return mixed
		 */
		protected function task( $callback ) {

			if ( isset( $callback['filter'], $callback['args'] ) ) {
				try {
					if ( empty( $this->global_coupons_new ) && ! is_array( $this->global_coupons_new ) ) {
						$this->global_coupons_new = array();
					}
					if ( ! class_exists( $callback['filter']['class'] ) ) {
						include_once 'class-' . strtolower( str_replace( '_', '-', $callback['filter']['class'] ) ) . '.php';
					}
					$object                     = $callback['filter']['class']::get_instance();
					$this->global_coupons_new[] = call_user_func_array( array( $object, $callback['filter']['function'] ), $callback['args'] );
				} catch ( Exception $e ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'Error: ' . $e->getMessage() . ' ' . __FILE__ . ' ' . __LINE__ ); // phpcs:ignore
					}
				}
			}
			return false;
		}

		/**
		 * Handle
		 *
		 * Pass each queue item to the task handler, while remaining
		 * within server memory and time limit constraints.
		 */
		protected function handle() {
			$this->lock_process();

			do {
				$batch = $this->get_batch();

				if ( empty( $batch->data ) ) {
					break;
				}

				$start_time = get_site_option( 'start_time_' . $this->identifier, false );
				if ( false === $start_time ) {
					update_site_option( 'start_time_' . $this->identifier, time() );
				}

				$all_tasks_count = get_site_option( 'all_tasks_count_' . $this->identifier, false );
				if ( false === $all_tasks_count ) {
					update_site_option( 'all_tasks_count_' . $this->identifier, count( $batch->data ) );
				}

				foreach ( $batch->data as $key => $value ) {
					$task = $this->task( $value );

					if ( false !== $task ) {
						$batch->data[ $key ] = $task;
					} else {
						unset( $batch->data[ $key ] );
					}

					// Update batch before sending more to prevent duplicate.
					$this->update( $batch->key, $batch->data );

					update_site_option( 'current_time_' . $this->identifier, time() );
					update_site_option( 'remaining_tasks_count_' . $this->identifier, count( $batch->data ) );

					if ( $this->time_exceeded() || $this->memory_exceeded() ) {
						// Batch limits reached.
						break;
					}
				}
				if ( empty( $batch->data ) ) {
					$this->delete( $batch->key );
				}
			} while ( ! $this->time_exceeded() && ! $this->memory_exceeded() && ! $this->is_queue_empty() );

			$this->unlock_process();

			// Start next batch or complete process.
			if ( ! $this->is_queue_empty() ) {
				$this->dispatch();
			} else {
				$this->complete();
			}
		}

		/**
		 * Start the background process
		 */
		public function dispatch_queue() {
			if ( ! empty( $this->data ) ) {
				$this->save()->dispatch();
			}
		}

		/**
		 * Complete.
		 *
		 * Override if applicable, but ensure that the below actions are
		 * performed, or, call parent::complete().
		 */
		protected function complete() {

			$global_coupons_new = array_filter( $this->global_coupons_new );

			// Code for updating the newly created global coupons to the option.
			if ( ! empty( $global_coupons_new ) ) {
				$global_coupons_list = get_option( 'sc_display_global_coupons' );
				$global_coupons      = ( ! empty( $global_coupons_list ) ) ? explode( ',', $global_coupons_list ) : array();
				$global_coupons_new  = array_filter( $global_coupons_new ); // for removing emty values.
				$global_coupons      = array_merge( $global_coupons, $global_coupons_new );
				update_option( 'sc_display_global_coupons', implode( ',', $global_coupons ), 'no' );
			}

			delete_site_option( 'start_time_' . $this->identifier );
			delete_site_option( 'current_time_' . $this->identifier );
			delete_site_option( 'all_tasks_count_' . $this->identifier );
			delete_site_option( 'remaining_tasks_count_' . $this->identifier );
			delete_site_option( 'bulk_coupon_action_' . $this->identifier );

			update_option( 'woo_sc_is_email_imported_coupons', 'no', 'no' );

			// Unschedule the cron healthcheck.
			$this->clear_scheduled_event();
		}

		/**
		 * Get progress of background coupon process
		 *
		 * @return array $progress
		 */
		public function get_coupon_background_progress() {
			$progress = array();

			$start_time            = get_site_option( 'start_time_' . $this->identifier, false );
			$current_time          = get_site_option( 'current_time_' . $this->identifier, false );
			$all_tasks_count       = get_site_option( 'all_tasks_count_' . $this->identifier, false );
			$remaining_tasks_count = get_site_option( 'remaining_tasks_count_' . $this->identifier, false );

			$percent_completion = floatval( 0 );
			if ( false !== $all_tasks_count && false !== $remaining_tasks_count ) {
				$percent_completion             = ( ( intval( $all_tasks_count ) - intval( $remaining_tasks_count ) ) * 100 ) / intval( $all_tasks_count );
				$progress['percent_completion'] = floatval( $percent_completion );
			}

			if ( $percent_completion > 0 && false !== $start_time && false !== $current_time ) {
				$time_taken_in_seconds         = $current_time - $start_time;
				$time_remaining_in_seconds     = ( $time_taken_in_seconds / $percent_completion ) * ( 100 - $percent_completion );
				$progress['remaining_seconds'] = ceil( $time_remaining_in_seconds );
			}

			return $progress;
		}

	}

}

WC_SC_Background_Coupon_Importer::get_instance();
