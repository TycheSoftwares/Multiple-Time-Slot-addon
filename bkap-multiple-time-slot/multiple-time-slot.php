<?php
/*
 Plugin Name: Woocommerce Booking Multiple Time Slot Addon
Plugin URI: http://www.tychesoftwares.com/store/premium-plugins/bkap-multiple-time-slot-addon
Description: This addon to the Woocommerce Booking and Appointment Plugin lets you select multiple timeslots on a date for each product on the website.
Version: 1.1
Author: Ashok Rane
Author URI: http://www.tychesoftwares.com/
*/

global $MultipleTimeslotUpdateChecker;
$MultipleTimeslotUpdateChecker = '1.1';

// this is the URL our updater / license checker pings. This should be the URL of the site with EDD installed
define( 'EDD_SL_STORE_URL_MULTIPLE_TIMESLOT_BOOK', 'http://www.tychesoftwares.com/' ); // IMPORTANT: change the name of this constant to something unique to prevent conflicts with other plugins using this system

// the name of your product. This is the title of your product in EDD and should match the download title in EDD exactly
define( 'EDD_SL_ITEM_NAME_MULTIPLE_TIMESLOT_BOOK', 'Multiple Time Slot addon for Woocommerce Booking and Appointment Plugin' ); // IMPORTANT: change the name of this constant to something unique to prevent conflicts with other plugins using this system

if( !class_exists( 'EDD_MULTIPLE_TIMESLOT_BOOK_Plugin_Updater' ) ) {
	// load our custom updater if it doesn't already exist
	include( dirname( __FILE__ ) . '/plugin-updates/EDD_MULTIPLE_TIMESLOT_BOOK_Plugin_Updater.php' );
}

// retrieve our license key from the DB
$license_key = trim( get_option( 'edd_sample_license_key_print_ticket_book' ) );

// setup the updater
$edd_updater = new EDD_MULTIPLE_TIMESLOT_BOOK_Plugin_Updater( EDD_SL_STORE_URL_MULTIPLE_TIMESLOT_BOOK, __FILE__, array(
		'version' 	=> '1.1', 		// current version number
		'license' 	=> $license_key, 	// license key (used get_option above to retrieve from DB)
		'item_name' => EDD_SL_ITEM_NAME_MULTIPLE_TIMESLOT_BOOK, 	// name of this plugin
		'author' 	=> 'Ashok Rane'  // author of this plugin
)
);

function is_bkap_multi_time_active() {
	if (is_plugin_active('bkap-multiple-time-slot/multiple-time-slot.php')) {
		return true;
	}
	else {
		return false;
	}
}
//register_uninstall_hook( __FILE__, 'bkap_multiple_time_slot_delete');

