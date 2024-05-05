<?php

namespace DOWP\AiContentGenerate\Api;

use WP_REST_Request;
use WP_REST_Server;

/**
 * SettingsApi Class
 */
class SettingsApi {

	/**
	 * Class constructor
	 */
	public function __construct() {
		add_action( "rest_api_init", [ $this, 'register_post_route' ] );
	}


	/**
	 * Rest API Callback
	 *
	 * @return void
	 */
	public function register_post_route() {
		register_rest_route( 'dowp/v1', 'settings', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_settings' ],
			'permission_callback' => [ $this, 'permission_check' ]
		] );

		register_rest_route( 'dowp/v1', 'settings', [
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => [ $this, 'update_settings' ],
			'permission_callback' => [ $this, 'permission_check' ]
		] );
	}

	/**
	 * Get Settings
	 *
	 * @return void
	 */
	public function get_settings() {
		$settings = get_option( 'dowp_aicg_settings' );

		wp_send_json( $settings );
	}

	/**
	 * Update Options
	 *
	 * @param $request
	 *
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response
	 */
	public function update_settings( $request ) {

		if ( ! $request->get_param( 'settings' ) ) {
			$response = [
				'status'        => "error",
				'error'         => 'BADREQUEST',
				'code'          => '400',
				'error_message' => esc_html__( 'Settings data not found.', "dowp-listing" )
			];
			wp_send_json( $response, 400 );
		}

		$success = false;
		$message = esc_html__( 'Something wrong!', 'dowp-listing' );

		$options = array_map( 'sanitize_text_field', $request->get_param( 'settings' ) );

		if ( ! empty( $options ) ) {
			update_option( 'dowp_aicg_settings', $options );
			delete_transient( 'dowp_options' );
			$success = true;
			$message = esc_html__( 'Updated settings successfully', 'dowp-listing' );
		}

		$response = [
			'success' => $success,
			'msg'     => $message
		];

		return rest_ensure_response( $response );
	}

	/**
	 * @param WP_REST_Request $request
	 *
	 * @return bool
	 */
	public function permission_check( WP_REST_Request $request ) {
		//return true;

		return current_user_can( 'manage_options' );
	}
}