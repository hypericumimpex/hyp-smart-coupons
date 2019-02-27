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

			add_action( 'admin_notices', array( $this, 'coupon_background_notice' ) );
			add_action( 'wp_ajax_wc_sc_coupon_background_progress', array( $this, 'ajax_coupon_background_progress' ) );

			add_filter( 'heartbeat_send', array( $this, 'check_coupon_background_progress' ), 10, 2 );

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
		 * Display notice if a background process is already running
		 */
		public function coupon_background_notice() {
			global $pagenow, $post;

			if ( ! is_admin() ) {
				return;
			}

			$page = ( ! empty( $_GET['page'] ) ) ? wc_clean( wp_unslash( $_GET['page'] ) ) : ''; // WPCS: sanitization ok. CSRF ok, input var ok.
			$tab  = ( ! empty( $_GET['tab'] ) ? ( 'send-smart-coupons' === $_GET['tab'] ? 'send-smart-coupons' : 'import-smart-coupons' ) : 'generate_bulk_coupons' ); // WPCS: sanitization ok. CSRF ok, input var ok.

			if ( ( ! empty( $post->post_type ) && 'shop_coupon' !== $post->post_type ) || ! in_array( $tab, array( 'generate_bulk_coupons', 'import-smart-coupons', 'send-smart-coupons' ), true ) ) {
				return;
			}

			if ( ! wp_script_is( 'jquery' ) ) {
				wp_enqueue_script( 'jquery' );
			}
			if ( ! wp_script_is( 'heartbeat' ) ) {
				wp_enqueue_script( 'heartbeat' );
			}

			if ( $this->is_process_running() ) {
				?>
				<div id="wc_sc_coupon_background_progress" class="error" style="display: none;">
					<p>
						<?php
							$bulk_action = get_site_option( 'bulk_coupon_action_' . $this->identifier );

						switch ( $bulk_action ) {

							case 'import_email':
							case 'import':
								$bulk_text = __( 'imported', 'woocommerce-smart-coupons' );
								break;

							case 'generate_email':
							case 'generate':
							default:
								$bulk_text = __( 'generated', 'woocommerce-smart-coupons' );
								break;

						}

							echo '<strong>' . esc_html__( 'Important', 'woocommerce-smart-coupons' ) . '</strong>: ' . esc_html__( 'Coupons are being', 'woocommerce-smart-coupons' );
							echo '&nbsp;' . esc_html( $bulk_text ) . '&nbsp;';
							echo esc_html__( 'in the background. You will be notified when it is completed.', 'woocommerce-smart-coupons' ) . '&nbsp;';
						?>
						<span id="wc_sc_remaining_time_label" style="display: none;">
							<?php echo esc_html__( 'Estimated time to complete', 'woocommerce-smart-coupons' ); ?>:&nbsp;
							<strong><span id="wc_sc_remaining_time"><?php echo esc_html( '--:--:--', 'woocommerce-smart-coupons' ); ?></span></strong>
							<?php echo wc_help_tip( __( 'Time may vary depending on the delay in network & background processing', 'woocommerce-smart-coupons' ) ); // WPCS: XSS ok. ?>
						</span>
					</p>
					<p>
						<?php echo esc_html__( 'You can continue with other work. But before bulk generating or importing new coupons, you need to wait for this process to complete.', 'woocommerce-smart-coupons' ); ?>
					</p>
				</div>
				<script type="text/javascript">
					jQuery(function(){
						var current_interval = false;
						function wc_sc_start_coupon_background_progress_timer( total_seconds, target_dom ) {
							var timer = total_seconds, hours, minutes, seconds;
							var target_element = target_dom.find('#wc_sc_remaining_time');
							var target_element_label = target_dom.find('#wc_sc_remaining_time_label');
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

								target_element_label.show();
								target_element.text(hours + ":" + minutes + ":" + seconds);

								if (--timer < 0) {
									timer = 0;
									clearInterval( current_interval );
									location.reload( true );
								}

							}, 1000);
						}
						jQuery(document).on( 'ready heartbeat-tick', function( event, data, response ){
							if ( data && data.total_seconds ) {
								var target_dom = jQuery('#wc_sc_coupon_background_progress');
								var total_seconds = data.total_seconds;
								target_dom.show();
								wc_sc_start_coupon_background_progress_timer( total_seconds, target_dom );
							} else {
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
										}
									}
								});
							}
						});
					});
				</script>
				<?php
			} else {
				$background_coupon_process_result = get_site_option( 'wc_sc_background_coupon_process_result' );
				if ( false !== $background_coupon_process_result ) {
					switch ( $background_coupon_process_result['action'] ) {
						case 'import_email':
							$action_title = __( 'Coupon import', 'woocommerce-smart-coupons' );
							$action_text  = __( 'added & emailed', 'woocommerce-smart-coupons' );
							break;
						case 'generate_email':
							$action_title = __( 'Coupon bulk generation', 'woocommerce-smart-coupons' );
							$action_text  = __( 'added & emailed', 'woocommerce-smart-coupons' );
							break;
						case 'import':
							$action_title = __( 'Coupon import', 'woocommerce-smart-coupons' );
							$action_text  = __( 'added', 'woocommerce-smart-coupons' );
							break;
						case 'generate':
						default:
							$action_title = __( 'Coupon bulk generation', 'woocommerce-smart-coupons' );
							$action_text  = __( 'added', 'woocommerce-smart-coupons' );
							break;
					}
					?>
					<div id="wc_sc_coupon_background_progress" class="updated" style="display: none;">
						<p>
							<strong><?php echo esc_html( $action_title ); ?></strong>:&nbsp;
							<?php echo esc_html__( 'Successfully', 'woocommerce-smart-coupons' ) . ' ' . esc_html( $action_text ) . ' ' . esc_html( $background_coupon_process_result['successful'] ) . ' ' . esc_html( _n( 'coupon', 'coupons', $background_coupon_process_result['successful'], 'woocommerce-smart-coupons' ) ) . '.'; ?>
						</p>
					</div>	
					<?php
					delete_site_option( 'wc_sc_background_coupon_process_result' );
				}
			}
			?>
			<script type="text/javascript">
				jQuery(function(){
					jQuery(document).ready(function(){
						var border_color = jQuery('#wc_sc_coupon_background_progress').css('border-left-color');
						jQuery('#wc_sc_coupon_background_progress').css('border-top', '1px solid ' + border_color);
						jQuery('#wc_sc_coupon_background_progress').css('border-right', '1px solid ' + border_color);
						jQuery('#wc_sc_coupon_background_progress').css('border-bottom', '1px solid ' + border_color);
						jQuery('#wc_sc_coupon_background_progress').show();
					});
				});
			</script>
			<?php

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
		 * Push coupon background progress in heartbeat response
		 *
		 * @param  array  $response  The response.
		 * @param  string $screen_id The screen id.
		 * @return array  $response
		 */
		public function check_coupon_background_progress( $response = array(), $screen_id = '' ) {

			if ( $this->is_process_running() ) {
				$progress = $this->get_coupon_background_progress();

				if ( ! empty( $progress['remaining_seconds'] ) ) {
					$response['total_seconds'] = $progress['remaining_seconds'];
				}
			}

			return $response;
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

			$bulk_coupon_action    = get_site_option( 'bulk_coupon_action_' . $this->identifier );
			$all_tasks_count       = get_site_option( 'all_tasks_count_' . $this->identifier );
			$remaining_tasks_count = get_site_option( 'remaining_tasks_count_' . $this->identifier );
			$success_count         = $all_tasks_count - $remaining_tasks_count;

			$coupon_background_process_result = array(
				'action'     => $bulk_coupon_action,
				'successful' => $success_count,
			);

			delete_site_option( 'start_time_' . $this->identifier );
			delete_site_option( 'current_time_' . $this->identifier );
			delete_site_option( 'all_tasks_count_' . $this->identifier );
			delete_site_option( 'remaining_tasks_count_' . $this->identifier );
			delete_site_option( 'bulk_coupon_action_' . $this->identifier );

			update_option( 'woo_sc_is_email_imported_coupons', 'no', 'no' );
			update_option( 'wc_sc_background_coupon_process_result', $coupon_background_process_result, 'no' );

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
