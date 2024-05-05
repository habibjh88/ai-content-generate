<?php

namespace DOWP\AiContentGenerate\Controllers;

/**
 * BlocksController class
 */
class BlocksController {

	/**
	 * Css Handler to generate dynamic ss for guten blocks
	 */
	private $version;

	/**
	 * Class Constructor
	 */
	public function __construct() {
		$this->version = defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : AI_CONTENT_VERSION;
		add_action( 'enqueue_block_editor_assets', [ $this, 'editor_assets' ] );
	}

	/**
	 * Load Editor Assets
	 *
	 * @return void
	 */
	public function editor_assets() {
		$deps_file = AI_CONTENT_PLUGIN_PATH . 'assets/block/main.asset.php';

		/*Fallback dependency array*/
		$dependency = [];
		$version    = $this->version;

		/*Set dependency and version*/
		if ( file_exists( $deps_file ) ) {
			$deps_file  = require( $deps_file );
			$dependency = $deps_file['dependencies'];
			$version    = $deps_file['version'];
		}
		//Block editor css

		//Main compile css and js file
		wp_enqueue_style( 'dowp-blocks-css', dowpAIC()->get_assets_uri( 'blocks/main.css' ), '', $this->version );
		wp_enqueue_script( 'dowp-blocks-js', dowpAIC()->get_assets_uri( 'blocks/main.js' ), $dependency, $version, true );

		global $pagenow;
		$editor_type = 'edit-post';

		if ( 'site-editor.php' === $pagenow ) {
			$editor_type = 'edit-site';
		}

		wp_localize_script( 'dowp-blocks-js', 'dowpParams', [
				'editor_type'     => $editor_type,
				'nonce'           => wp_create_nonce( 'dowp_nonce' ),
				'ajaxurl'         => admin_url( 'admin-ajax.php' ),
				'site_url'        => site_url(),
				'admin_url'       => admin_url(),
				'plugin_url'      => AI_CONTENT_PLUGIN_URL,
				'current_user_id' => get_current_user_id(),
				'avatar'          => esc_url( get_avatar_url( get_current_user_id() ) )
			]
		);

	}

}