//if (is_woocommerce_active())
{
	/**
	 * Localisation
	 **/
	load_plugin_textdomain('multiple-time-slot', false, dirname( plugin_basename( __FILE__ ) ) . '/');

	/**
	 * multiple_time_slot class
	 **/
	if (!class_exists('multiple_time_slot')) {

		class multiple_time_slot {

			public function __construct() {
				
				$this->weekdays = array('booking_weekday_0' => 'Sunday',
						'booking_weekday_1' => 'Monday',
						'booking_weekday_2' => 'Tuesday',
						'booking_weekday_3' => 'Wednesday',
						'booking_weekday_4' => 'Thursday',
						'booking_weekday_5' => 'Friday',
						'booking_weekday_6' => 'Saturday'
				);
				// Initialize settings
				register_activation_hook( __FILE__, array(&$this, 'multiple_time_slot_activate'));
				// used to add new settings on the product page booking box
				add_action('bkap_after_time_enabled', array(&$this, 'show_field_settings'));
				add_filter('bkap_save_product_settings', array(&$this, 'product_settings_save'), 10, 2);
				add_filter('bkap_function_slot', array(&$this, 'slot_function'),10,1);
				add_filter('bkap_slot_type', array(&$this, 'slot_type'),10,1);
				add_filter('bkap_addon_add_cart_item_data', array(&$this, 'multiple_time_add_cart_item_data'), 15, 3);
				add_filter('bkap_get_cart_item_from_session', array(&$this, 'multiple_time_get_cart_item_from_session'),10,2);
				add_filter('bkap_get_item_data', array(&$this, 'multiple_time_get_item_data'), 10, 2 );
			//	add_action('bkap_update_booking_history', array(&$this, 'multiple_time_order_item_meta'), 10,2);
				add_action('bkap_update_booking_history', array(&$this, 'multiple_time_order_item_meta'), 50,2);
			//	add_action('bkap_validate_on_checkout', array(&$this, 'bkap_quantity_check'),10,1);
				// Validate on cart and checkout page
				add_action('bkap_validate_cart_items', array(&$this, 'multiple_time_quantity_check'),10,1);
				// Validation on the product page
				add_action('bkap_validate_add_to_cart',array(&$this,'multiple_time_quantity_prod'),10,2);
				add_action('bkap_order_status_cancelled', array(&$this, 'bkap_cancel_order'),10,3);
				add_action('bkap_add_submenu',array(&$this, 'multiple_timeslot_menu'));
				// Add a price div on the product page for single day bookings
				add_action('bkap_display_price_div', array(&$this, 'multiple_time_display_price'),10,1);
				// Display multiple time slot price for single day bookings
				add_action('bkap_display_updated_addon_price', array(&$this, 'show_multiple_time_price'), 10, 3);
				// print hidden field for number of slots selected
				add_action('bkap_print_hidden_fields', array(&$this, 'multiple_print_fields'), 10,1);
				// Ajax calls
				add_action('init', array(&$this, 'multiple_time_load_ajax'));
				add_action('admin_init', array(&$this, 'edd_sample_register_option_multiple_timeslot'));
				add_action('admin_init', array(&$this, 'edd_sample_deactivate_license_multiple_timeslot'));
				add_action('admin_init', array(&$this, 'edd_sample_activate_license_multiple_timeslot'));
	
			}
			
			function multiple_time_load_ajax() {
				if ( !is_user_logged_in() ) {
					add_action('wp_ajax_nopriv_bkap_multiple_time_slot',  array(&$this,'bkap_multiple_time_slot'));
				}
				else {
					add_action('wp_ajax_bkap_multiple_time_slot',  array(&$this,'bkap_multiple_time_slot'));
				}
				
			}
			
			function edd_sample_activate_license_multiple_timeslot()
			{
				// listen for our activate button to be clicked
				if( isset( $_POST['edd_license_activate'] ) )
				{
					// run a quick security check
					if( ! check_admin_referer( 'edd_sample_nonce', 'edd_sample_nonce' ) )
						return; // get out if we didn't click the Activate button
			
					// retrieve the license from the database
					$license = trim( get_option('edd_sample_license_key_multiple_timeslot_book' ) );
						
					// data to send in our API request
					$api_params = array(
							'edd_action'=> 'activate_license',
							'license' 	=> $license,
							'item_name' => urlencode( EDD_SL_ITEM_NAME_MULTIPLE_TIMESLOT_BOOK ) // the name of our product in EDD
					);
			
					// Call the custom API.
					$response = wp_remote_get( add_query_arg( $api_params, EDD_SL_STORE_URL_MULTIPLE_TIMESLOT_BOOK ), array( 'timeout' => 15, 'sslverify' => false ) );
			
					// make sure the response came back okay
					if ( is_wp_error( $response ) )
						return false;
			
					// decode the license data
					$license_data = json_decode( wp_remote_retrieve_body( $response ) );
			
					// $license_data->license will be either "active" or "inactive"
			
					update_option( 'edd_sample_license_status_multiple_timeslot_book', $license_data->license );
			
				}
			}
			/***********************************************
			 * Illustrates how to deactivate a license key.
			* This will descrease the site count
			***********************************************/
			
			function edd_sample_deactivate_license_multiple_timeslot()
			{
				// listen for our activate button to be clicked
				if( isset( $_POST['edd_license_deactivate'] ) )
				{
					// run a quick security check
					if( ! check_admin_referer( 'edd_sample_nonce', 'edd_sample_nonce' ) )
						return; // get out if we didn't click the Activate button
			
					// retrieve the license from the database
					$license = trim( get_option( 'edd_sample_license_key_multiple_timeslot_book' ) );
						
			
					// data to send in our API request
					$api_params = array(
							'edd_action'=> 'deactivate_license',
							'license' 	=> $license,
							'item_name' => urlencode( EDD_SL_ITEM_NAME_MULTIPLE_TIMESLOT_BOOK ) // the name of our product in EDD
					);
			
					// Call the custom API.
					$response = wp_remote_get( add_query_arg( $api_params, EDD_SL_STORE_URL_MULTIPLE_TIMESLOT_BOOK ), array( 'timeout' => 15, 'sslverify' => false ) );
			
					// make sure the response came back okay
					if ( is_wp_error( $response ) )
						return false;
			
					// decode the license data
					$license_data = json_decode( wp_remote_retrieve_body( $response ) );
			
					// $license_data->license will be either "deactivated" or "failed"
					if( $license_data->license == 'deactivated' )
						delete_option( 'edd_sample_license_status_multiple_timeslot_book' );
			
				}
			}
			/************************************
			 * this illustrates how to check if
			* a license key is still valid
			* the updater does this for you,
			* so this is only needed if you
			* want to do something custom
			*************************************/
			
			function edd_sample_check_license()
			{
				global $wp_version;
					
				$license = trim( get_option( 'edd_sample_license_key_multiple_timeslot_book' ) );
					
				$api_params = array(
						'edd_action' => 'check_license',
						'license' => $license,
						'item_name' => urlencode( EDD_SL_ITEM_NAME_MULTIPLE_TIMESLOT_BOOK )
				);
					
				// Call the custom API.
				$response = wp_remote_get( add_query_arg( $api_params, EDD_SL_STORE_URL_MULTIPLE_TIMESLOT_BOOK ), array( 'timeout' => 15, 'sslverify' => false ) );
					
					
				if ( is_wp_error( $response ) )
					return false;
					
				$license_data = json_decode( wp_remote_retrieve_body( $response ) );
					
				if( $license_data->license == 'valid' ) {
					echo 'valid'; exit;
					// this license is still valid
				} else {
					echo 'invalid'; exit;
					// this license is no longer valid
				}
			}
				
			function edd_sample_register_option_multiple_timeslot()
			{
				// creates our settings in the options table
				register_setting('edd_multiple_timeslot_license', 'edd_sample_license_key_multiple_timeslot_book', array(&$this, 'edd_sanitize_license_multiple_timeslot' ));
			}
			
			
			function edd_sanitize_license_multiple_timeslot( $new )
			{
				$old = get_option( 'edd_sample_license_key_multiple_timeslot_book' );
				if( $old && $old != $new ) {
					delete_option( 'edd_sample_license_status_multiple_timeslot_book' ); // new license has been entered, so must reactivate
				}
				return $new;
			}
			
			function edd_sample_license_page()
			{
				$license 	= get_option( 'edd_sample_license_key_multiple_timeslot_book' );
				$status 	= get_option( 'edd_sample_license_status_multiple_timeslot_book' );
					
				?>
													<div class="wrap">
														<h2><?php _e('Plugin License Options'); ?></h2>
														<form method="post" action="options.php">
														
															<?php settings_fields('edd_multiple_timeslot_license'); ?>
															
															<table class="form-table">
																<tbody>
																	<tr valign="top">	
																		<th scope="row" valign="top">
																			<?php _e('License Key'); ?>
																		</th>
																		<td>
																			<input id="edd_sample_license_key_multiple_timeslot_book" name="edd_sample_license_key_multiple_timeslot_book" type="text" class="regular-text" value="<?php esc_attr_e( $license ); ?>" />
																			<label class="description" for="edd_sample_license_key"><?php _e('Enter your license key'); ?></label>
																		</td>
																	</tr>
																	<?php if( false !== $license ) { ?>
																		<tr valign="top">	
																			<th scope="row" valign="top">
																				<?php _e('Activate License'); ?>
																			</th>
																			<td>
																				<?php if( $status !== false && $status == 'valid' ) { ?>
																					<span style="color:green;"><?php _e('active'); ?></span>
																					<?php wp_nonce_field( 'edd_sample_nonce', 'edd_sample_nonce' ); ?>
																					<input type="submit" class="button-secondary" name="edd_license_deactivate" value="<?php _e('Deactivate License'); ?>"/>
																				<?php } else {
																					wp_nonce_field( 'edd_sample_nonce', 'edd_sample_nonce' ); ?>
																					<input type="submit" class="button-secondary" name="edd_license_activate" value="<?php _e('Activate License'); ?>"/>
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
						
								function multiple_timeslot_menu()
								{
									$page = add_submenu_page('booking_settings', __( 'Activate Multiple Timeslot License', 'woocommerce-booking' ), __( 'Activate Multiple Timeslot License', 'woocommerce-booking' ), 'manage_woocommerce', 'multiple_timeslot_license_page', array(&$this, 'edd_sample_license_page' ));
								}
			
			function multiple_time_slot_activate()
			{
			
			}
				
			function slot_function() {
				 return 'bkap_multiple_time_slot';
			}
			 
			function slot_type($product_id) {
				$booking_settings = get_post_meta($product_id, 'woocommerce_booking_settings', true);
				if(isset($booking_settings['booking_enable_time']) && $booking_settings['booking_enable_time'] == 'on') {
					if(isset($booking_settings['booking_enable_multiple_time'] ) && $booking_settings['booking_enable_multiple_time'] == "multiple" ) {
						return 'multiple';
					}
				}
			}
			
			function show_field_settings($product_id) {
				$booking_settings = get_post_meta($product_id, 'woocommerce_booking_settings', true);
				$booking_time_slot_selection = 'none';
				if (isset($booking_settings['booking_enable_time']) && $booking_settings['booking_enable_time'] == 'on') {
					$booking_time_slot_selection = 'show';
				}
				?>			
				<tr id="booking_time_slot" style="display:<?=$booking_time_slot_selection?>;">
					<th>
						<label for="booking_time_slot_label"><b><?php _e( 'Time Slot Selection:', 'woocommerce-booking');?></b></label>
					</th>
					<td>
						<?php 
						$enable_time = "";
						if(isset($booking_settings['booking_enable_multiple_time']) && $booking_settings['booking_enable_multiple_time'] == "multiple" ) {
							$enable_time = "checked";
							$enabled_time = "";
						}
						?>
						<input type="radio" name="booking_enable_time_radio" id="booking_enable_time_radio" value="single" <?php echo $enabled_time = "checked";?>><b><?php _e('Single&nbsp&nbsp&nbsp&nbsp&nbsp;', 'woocommerce-booking');?> </b></input>
						<input type="radio" id="booking_enable_time_radio" name="booking_enable_time_radio" value="multiple"<?php echo $enable_time;?>><b><?php _e('Multiple', 'woocommerce-booking');?> </b></input>
						<img class="help_tip" width="16" height="16" data-tip="<?php _e('Enable Single to select single timeslot on product page or Enable Multiple to select multiple timeslots on product page.', 'woocommerce-booking');?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png"/>
					</td>
				</tr>
				<script type="text/javascript">
					jQuery("#booking_enable_time").change(function() {
						if(jQuery('#booking_enable_time').attr('checked')) {
							jQuery('#booking_time_slot').show();
								
						}
						else {
							jQuery('#booking_time_slot').hide();
						}
						});
				</script>
				<?php 
			}

			function product_settings_save($booking_settings, $product_id) {
				if(isset($_POST['booking_enable_time_radio']) && $_POST['booking_enable_time_radio'] == 'single') {
						$enable_multiple_time = 'single';
				} 
				else if(isset($_POST['booking_enable_time_radio']) && $_POST['booking_enable_time_radio'] == 'multiple') {
					$enable_multiple_time = 'multiple';
				}
				
				$booking_settings['booking_enable_multiple_time'] = $enable_multiple_time;
				return $booking_settings;
			}
			
			/*********************************************************
			 * Add price div on product page
			 ********************************************************/
			function multiple_time_display_price($product_id) {
				$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true);
				if(isset($booking_settings['booking_enable_multiple_time']) && $booking_settings['booking_enable_multiple_time'] == 'multiple'):
					if (function_exists('is_bkap_seasonal_active') && is_bkap_seasonal_active()) {
					}
					else {
						$currency_symbol = get_woocommerce_currency_symbol();
						$show_price = 'show';
						print('<div id="show_addon_price" name="show_addon_price" class="show_addon_price" style="display:'.$show_price.';">'.$currency_symbol.' 0</div>');
					} 
				endif;
			}
			
			/*************************************************************
			 * Print hidden fields to display the number of slots selected
			 ************************************************************/
			function multiple_print_fields($product_id) {
				$booking_settings = get_post_meta($product_id, 'woocommerce_booking_settings', true);
				if(isset($booking_settings['booking_enable_multiple_time']) && $booking_settings['booking_enable_multiple_time'] == 'multiple') {
					print('<input type="hidden" id="wapbk_number_of_timeslots" name="wapbk_number_of_timeslots" value="0"/>');
				}
			}
			
			/**************************************************************
			 * Calculate the pridct when time slots are selected
			 *************************************************************/
			function show_multiple_time_price($product_id,$booking_date,$variation_id) {
				$product = get_product($product_id);
				$product_type = $product->product_type;
				$booking_settings = get_post_meta($product_id, 'woocommerce_booking_settings', true);
				if (!isset($_POST['price']) || (isset($_POST['price']) && $_POST['price'] == 0)) {
					if ( $product_type == 'variable'){
						$price = get_post_meta( $variation_id, '_sale_price', true);
						if($price == '') {
							$price = get_post_meta( $variation_id, '_regular_price', true);
						}
					}
					elseif($product_type == 'simple') {
						$price = get_post_meta( $product_id, '_sale_price', true);
						if($price == '') {
							$price = get_post_meta( $product_id, '_regular_price', true);
						}
					}
				}
				else {
					$price = $_POST['price'];
				}
			
				if(isset($booking_settings['booking_enable_multiple_time']) && $booking_settings['booking_enable_multiple_time'] == 'multiple') {
					if (isset($_POST['timeslots'])) {
						$price = $price * $_POST['timeslots'];
					}
				}
				if (function_exists('is_bkap_deposits_active') && is_bkap_deposits_active()) {
					$_POST['price'] = $price;
				}
				else {
					echo $price;
					die();
				}	
			}
			
			/********************************************************
			 * Ajax fn run when time slots are selected
			 *******************************************************/
			function bkap_multiple_time_slot() {
				$current_date = $_POST['current_date'];
				$post_id = $_POST['post_id'];
				$time_drop_down = bkap_booking_process::get_time_slot($current_date,$post_id);
			
				$time_drop_down_array = explode("|",$time_drop_down);
				$checkbox = "<label>".get_option('book.time-label').": </label><br>";
				$i = 0;
				foreach ($time_drop_down_array as $k => $v) {
					$i++; 
					if ($v != "") {
						$checkbox .= "<input type='checkbox' id='timeslot_".$i."' name='time_slot[]' value='".$v."' onClick='multi_timeslot(this)'>".$v."</input><br>";
					}
				}
				
				echo $checkbox;
				?>
				<script type="text/javascript">

					function multi_timeslot(chk) {
						var values = new Array();
						jQuery.each(jQuery("input[name='time_slot[]']:checked"), function() {
							  values.push(jQuery(this).val());
						});
						var slots_selected = values.length;
						jQuery("#wapbk_number_of_timeslots").val(slots_selected);
						// call the single day price calculation fn as the price needs to be calculated whenever a time slot is selected/de-selected
						bkap_single_day_price();
						var sold_individually = jQuery("#wapbk_sold_individually").val();
						if (slots_selected > 0) {
							jQuery( ".single_add_to_cart_button" ).show();
							if(sold_individually == "yes") {
								jQuery( ".quantity" ).hide();
							}
							else {
								jQuery( ".quantity" ).show();
							}
						}
						else {
							jQuery( ".single_add_to_cart_button" ).hide();
							jQuery( ".quantity" ).hide()
						}
					}		
				</script>
				<?php 
				die();
			}
			/******************************************************
			 * Adjust prices when addons are set 
			 *****************************************************/
			function add_cart_item( $cart_item ) {
				global $wpdb;
				
				$product_type = bkap_common::bkap_get_product_type($cart_item['product_id']);
				$price = bkap_common::bkap_get_price($cart_item['product_id'],$cart_item['variation_id'],$product_type);
				// Adjust price if addons are set
				if (isset($cart_item['booking'])) :
					$extra_cost = 0;
					foreach ($cart_item['booking'] as $addon) :
						if (isset($addon['price']) && $addon['price']>0) $extra_cost += $addon['price'];
					endforeach;
				
					$extra_cost = $extra_cost - $price;
					$cart_item['data']->adjust_price( $extra_cost );
				
				endif;
				
				return $cart_item;
			}
			
			/******************************************************
			 * calculate prices when products are added to the cart
			 *****************************************************/
			function multiple_time_add_cart_item_data($cart_arr, $product_id, $variation_id) {
				$booking_settings = get_post_meta($product_id, 'woocommerce_booking_settings', true);
				$time_slots = "";
				$price = "";
				$product = get_product($product_id);
				$product_type = $product->product_type;
	
				if (isset($_POST['price']) && $_POST['price'] != 0) {
					$price = $_POST['price'];
				}
				else {
					$price = bkap_common::bkap_get_price($product_id, $variation_id, $product_type);
				}
				if(isset($booking_settings['booking_enable_time']) && $booking_settings['booking_enable_time'] == 'on') {
					if(isset($booking_settings['booking_enable_multiple_time']) && $booking_settings['booking_enable_multiple_time'] == 'multiple') {
						$time_multiple_disp = $_POST['time_slot'];
						$i = 0;
						foreach($time_multiple_disp as $k => $v) {
							$time_slots .= "<br>".$v;
							$i++;
						}
						$price = $price * $i;
						$cart_arr['time_slot'] = $time_slots;
					}	
				}
				
				//Round the price if needed
				$round_price = $price;
				$global_settings = json_decode(get_option('woocommerce_booking_global_settings'));
				if (isset($global_settings->enable_rounding) && $global_settings->enable_rounding == "on")
					$round_price = round($price);
				$price = $round_price;
				
				if (function_exists('is_bkap_deposits_active') && is_bkap_deposits_active()) {
					$_POST['price'] = $price;
				}
				else {
					$cart_arr ['price'] = $price;
				}
				return $cart_arr;
			}
			/**********************************************************
			 * Cart in session
			 *********************************************************/
			function multiple_time_get_cart_item_from_session( $cart_item, $values ) {
				if (isset($values['booking'])) :
					$cart_item['booking'] = $values['booking'];
					$booking_settings = get_post_meta($cart_item['product_id'], 'woocommerce_booking_settings', true);
					if (isset($booking_settings['booking_enable_multiple_time']) && $booking_settings['booking_enable_multiple_time'] == 'multiple') {
						$cart_item = $this->add_cart_item( $cart_item );
					}
				endif;
				return $cart_item;
			}
			/***********************************************************
			 * Add the multiple time slots on the cart and checkout page
			 **********************************************************/
			function multiple_time_get_item_data( $other_data, $cart_item ) {
				if (isset($cart_item['booking'])) :
					foreach ($cart_item['booking'] as $booking) :
						$booking_settings = get_post_meta($cart_item['product_id'], 'woocommerce_booking_settings', true);	
						$saved_settings = json_decode(get_option('woocommerce_booking_global_settings'));
						if(isset($saved_settings) && $saved_settings->booking_time_format != '') {
							$time_format = $saved_settings->booking_time_format;
						}
						else {
							$time_format = '';
						}
						if ($time_format == "" OR $time_format == "NULL") {
							$time_format = "12";
						}	
						$time_slot_to_display = '';
						if (isset($booking['time_slot'])) {
							$time_slot_to_display = $booking['time_slot'];
						}
						if(isset($booking_settings['booking_enable_time']) && $booking_settings['booking_enable_time'] == 'on') {
							if(isset($booking_settings['booking_enable_multiple_time']) && $booking_settings['booking_enable_multiple_time'] == 'multiple') {
								$time_exploded = explode("<br>", $time_slot_to_display);
								array_shift($time_exploded);
								$time_slot = '';
								foreach($time_exploded as $k => $v) {
									$time_explode = explode("-",$v);
									if ($time_format == "" OR $time_format == "NULL") {
										$time_format = "12";
									}
									if ($time_format == '12') {
										$from_time = date('h:i A', strtotime($time_explode[0]));
										if (isset($time_explode[1])) {
											$to_time = date('h:i A', strtotime($time_explode[1]));
										}
										else {
											$to_time = "";
										}	
									}	
									else {	
										$from_time = date('H:i', strtotime($time_explode[0]));
										if (isset($time_explode[1])) {
											$to_time = date('H:i', strtotime($time_explode[1]));
										}
										else {
											$to_time = "";
										}
									}
									if($to_time != '') {	
										$time_slot .= "<br>".$from_time.' - '.$to_time;
									}
									else {
										$time_slot .= "<br>".$from_time;
									}
								}
								$name = get_option('book.item-cart-time');
								$other_data[] = array(
										'name'    => $name,
										'display' => $time_slot
								);
							}
						}
					endforeach;
				endif;
				return $other_data;
			}
			/**************************************************************
			 * Add time slots as order item meta
			 *************************************************************/
			function multiple_time_order_item_meta( $values,$order) {
				global $wpdb;
				//print_r($values);exit;
				$product_id = $values['product_id'];
				$quantity = $values['quantity'];
				$booking = $values['booking'];
				$booking_settings = get_post_meta($product_id,'woocommerce_booking_settings',true);
				$saved_settings = json_decode(get_option('woocommerce_booking_global_settings'));
				if(isset($saved_settings) && $saved_settings->booking_time_format != '') {
					$time_format = $saved_settings->booking_time_format;
				}
				else {
					$time_format = '';
				}
				if ($time_format == "" OR $time_format == "NULL") {
					$time_format = "12";
				}
				$date = '';
				$time_slot_to_display = '';
				$order_item_id = $order->order_item_id;
				$order_id = $order->order_id;
				if(isset($booking_settings['booking_enable_time']) && $booking_settings['booking_enable_time'] == 'on') {
					if(isset($booking_settings['booking_enable_multiple_time']) && $booking_settings['booking_enable_multiple_time'] == 'multiple') {
						if ($booking[0]['date'] != "") {
							$date = $booking[0]['date'];
							//echo $date;
							$name = get_option('book.item-meta-date');
							woocommerce_add_order_item_meta( $order_item_id, $name, sanitize_text_field( $date , true ) );
						}
						if($booking[0]['time_slot'] != "") {
							$time_slot = $booking[0]['time_slot'];
							$hidden_date = $booking[0]['hidden_date'];
							$time_exploded = explode("<br>", $time_slot);
							array_shift($time_exploded);
							foreach($time_exploded as $k => $v) {
								$time_explode = explode("-",$v);
								$from_time = trim($time_explode[0]);
								
								if (isset($time_explode[1])) {
									$to_time = trim($time_explode[1]);
								}
								else {
									$to_time = '';
								}
								if ($time_format == '12') {
									$from_time = date('h:i A', strtotime($time_explode[0]));
									if (isset($time_explode[1])) {
										$to_time = date('h:i A', strtotime($time_explode[1]));
									}
									else {
										$to_time = '';
									}
								}
								if($to_time != '') {
									$time_slot_to_display .= $from_time.' - '.$to_time.",";
								}
								else {	
									$time_slot_to_display .= $from_time.",";
								}
																
								$date_query = date('Y-m-d',strtotime($booking[0]['hidden_date']));
								//echo $date_query;exit;
								$query_from_time = date('G:i', strtotime($time_explode[0]));
								if (isset($time_explode[1])) {
									$query_to_time = date('G:i', strtotime($time_explode[1]));
								}
								if($query_to_time != '') {
									$query = "UPDATE `".$wpdb->prefix."booking_history`
										SET available_booking = available_booking - ".$quantity."
										WHERE post_id = '".$product_id."' AND
										start_date = '".$date_query."' AND
										from_time = '".$query_from_time."' AND
										to_time = '".$query_to_time."' ";
									mysql_query( $query );
									$order_select_query = "SELECT * FROM `".$wpdb->prefix."booking_history`
											WHERE post_id = '".$product_id."' AND
											start_date = '".$date_query."' AND
											from_time = '".$query_from_time."' AND
											to_time = '".$query_to_time."' ";
									$order_results = $wpdb->get_results( $order_select_query );
									foreach($order_results as $k => $v) {
										$details[$product_id][] = $v;
									}
								}
								else {
									$query = "UPDATE `".$wpdb->prefix."booking_history`
										SET available_booking = available_booking - ".$quantity."
										WHERE post_id = '".$product_id."' AND
										start_date = '".$date_query."' AND
										from_time = '".$query_from_time."'";
									mysql_query( $query );
									$order_select_query = "SELECT * FROM `".$wpdb->prefix."booking_history`
											WHERE post_id = '".$product_id."' AND
											start_date = '".$date_query."' AND
											from_time = '".$query_from_time."'";
									$order_results = $wpdb->get_results( $order_select_query );
									foreach($order_results as $k => $v) {
										$details[$product_id][] = $v;
									}
								}
								//print_r($order_results);
								$i = 0;
								foreach($order_results as $k => $v) {	
									$booking_id = $order_results[$i]->id;
									$order_query = "INSERT INTO `".$wpdb->prefix."booking_order_history`
										(order_id,booking_id)
										VALUES (
										'".$order_id."',
										'".$booking_id."' )";
									mysql_query( $order_query );
									$i++;	
								}
							}
							$time_slot_to_display = trim($time_slot_to_display, ",");
							woocommerce_add_order_item_meta( $order_item_id, get_option('book.item-meta-time'), $time_slot_to_display, true );
						}
						$book_global_settings = json_decode(get_option('woocommerce_booking_global_settings'));
						$booking_settings = get_post_meta($product_id, 'woocommerce_booking_settings' , true);
						$lockout_settings = '';
						if (isset($booking_settings['booking_time_settings'][$hidden_date])) {
							$lockout_settings = $booking_settings['booking_time_settings'][$hidden_date];
						}
						if($lockout_settings == '') {
							$week_day = date('l',strtotime($hidden_date));
							$weekday = array_search($week_day,$this->weekdays);
							$lockout_settings = $booking_settings['booking_time_settings'][$weekday];
						}
						$from_lockout_time = explode(":",$query_from_time);
						$from_hours = $from_lockout_time[0];
						$from_minute = $from_lockout_time[1];
						if($query_to_time != '') {
							$to_lockout_time = explode(":",$query_to_time);
							$to_hours = $to_lockout_time[0];
							$to_minute = $to_lockout_time[1];
						}
						else {
							$to_hours = '';
							$to_minute = '';
						}
						//print_r($lockout_settings);
						//exit;
						foreach($lockout_settings as $l_key => $l_value) {
							if($l_value['from_slot_hrs'] == $from_hours && $l_value['from_slot_min'] == $from_minute && $l_value['to_slot_hrs'] == $to_hours && $l_value['to_slot_min'] == $to_minute) {
								$global_timeslot_lockout = $l_value['global_time_check'];
							}
						}
						
						if($book_global_settings->booking_global_timeslot == 'on' || $global_timeslot_lockout == 'on') {
							$args = array( 'post_type' => 'product', 'posts_per_page' => -1 );
							$product = query_posts( $args );
							
							$product_ids = array();
							foreach($product as $k => $v) {
								$product_ids[] = $v->ID;
							}
							foreach($product_ids as $k => $v) {
								$booking_settings = get_post_meta($v, 'woocommerce_booking_settings' , true);
								if(isset($booking_settings['booking_enable_time']) && $booking_settings['booking_enable_time'] == 'on') {
									//echo "<pre>";print_r($details);echo "</pre>";exit;
									if(!array_key_exists($v,$details)) {
										foreach($details as $key => $val) {
											foreach($val as $v_key => $v_val) {
												$booking_settings = get_post_meta($v, 'woocommerce_booking_settings', true);
												//echo"<pre>";print_r($v_val);echo"</pre>";exit;
												$start_date = $v_val->start_date;
												$from_time = $v_val->from_time;
												$to_time = $v_val->to_time;
												if($to_time != "") {
													$query = "UPDATE `".$wpdb->prefix."booking_history`
													SET available_booking = available_booking - ".$quantity."
													WHERE post_id = '".$v."' AND
													start_date = '".$date_query."' AND
													from_time = '".$from_time."' AND
													to_time = '".$to_time."' ";
													$updated = $wpdb->query( $query );
													if($updated == 0) {
														if($v_val->weekday == '') {
															$week_day = date('l',strtotime($date_query));
															$weekday = array_search($week_day,$this->weekdays);
															//echo $weekday;exit;
														}
														else {	
															$weekday = $v_val->weekday;
														}
														$query = "SELECT * FROM `".$wpdb->prefix."booking_history`
															WHERE post_id = '".$v."'
															AND weekday = '".$weekday."'
															AND start_date = '0000-00-00'";
															//echo $query;exit;
														$results = $wpdb->get_results( $query );
														if (!$results) {
															break;
														}
														else {
															//print_r($results);exit;
															foreach($results as $r_key => $r_val) {
																if($from_time == $r_val->from_time && $to_time == $r_val->to_time) {
																	$available_booking = $r_val->available_booking - $quantity;
																	$query_insert = "INSERT INTO `".$wpdb->prefix."booking_history`
																		(post_id,weekday,start_date,from_time,to_time,total_booking,available_booking)
																		VALUES (
																	'".$v."',
																	'".$weekday."',
																	'".$start_date."',
																	'".$r_val->from_time."',
																	'".$r_val->to_time."',
																	'".$r_val->available_booking."',
																	'".$available_booking."' )";
																	//echo $query_insert;exit;
																	$wpdb->query( $query_insert );
																}
																else {
																	$query_insert = "INSERT INTO `".$wpdb->prefix."booking_history`
																	(post_id,weekday,start_date,from_time,to_time,total_booking,available_booking)
																	VALUES (
																	'".$v."',
																	'".$weekday."',
																	'".$start_date."',
																	'".$r_val->from_time."',
																	'".$r_val->to_time."',
																	'".$r_val->available_booking."',
																	'".$r_val->available_booking."' )";
																	$wpdb->query( $query_insert );
																}
															}
														}
													}
												}
												else {
													$query = "UPDATE `".$wpdb->prefix."booking_history`
													SET available_booking = available_booking - ".$quantity."
													WHERE post_id = '".$v."' AND
													start_date = '".$date_query."' AND
													from_time = '".$from_time."'
													AND to_time = ''";
													//$wpdb->query( $query );
													$updated = $wpdb->query( $query );
													if($updated == 0) {
														if($v_val->weekday == '') {
															$week_day = date('l',strtotime($date_query));
															$weekday = array_search($week_day,$this->weekdays);
															//echo $weekday;exit;
														}
														else {
															$weekday = $v_val->weekday;
														}
														$query = "SELECT * FROM `".$wpdb->prefix."booking_history`
															WHERE post_id = '".$v."'
															AND weekday = '".$weekday."'
															AND to_time = '' 
															AND start_date = '0000-00-00'";
														$results = $wpdb->get_results( $query );
														if (!$results) break;
														else {
															foreach($results as $r_key => $r_val) {
																if($from_time == $r_val->from_time) {
																	$available_booking = $r_val->available_booking - $quantity;
																	$query_insert = "INSERT INTO `".$wpdb->prefix."booking_history`
																		(post_id,weekday,start_date,from_time,total_booking,available_booking)
																		VALUES (
																		'".$v."',
																		'".$weekday."',
																	'".$start_date."',
																	'".$r_val->from_time."',
																	'".$r_val->available_booking."',
																	'".$available_booking."' )";
																	$wpdb->query( $query_insert );
																}
																else {
																	$query_insert = "INSERT INTO `".$wpdb->prefix."booking_history`
																	(post_id,weekday,start_date,from_time,total_booking,available_booking)
																	VALUES (
																	'".$v."',
																	'".$weekday."',
																	'".$start_date."',
																	'".$r_val->from_time."',
																	'".$r_val->available_booking."',
																	'".$r_val->available_booking."' )";
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

			/*****************************************************
			 * Quantity check for multiple time slots
			 ****************************************************/
			function multiple_time_slot_quantity_check($product_id,$booking_date,$booking_time_slot,$quantity) {
				global $woocommerce, $wpdb;
				$quantity_check_pass = 'yes';
				$booking_settings = get_post_meta($product_id , 'woocommerce_booking_settings' , true);
				$saved_settings = json_decode(get_option('woocommerce_booking_global_settings'));
				if(isset($saved_settings) && $saved_settings->booking_time_format != '') {
					$time_format = $saved_settings->booking_time_format;
				}
				else {
					$time_format = '';
				}
				if ($time_format == "" OR $time_format == "NULL") {
					$time_format = "12";
				}
				
				if($booking_settings['booking_enable_time'] == 'on') {
					if($booking_settings['booking_enable_multiple_time'] == 'multiple') {
						$time_exploded = explode("<br>",$booking_time_slot);
						foreach($time_exploded as $k => $v) {
							if($v != "") {
								$time_explode = explode("-",$v);
								$from_time = date('G:i', strtotime($time_explode[0]));
								if(isset($time_explode[1])) {
									$to_time = date('G:i', strtotime($time_explode[1]));
								}
								else {
									$to_time = '';
								}
								if($to_time != '') {
									$query = "SELECT available_booking, start_date FROM `".$wpdb->prefix."booking_history`
												WHERE post_id = '".$product_id."'
												AND start_date = '".$booking_date."'
												AND from_time = '".$from_time."'
												AND to_time = '".$to_time."' ";
									$results = $wpdb->get_results( $query );
								}
								else {
									$query = "SELECT available_booking, start_date FROM `".$wpdb->prefix."booking_history`
												WHERE post_id = '".$product_id."'
												AND start_date = '".$booking_date."'
												AND from_time = '".$from_time."'";
									$results = $wpdb->get_results( $query );
								}
								if (!$results) break;
								else {
									$post_title = get_post($product_id);
									if($booking_time_slot != "") {
										// if current format is 12 hour format, then convert the times to 24 hour format to check in database
										if ($time_format == '12') {
											$from_time = date('h:i A', strtotime($time_explode[0]));
											if(isset($time_explode[1])) {
												$to_time = date('h:i A', strtotime($time_explode[1]));
											}
											else {
												$to_time = '';
											}
											if($to_time != '') {
												$time_slot_to_display = $from_time.' - '.$to_time;
											}
											else {
												$time_slot_to_display = $from_time;
											}
										}
										else if ($time_format == '24') {
											$from_time = date('H:i ', strtotime($time_explode[0]));
											if(isset($time_explode[1])) {
												$to_time = date('H:i ', strtotime($time_explode[1]));
											}
											else {
												$to_time = '';
											}
											if($to_time != '') {
												$time_slot_to_display = $from_time.' - '.$to_time;
											}
											else {
												$time_slot_to_display = $from_time;
											}
										}
										if( $results[0]->available_booking > 0 && $results[0]->available_booking < $quantity) {
											$message = $post_title->post_title.bkap_get_book_t('book.limited-booking-msg1') .$results[0]->available_booking.bkap_get_book_t('book.limited-booking-msg2').$time_slot_to_display.'.';
											wc_add_notice( $message, $notice_type = 'error');
											$quantity_check_pass = 'no';
										}
										elseif ( $results[0]->available_booking == 0 ) {
											$message = bkap_get_book_t('book.no-booking-msg1').$post_title->post_title.bkap_get_book_t('book.no-booking-msg2').$time_slot_to_display.bkap_get_book_t('book.no-booking-msg3');
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
			
			/**************************************************************
			 * Quantity check on the product page
			 *************************************************************/
			function multiple_time_quantity_prod($_POST,$post_id) {
				global $woocommerce,$wpdb;
				$date_check = date('Y-m-d', strtotime($_POST['wapbk_hidden_date']));
				
				if (isset($_POST['quantity'])) {
					$item_quantity = $_POST['quantity'];
				}
				else {
					$item_quantity = 1;
				}
				$time_slot_str = '';
				foreach ($_POST['time_slot'] as $k => $v) {
					$time_slot_str .= $v . "<br>"; 
				}
				//check if the same product has been added to the cart for the same dates
				foreach ( $woocommerce->cart->get_cart() as $cart_item_key => $values ) {
					$booking = $values['booking'];
					$quantity = $values['quantity'];
					$product_id = $values['product_id'];
					
					$prod_time_slot = explode("<br>",$booking[0]['time_slot']); 	
					$prod_time_slot_str = '';
					foreach ($prod_time_slot as $k => $v) {
						if ($v != "") {
							$prod_time_slot_str .= $v . "<br>";
						}
					} 
					
					if ($product_id == $post_id && $booking[0]['hidden_date'] == $_POST['wapbk_hidden_date'] && $prod_time_slot_str == $time_slot_str) {
						$item_quantity += $quantity;
					}
				}
				$quantity_check_pass = multiple_time_slot::multiple_time_slot_quantity_check($post_id,$date_check,$time_slot_str,$item_quantity);
				return $quantity_check_pass;
			}
			/*************************************************************
			 * Quantity check on the cart and checkout page
			 ************************************************************/
			function multiple_time_quantity_check($value) {
				$date_check = date('Y-m-d', strtotime($value['booking'][0]['hidden_date']));
				
				$quantity_check_pass = multiple_time_slot::multiple_time_slot_quantity_check($value['product_id'],$date_check,$value['booking'][0]['time_slot'],$value['quantity']);
				
			}
			/*****************************************************
			 * Woocommerce cancel order
			 ****************************************************/
			function bkap_cancel_order($order_id,$item_value,$booking_id) {
				global $wpdb;
				$product_id = $item_value['product_id'];
				$quantity = $item_value['qty'];
				$booking_settings = get_post_meta($product_id, 'woocommerce_booking_settings', true);
				if($booking_settings['booking_enable_time'] == 'on') {
					if($booking_settings['booking_enable_multiple_time'] == 'multiple') {
						$select_data_query = "SELECT * FROM `".$wpdb->prefix."booking_history`
											WHERE id='".$booking_id."'";
											echo $select_data_query;
						$results_data = $wpdb->get_results ( $select_data_query );
						$j=0;
						foreach($results_data as $k => $v) {
							$start_date = $results_data[$j]->start_date;
							$from_time = $results_data[$j]->from_time;
							$to_time = $results_data[$j]->to_time;
							if($from_time != '' && $to_time != '' || $from_time != '') {
								if($to_time != '') {
									$query = "UPDATE `".$wpdb->prefix."booking_history`
												SET available_booking = available_booking + ".$quantity."
												WHERE 
												id = '".$booking_id."' AND
												start_date = '".$start_date."' AND
												from_time = '".$from_time."' AND
												to_time = '".$to_time."' AND 
												post_id = '".$product_id."'";
											
								}
								else {
									$query = "UPDATE `".$wpdb->prefix."booking_history`
												SET available_booking = available_booking + ".$quantity."
												WHERE 
											id = '".$booking_id."' AND
											start_date = '".$start_date."' AND
											from_time = '".$from_time."' AND 
											post_id = '".$product_id."'";
											
								}
								mysql_query( $query );
							}
							$j++;
						}	
					}
				}
			}
		}
	}
	$multiple_time_slot = new multiple_time_slot();
}
?>