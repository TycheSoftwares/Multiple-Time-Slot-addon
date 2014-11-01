<?php 

/*
 Plugin Name: Woocommerce Booking Multiple Time Slot Addon
Plugin URI: http://www.tychesoftwares.com/store/premium-plugins/bkap-multiple-time-slot-addon
Description: This addon to the Woocommerce Booking and Appointment Plugin lets you select multiple timeslots on a date for each product on the website.
Version: 1.1
Author: Ashok Rane
Author URI: http://www.tychesoftwares.com/
*/

/*require 'plugin-updates/plugin-update-checker.php';
$ExampleUpdateChecker = new PluginUpdateChecker(
		'http://www.tychesoftwares.com/plugin-updates/bkap-multiple-time-slot-addon/info.json',
		__FILE__
);*/
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
				//add_action('bkap_display_updated_price', array(&$this, 'show_updated_price'));
				add_filter('bkap_slot_type', array(&$this, 'slot_type'),10,1);
				add_filter('bkap_multiple_add_cart_item_data', array(&$this, 'add_cart_item_data'), 10, 2);
				add_filter('bkap_get_cart_item_from_session', array(&$this, 'get_cart_item_from_session'),10,2);
				add_filter('bkap_timeslot_get_item_data', array(&$this, 'get_item_data'), 10, 2 );
				add_action('bkap_update_booking_history', array(&$this, 'order_item_meta'), 10,2);
				add_action('bkap_validate_cart_items', array(&$this, 'bkap_quantity_check'),10,1);
				add_filter('bkap_validate_add_to_cart', array(&$this, 'bkap_quantity'),10,2);
				add_action('bkap_order_status_cancelled', array(&$this, 'bkap_cancel_order'),10,3);
				add_action('bkap_add_submenu',array(&$this, 'multiple_timeslot_menu'));
				
				add_action('admin_init', array(&$this, 'edd_multiple_time_slot_register_option'));
				add_action('admin_init', array(&$this, 'edd_multiple_time_slot_deactivate_license'));
				add_action('admin_init', array(&$this, 'edd_multiple_time_slot_activate_license'));
				//require_once( ABSPATH . "wp-includes/pluggable.php" );
				add_action('init', array(&$this, 'multiple_time_slot_load_ajax'));
			}
			function multiple_time_slot_load_ajax()
			{
				if ( !is_user_logged_in() )
				{
					add_action('wp_ajax_nopriv_bkap_multiple_time_slot',  array(&$this,'bkap_multiple_time_slot'));
				}
				else
				{
					add_action('wp_ajax_bkap_multiple_time_slot',  array(&$this,'bkap_multiple_time_slot'));
				}
			}
			function edd_multiple_time_slot_activate_license()
			{
				// listen for our activate button to be clicked
				if( isset( $_POST['edd_multiple_time_slot_license_activate'] ) )
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
			
			function edd_multiple_time_slot_deactivate_license()
			{
				// listen for our activate button to be clicked
				if( isset( $_POST['edd_multiple_time_slot_license_deactivate'] ) )
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
			
			function edd_multiple_time_slot_check_license()
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
				
			function edd_multiple_time_slot_register_option()
			{
				// creates our settings in the options table
				register_setting('edd_multiple_time_slot_license', 'edd_sample_license_key_multiple_timeslot_book', array(&$this, 'edd_sanitize_license' ));
			}
			
			
			function edd_sanitize_license( $new )
			{
				$old = get_option( 'edd_sample_license_key_multiple_timeslot_book' );
				if( $old && $old != $new ) {
					delete_option( 'edd_sample_license_status_multiple_timeslot_book' ); // new license has been entered, so must reactivate
				}
				return $new;
			}
			
			function edd_multiple_time_slot_license_page()
			{
				$license 	= get_option( 'edd_sample_license_key_multiple_timeslot_book' );
				$status 	= get_option( 'edd_sample_license_status_multiple_timeslot_book' );
					
				?>
													<div class="wrap">
														<h2><?php _e('Plugin License Options'); ?></h2>
														<form method="post" action="options.php">
														
															<?php settings_fields('edd_multiple_time_slot_license'); ?>
															
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
																					<input type="submit" class="button-secondary" name="edd_multiple_time_slot_license_deactivate" value="<?php _e('Deactivate License'); ?>"/>
																				<?php } else {
																					wp_nonce_field( 'edd_sample_nonce', 'edd_sample_nonce' ); ?>
																					<input type="submit" class="button-secondary" name="edd_multiple_time_slot_license_activate" value="<?php _e('Activate License'); ?>"/>
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
									$page = add_submenu_page('booking_settings', __( 'Activate Multiple Timeslot License', 'woocommerce-booking' ), __( 'Activate Multiple Timeslot License', 'woocommerce-booking' ), 'manage_woocommerce', 'multiple_timeslot_license_page', array(&$this, 'edd_multiple_time_slot_license_page' ));
								}
			
			function multiple_time_slot_activate()
			{
			
			}
				
			function slot_function()
			{
				 return 'bkap_multiple_time_slot';
			}
			 
			function slot_type($product_id)
			{
				$booking_settings = get_post_meta($product_id, 'woocommerce_booking_settings', true);
				if($booking_settings['booking_enable_time'] == 'on')
				{
					if($booking_settings['booking_enable_multiple_time'] == "multiple" )
					{
						return 'multiple';
					}
				}
			}
			
			function show_field_settings($product_id)
			{
					$booking_settings = get_post_meta($product_id, 'woocommerce_booking_settings', true);
					$booking_time_slot_selection = 'none';
					if (isset($booking_settings['booking_enable_time']) && $booking_settings['booking_enable_time'] == 'on')
					{
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
							if(isset($booking_settings['booking_enable_multiple_time']) && $booking_settings['booking_enable_multiple_time'] == "multiple" )
							{
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
							if(jQuery('#booking_enable_time').attr('checked'))
							{
								jQuery('#booking_time_slot').show();
									
							}
							else
							{
								jQuery('#booking_time_slot').hide();
							}
							});
					</script>
				<?php 
			}

			function product_settings_save($booking_settings, $product_id)
			{
				if(isset($_POST['booking_enable_time_radio']) && $_POST['booking_enable_time_radio'] == 'single')
				{
						$enable_multiple_time = 'single';
				}
				else if(isset($_POST['booking_enable_time_radio']) && $_POST['booking_enable_time_radio'] == 'multiple')
				{
					$enable_multiple_time = 'multiple';
				}
				
				$booking_settings['booking_enable_multiple_time'] = $enable_multiple_time;
				return $booking_settings;
			}
			
			function bkap_multiple_time_slot()
			{
				global $wpdb,$woocommerce_booking;
				$saved_settings = json_decode(get_option('woocommerce_booking_global_settings'));
				if(isset($saved_settings) && $saved_settings->booking_time_format != '')
				{
					$time_format = $saved_settings->booking_time_format;
				}
				else
				{
					$time_format = '';
				}
				if ($time_format == '') $time_format = '12';
				$time_format_value = 'G:i';
				if ($time_format == '12')
				{
					$time_format_to_show = 'h:i A';
				}
				else 
				{
					$time_format_to_show = 'H:i';
				}
				$current_date = $_POST['current_date'];
				$date_to_check = date('Y-m-d', strtotime($current_date));
				$day_check = "booking_weekday_".date('w', strtotime($current_date));
				$to_time_value = '';
				$from_time = '';
				$to_time = '';
				$post_id = $_POST['post_id'];
				$booking_settings = get_post_meta($post_id,'woocommerce_booking_settings',true);
				$check_query = "SELECT * FROM `".$wpdb->prefix."booking_history`
									WHERE start_date='".$date_to_check."'
									AND post_id='".$post_id."'
									AND available_booking > 0 ";
				
				$results_check = $wpdb->get_results ( $check_query );
				if ( count($results_check) > 0 )
				{
					$checkbox = get_option('book.item-meta-time').":<br>";
					$specific = "N";
					$i = 1;
					foreach ( $results_check as $key => $value )
					{
						if ($value->weekday == "") 
						{
							$specific = "Y";
							if ($value->from_time != '') 
							{
								$from_time = date($time_format_to_show, strtotime($value->from_time));
								$from_time_value = date($time_format_value, strtotime($value->from_time));
			
							}
							$to_time = $value->to_time;
										
							if( $to_time != '' )
							{
								$to_time = date($time_format_to_show, strtotime($value->to_time));
								$to_time_value = date($time_format_value, strtotime($value->to_time));
								$checkbox .= "<input type='checkbox' id='timeslot_".$i."' name='timeslot[]' value='".$from_time." - ".$to_time."'>".$from_time."-".$to_time."<br>";
							}
							else
							{
								$checkbox .= "<input type='checkbox' id='timeslot_".$i."' name='timeslot[]' value='".$from_time."'/>".$from_time."<br>";
							}
						}
						$i++;
					}
					if ($specific == "N")
					{
						$i=1;
						foreach ( $results_check as $key => $value )
						{
							if ($value->from_time != '')
							{
								$from_time = date($time_format_to_show, strtotime($value->from_time));
								$from_time_value = date($time_format_value, strtotime($value->from_time));
							}
							$to_time = $value->to_time;
										
							if( $to_time != '' )
							{
								$to_time = date($time_format_to_show, strtotime($value->to_time));
								$to_time_value = date($time_format_value, strtotime($value->to_time));
								$checkbox .= "<input type='checkbox' id='timeslot_".$i."' name='timeslot[]' value='".$from_time." - ".$to_time."'/>".$from_time."-".$to_time."<br>";
							}
							else
							{
								if ($value->from_time != '')
								{ 
									$checkbox .= "<input type = 'checkbox' id='timeslot_".$i."' name='timeslot[]' value='".$from_time."'/>".$from_time."<br>";
			
									}
							}
							$i++;
						}
						$check_day_query = "SELECT * FROM `".$wpdb->prefix."booking_history`
														WHERE weekday='".$day_check."'
														AND post_id='".$post_id."'
														AND start_date='0000-00-00'
														AND available_booking > 0";
									$results_day_check = $wpdb->get_results ( $check_day_query );
									
									//remove duplicate time slots that have available booking set to 0
						foreach ($results_day_check as $k => $v)
						{
							$from_time_qry = date($time_format_value, strtotime($v->from_time));
							if ($v->to_time != '') $to_time_qry = date($time_format_value, strtotime($v->to_time));
										
							$time_check_query = "SELECT * FROM `".$wpdb->prefix."booking_history`
													WHERE start_date='".$date_to_check."'
													AND post_id='".$post_id."'
													AND from_time='".$from_time_qry."'
													AND to_time='".$to_time_qry."'";
										$results_time_check = $wpdb->get_results ( $time_check_query );
										
							if (count($results_time_check) > 0) unset($results_day_check[$k]);
						}
						foreach ($results_day_check as $k => $v)
						{
							foreach ($results_check as $key => $value)
							{
								if ($v->from_time != '' && $v->to_time != '')
								{
									$from_time_chk = date($time_format_value, strtotime($v->from_time));
									if ($value->from_time == $from_time_chk)
									{
										if ($v->to_time != '') $to_time_chk = date($time_format_value, strtotime($v->to_time));
										if ($value->to_time == $to_time_chk) unset($results_day_check[$k]);
									}
								}
								else
								{
									if($v->from_time == $value->from_time)
									{
										if ($v->to_time == $value->to_time) unset($results_day_check[$k]);
									}
								}
							}
						}
						$i=1;
						foreach ( $results_day_check as $key => $value )
						{
							if ($value->from_time != '')
							{
								$from_time = date($time_format_to_show, strtotime($value->from_time));
								$from_time_value = date($time_format_value, strtotime($value->from_time));
							}
							$to_time = $value->to_time;
							if ( $to_time != '' )
							{
								$to_time = date($time_format_to_show, strtotime($value->to_time));
								$to_time_value = date($time_format_value, strtotime($value->to_time));
								$checkbox .= "<input type = 'checkbox' id='timeslot_".$i."' name='timeslot[]' value='".$from_time." - ".$to_time."'>".$from_time."-".$to_time."<br>";
							}
							else
							{
								if ($value->from_time != '') 
								{
									$checkbox .= "<input type = 'checkbox' id='timeslot_".$i."' name='timeslot[]' value='".$from_time."'>".$from_time."<br>";
								}
							}
							$insert_date = "INSERT INTO `".$wpdb->prefix."booking_history`
														(post_id,weekday,start_date,from_time,to_time,total_booking,available_booking)
														VALUES (
														'".$post_id."',
														'".$day_check."',
														'".$date_to_check."',
														'".$from_time_value."',
														'".$to_time_value."',
														'".$value->total_booking."',
														'".$value->available_booking."' )";
										mysql_query( $insert_date );
										$i++;
						}
					}
				}
				else
				{
					$check_day_query = "SELECT * FROM `".$wpdb->prefix."booking_history`
													 WHERE weekday='".$day_check."'
													 AND post_id='".$post_id."'
													 AND start_date='0000-00-00'
													 AND available_booking > 0";
					$results_day_check = $wpdb->get_results ( $check_day_query );
					$checkbox = get_option('book.item-meta-time').":<br>";
					$i = 1;
					foreach ( $results_day_check as $key => $value )
					{
						if ($value->from_time != '')
						{
							$from_time = date($time_format_to_show, strtotime($value->from_time));
							$from_time_value = date($time_format_value, strtotime($value->from_time));
						}
						$to_time = $value->to_time;
						if ( $to_time != '' )
						{
							$to_time = date($time_format_to_show, strtotime($value->to_time));
							$to_time_value = date($time_format_value, strtotime($value->to_time));
							$checkbox .= "<input type = 'checkbox' id='timeslot_".$i."' name='timeslot[]' value='".$from_time." - ".$to_time."'>".$from_time."-".$to_time."<br>";
						}
						else 
						{
							$checkbox.= "<input type = 'checkbox' id='timeslot_".$i."' name='timeslot[]' value='".$from_time."'/>".$from_time."<br>";
						}
						$insert_date = "INSERT INTO `".$wpdb->prefix."booking_history`
													(post_id,weekday,start_date,from_time,to_time,total_booking,available_booking)
													VALUES (
													'".$post_id."',
													'".$day_check."',
													'".$date_to_check."',
													'".$from_time_value."',
													'".$to_time_value."',
													'".$value->total_booking."',
													'".$value->available_booking."' )";
													
							mysql_query( $insert_date );	
						$i++;
					}
				}
				if(isset($booking_settings['booking_seasonal_pricing_enable']) && is_plugin_active('bkap-seasonal-pricing/seasonal_pricing.php'))
				{
					print('<input type="hidden" id="seasonal" name="seasonal" value="'.$booking_settings['booking_seasonal_pricing_enable'].'"/>');
				}
				else
				{
					print('<input type="hidden" id="seasonal" name="seasonal" value="no"/>');
				}
				if (isset($booking_settings['booking_seasonal_pricing_enable']) && $booking_settings['booking_seasonal_pricing_enable'] == "yes")
				{
					$query = "SELECT * FROM `".$wpdb->prefix."booking_seasonal_pricing`
					WHERE post_id = '".$post_id."'
					AND start_date <= '".$date_to_check."'
					AND end_date >= '".$date_to_check."'";
					//echo $query;exit;
					$results = $wpdb->get_results($query);
					//			print_r($results);
					if ($results)
					{
						foreach($results as $k => $v)
						{
							//print_r($v);
							$adjustment[] = $this->calculate($v->amount_or_percent, $v->operator, $v->price);
							$operator[] = $v->operator;
							$amount[] = $v->amount_or_percent; 
						}
						//print_r($adjustment);
						//print_r($operator);
						//print_r($amount);
						$adjustment_str = implode(',',$adjustment);
						$operator_str = implode(',',$operator);
						$amount_str = implode(',',$amount);
						print('<input type="hidden" id="adjustment" name="adjustment" value="'.$adjustment_str.'"/>');
						print('<input type="hidden" id="adjustment_amount_or_percent" name="adjustment_amount_or_percent" value="'.$amount_str.'"/>');
						print('<input type="hidden" id="adjustment_operator" name="adjustment_operator" value="'.$operator_str.'"/>');
						
						//echo $adjustment;exit;
						/*if ($results[0]->amount_or_percent == "percent")
						 {
						$adjustment = $adjustment * $price;
						//$price = $price + $adjustment;
						}
						elseif($results[0]->amount_or_percent == "amount") $price = $price + $adjustment;*/
					}
					else
					{
						print('<input type="hidden" id="adjustment" name="adjustment" value=""/>');
						print('<input type="hidden" id="adjustment_amount_or_percent" name="adjustment_amount_or_percent" value=""/>');
						print('<input type="hidden" id="adjustment_operator" name="adjustment_operator" value=""/>');
					}
				}
				echo $checkbox;
				$currency_symbol = get_woocommerce_currency_symbol();
				print('<input type="hidden" id="wapbk_symbol" name="wapbk_symbol" value="'.$currency_symbol.'"/>');
				$product = get_product($post_id);
				//print_r($product);
				$product_type = $product->product_type;
				//print_r($_POST);exit;
				if ( $product_type == 'variable')
				{
					$variation_id = $this->get_selected_variation_id($post_id, $_POST);
					if ($variation_id != "")
					{
						$sale_price = get_post_meta( $variation_id, '_sale_price', true);
						if($sale_price == '')
						{
							$regular_price = get_post_meta( $variation_id, '_regular_price',true);
								$price = $regular_price;
						}
						else
						{
							$price = $sale_price;
						}
					}
					else echo "Please select an option.";
				}
				else if ( $product_type == 'simple')
				{
					$variation_id = "0";
					//echo "variation id".$variation_id;exit;
					$sale_price = get_post_meta( $post_id, '_sale_price', true);
					if($sale_price == '')
					{
						$regular_price = get_post_meta( $post_id, '_regular_price',true);
						$price = $regular_price;
					}
					else
					{
						$price = $sale_price;
					}
				}
				print('<input type="hidden" id="wapbk_price" value="'.$price.'"/>');
				$show_updated_price_on_product_page = 0;
				if($show_updated_price_on_product_page == 0)
				{
					$show_price = 'show';
				}
				else
				{
					$show_price = 'none';
				}
				print('<div id="show_price" name="show_price" class="show_price" style="display:'.$show_price.';">'.$currency_symbol.' 0</div>');
				print('<input type="hidden" id="wapbk_hidden_price" name="wapbk_hidden_price"/>');
				die();
			}
			function get_selected_variation_id($product_id, $post_data)
			{
				global $wpdb;
				//print_r($post_data);
				$product = get_product($product_id);
				$variations = $product->get_available_variations();
				$attributes = $product->get_variation_attributes();
				$attribute_fields_str = "";
				$attribute_fields = array();
				$variation_id_arr = $variation_id_exclude = array();
				//print_r($variations);
				foreach ($variations as $var_key => $var_val)
				{
					$attribute_sub_query = '';
					$variation_id = $var_val['variation_id'];
					foreach ($var_val['attributes'] as $a_key => $a_val)
					{
						$attribute_name = $a_key;
						//echo $attribute_name;
						// for each attribute, we are checking the value selected by the user
						if (isset($post_data[$attribute_name]))
						{
							$attribute_sub_query[] = " (`meta_key` = '$attribute_name' AND `meta_value` = '$post_data[$attribute_name]')  ";
							$attribute_sub_query_str = " (`meta_key` = '$attribute_name' AND (`meta_value` = '$post_data[$attribute_name]' OR `meta_value` = ''))  ";
							$check_price_query = "SELECT * FROM `".$wpdb->prefix."postmeta`
							WHERE
							$attribute_sub_query_str
							AND
							post_id='".$variation_id."' ";
							//echo $check_price_query.'<br>';
							$results_price_check = $wpdb->get_results ( $check_price_query );
							//print_r($results_price_check);
							// if no records are found, then that variation_id is put in exclude array
							if (count($results_price_check) > 0)
							{
								if (!in_array($variation_id, $variation_id_arr))
								$variation_id_arr[] = $variation_id;
							}
							else
							{
								if (!in_array($variation_id, $variation_id_exclude))
								$variation_id_exclude[] = $variation_id;
							}
						}
					}
				}
							// here we remove all variation ids from the $variation_id_arr that are present in the $variation_id_exclude array
							// this should leave us with only 1 variation id
				$variation_id_final = array_diff($variation_id_arr, $variation_id_exclude);
							//	echo 'here <pre>';print_r($variation_id_final);echo '</pre>';
				$variation_id_to_fetch = array_pop($variation_id_final);
				//echo $variation_id_to_fetch;exit;
				return $variation_id_to_fetch;
			}
			/*function show_updated_price($product_id)
			{
				global $wpdb;
				$booking_settings = get_post_meta($product_id, 'woocommerce_booking_settings', true);
				if($booking_settings['booking_enable_time'] == 'on')
				{
					if($booking_settings['booking_enable_multiple_time'] == 'multiple')
					{
						$currency_symbol = get_woocommerce_currency_symbol();
						print('<input type="hidden" id="wapbk_symbol" name="wapbk_symbol" value="'.$currency_symbol.'"/>');
						
						$sale_price = get_post_meta( $product_id, '_sale_price', true);
						if($sale_price == '')
						{
							$regular_price = get_post_meta( $product_id, '_regular_price',true);
							$price = $regular_price;
						}
						else
						{	
							$price = $sale_price;
						}
						print('<input type="hidden" id="wapbk_price" value="'.$price.'"/>');
						$show_updated_price_on_product_page = 0;
						if($show_updated_price_on_product_page == 0)
						{
							$show_price = 'show';
						}
						else
						{	
							$show_price = 'none';
						}
						print('<div id="show_price" name="show_price" class="show_price" style="display:'.$show_price.';">'.$currency_symbol.' 0</div>');
						print('<input type="hidden" id="wapbk_hidden_price" name="wapbk_hidden_price"/>');
						
					}
				}
			}*/
			function calculate($amount_percent, $operator, $price )
			{
				if ($amount_percent == "amount")
				{
					if ($operator == "add")
					{
						$adjustment = $price;
					}
					elseif($operator == "subtract")
					{
						$adjustment = $price * -1;
					}
				}
				elseif ($amount_percent == "percent")
				{
					if ($operator == "add")
					{
						$adjustment = $price/100;
					}
					elseif ($operator == "subtract")
					{
						$adjustment = $price/100;
						$adjustment = $adjustment * -1;
					}
				}
				return $adjustment;
			}
			function add_cart_item( $cart_item ) 
			{
				// Adjust price if addons are set
				if (isset($cart_item['booking'])) :
					$extra_cost = 0;
					foreach ($cart_item['booking'] as $addon) :
							if (isset($addon['price']) && $addon['price']>0) $extra_cost += $addon['price'];
					endforeach;
							
					$product = get_product($cart_item['product_id']);
					$product_type = $product->product_type;
					
					if ( $product_type == 'variable')
					{
						$sale_price = get_post_meta( $cart_item['variation_id'], '_sale_price', true);
						if($sale_price == '')
						{
							$regular_price = get_post_meta( $cart_item['variation_id'], '_regular_price', true);
							$extra_cost = $extra_cost - $regular_price;
						}
						else
						{
							$extra_cost = $extra_cost - $sale_price;
						}
					}
					elseif($product_type == 'simple')
					{
						$sale_price = get_post_meta( $cart_item['product_id'], '_sale_price', true);
						if($sale_price == '')
						{
							$regular_price = get_post_meta( $cart_item['product_id'], '_regular_price', true);
							$extra_cost = $extra_cost - $regular_price;
						}
						else
						{
							$extra_cost = $extra_cost - $sale_price;
						}
					}
					$cart_item['data']->adjust_price( $extra_cost );
								
				endif;
				return $cart_item;
			}
			function add_cart_item_data($cart_item_meta, $product_id)
			{
				
				$booking_settings = get_post_meta($product_id, 'woocommerce_booking_settings', true);
				$time_slots = "";
				$price = "";
				$round_price = '';
				
				if($booking_settings['booking_enable_time'] == 'on')
				{
					if($booking_settings['booking_enable_multiple_time'] == 'multiple')
					{
						$date_disp = $_POST['booking_calender'];
						$time_multiple_disp = $_POST['timeslot'];
						$hidden_date = $_POST['wapbk_hidden_date'];
						foreach($time_multiple_disp as $k => $v)
						{
							$time_slots .= "<br>".$v;
						}
						$price = $_POST['wapbk_hidden_price'];
						//echo $price;exit;
						if (isset($booking_settings['booking_partial_payment_enable']))
						{
							$total = $price ;
							if(isset($booking_settings['booking_partial_payment_radio']) && $booking_settings['booking_partial_payment_radio']=='value')
							{
								$deposit = $booking_settings['booking_partial_payment_value'];
								$rem = $total-$deposit;
								$cart_arr['Total'] = $total;
								$cart_arr['Remaining'] = $rem;
								$cart_arr['Deposit'] = $deposit;
								$cart_arr ['price'] = $deposit;
							}
							elseif(isset($booking_settings['booking_partial_payment_radio']) && $booking_settings['booking_partial_payment_radio']=='percent')
							{
								$deposit = $total* ($booking_settings['booking_partial_payment_value']/100);
								$rem = $total-$deposit;
								$cart_arr['Total'] = $total;
								$cart_arr['Remaining'] = $rem;
								$cart_arr['Deposit'] = $deposit;
								$cart_arr ['price'] = $deposit;
							}	
							$global_settings = json_decode(get_option('woocommerce_booking_global_settings'));
							if (isset($global_settings->enable_rounding) && $global_settings->enable_rounding == "on")
							{
								if (isset($booking_settings['booking_partial_payment_enable']))
								{
									$cart_arr['price'] = round($cart_arr['price']);
									if(isset(	$cart_arr['Total']))
										$cart_arr['Total'] = round($cart_arr['Total']);
									if(isset(	$cart_arr['Deposit']))
										$cart_arr['Deposit'] = round($cart_arr['Deposit']);
									if(isset(	$cart_arr['Remaining']))
										$cart_arr['Remaining'] = round($cart_arr['Remaining']);
								}
							}
						}
						else
						{
							$global_settings = json_decode(get_option('woocommerce_booking_global_settings'));
							if (isset($global_settings->enable_rounding) && $global_settings->enable_rounding == "on")
							{
								$round_price = round($price);
								$price = $round_price;
							}
							$cart_arr['price'] = $price;
						}
						$cart_arr['date'] = $date_disp;
						$cart_arr['time_slot'] = $time_slots;
						$cart_arr['hidden_date'] = $hidden_date;
						if(isset($booking_settings['booking_show_comment']) && $booking_settings['booking_show_comment'] == 'on')
						{
							$cart_arr['comments'] = $_POST['comments'];
						}
					}
					//print_r($cart_arr);exit;
					return $cart_arr;
				}
			}
			
			function get_cart_item_from_session( $cart_item, $values ) 
			{
				if (isset($values['booking'])) :
				$cart_item['booking'] = $values['booking'];
				$booking_settings = get_post_meta($cart_item['product_id'], 'woocommerce_booking_settings', true);
				//$show_checkout_date_calendar = 1;
				if (isset($booking_settings['booking_enable_multiple_time']) && $booking_settings['booking_enable_multiple_time'] == 'multiple')
				{
					if(isset($booking_settings['booking_partial_payment_enable']) && is_plugin_active('bkap-deposits/deposits.php'))
					{
							if(isset($cart_item['booking'][0]['date']))
							{
								if(isset($booking_settings['booking_partial_payment_enable']))
								{
									$cart_item = $this->add_cart_item( $cart_item );
						
								}
								
							}
					}
					else
					{
						if(!isset($booking_settings['booking_seasonal_pricing_enable']))
						{
							$cart_item = $this->add_cart_item( $cart_item );
						}
					}
				}
				endif;
				//print_r($cart_item);
				return $cart_item;
			}

			function get_item_data( $other_data, $cart_item ) 
			{
				if (isset($cart_item['booking'])) :
				foreach ($cart_item['booking'] as $booking) :
				$booking_settings = get_post_meta($cart_item['product_id'], 'woocommerce_booking_settings', true);	
				$saved_settings = json_decode(get_option('woocommerce_booking_global_settings'));
				if(isset($saved_settings) && $saved_settings->booking_time_format != '')
				{
					$time_format = $saved_settings->booking_time_format;
				}
				else
				{
					$time_format = '';
				}
				if ($time_format == "" OR $time_format == "NULL") $time_format = "12";
				$time_slot_to_display = $booking['time_slot'];
				if($booking_settings['booking_enable_time'] == 'on')
				{
					if($booking_settings['booking_enable_multiple_time'] == 'multiple')
					{
						
						$time_exploded = explode("<br>", $time_slot_to_display);
						array_shift($time_exploded);
						$time_slot = '';
						foreach($time_exploded as $k => $v)
						{
							$time_explode = explode("-",$v);
							if ($time_format == "" OR $time_format == "NULL") $time_format = "12";
							if ($time_format == '12')
							{
								$from_time = date('h:i A', strtotime($time_explode[0]));
								if (isset($time_explode[1])) $to_time = date('h:i A', strtotime($time_explode[1]));
								else $to_time = "";
							}
							else
							{	
								$from_time = date('H:i', strtotime($time_explode[0]));
								if (isset($time_explode[1])) $to_time = date('H:i', strtotime($time_explode[1]));
								else $to_time = "";
							}
							if($to_time != '')
							{	
								$time_slot .= "<br>".$from_time.' - '.$to_time;
							}
							else
							{
								$time_slot .= "<br>".$from_time;
					
							}
													
						}
						$name = get_option('book.item-cart-time');
						$other_data[] = array(
								'name'    => $name,
								'display' => $time_slot
						);
						if(isset($booking_settings['booking_partial_payment_enable']) && isset($booking_settings['booking_partial_payment_radio']) && $booking_settings['booking_partial_payment_radio']!='' &&  is_plugin_active('bkap-deposits/deposits.php'))
						{
							$currency_symbol = get_woocommerce_currency_symbol();
							if (isset($cart_item['booking']))
							{
								$price = '';
								foreach ($cart_item['booking'] as $booking)
								{
									if(isset($booking_settings['booking_partial_payment_radio']))
									{
										if(isset($cart_item['quantity']))
										{
											if (isset($global_settings->enable_rounding) && $global_settings->enable_rounding == "on")
											{
												$booking['Total'] = round($booking['Total'] * $cart_item['quantity']);
												$booking['Deposit'] = round($booking['Deposit'] * $cart_item['quantity']);
												$booking['Remaining'] = round($booking['Remaining'] * $cart_item['quantity']);
											}
											else
											{
												$booking['Total'] = $booking['Total'] * $cart_item['quantity'];
												$booking['Deposit'] = $booking['Deposit'] * $cart_item['quantity'];
												$booking['Remaining'] = $booking['Remaining'] * $cart_item['quantity'];
											}
										}
										$price .= "<br> ".book_t('book.item-partial-total').": $currency_symbol".$booking['Total']."<br> ".book_t('book.item-partial-deposit').": $currency_symbol".$booking['Deposit']."<br>".book_t('book.item-partial-remaining').": $currency_symbol".$booking['Remaining'];
									}
								}
								$other_data[] = array(
										'name'    => book_t('book.partial-payment-heading'),
										'display' => $price
								);
							}
						}
						if(isset($booking_settings["booking_show_comment"]) && $booking_settings["booking_show_comment"] == 'on' && is_plugin_active('bkap-tour-operators/tour_operators_addon.php'))
						{
							if (isset($cart_item['booking'])) 
							{
								$price = '';
								foreach ($cart_item['booking'] as $booking) 
								{
									if(isset($booking['comments']))
									{
										$price = $booking['comments'];
									}
								}
								if(!empty($price))
								{
									$other_data[] = array(
										'name'    => book_t('book.item-comments'),
										'display' => $price
									);
								}
							}
						}
					}
				}
				endforeach;
				endif;
				return $other_data;
			}
			
			function order_item_meta( $values,$order) 
			{
				global $wpdb;
				//print_r($values);exit;
				$product_id = $values['product_id'];
				$quantity = $values['quantity'];
				$booking = $values['booking'];
				$booking_settings = get_post_meta($product_id,'woocommerce_booking_settings',true);
				$saved_settings = json_decode(get_option('woocommerce_booking_global_settings'));
				if(isset($saved_settings) && $saved_settings->booking_time_format != '')
				{
					$time_format = $saved_settings->booking_time_format;
				}
				else
				{
					$time_format = '';
				}
				if ($time_format == "" OR $time_format == "NULL") $time_format = "12";
				$date = '';
				$time_slot_to_display = '';
				$order_item_id = $order->order_item_id;
				$order_id = $order->order_id;
				if($booking_settings['booking_enable_time'] == 'on')
				{
					if($booking_settings['booking_enable_multiple_time'] == 'multiple')
					{
						if ($booking[0]['date'] != "")
						{
							$date = $booking[0]['date'];
							//echo $date;
							$name = get_option('book.item-meta-date');
							woocommerce_add_order_item_meta( $order_item_id, $name, sanitize_text_field( $date , true ) );
						}
						if($booking[0]['time_slot'] != "")
						{
							$time_slot = $booking[0]['time_slot'];
							$hidden_date = $booking[0]['hidden_date'];
							$time_exploded = explode("<br>", $time_slot);
							array_shift($time_exploded);
							foreach($time_exploded as $k => $v)
							{
								$time_explode = explode("-",$v);
								$from_time = trim($time_explode[0]);
								
								if (isset($time_explode[1])) $to_time = trim($time_explode[1]);
								else $to_time = '';
								if ($time_format == '12')
								{
									$from_time = date('h:i A', strtotime($time_explode[0]));
									if (isset($time_explode[1])) $to_time = date('h:i A', strtotime($time_explode[1]));
									else $to_time = '';
								}
								if($to_time != '')
								{
									$time_slot_to_display .= $from_time.' - '.$to_time.",";
								}
								else
								{	
									$time_slot_to_display .= $from_time.",";
								}
																
								$date_query = date('Y-m-d',strtotime($booking[0]['hidden_date']));
								//echo $date_query;exit;
								$query_from_time = date('G:i', strtotime($time_explode[0]));
								if (isset($time_explode[1])) $query_to_time = date('G:i', strtotime($time_explode[1]));
								if($query_to_time != '')
								{
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
									foreach($order_results as $k => $v)
									{
										$details[$product_id][] = $v;
									}
								}
								else
								{
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
									foreach($order_results as $k => $v)
									{
										$details[$product_id][] = $v;
									}
							
								}
								//print_r($order_results);
								$i = 0;
								foreach($order_results as $k => $v)
								{	
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
						$lockout_settings = $booking_settings['booking_time_settings'][$hidden_date];
						if($lockout_settings == '')
						{
							$week_day = date('l',strtotime($hidden_date));
							$weekday = array_search($week_day,$this->weekdays);
							$lockout_settings = $booking_settings['booking_time_settings'][$weekday];
						}
						$from_lockout_time = explode(":",$query_from_time);
						$from_hours = $from_lockout_time[0];
						$from_minute = $from_lockout_time[1];
						if($query_to_time != '')
						{
							$to_lockout_time = explode(":",$query_to_time);
							$to_hours = $to_lockout_time[0];
							$to_minute = $to_lockout_time[1];
						}
						else
						{
							$to_hours = '';
							$to_minute = '';
						}
						//print_r($lockout_settings);
						//exit;
						foreach($lockout_settings as $l_key => $l_value)
						{
							if($l_value['from_slot_hrs'] == $from_hours && $l_value['from_slot_min'] == $from_minute && $l_value['to_slot_hrs'] == $to_hours && $l_value['to_slot_min'] == $to_minute)
							{
								$global_timeslot_lockout = $l_value['global_time_check'];
							}
						}
						//print_r($global_timeslot_lockout);exit;
						if($book_global_settings->booking_global_timeslot == 'on' || $global_timeslot_lockout == 'on')
						{
							$args = array( 'post_type' => 'product', 'posts_per_page' => -1 );
							$product = query_posts( $args );
							//print_r($product);exit;
							foreach($product as $k => $v)
							{
								$product_ids[] = $v->ID;
							}
							foreach($product_ids as $k => $v)
							{
								$booking_settings = get_post_meta($v, 'woocommerce_booking_settings' , true);
								if($booking_settings['booking_enable_time'] == 'on')
								{
									//echo "<pre>";print_r($details);echo "</pre>";exit;
									if(!array_key_exists($v,$details))
									{
										foreach($details as $key => $val)
										{
											foreach($val as $v_key => $v_val)
											{
												$booking_settings = get_post_meta($v, 'woocommerce_booking_settings', true);
												//echo"<pre>";print_r($v_val);echo"</pre>";exit;
												$start_date = $v_val->start_date;
												$from_time = $v_val->from_time;
												$to_time = $v_val->to_time;
												if($to_time != "")
												{
													$query = "UPDATE `".$wpdb->prefix."booking_history`
													SET available_booking = available_booking - ".$quantity."
													WHERE post_id = '".$v."' AND
													start_date = '".$date_query."' AND
													from_time = '".$from_time."' AND
													to_time = '".$to_time."' ";
													$updated = $wpdb->query( $query );
													if($updated == 0)
													{
														if($v_val->weekday == '')
														{
															$week_day = date('l',strtotime($date_query));
															$weekday = array_search($week_day,$this->weekdays);
															//echo $weekday;exit;
														}
														else
														{	
															$weekday = $v_val->weekday;
														}
														$query = "SELECT * FROM `".$wpdb->prefix."booking_history`
															WHERE post_id = '".$v."'
															AND weekday = '".$weekday."'";
															//echo $query;exit;
														$results = $wpdb->get_results( $query );
														if (!$results) break;
														else
														{
															//print_r($results);exit;
															foreach($results as $r_key => $r_val)
															{
																if($from_time == $r_val->from_time && $to_time == $r_val->to_time)
																{
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
																else
																{
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
												else
												{
													$query = "UPDATE `".$wpdb->prefix."booking_history`
													SET available_booking = available_booking - ".$quantity."
													WHERE post_id = '".$v."' AND
													start_date = '".$date_query."' AND
													from_time = '".$from_time."'
													AND to_time = ''";
													//$wpdb->query( $query );
													$updated = $wpdb->query( $query );
													if($updated == 0)
													{
														if($v_val->weekday == '')
														{
															$week_day = date('l',strtotime($date_query));
															$weekday = array_search($week_day,$this->weekdays);
															//echo $weekday;exit;
														}
														else
														{
															$weekday = $v_val->weekday;
														}
														$query = "SELECT * FROM `".$wpdb->prefix."booking_history`
															WHERE post_id = '".$v."'
															AND weekday = '".$weekday."'
															AND to_time = '' ";
														$results = $wpdb->get_results( $query );
														if (!$results) break;
														else
														{
															foreach($results as $r_key => $r_val)
															{
																if($from_time == $r_val->from_time)
																{
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
																else
																{
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
						if (isset($booking_settings['booking_partial_payment_enable']) && isset($booking_settings['booking_partial_payment_radio']) && is_plugin_active("bkap-deposits/deposits.php"))
						{
							if (isset($global_settings->enable_rounding) && $global_settings->enable_rounding == "on")
							{
								woocommerce_add_order_item_meta($order_item_id,  book_t('book.item-partial-total'), $currency_symbol.round($values['booking'][0]['Total'] *$values['quantity']), true );
								woocommerce_add_order_item_meta($order_item_id,  book_t('book.item-partial-deposit'), $currency_symbol.round($values['booking'][0]['Deposit']* $values['quantity']), true );
								woocommerce_add_order_item_meta($order_item_id,  book_t('book.item-partial-remaining'), $currency_symbol.round($values['booking'][0]['Remaining']* $values['quantity']), true );
							}
							else
							{
								woocommerce_add_order_item_meta($order_item_id,  book_t('book.item-partial-total'), $currency_symbol.$values['booking'][0]['Total'] *$values['quantity'], true );
								woocommerce_add_order_item_meta($order_item_id,  book_t('book.item-partial-deposit'), $currency_symbol.$values['booking'][0]['Deposit']* $values['quantity'], true );
								woocommerce_add_order_item_meta($order_item_id,  book_t('book.item-partial-remaining'), $currency_symbol.$values['booking'][0]['Remaining']* $values['quantity'], true );
							}
						}
						if(isset($values['booking'][0]['comments']) && !empty($values['booking'][0]['comments']))
						{
							woocommerce_add_order_item_meta($order_item_id,  book_t('book.item-comments'),$values['booking'][0]['comments'], true );
						}
					}
				}
			}

			function bkap_quantity_check($value)
			{
				global $woocommerce, $wpdb;
				$date_check = date('Y-m-d', strtotime($value['booking'][0]['hidden_date']));
				$booking_settings = get_post_meta($value['product_id'] , 'woocommerce_booking_settings' , true);
				$saved_settings = json_decode(get_option('woocommerce_booking_global_settings'));
				$quantity_check_pass = 'true';
				if(isset($saved_settings) && $saved_settings->booking_time_format != '')
				{
					$time_format = $saved_settings->booking_time_format;
				}
				else
				{
					$time_format = '';
				}
				if ($time_format == "" OR $time_format == "NULL") $time_format = "12";
				if($booking_settings['booking_enable_time'] == 'on')
				{
					if($booking_settings['booking_enable_multiple_time'] == 'multiple')
					{
						$time_exploded = explode("<br>", $value['booking'][0]['time_slot']);
						foreach($time_exploded as $k => $v)
						{
							if($v != "")
							{
								$time_explode = explode("-",$v);
								$from_time = date('G:i', strtotime($time_explode[0]));
								if(isset($time_explode[1])) $to_time = date('G:i', strtotime($time_explode[1]));
								else $to_time = '';
								if($to_time != '')
								{
									$query = "SELECT available_booking, start_date,total_booking FROM `".$wpdb->prefix."booking_history`
										WHERE post_id = '".$value['product_id']."'
										AND start_date = '".$date_check."'
										AND from_time = '".$from_time."'
										AND to_time = '".$to_time."' ";
									$results = $wpdb->get_results( $query );
								}
								else
								{
									$query = "SELECT available_booking, start_date, total_booking FROM `".$wpdb->prefix."booking_history`
										WHERE post_id = '".$value['product_id']."'
										AND start_date = '".$date_check."'
										AND from_time = '".$from_time."'";
									$results = $wpdb->get_results( $query );
								}
								if (!$results) break;
								else if(count($results) > 0)
								{
									$post_title = get_post($value['product_id']);
									if($value['booking'][0]['time_slot'] != "")
									{
										// if current format is 12 hour format, then convert the times to 24 hour format to check in database
										if ($time_format == '12')
										{
											$from_time = date('h:i A', strtotime($time_explode[0]));
											if(isset($time_explode[1])) $to_time = date('h:i A', strtotime($time_explode[1]));
											else $to_time = '';
											if($to_time != '')
											{
												$time_slot_to_display = $from_time.' - '.$to_time;
											}
											else
											{
												$time_slot_to_display = $from_time;
											}
										}
										else if ($time_format == '24')
										{
											$from_time = date('H:i ', strtotime($time_explode[0]));
											if(isset($time_explode[1])) $to_time = date('H:i ', strtotime($time_explode[1]));
											else $to_time = '';
											if($to_time != '')
											{
												$time_slot_to_display = $from_time.' - '.$to_time;
											}
											else
											{
												$time_slot_to_display = $from_time;
											}
										}
										if($results[0]->available_booking > 0 && $results[0]->available_booking < $value['quantity']) 
										{
											$message = $post_title->post_title.book_t('book.limited-booking-msg1') .$results[0]->available_booking.book_t('book.limited-booking-msg2').$time_slot_to_display.'.';
											wc_add_notice( $message, $notice_type = 'error');
											/*$woocommerce->add_error( sprintf(__($post_title->post_title.book_t('book.limited-booking-msg1') .$bookings_available.book_t('book.limited-booking-msg2').$time_slot_to_display.'.', 	'woocommerce')) );*/
											$quantity_check_pass = 'false';
										}
										elseif ( $results[0]->total_booking > 0 && $results[0]->available_booking  == 0 )
										{
											$message = book_t('book.no-booking-msg1').$post_title->post_title.book_t('book.no-booking-msg2').$time_slot_to_display.book_t('book.no-booking-msg3');
											wc_add_notice( $message, $notice_type = 'error');
											/*$woocommerce->add_error( sprintf(__(book_t('book.no-booking-msg1').$post_title->post_title.book_t('book.no-booking-msg2').$time_slot_to_display.book_t('book.no-booking-msg3'), 'woocommerce')) );*/
											$quantity_check_pass = 'false';
										}
									}
								}
							}
						}
					}
				}
			}
			function bkap_quantity($post,$post_id)
			{
				global $woocommerce, $wpdb;
				$date_check = date('Y-m-d', strtotime($post['wapbk_hidden_date']));
				$booking_settings = get_post_meta($post_id , 'woocommerce_booking_settings' , true);
				$saved_settings = json_decode(get_option('woocommerce_booking_global_settings'));
				$quantity_check_pass = 'true';
				if(isset($saved_settings) && $saved_settings->booking_time_format != '')
				{
					$time_format = $saved_settings->booking_time_format;
				}
				else
				{
					$time_format = '';
				}
				if ($time_format == "" OR $time_format == "NULL") $time_format = "12";
				if($booking_settings['booking_enable_time'] == 'on')
				{
					if($booking_settings['booking_enable_multiple_time'] == 'multiple')
					{
						$time_exploded = $post['timeslot'];
						foreach($time_exploded as $k => $v)
						{
							if($v != "")
							{
								$time_explode = explode("-",$v);
								$from_time = date('G:i', strtotime($time_explode[0]));
								if(isset($time_explode[1])) $to_time = date('G:i', strtotime($time_explode[1]));
								else $to_time = '';
								if($to_time != '')
								{
									$query = "SELECT available_booking, start_date,total_booking FROM `".$wpdb->prefix."booking_history`
										WHERE post_id = '".$post_id."'
										AND start_date = '".$date_check."'
										AND from_time = '".$from_time."'
										AND to_time = '".$to_time."' ";
									$results = $wpdb->get_results( $query );
								}
								else
								{
									$query = "SELECT available_booking, start_date, total_booking FROM `".$wpdb->prefix."booking_history`
										WHERE post_id = '".$post_id."'
										AND start_date = '".$date_check."'
										AND from_time = '".$from_time."'";
									$results = $wpdb->get_results( $query );
								}
								if (!$results) break;
								else if(count($results) > 0)
								{
									$post_title = get_post($post_id);
									if($post['timeslot'] != "")
									{
										// if current format is 12 hour format, then convert the times to 24 hour format to check in database
										if ($time_format == '12')
										{
											$from_time = date('h:i A', strtotime($time_explode[0]));
											if(isset($time_explode[1])) $to_time = date('h:i A', strtotime($time_explode[1]));
											else $to_time = '';
											if($to_time != '')
											{
												$time_slot_to_display = $from_time.' - '.$to_time;
											}
											else
											{
												$time_slot_to_display = $from_time;
											}
										}
										else if ($time_format == '24')
										{
											$from_time = date('H:i ', strtotime($time_explode[0]));
											if(isset($time_explode[1])) $to_time = date('H:i ', strtotime($time_explode[1]));
											else $to_time = '';
											if($to_time != '')
											{
												$time_slot_to_display = $from_time.' - '.$to_time;
											}
											else
											{
												$time_slot_to_display = $from_time;
											}
										}
										if( $results[0]->available_booking > 0 && $results[0]->available_booking < $post['quantity'])
										{
											$message = $post_title->post_title.book_t('book.limited-booking-msg1') .$results[0]->available_booking.book_t('book.limited-booking-msg2').$time_slot_to_display.'.';
											wc_add_notice( $message, $notice_type = 'error');
											$quantity_check_pass = 'false';
										}
										elseif ( $results[0]->total_booking > 0 && $results[0]->available_booking == 0 )
										{
											$message = book_t('book.no-booking-msg1').$post_title->post_title.book_t('book.no-booking-msg2').$time_slot_to_display.book_t('book.no-booking-msg3');
											wc_add_notice( $message, $notice_type = 'error');
											/*$woocommerce->add_error( sprintf(__(book_t('book.no-booking-msg1').$post_title->post_title.book_t('book.no-booking-msg2').$time_slot_to_display.book_t('book.no-booking-msg3'), 'woocommerce')) );*/
											$quantity_check_pass = 'false';
										}
										if ($quantity_check_pass == "true")
										{
											foreach ( $woocommerce->cart->get_cart() as $cart_item_key => $values )
											{
												$booking = $values['booking'];
												$quantity = $values['quantity'];
												$product_id = $values['product_id'];
												//print_r($values);exit;
												if ($product_id == $post_id && $booking[0]['wapbk_hidden_date'] == $post['wapbk_hidden_date'] && $booking[0]['time_slot'] == $post['time_slot'])
												{
													$total_quantity = $post['quantity'] + $quantity;
													//echo $total_quantity;exit;
													if ($results[0]->available_booking > 0 && $results[0]->available_booking < $post['quantity'])
													{
														$message = $post_title->post_title.book_t('book.limited-booking-msg1') .$results[0]->available_booking.book_t('book.limited-booking-msg2').$time_slot_to_display.'.';
														wc_add_notice( $message, $notice_type = 'error');
														$quantity_check_pass = 'false';
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
				return $quantity_check_pass;
			}
				function bkap_cancel_order($order_id,$item_value,$booking_id)
				{
					global $wpdb;
					$product_id = $item_value['product_id'];
					$quantity = $item_value['qty'];
					$booking_settings = get_post_meta($product_id, 'woocommerce_booking_settings', true);
					if($booking_settings['booking_enable_time'] == 'on')
					{
						if($booking_settings['booking_enable_multiple_time'] == 'multiple')
						{
							$select_data_query = "SELECT * FROM `".$wpdb->prefix."booking_history`
												WHERE id='".$booking_id."'";
												echo $select_data_query;
							$results_data = $wpdb->get_results ( $select_data_query );
							$j=0;
							foreach($results_data as $k => $v)
							{
								$start_date = $results_data[$j]->start_date;
								$from_time = $results_data[$j]->from_time;
								$to_time = $results_data[$j]->to_time;
								if($from_time != '' && $to_time != '' || $from_time != '')
								{
									if($to_time != '')
									{
										$query = "UPDATE `".$wpdb->prefix."booking_history`
													SET available_booking = available_booking + ".$quantity."
													WHERE 
													id = '".$booking_id."' AND
													start_date = '".$start_date."' AND
													from_time = '".$from_time."' AND
													to_time = '".$to_time."' AND 
													post_id = '".$product_id."'";
												
									}
									else
									{
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