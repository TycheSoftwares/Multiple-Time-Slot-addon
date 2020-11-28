<?php
/**
 * Plugin Name: Multiple Time Slot Addon
 * Plugin URI: https://www.tychesoftwares.com/store/premium-plugins/multiple-time-slot-addon-woocommerce-booking-appointment-plugin/
 * Description: This is an addon for the Booking & Appointment Plugin for WooCommerce which lets you to select multiple time slots on a date for each product on the website.
 * Version: 2.5
 * Author: Tyche Softwares
 * Author URI: http://www.tychesoftwares.com/
 * Requires PHP: 5.6
 * WC requires at least: 3.0.0
 * WC tested up to: 4.7
 *
 * @package BKAP/Multiple-Timeslot
 */

/**
 * Class for Multiple Time Slots Functionalities to be used along with Booking functionalities.
 *
 * @author   Tyche Softwares
 * @package  Addon/Multiple-TimeSlots
 * @category Classes
 */
if ( ! class_exists( 'Bkap_Multiple_Time_Slots' ) ) {

	/**
	 * Bkap_Multiple_Time_Slots class
	 *
	 * @since 1.0
	 */
	class Bkap_Multiple_Time_Slots {

		/**
		 * Default constructor
		 *
		 * @since 1.0
		 */
		public function __construct() {

			$this->weekdays = array(
				'booking_weekday_0' => 'Sunday',
				'booking_weekday_1' => 'Monday',
				'booking_weekday_2' => 'Tuesday',
				'booking_weekday_3' => 'Wednesday',
				'booking_weekday_4' => 'Thursday',
				'booking_weekday_5' => 'Friday',
				'booking_weekday_6' => 'Saturday',
			);
			$this->bkap_multiple_time_slots_define_constants(); // Defining Constants.
			$this->bkap_multiple_time_slots_edd_plugin_updater(); // Plugin Updater.
			$this->bkap_init_hooks(); // Plujgin Hooks.
		}

		/**
		 * Define constants to be used accross the plugin
		 *
		 * @since 1.0
		 */
		public static function bkap_multiple_time_slots_define_constants() {

			/**
			 * This is the URL our updater / license checker pings. This should be the URL of the site with EDD installed
			 *
			 * IMPORTANT: change the name of this constant to something unique to prevent conflicts with other plugins using this system
			 */
			define( 'EDD_SL_STORE_URL_MULTIPLE_TIMESLOT_BOOK', 'http://www.tychesoftwares.com/' );

			/**
			 * The name of your product. This is the title of your product in EDD and should match the download title in EDD exactly
			 *
			 * IMPORTANT: change the name of this constant to something unique to prevent conflicts with other plugins using this system
			 */
			define( 'EDD_SL_ITEM_NAME_MULTIPLE_TIMESLOT_BOOK', 'Multiple Time Slot addon for Woocommerce Booking and Appointment Plugin' );

			define( 'BKAPMTS_VERSION', '2.5' );
		}

		/**
		 * Multiple Time Slots Addon for Booking & Appointment Plugin Updater.
		 *
		 * @since 1.0
		 */
		public static function bkap_multiple_time_slots_edd_plugin_updater() {

			if ( ! class_exists( 'EDD_MULTIPLE_TIMESLOT_BOOK_Plugin_Updater' ) ) {
				// load our custom updater if it doesn't already exist.
				include( dirname( __FILE__ ) . '/plugin-updates/EDD_MULTIPLE_TIMESLOT_BOOK_Plugin_Updater.php' );

				// retrieve our license key from the DB.
				$license_key = trim( get_option( 'edd_sample_license_key_multiple_timeslot_book' ) );

				// setup the updater.
				$edd_updater = new EDD_MULTIPLE_TIMESLOT_BOOK_Plugin_Updater(
					EDD_SL_STORE_URL_MULTIPLE_TIMESLOT_BOOK,
					__FILE__,
					array(
						'version'   => BKAPMTS_VERSION, // current version number.
						'license'   => $license_key, // license key.
						'item_name' => EDD_SL_ITEM_NAME_MULTIPLE_TIMESLOT_BOOK, // name of this plugin.
						'author'    => 'Ashok Rane',  // author of this plugin.
					)
				);
			}
		}

		/**
		 * This function contains all the hooks for this addon.
		 *
		 * @since 1.0
		 */
		public function bkap_init_hooks() {

			if ( $this->bkap_is_woocommerce_bkap_activated() ) {
				add_action( 'woocommerce_init', array( $this, 'bkap_load' ) );
			} else {
				add_action( 'admin_notices', array( &$this, 'bkapmts_error_notice' ) );
			}
		}

		/**
		 * This function will load hooks upon WooCommerce Init Hook.
		 *
		 * @since 1.0
		 */
		public function bkap_load() {

				// Initialize settings.
				register_activation_hook( __FILE__, array( &$this, 'bkapmts_activate' ) );
				add_action( 'init', array( &$this, 'bkapmts_update_po_file' ) );

				add_action( 'init', array( &$this, 'bkapmts_include_file' ), 5 );
				add_action( 'admin_init', array( &$this, 'bkapmts_include_file' ) );
				add_action( 'admin_init', array( &$this, 'bkapmts_update_settings' ) );

				// used to add new settings on the product page booking box.
				add_action( 'bkap_before_time_enabled', array( &$this, 'bkapmts_show_field_settings' ) );
				add_filter( 'bkap_save_product_settings', array( &$this, 'bkapmts_product_settings_save' ), 10, 2 );
				add_filter( 'bkap_function_slot', array( &$this, 'bkapmts_slot_function' ), 10, 1 );
				add_filter( 'bkap_slot_type', array( &$this, 'bkapmts_slot_type' ), 10, 1 );
				add_filter( 'bkap_addon_add_cart_item_data', array( &$this, 'bkapmts_add_cart_item_data' ), 15, 3 );
				add_filter( 'bkap_get_item_data', array( &$this, 'bkapmts_get_item_data' ), 10, 2 );
				add_action( 'bkap_update_booking_history', array( &$this, 'bkapmts_order_item_meta' ), 50, 2 );
				// Validate on cart and checkout page.
				add_action( 'bkap_validate_cart_items', array( &$this, 'bkapmts_quantity_check' ), 10, 1 );
				// Validation on the product page.
				add_action( 'bkap_validate_add_to_cart', array( &$this, 'bkapmts_quantity_prod' ), 10, 2 );
				add_action( 'bkap_order_status_cancelled', array( &$this, 'bkapmts_cancel_order' ), 10, 3 );
				add_action( 'bkap_add_submenu', array( &$this, 'bkapmts_menu' ) );
				// Display multiple time slot price for single day bookings.
				add_action( 'bkap_display_updated_addon_price', array( &$this, 'bkapmts_show_multiple_time_price' ), 10, 7 );
				// print hidden field for number of slots selected.
				add_action( 'bkap_print_hidden_fields', array( &$this, 'bkapmts_print_fields' ), 10, 2 );
				// Ajax calls.
				add_action( 'init', array( &$this, 'bkapmts_time_load_ajax' ) );
				// print hidden fields on the front end product page.
				add_action( 'bkap_print_hidden_fields', array( &$this, 'bkapmts_print_hidden_lockout' ), 10, 2 );
				// CSS & JS.
				add_action( 'woocommerce_before_single_product', array( &$this, 'bkapmts_front_side_scripts_css' ) );
				add_action( 'wp_head', array( &$this, 'bkapmts_scripts' ) );
				add_action( 'admin_head', array( &$this, 'bkapmts_scripts' ) );
		}

		/**
		 * This function will add hidden field on the front end for setting multiple multiple prices..
		 *
		 * @param int   $product_id Product ID.
		 * @param array $booking_settings Product Booking Settings.
		 * @since 1.0
		 */
		public function bkapmts_print_hidden_lockout( $product_id, $booking_settings ) {

			if ( 'multiple' === self::bkapmts_slot_type( $product_id, $booking_settings ) ) {
				?>
				<input 
					type='hidden' 
					id='total_multiple_price_calculated' 
					name='total_multiple_price_calculated' 
					value='' 
				>
				<?php
			}
		}

		/**
		 * This function will add CSS file on the front end.
		 *
		 * @since 1.0
		 */
		public function bkapmts_front_side_scripts_css() {

			global $post;

			$product_id       = bkap_common::bkap_get_product_id( $post->ID );
			$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true );

			if ( isset( $booking_settings['booking_enable_date'] ) && 'on' === $booking_settings['booking_enable_date'] ) {
				wp_enqueue_style(
					'multiple-timeslot',
					plugins_url( 'assets/css/multiple-timeslot.css', __FILE__ ),
					'',
					BKAPMTS_VERSION,
					false
				);
			}
		}

		/**
		 * This function is for doing activity on activation of the Multiple Time Slots Addon.
		 *
		 * @since 1.0
		 */
		public function bkapmts_activate(){}

		/**
		 * This function is to add required rental settings.
		 *
		 * @since 1.0
		 */
		public function bkapmts_update_settings() {

			$bkapmts_version = get_option( 'bkap_multiple_time_slots_db_version' );

			if ( $bkapmts_version != BKAPMTS_VERSION ) {
				update_option( 'bkap_multiple_time_slots_db_version', BKAPMTS_VERSION );
			}
		}

		/**
		 * Check if WooCommerce and Booking is activated.
		 *
		 * @since 1.10
		 * @return bool $check true is Booking and WooCommerce is active else false.
		 */
		public function bkap_is_woocommerce_bkap_activated() {

			$blog_plugins = get_option( 'active_plugins', array() );
			$site_plugins = get_site_option( 'active_sitewide_plugins', array() );

			$woocommerce_basename = 'woocommerce/woocommerce.php';
			$bkap_basename        = 'woocommerce-booking/woocommerce-booking.php';

			$check = false;

			if ( ( in_array( $woocommerce_basename, $blog_plugins ) || isset( $site_plugins[ $woocommerce_basename ] ) ) ) {
				if ( in_array( $bkap_basename, $blog_plugins ) || isset( $site_plugins[ $bkap_basename ] ) ) {
					return true;
				}
			}
			return $check;
		}

		/**
		 * Including required files.
		 *
		 * @since 1.10
		 */
		public static function bkapmts_include_file() {
			include_once( 'includes/multiple-timeslots-functions.php' );
		}

		/**
		 * Load plugin text domain and specify the location of localization po & mo files
		 *
		 * @since 1.10
		 * @hook init
		 */
		public static function bkapmts_update_po_file() {
			$domain = 'multiple-time-slot';
			$locale = apply_filters( 'plugin_locale', get_locale(), $domain );
			$loaded = load_textdomain( $domain, trailingslashit( WP_LANG_DIR ) . $domain . '-' . $locale . '.mo' );

			if ( $loaded ) {
				return $loaded;
			} else {
				load_plugin_textdomain( $domain, false, basename( dirname( __FILE__ ) ) . '/languages/' );
			}
		}

		/**
		 * Function to display a notice to user if the Booking plugin is deactive.
		 *
		 * @since 1.0
		 * @hook admin_notices
		 */
		public function bkapmts_error_notice() {
			echo '<div class="error">
					<p><b>Multiple Time Slot Addon is enabled but not effective. It requires Booking & Appointment Plugin for WooCommerce in order to work.</b></p>
				 </div>';
		}

		/**
		 * Function is registeging the AJAX calls.
		 *
		 * @since 1.0
		 * @hook admin_notices
		 */
		public function bkapmts_time_load_ajax() {
			if ( ! is_user_logged_in() ) {
				add_action( 'wp_ajax_nopriv_bkap_multiple_time_slot', array( &$this, 'bkap_multiple_time_slot' ) );
			} else {
				add_action( 'wp_ajax_bkap_multiple_time_slot', array( &$this, 'bkap_multiple_time_slot' ) );
			}
		}

		/**
		 * Function adding the sub-menu under Booking menu
		 *
		 * @since 1.0
		 * @hook bkap_add_submenu
		 */
		public function bkapmts_menu() {
			$page = add_submenu_page(
				'edit.php?post_type=bkap_booking',
				__( 'Activate Multiple Timeslot License', 'multiple-time-slot' ),
				__( 'Activate Multiple Timeslot License', 'multiple-time-slot' ),
				'manage_woocommerce',
				'multiple_timeslot_license_page',
				array( &$this, 'edd_sample_license_page' )
			);
		}

		/**
		 * License Page.
		 *
		 * @since 1.0
		 * @hook bkap_add_submenu
		 */
		public function edd_sample_license_page() {
			$license = get_option( 'edd_sample_license_key_multiple_timeslot_book' );
			$status  = get_option( 'edd_sample_license_status_multiple_timeslot_book' );
			?>
			<div class="wrap">
				<h2><?php esc_html_e( 'Plugin License Options' ); ?></h2>
				<form method="post" action="options.php">
				
					<?php settings_fields( 'edd_multiple_timeslot_license' ); ?>
					
					<table class="form-table">
						<tbody>
							<tr valign="top">	
								<th scope="row" valign="top">
									<?php esc_html_e( 'License Key' ); ?>
								</th>
								<td>
									<input id="edd_sample_license_key_multiple_timeslot_book" name="edd_sample_license_key_multiple_timeslot_book" type="text" class="regular-text" value="<?php esc_attr_e( $license ); ?>" />
									<label class="description" for="edd_sample_license_key"><?php esc_html_e( 'Enter your license key' ); ?></label>
								</td>
							</tr>
							<?php if ( false !== $license ) { ?>
								<tr valign="top">	
									<th scope="row" valign="top">
										<?php esc_html_e( 'Activate License' ); ?>
									</th>
									<td>
										<?php if ( false !== $status && 'valid' === $status ) { ?>
											<span style="color:green;"><?php esc_html_e( 'active' ); ?></span>
											<?php wp_nonce_field( 'edd_sample_nonce', 'edd_sample_nonce' ); ?>
											<input type="submit" class="button-secondary" name="edd_license_deactivate" value="<?php esc_html_e( 'Deactivate License' ); ?>"/>
											<?php
										} else {
											wp_nonce_field( 'edd_sample_nonce', 'edd_sample_nonce' );
											?>
											<input type="submit" class="button-secondary" name="edd_license_activate" value="<?php esc_html_e( 'Activate License' ); ?>"/>
										<?php } ?>
									</td>
								</tr>
							<?php } ?>
						</tbody>
					</table>	
					<?php submit_button(); ?>
				
				</form>
			<?php
		}

		/**
		 * This function will return string which is to be used for the action of an AJAX
		 *
		 * @since 1.0
		 */
		public function bkapmts_slot_function() {
			 return 'bkap_multiple_time_slot';
		}

		/**
		 * This function will return string which is to be used for the action of an AJAX
		 *
		 * @param int   $product_id Product ID.
		 * @param array $booking_settings Booking Settings (optional).
		 * @since 1.0
		 */
		public function bkapmts_slot_type( $product_id, $booking_settings = array() ) {

			if ( empty( $booking_settings ) ) {
				$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true );
			}

			if ( isset( $booking_settings['booking_enable_time'] ) && 'on' === $booking_settings['booking_enable_time'] ) {
				if ( isset( $booking_settings['booking_enable_multiple_time'] ) && 'multiple' === $booking_settings['booking_enable_multiple_time'] ) {
					return 'multiple';
				}
			}
		}

		/**
		 * This function will show an option in the Booking Meta Box for Single OR Multiple timeslots
		 *
		 * @param int $product_id Product ID.
		 * @since 1.0
		 */
		public function bkapmts_show_field_settings( $product_id ) {

			$booking_settings            = get_post_meta( $product_id, 'woocommerce_booking_settings', true );
			$booking_time_slot_selection = 'none';

			if ( isset( $booking_settings['booking_enable_time'] ) && 'on' === $booking_settings['booking_enable_time'] ) {
				$booking_time_slot_selection = 'show';
			}
			?>
			<div id="booking_time_slot">
				
				<div style="max-width:30%;display:inline-block;">
					<h4>
						<label for="booking_time_slot_label"><?php esc_html_e( 'Time Slot Selection:', 'multiple-time-slot' ); ?></label>
					</h4>
				</div>
				
				<div style="max-width:60%;display:inline-block;margin-left:10%;">
					<?php
					$enable_time = '';
					if ( isset( $booking_settings['booking_enable_multiple_time'] )
						&& 'multiple' === $booking_settings['booking_enable_multiple_time']
					) {
						$enable_time  = 'checked';
						$enabled_time = '';
					}
					?>
					<input 	type="radio"
							name="booking_enable_time_radio"
							id="booking_enable_time_radio"
							value="single"
							<?php echo $enabled_time = 'checked'; ?>><?php esc_html_e( 'Single', 'multiple-time-slot' ); ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
						</input>
					<input 	type="radio"
							id="booking_enable_time_radio"
							name="booking_enable_time_radio"
							value="multiple"<?php echo $enable_time; ?>><?php esc_html_e( 'Multiple', 'multiple-time-slot' ); ?>
						</input>
				</div>

				<div style="width:10%;display:inline-block;margin-left:10%;">
					<img 	class="help_tip"
							width="16"
							height="16"
							data-tip="<?php esc_html_e( 'Enable Single to select single timeslot on product page or Enable Multiple to select multiple timeslots on product page.', 'multiple-time-slot' ); ?>"
							src="<?php echo plugins_url(); ?>/woocommerce/assets/images/help.png"/>
				</div>
			</div>
			<script type="text/javascript">
				jQuery( "#booking_enable_time").change( function() {
					if( jQuery( '#booking_enable_time' ).attr( 'checked' ) ) {
						jQuery( '#booking_time_slot' ).show();								
					} else {
						jQuery('#booking_time_slot').hide();
					}
					});
			</script>
			<?php
		}

		/**
		 * Print hidden fields to display the number of slots selected
		 *
		 * @param array $booking_settings Product Booking Setting.
		 * @param int   $product_id Product ID.
		 * @since 1.0.
		 */
		public function bkapmts_product_settings_save( $booking_settings, $product_id ) {

			if ( isset( $_POST['booking_enable_time_radio'] ) && 'single' === $_POST['booking_enable_time_radio'] ) {
					$enable_multiple_time = 'single';
			} else if ( isset( $_POST['booking_enable_time_radio'] ) && 'multiple' === $_POST['booking_enable_time_radio'] ) {
				$enable_multiple_time = 'multiple';
			}
			$booking_settings['booking_enable_multiple_time'] = $enable_multiple_time;

			return $booking_settings;
		}

		/**
		 * Print hidden fields to display the number of slots selected
		 *
		 * @param int   $product_id Product ID.
		 * @param array $booking_settings Product Booking Setting.
		 * @since 1.0
		 */
		public function bkapmts_print_fields( $product_id, $booking_settings ) {

			if ( isset( $booking_settings['booking_enable_multiple_time'] ) && 'multiple' === $booking_settings['booking_enable_multiple_time'] ) {
				print( '<input type="hidden" id="wapbk_number_of_timeslots" name="wapbk_number_of_timeslots" value="0"/>' );
			}
		}

		/**
		 * Calculate the pridct when time slots are selected
		 *
		 * @param int    $product_id Product ID.
		 * @param array  $booking_settings Product Booking Setting.
		 * @param obj    $product Product Object.
		 * @param string $booking_date Selected Booking Date.
		 * @param int    $variation_id Variation ID.
		 * @param string $gf_options Options of the gravity form.
		 * @param int    $resource_id Resource ID.
		 * @since 1.0
		 */
		public function bkapmts_show_multiple_time_price( $product_id, $booking_settings, $product, $booking_date, $variation_id, $gf_options, $resource_id ) {

			if ( 'multiple' == self::bkapmts_slot_type( $product_id, $booking_settings ) ) {

				$product_type = $product->get_type();

				if ( ! isset( $_POST['price'] ) || ( isset( $_POST['price'] ) && $_POST['price'] == 0 ) ) {

					$wpml_multicurreny_enabled = 'no';

					if ( function_exists( 'icl_object_id' ) ) {

						global $woocommerce_wpml, $woocommerce;

						if ( isset( $woocommerce_wpml->settings['enable_multi_currency'] ) && $woocommerce_wpml->settings['enable_multi_currency'] == '2' ) {
							$custom_post = bkap_common::bkap_get_custom_post( $product_id, $variation_id, $product_type );
							if ( 'variable' === $product_type ) {
								if ( $custom_post == 1 ) {
									$client_currency = $woocommerce->session->get( 'client_currency' );
									if ( $client_currency != '' && $client_currency != get_option( 'woocommerce_currency' ) ) {
										$price = get_post_meta( $variation_id, '_price_' . $client_currency, true );
										$wpml_multicurreny_enabled = 'yes';
									}
								}
							} elseif ( 'simple' === $product_type ) {
								if ( $custom_post == 1 ) {
									$client_currency = $woocommerce->session->get( 'client_currency' );
									if ( $client_currency != '' && $client_currency != get_option( 'woocommerce_currency' ) ) {
										$price = get_post_meta( $product_id, '_price_' . $client_currency, true );
										$wpml_multicurreny_enabled = 'yes';
									}
								}
							}
						}
					}

					if ( $wpml_multicurreny_enabled == 'no' ) {
						if ( $product_type == 'variable' ) {
							$price = get_post_meta( $variation_id, '_sale_price', true );
							if ( $price == '' ) {
								$price = get_post_meta( $variation_id, '_regular_price', true );
							}
						} elseif ( $product_type == 'simple' ) {
							$price = get_post_meta( $product_id, '_sale_price', true );
							if ( $price == '' ) {
								$price = get_post_meta( $product_id, '_regular_price', true );
							}
						}
					}
				} else {
					$prices = '';
					if ( is_array( $_POST['price'] ) ) {
						$price  = array_sum( $_POST['price'] ) * $_POST['quantity'];
						$prices = implode( ',', $_POST['price'] );
					} else {
						$price = $_POST['price'];
					}
				}

				$wp_send_json = array();

				if ( function_exists( 'is_bkap_deposits_active' ) && is_bkap_deposits_active() ) {
					$_POST['price'] = $price;
				} else {

					/**
					 * Save the actual Bookable amount, as a raw amount
					 * If Multi currency is enabled, convert the amount before saving it
					 */
					$total_price = $price * $_POST['quantity'];
					if ( function_exists( 'icl_object_id' ) ) {
						$custom_post = bkap_common::bkap_get_custom_post( $product_id, $variation_id, $product_type );
						if ( $custom_post == 0 ) {
							$total_price = apply_filters( 'wcml_raw_price_amount', $price );
						}
					}

					$wp_send_json['total_price_calculated']          = $total_price;
					$wp_send_json['bkap_price_charged']              = $total_price;
					$wp_send_json['total_multiple_price_calculated'] = $prices;

					$wc_price_args   = bkap_common::get_currency_args();
					$formatted_price = wc_price( $total_price, $wc_price_args );

					// display the price on the front end product page.
					$display_price = get_option( 'book_price-label' ) . ' ' . $formatted_price;
					$wp_send_json['bkap_price'] = addslashes( $display_price );
					wp_send_json( $wp_send_json );
				}
			}
		}

		/**
		 * Ajax fn run when time slots are selected
		 */
		public function bkap_multiple_time_slot() {

			$current_date         = $_POST['current_date'];
			$current_date_ymd     = date( 'Y-m-d', strtotime( $current_date ) );
			$post_id              = $_POST['post_id'];
			$time_drop_down       = bkap_booking_process::get_time_slot( $current_date, $post_id );
			$time_drop_down_array = explode( '|', $time_drop_down );
			$checkbox             = '<label>' . get_option( 'book_time-label' ) . ': </label><br>';
			$i                    = 0;
			$global_settings      = bkap_global_setting();
			$time_format_to_show  = bkap_common::bkap_get_time_format( $global_settings );
			$timezone_check       = bkap_timezone_check( $global_settings ); // Check if the timezone setting is enabled.

			if ( $timezone_check /*&& ( isset( $_POST['bkap_page'] ) && $_POST['bkap_page'] != 'bkap_post' )*/ ) {
				$gmt_offset  = get_option( 'gmt_offset' );
				$gmt_offset  = $gmt_offset * 60 * 60;
				$bkap_offset = $_COOKIE['bkap_offset'];
				$bkap_offset = $bkap_offset * 60;
				$offset      = $bkap_offset - $gmt_offset;
				date_default_timezone_set( bkap_booking_get_timezone_string() );
			}

			foreach ( $time_drop_down_array as $k => $v ) {
				$i++;
				if ( '' != $v ) {
					$store_time = sprintf( __( 'Store time is %s %s', 'multiple-time-slot' ), $current_date_ymd, $v );
					$vexplode   = explode( ' - ', $v );
					if ( $timezone_check /*&& ( isset( $_POST['bkap_page'] ) && $_POST['bkap_page'] != 'bkap_post' )*/ ) {
						$from_time = date( $time_format_to_show, $offset + strtotime( $vexplode[0] ) );
						$to_time   = date( $time_format_to_show, $offset + strtotime( $vexplode[1] ) );
						$v         = $from_time . ' - ' . $to_time;
					}

					$checkbox .= "<div title='" . $store_time . "'><label class=\"mul-button\"><input type='checkbox' id='timeslot_" . $i . "' name='time_slot[]' value='" . $v . "' onClick='multi_timeslot(this)'><b>" . $v . '</b></input><br></label></div>';
				}
			}

			if ( $timezone_check /*&& ( isset( $_POST['bkap_page'] ) && $_POST['bkap_page'] != 'bkap_post' )*/ ) {
				date_default_timezone_set( 'UTC' );
			}

			$wp_send_json['bkap_time_count']    = $i;
			$wp_send_json['bkap_time_dropdown'] = $checkbox;
			wp_send_json( $wp_send_json );
		}

		/**
		 * Script to call for price calculation based on the selected timeslots
		 *
		 * @since 1.0
		 */
		public function bkapmts_scripts() {
			?>
			<script type="text/javascript">
				function multi_timeslot( chk ) {

					var values = new Array();
					jQuery.each( jQuery( "input[name='time_slot[]']:checked" ), function() {
						values.push( jQuery(this).val() );
					});

					var slots_selected = values.length;
					jQuery("#wapbk_number_of_timeslots").val( slots_selected );
					// call the single day price calculation fn as the price needs to be calculated whenever a time slot is selected/de-selected
					bkap_single_day_price();

					var sold_individually = jQuery( "#wapbk_sold_individually" ).val();
					if ( slots_selected > 0 ) {

						jQuery( ".single_add_to_cart_button" ).show();
						if ( sold_individually == "yes" ) {
							jQuery( ".quantity" ).hide();
						} else {
							jQuery( ".quantity" ).show();
						}
					} else {
						jQuery( ".single_add_to_cart_button" ).hide();
						jQuery( ".quantity" ).hide()
					}
				}		
			</script>
			<?php
		}

		/**
		 * Calculate prices when products are added to the cart.
		 *
		 * @param array $cart_arr Cart Item Array.
		 * @param int   $product_id Product ID.
		 * @param int   $variation_id Variation ID.
		 *
		 * @since 1.0
		 */
		public function bkapmts_add_cart_item_data( $cart_arr, $product_id, $variation_id ) {

			$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true );
			$time_slots       = '';
			$product          = wc_get_product( $product_id );
			$product_type     = $product->get_type();

			if ( isset( $booking_settings['booking_enable_time'] ) && 'on' === $booking_settings['booking_enable_time'] ) {
				if ( isset( $booking_settings['booking_enable_multiple_time'] ) && 'multiple' === $booking_settings['booking_enable_multiple_time'] ) {
					$time_multiple_disp = $_POST['time_slot'];
					$i                  = 0;
					foreach ( $time_multiple_disp as $k => $v ) {
						$time_slots .= '<br>' . $v;
						$i++;
					}
					$cart_arr['time_slot'] = $time_slots;
				}
			}
			return $cart_arr;
		}

		/**
		 * Add the multiple time slots on the cart and checkout page
		 *
		 * @param array $other_data Other Data.
		 * @param array $cart_item Cart Item Array.
		 *
		 * @since 1.0
		 */
		public function bkapmts_get_item_data( $other_data, $cart_item ) {
			if ( isset( $cart_item['bkap_booking'] ) ) :
				foreach ( $cart_item['bkap_booking'] as $booking ) :

					$booking_settings = get_post_meta( $cart_item['product_id'], 'woocommerce_booking_settings', true );
					$saved_settings   = json_decode( get_option( 'woocommerce_booking_global_settings' ) );

					if ( isset( $saved_settings ) && '' != $saved_settings->booking_time_format ) {
						$time_format = $saved_settings->booking_time_format;
					} else {
						$time_format = '';
					}

					if ( '' == $time_format || 'NULL' == $time_format ) {
						$time_format = '12';
					}

					$time_slot_to_display = '';
					if ( isset( $booking['time_slot'] ) ) {
						$time_slot_to_display = $booking['time_slot'];
					}

					if ( isset( $booking_settings['booking_enable_time'] ) && 'on' === $booking_settings['booking_enable_time'] ) {
						if ( isset( $booking_settings['booking_enable_multiple_time'] ) && 'multiple' === $booking_settings['booking_enable_multiple_time'] ) {

							$time_exploded = explode( '<br>', $time_slot_to_display );
							array_shift( $time_exploded );
							$time_slot = '';

							foreach ( $time_exploded as $k => $v ) {

								$time_explode = explode( '-', $v );
								if ( '' == $time_format || 'NULL' == $time_format ) {
									$time_format = '12';
								}

								if ( '12' === $time_format ) {
									$from_time = date( 'h:i A', strtotime( $time_explode[0] ) );
									if ( isset( $time_explode[1] ) ) {
										$to_time = date( 'h:i A', strtotime( $time_explode[1] ) );
									} else {
										$to_time = '';
									}
								} else {
									$from_time = date( 'H:i', strtotime( $time_explode[0] ) );
									if ( isset( $time_explode[1] ) ) {
										$to_time = date( 'H:i', strtotime( $time_explode[1] ) );
									} else {
										$to_time = '';
									}
								}
								if ( '' != $to_time ) {
									$time_slot .= '<br>' . $from_time . ' - ' . $to_time;
								} else {
									$time_slot .= '<br>' . $from_time;
								}
							}

							$name         = get_option( 'book_item-cart-time' );
							$other_data[] = array(
								'name'    => $name,
								'display' => $time_slot,
							);
						}
					}
				endforeach;
			endif;
			return $other_data;
		}

		/**
		 * Add time slots as order item meta
		 *
		 * @param array $values Cart Item.
		 * @param obj   $order Order Object.
		 * @since 1.0
		 */
		public function bkapmts_order_item_meta( $values, $order ) {
			global $wpdb;

			$product_id       = $values['product_id'];
			$quantity         = $values['quantity'];
			$booking          = $values['bkap_booking'];
			$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true );
			$saved_settings   = json_decode( get_option( 'woocommerce_booking_global_settings' ) );

			if ( isset( $saved_settings ) && $saved_settings->booking_time_format != '' ) {
				$time_format = $saved_settings->booking_time_format;
			} else {
				$time_format = '';
			}

			if ( '' == $time_format || 'NULL' == $time_format ) {
				$time_format = '12';
			}

			$date                 = '';
			$time_slot_to_display = '';
			// This variable contains all the time slots in the G:i format to be saved in the hidden field in the item meta table.
			$meta_data_format = '';
			$order_item_id    = $order->order_item_id;
			$order_id         = $order->order_id;

			if ( isset( $booking_settings['booking_enable_time'] ) && $booking_settings['booking_enable_time'] == 'on' ) {

				if ( isset( $booking_settings['booking_enable_multiple_time'] ) && $booking_settings['booking_enable_multiple_time'] == 'multiple' ) {
					if ( array_key_exists( 'date', $booking[0] ) && $booking[0][ 'date' ] != "" ) {
						$date = $booking[0]['date'];
						$name = get_option( 'book_item-meta-date' );
						wc_add_order_item_meta( $order_item_id, $name, sanitize_text_field( $date , true ) );
					}

					if ( array_key_exists( 'hidden_date', $booking[0] ) && $booking[0]['hidden_date'] != "" ) {
						// save the date in Y-m-d format.
						$date_booking = date( 'Y-m-d', strtotime( $booking[0]['hidden_date'] ) );
						wc_add_order_item_meta( $order_item_id, '_wapbk_booking_date', sanitize_text_field( $date_booking, true ) );
					}

					if ( '' != $booking[0]['time_slot'] ) {
						$time_slot     = $booking[0]['time_slot'];
						$hidden_date   = $booking[0]['hidden_date'];
						$time_exploded = explode( '<br>', $time_slot );
						array_shift( $time_exploded );

						foreach ( $time_exploded as $k => $v ) {
							$time_explode = explode( '-', $v );
							$from_time    = trim( $time_explode[0] );

							if ( isset( $time_explode[1] ) ) {
								$to_time = trim( $time_explode[1] );
							} else {
								$to_time = '';
							}

							if ( $time_format == '12' ) {
								$from_time = date( 'h:i A', strtotime( $time_explode[0] ) );
								if ( isset( $time_explode[1] ) ) {
									$to_time = date( 'h:i A', strtotime( $time_explode[1] ) );
								} else {
									$to_time = '';
								}
							}

							if ( $to_time != '' ) {
								$time_slot_to_display .= $from_time . ' - ' . $to_time . ',';
							} else {	
								$time_slot_to_display .= $from_time . ',';
							}

							$date_query        = date( 'Y-m-d', strtotime( $booking[0]['hidden_date'] ) );
							$query_from_time   = date( 'G:i', strtotime($time_explode[0] ) );
							$meta_data_format .= $query_from_time;

							if ( isset( $time_explode[1] ) ) {
								$query_to_time     = date( 'G:i', strtotime( $time_explode[1] ) );
								$meta_data_format .= ' - ' . $query_to_time . ',';
							} else {
								$meta_data_format .= ',';
							}

							if ( $query_to_time != '' ) {

								$query = "UPDATE `".$wpdb->prefix."booking_history`
											SET available_booking = available_booking - " . $quantity . "
											WHERE post_id = '" . $product_id . "' AND
											start_date = '" . $date_query . "' AND
											from_time = '" . $query_from_time . "' AND
											to_time = '" . $query_to_time . "' AND
											total_booking > 0";
								$wpdb->query( $query );

								$order_select_query = "SELECT * FROM `" . $wpdb->prefix . "booking_history`
														WHERE post_id = '" . $product_id . "' AND
														start_date = '" . $date_query . "' AND
														from_time = '" . $query_from_time . "' AND
														to_time = '" . $query_to_time . "' AND
														status = ''";
								$order_results = $wpdb->get_results( $order_select_query );

								foreach ( $order_results as $k => $v ) {
									$details[ $product_id ][] = $v;
								}
							} else {
								$query = "UPDATE `" . $wpdb->prefix . "booking_history`
											SET available_booking = available_booking - " . $quantity . "
											WHERE post_id = '" . $product_id . "' AND
											start_date = '" . $date_query . "' AND
											from_time = '" . $query_from_time . "' AND
											total_booking > 0";
								$wpdb->query( $query );

								$order_select_query = "SELECT * FROM `" . $wpdb->prefix . "booking_history`
														WHERE post_id = '" . $product_id . "' AND
														start_date = '" . $date_query . "' AND
														from_time = '" . $query_from_time . "' AND
														status = ''";
								$order_results = $wpdb->get_results( $order_select_query );

								foreach ( $order_results as $k => $v ) {
									$details[ $product_id ][] = $v;
								}
							}

							$i = 0;

							foreach ( $order_results as $k => $v ) {
								$booking_id  = $order_results[ $i ]->id;
								$order_query = "INSERT INTO `" . $wpdb->prefix . "booking_order_history`
												(order_id,booking_id)
												VALUES (
												'" . $order_id . "',
												'" . $booking_id . "' )";
								$wpdb->query( $order_query );
								$i++;
							}
						}
						$time_slot_to_display = trim( $time_slot_to_display, ',' );
						$meta_data_format     = trim( $meta_data_format, ',' );
						wc_add_order_item_meta( $order_item_id, get_option( 'book_item-meta-time' ), $time_slot_to_display, true );
						wc_add_order_item_meta( $order_item_id, '_wapbk_time_slot', $meta_data_format, true );
					}

					$lockout_settings = '';
					if ( isset( $booking_settings['booking_time_settings'][ $hidden_date ] ) ) {
						$lockout_settings = $booking_settings['booking_time_settings'][ $hidden_date ];
					}

					if ( $lockout_settings == '' ) {
						$week_day         = date( 'l', strtotime( $hidden_date ) );
						$weekday          = array_search( $week_day, $this->weekdays );
						$lockout_settings = $booking_settings['booking_time_settings'][ $weekday ];
					}

					$from_lockout_time = explode( ':', $query_from_time );
					$from_hours        = $from_lockout_time[0];
					$from_minute       = $from_lockout_time[1];

					if ( $query_to_time != '') {
						$to_lockout_time = explode( ':', $query_to_time );
						$to_hours        = $to_lockout_time[0];
						$to_minute       = $to_lockout_time[1];
					} else {
						$to_hours  = '';
						$to_minute = '';
					}

					foreach ( $lockout_settings as $l_key => $l_value ) {
						if ( $l_value['from_slot_hrs'] == $from_hours
							&& $l_value['from_slot_min'] == $from_minute
							&& $l_value['to_slot_hrs'] == $to_hours
							&& $l_value['to_slot_min'] == $to_minute
						) {
							$global_timeslot_lockout = $l_value['global_time_check'];
						}
					}

					if ( $global_settings->booking_global_timeslot == 'on' || $global_timeslot_lockout == 'on' ) {
						$args    = array( 'post_type' => 'product', 'posts_per_page' => -1 );
						$product = query_posts( $args );

						$product_ids = array();
						foreach ( $product as $k => $v ) {
							$product_ids[] = $v->ID;
						}

						foreach ( $product_ids as $k => $v ) {

							$booking_settings = get_post_meta( $v, 'woocommerce_booking_settings', true );

							if ( isset( $booking_settings['booking_enable_time'] ) && $booking_settings['booking_enable_time'] == 'on' ) {

								if ( ! array_key_exists( $v, $details ) ) {

									foreach ( $details as $key => $val ) {

										foreach( $val as $v_key => $v_val ) {

											$start_date = $v_val->start_date;
											$from_time  = $v_val->from_time;
											$to_time    = $v_val->to_time;

											if ( $to_time != "" ) {
												$query = "UPDATE `" . $wpdb->prefix . "booking_history`
															SET available_booking = available_booking - " . $quantity . "
															WHERE post_id = '" . $v . "' AND
															start_date = '" . $date_query . "' AND
															from_time = '" . $from_time . "' AND
															to_time = '" . $to_time . "' ";
												$updated = $wpdb->query( $query );

												if ( $updated == 0 ) {
													if ( $v_val->weekday == '' ) {
														$week_day = date( 'l', strtotime( $date_query ) );
														$weekday  = array_search( $week_day, $this->weekdays );
													} else {
														$weekday = $v_val->weekday;
													}
													$query = "SELECT * FROM `" . $wpdb->prefix . "booking_history`
																WHERE post_id = '" . $v . "'
																AND weekday = '" . $weekday . "'
																AND start_date = '0000-00-00'";
													$results = $wpdb->get_results( $query );

													if ( ! $results ) {
														break;
													} else {

														foreach ( $results as $r_key => $r_val ) {
															if ( $from_time == $r_val->from_time && $to_time == $r_val->to_time ) {

																$available_booking = $r_val->available_booking - $quantity;
																$query_insert = "INSERT INTO `" . $wpdb->prefix . "booking_history`
																	(post_id,weekday,start_date,from_time,to_time,total_booking,available_booking)
																	VALUES (
																'" . $v . "',
																'" . $weekday . "',
																'" . $start_date . "',
																'" . $r_val->from_time . "',
																'" . $r_val->to_time . "',
																'" . $r_val->available_booking . "',
																'" . $available_booking . "' )";
																$wpdb->query( $query_insert );
															} else {
																$query_insert = "INSERT INTO `" . $wpdb->prefix . "booking_history`
																(post_id,weekday,start_date,from_time,to_time,total_booking,available_booking)
																VALUES (
																'" . $v . "',
																'" . $weekday . "',
																'" . $start_date . "',
																'" . $r_val->from_time . "',
																'" . $r_val->to_time . "',
																'" . $r_val->available_booking . "',
																'" . $r_val->available_booking . "' )";
																$wpdb->query( $query_insert );
															}
														}
													}
												}
											} else {
												$query = "UPDATE `" . $wpdb->prefix . "booking_history`
															SET available_booking = available_booking - " . $quantity . "
															WHERE post_id = '" . $v . "' AND
															start_date = '" . $date_query . "' AND
															from_time = '" . $from_time . "'
															AND to_time = ''";
												$updated = $wpdb->query( $query );

												if ( $updated == 0 ) {

													if ( $v_val->weekday == '' ) {
														$week_day = date( 'l',strtotime( $date_query ) );
														$weekday  = array_search( $week_day, $this->weekdays );
													} else {
														$weekday = $v_val->weekday;
													}

													$query = "SELECT * FROM `" . $wpdb->prefix . "booking_history`
																WHERE post_id = '" . $v . "'
																AND weekday = '" . $weekday . "'
																AND to_time = '' 
																AND start_date = '0000-00-00'";
													$results = $wpdb->get_results( $query );

													if ( !$results ) break;
													else {
														foreach( $results as $r_key => $r_val ) {

															if ( $from_time == $r_val->from_time ) {
																$available_booking = $r_val->available_booking - $quantity;
																$query_insert = "INSERT INTO `" . $wpdb->prefix . "booking_history`
																	(post_id,weekday,start_date,from_time,total_booking,available_booking)
																	VALUES (
																	'" . $v . "',
																	'" . $weekday . "',
																'" . $start_date . "',
																'" . $r_val->from_time . "',
																'" . $r_val->available_booking . "',
																'" . $available_booking . "' )";
																$wpdb->query( $query_insert );
															} else {
																$query_insert = "INSERT INTO `" . $wpdb->prefix . "booking_history`
																(post_id,weekday,start_date,from_time,total_booking,available_booking)
																VALUES (
																'" . $v . "',
																'" . $weekday . "',
																'" . $start_date . "',
																'" . $r_val->from_time . "',
																'" . $r_val->available_booking . "',
																'" . $r_val->available_booking . "' )";
																$wpdb->query( $query_insert );
															}
														}
													}
												}
											}
										}
									}
								}
							}
						}
					}
				}
			}
		}

		/**
		 * Quantity check for multiple time slots
		 */
		public function mts_quantity_check( $product_id, $booking_date, $booking_time_slot, $quantity ) {

			global $woocommerce, $wpdb;

			$quantity_check_pass = 'yes';
			$booking_settings    = get_post_meta( $product_id, 'woocommerce_booking_settings', true );
			$saved_settings      = json_decode( get_option( 'woocommerce_booking_global_settings' ) );

			if ( isset( $saved_settings ) && $saved_settings->booking_time_format != '' ) {
				$time_format = $saved_settings->booking_time_format;
			} else {
				$time_format = '';
			}

			if ( $time_format == '' || $time_format == 'NULL' ) {
				$time_format = '12';
			}

			if ( $booking_settings['booking_enable_time'] == 'on' ) {

				if ( $booking_settings['booking_enable_multiple_time'] == 'multiple' ) {
					$time_exploded = explode( '<br>', $booking_time_slot );

					foreach ( $time_exploded as $k => $v ) {
						if ( $v != '' ) {
							$time_explode 	= explode( '-', $v );

							$timezone_check = bkap_timezone_check( $saved_settings );
							if ( $timezone_check ) {
								$offset       = bkap_get_offset( $_COOKIE['bkap_offset'] );
								$from_time    = bkap_time_convert_asper_timezone( $time_explode[0], $offset );              
								$to_time      = isset( $time_explode[1] ) ? bkap_time_convert_asper_timezone( $time_explode[1], $offset ) : '';
								$booking_date = bkap_time_convert_asper_timezone( $booking_date . ' ' . $time_explode[0], $offset, 'Y-m-d' );
								// Converting booking date to store timezone for getting correct availability.
							} else {
								$from_time = date( 'H:i', strtotime( $time_explode[0] ) );
								$to_time   = isset( $time_explode[1] ) ? date( 'H:i', strtotime( $time_explode[1] ) ) : '';
							}

							if ( $to_time != '' ) {
								$query   = "SELECT total_booking, available_booking, start_date FROM `" . $wpdb->prefix . "booking_history`
											WHERE post_id = '" . $product_id . "'
											AND start_date = '" . $booking_date . "'
											AND TIME_FORMAT( from_time, '%H:%i' ) = '" . $from_time . "'
											AND TIME_FORMAT( to_time, '%H:%i' ) = '" . $to_time . "' ";
								$results = $wpdb->get_results( $query );
							} else {
								$query = "SELECT total_booking, available_booking, start_date FROM `" . $wpdb->prefix . "booking_history`
											WHERE post_id = '" . $product_id . "'
											AND start_date = '" . $booking_date . "'
											AND from_time = '" . $from_time . "'";
								$results = $wpdb->get_results( $query );
							}
							if ( ! $results ) break;
							else {
								$post_title = get_post( $product_id );
								if ( $booking_time_slot != '' ) {
									// if current format is 12 hour format, then convert the times to 24 hour format to check in database.
									if ( $time_format == '12' ) {
										$from_time = date( 'h:i A', strtotime( $time_explode[0] ) );
										if ( isset( $time_explode[1] ) ) {
											$to_time = date( 'h:i A', strtotime( $time_explode[1] ) );
										} else {
											$to_time = '';
										}
										if ( $to_time != '' ) {
											$time_slot_to_display = $from_time . ' - ' . $to_time;
										} else {
											$time_slot_to_display = $from_time;
										}
									} else if ( $time_format == '24' ) {
										$from_time = date( 'H:i ', strtotime( $time_explode[0] ) );
										if ( isset( $time_explode[1] ) ) {
											$to_time = date( 'H:i ', strtotime( $time_explode[1] ) );
										} else {
											$to_time = '';
										}
										if ( $to_time != '' ) {
											$time_slot_to_display = $from_time . ' - ' . $to_time;
										} else {
											$time_slot_to_display = $from_time;
										}
									}

									if ( $results[0]->available_booking > 0 && $results[0]->available_booking < $quantity ) {

										$message = sprintf( __( '%s is available only for %d spot on %s', 'multiple-time-slot' ), $post_title->post_title, $results[0]->available_booking, $time_slot_to_display );
										wc_add_notice( $message, $notice_type = 'error' );
										$quantity_check_pass = 'no';
									} elseif ( $results[0]->total_booking > 0 && $results[0]->available_booking == 0 ) {
										$message = sprintf( __( 'Bookings are full for %s on  %s. Please choose some other date and time to continue.', 'multiple-time-slot' ), $post_title->post_title, $time_slot_to_display );
										wc_add_notice( $message, $notice_type = 'error');
										$quantity_check_pass = 'no';
									}
								}
							}
						}
					}
				}
			}
			return $quantity_check_pass;
		}

		/**
		 * Quantity check on the product page
		 *
		 * @param array $POST POST Data.
		 * @param int   $post_id Product ID.
		 * @since 1.0
		 */
		public function bkapmts_quantity_prod( $POST, $post_id ) {

			global $woocommerce,$wpdb;

			$date_check = date( 'Y-m-d', strtotime( $POST['wapbk_hidden_date'] ) );

			if ( isset( $POST['quantity'] ) ) {
				$item_quantity = $POST['quantity'];
			} else {
				$item_quantity = 1;
			}

			$time_slot_str = '';

			foreach ( $POST['time_slot'] as $k => $v ) {
				$time_slot_str .= $v . '<br>';
			}
			// check if the same product has been added to the cart for the same dates.
			foreach ( $woocommerce->cart->get_cart() as $cart_item_key => $values ) {
				$booking            = $values['bkap_booking'];
				$quantity           = $values['quantity'];
				$product_id         = $values['product_id'];
				$prod_time_slot_str = '';

				if ( isset( $booking[0]['time_slot'] ) ) {

					$prod_time_slot     = explode( '<br>', $booking[0]['time_slot'] );
					$prod_time_slot_str = '';
					foreach ( $prod_time_slot as $k => $v ) {
						if ( $v != '' ) {
							$prod_time_slot_str .= $v . '<br>';
						}
					}
				}
				if ( $product_id == $post_id
					&& $booking[0]['hidden_date'] == $POST['wapbk_hidden_date']
					&& $prod_time_slot_str == $time_slot_str
				) {
					$item_quantity += $quantity;
				}
			}

			$quantity_check_pass = Bkap_Multiple_Time_Slots::mts_quantity_check( $post_id, $date_check, $time_slot_str, $item_quantity );
			return $quantity_check_pass;
		}

		/**
		 * Quantity check on the cart and checkout page
		 *
		 * @param array $value Cart Item Data.
		 * @since 1.0
		 */
		public function bkapmts_quantity_check( $value ) {

			$bkap_booking        = $value['bkap_booking'][0];
			$date_check          = date( 'Y-m-d', strtotime( $bkap_booking['hidden_date'] ) );
			$quantity_check_pass = Bkap_Multiple_Time_Slots::mts_quantity_check( $value['product_id'], $date_check, $bkap_booking['time_slot'], $value['quantity'] );
		}

		/**
		 * Woocommerce cancel order
		 *
		 * @param int   $order_id Order ID.
		 * @param array $item_value Cart Item Data.
		 * @param int   $booking_id Booking ID.
		 * @since 1.0
		 */
		public function bkapmts_cancel_order( $order_id, $item_value, $booking_id ) {

			global $wpdb;
			$product_id       = $item_value['product_id'];
			$quantity         = $item_value['qty'];
			$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true );

			if ( 'on' === $booking_settings['booking_enable_time'] ) {
				if ( 'multiple' === $booking_settings['booking_enable_multiple_time'] ) {

					$select_data_query = "SELECT * FROM `" . $wpdb->prefix . "booking_history`
											WHERE id='" . $booking_id . "'";
					$results_data      = $wpdb->get_results( $select_data_query );

					$j = 0;

					foreach ( $results_data as $k => $v ) {

						$start_date = $results_data[ $j ]->start_date;
						$from_time  = $results_data[ $j ]->from_time;
						$to_time    = $results_data[ $j ]->to_time;

						if ( $from_time != '' && $to_time != '' || $from_time != '' ) {
							if ( $to_time != '' ) {
								$query = "UPDATE `" . $wpdb->prefix . "booking_history`
											SET available_booking = available_booking + " . $quantity . "
											WHERE 
											id = '" . $booking_id . "' AND
											start_date = '" . $start_date . "' AND
											from_time = '" . $from_time . "' AND
											to_time = '" . $to_time . "' AND 
											post_id = '" . $product_id . "'";
							} else {
								$query = "UPDATE `" . $wpdb->prefix . "booking_history`
											SET available_booking = available_booking + " . $quantity . "
											WHERE 
											id = '" . $booking_id . "' AND
											start_date = '" . $start_date . "' AND
											from_time = '" . $from_time . "' AND 
											post_id = '" . $product_id . "'";
							}
							$wpdb->query( $query );
						}
						$j++;
					}
				}
			}
		}
	} // end of class.
	$bkap_multiple_time_slots = new Bkap_Multiple_Time_Slots();
}
?>