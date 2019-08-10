<?php

// uncomment this line for testing
//set_site_transient( 'update_plugins', null );

/**
 * Allows plugins to use their own update API.
 *
 * @author Ashok Rane
 * @version 2.1
 */
class EDD_MULTIPLE_TIMESLOT_BOOK_Plugin_Updater {
	private $api_url  = 'http://www.tychesoftwares.com/';
	private $api_data = array();
	private $name     = 'Multiple Time Slot addon for WooCommerce Booking and Appointment Plugin';
	private $slug     = 'multiple_timeslot';

	/**
	 * Class constructor.
	 *
	 * @uses plugin_basename()
	 * @uses hook()
	 *
	 * @param string $_api_url The URL pointing to the custom API endpoint.
	 * @param string $_plugin_file Path to the plugin file.
	 * @param array $_api_data Optional data to send with API calls.
	 * @return void
	 */
	function __construct( $_api_url, $_plugin_file, $_api_data = null ) {
		$this->api_url  = trailingslashit( $_api_url );
		$this->api_data = urlencode_deep( $_api_data );
		$this->name     = plugin_basename( $_plugin_file );
		$this->slug     = basename( $_plugin_file, '.php');
		$this->version  = $_api_data['version'];

		// Set up hooks.
		$this->hook();
	}

	/**
	 * Set up Wordpress filters to hook into WP's update process.
	 *
	 * @uses add_filter()
	 *
	 * @return void
	 */
	private function hook() {
		add_action( 'admin_init', 								array( &$this, 'edd_sample_register_option_multiple_timeslot' ) );
		add_action( 'admin_init', 								array( &$this, 'edd_sample_deactivate_license_multiple_timeslot' ) );
		add_action( 'admin_init', 								array( $this, 'edd_sample_activate_license_multiple_timeslot' ) );
		add_filter( 'pre_set_site_transient_update_plugins', 	array( $this, 'pre_set_site_transient_update_plugins_filter' ) );
		add_filter( 'plugins_api', 								array( $this, 'plugins_api_filter' ), 10, 3 );
	}

	function edd_sample_register_option_multiple_timeslot() {
		// creates our settings in the options table
		register_setting( 	'edd_multiple_timeslot_license',
							'edd_sample_license_key_multiple_timeslot_book',
							array( &$this, 'edd_sanitize_license_multiple_timeslot' )
						);
	}

	function edd_sanitize_license_multiple_timeslot( $new ) {
		$old = get_option( 'edd_sample_license_key_multiple_timeslot_book' );
		if ( $old && $old != $new ) {
			delete_option( 'edd_sample_license_status_multiple_timeslot_book' ); // new license has been entered, so must reactivate
		}
		return $new;
	}

	/**
	 * Illustrates how to deactivate a license key. This will descrease the site count
	 */
	
	function edd_sample_deactivate_license_multiple_timeslot() {
		// listen for our activate button to be clicked
		if ( isset( $_POST['edd_license_deactivate'] ) )
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

	function edd_sample_activate_license_multiple_timeslot() {
		// listen for our activate button to be clicked
		if ( isset( $_POST['edd_license_activate'] ) ) {
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

	/**
	 * this illustrates how to check if a license key is still valid the updater does this for you, so this is only needed if you want to do something custom
	 */
	
	function edd_sample_check_license() {
			
		$license 	= trim( get_option( 'edd_sample_license_key_multiple_timeslot_book' ) );
			
		$api_params = array(	'edd_action' => 'check_license',
								'license' => $license,
								'item_name' => urlencode( EDD_SL_ITEM_NAME_MULTIPLE_TIMESLOT_BOOK )
							);
			
		// Call the custom API.
		$response 	= wp_remote_get( add_query_arg( $api_params, EDD_SL_STORE_URL_MULTIPLE_TIMESLOT_BOOK ), array( 'timeout' => 15, 'sslverify' => false ) );
			
			
		if ( is_wp_error( $response ) )
			return false;
			
		$license_data = json_decode( wp_remote_retrieve_body( $response ) );
			
		if ( $license_data->license == 'valid' ) {
			echo 'valid'; exit; // this license is still valid					
		} else {
			echo 'invalid'; exit; // this license is no longer valid					
		}
	}

	/**
	 * Check for Updates at the defined API endpoint and modify the update array.
	 *
	 * This function dives into the update api just when Wordpress creates its update array,
	 * then adds a custom API call and injects the custom plugin data retrieved from the API.
	 * It is reassembled from parts of the native Wordpress plugin update code.
	 * See wp-includes/update.php line 121 for the original wp_update_plugins() function.
	 *
	 * @uses api_request()
	 *
	 * @param array $_transient_data Update array build by Wordpress.
	 * @return array Modified update array with custom plugin data.
	 */
	function pre_set_site_transient_update_plugins_filter( $_transient_data ) {


		if( empty( $_transient_data ) ) return $_transient_data;

		$to_send = array( 'slug' => $this->slug );

		$api_response = $this->api_request( 'plugin_latest_version', $to_send );

		if( false !== $api_response && is_object( $api_response ) && isset( $api_response->new_version ) ) {
			if( version_compare( $this->version, $api_response->new_version, '<' ) )
				$_transient_data->response[$this->name] = $api_response;
	}
		return $_transient_data;
	}


	/**
	 * Updates information on the "View version x.x details" page with custom data.
	 *
	 * @uses api_request()
	 *
	 * @param mixed $_data
	 * @param string $_action
	 * @param object $_args
	 * @return object $_data
	 */
	function plugins_api_filter( $_data, $_action = '', $_args = null ) {
		if ( ( $_action != 'plugin_information' ) || !isset( $_args->slug ) || ( $_args->slug != $this->slug ) ) return $_data;

		$to_send = array( 'slug' => $this->slug );

		$api_response = $this->api_request( 'plugin_information', $to_send );
		if ( false !== $api_response ) $_data = $api_response;

		return $_data;
	}

	/**
	 * Calls the API and, if successfull, returns the object delivered by the API.
	 *
	 * @uses get_bloginfo()
	 * @uses wp_remote_post()
	 * @uses is_wp_error()
	 *
	 * @param string $_action The requested action.
	 * @param array $_data Parameters for the API action.
	 * @return false||object
	 */
	private function api_request( $_action, $_data ) {

		global $wp_version;

		$data = array_merge( $this->api_data, $_data );

		if( $data['slug'] != $this->slug )
			return;

		if( empty( $data['license'] ) )
			return;

		$api_params = array(
			'edd_action' 	=> 'get_version',
			'license' 		=> $data['license'],
			'name' 			=> $data['item_name'],
			'slug' 			=> $this->slug,
			'author'		=> $data['author']
		);
		$request = wp_remote_post( $this->api_url, array( 'timeout' => 15, 'sslverify' => false, 'body' => $api_params ) );

		if ( ! is_wp_error( $request ) ):
			$request = json_decode( wp_remote_retrieve_body( $request ) );
			if( $request && isset( $request->sections ) )
				$request->sections = maybe_unserialize( $request->sections );
			return $request;
		else:
			return false;
		endif;
	}
}