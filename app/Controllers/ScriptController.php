<?php
/**
 * Script Controller class.
 */

namespace DOWP\AiContentGenerate\Controllers;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * Script Controller class.
 */
class ScriptController {
	/**
	 * Version
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Settings
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Class construct
	 */
	public function __construct() {
		$this->version = defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : AI_CONTENT_VERSION;
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'load_admin_script' ] );
	}


	/**
	 * Enqueue scripts.
	 *
	 * @return void
	 */
	public function enqueue() {

	}


	public function load_admin_script() {

		$deps_file = AI_CONTENT_PLUGIN_PATH . 'assets/settings/main.asset.php';

		/*Fallback dependency array*/
		$dependency = [];
		$version = $this->version;

		/*Set dependency and version*/
		if ( file_exists( $deps_file ) ) {
			$deps_file = require( $deps_file );
			$dependency      = $deps_file['dependencies'];
			$version      = $deps_file['version'];
		}


		wp_enqueue_style( 'ai-content-generate-admin', AI_CONTENT_PLUGIN_URL . '/assets/css/settings-admin.css', null, $this->version );

		wp_register_script( 'ai-content-generate-admin', AI_CONTENT_PLUGIN_URL . "/assets/settings/main.js", $dependency, $version, true );

		$localize_data = apply_filters( 'ai_content_listing_admin_localize_options', [
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'rest_root'    => esc_url_raw( rest_url() ),
			'claim_status' => [
				'pending'   => __( 'Pending', 'ai-content-generate' ),
				'approved'  => __( 'Approved', 'ai-content-generate' ),
				//'completed' => __( 'Completed', 'ai-content-generate' ),
				'cancelled' => __( 'Cancelled', 'ai-content-generate' ),
			]
		] );

		wp_localize_script( 'ai-content-generate-admin', 'ai_content', $localize_data );

		wp_enqueue_script( 'ai-content-generate-admin' );
	}
}
