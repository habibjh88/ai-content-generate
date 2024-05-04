<?php
/**
 * Main initialization class.
 *
 * @package DOWP
 */

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

require_once AI_CONTENT_PLUGIN_PATH . '/vendor/autoload.php';

use DOWP\AiContentGenerate\Api\RestApi;
use DOWP\AiContentGenerate\Api\SettingsApi;
use DOWP\AiContentGenerate\Controllers\BlocksController;
use DOWP\AiContentGenerate\Controllers\ScriptController;
use DOWP\AiContentGenerate\Admin\AdminHooks;

if ( ! class_exists( dwpAIC::class ) ) {
	/**
	 * Main initialization class.
	 */
	final class dwpAIC {


		/**
		 * Store the singleton object.
		 *
		 * @var boolean
		 */
		private static $singleton = false;

		/**
		 * Create an inaccessible constructor.
		 */
		private function __construct() {
			$this->__init();
		}

		/**
		 * Fetch an instance of the class.
		 */
		public static function getInstance() {
			if ( false === self::$singleton ) {
				self::$singleton = new self();
			}

			return self::$singleton;
		}

		/**
		 * Class init
		 *
		 * @return void
		 */
		protected function __init() {
			new RestApi();
			new SettingsApi();
			new ScriptController();
			new BlocksController();
			new AdminHooks();
		}

		/**
		 * Get the plugin path.
		 *
		 * @return string
		 */
		public function plugin_path() {
			return untrailingslashit( plugin_dir_path( AI_CONTENT_PLUGIN_FILE ) );
		}

		/**
		 * Plugin template path
		 *
		 * @return string
		 */
		public function plugin_template_path() {
			$plugin_template = $this->plugin_path() . '/templates/';

			return apply_filters( 'tlp_tpg_template_path', $plugin_template );
		}

		/**
		 * Default template path
		 *
		 * @return string
		 */
		public function default_template_path() {
			return apply_filters( 'dowp_default_template_path', untrailingslashit( plugin_dir_path( AI_CONTENT_PLUGIN_FILE ) ) );
		}

		/**
		 * Nonce text
		 *
		 * @return string
		 */
		public static function nonceText() {
			return 'dowp_nonce_secret';
		}

		/**
		 * Nonce ID
		 *
		 * @return string
		 */
		public static function nonceId() {
			return 'dowp_nonce';
		}

		/**
		 * Get assets URI
		 *
		 * @param string $file File.
		 *
		 * @return string
		 */
		public function get_assets_uri( $file ) {
			$file = ltrim( $file, '/' );

			return trailingslashit( AI_CONTENT_PLUGIN_URL . '/assets' ) . $file;
		}


		/**
		 * Get the template path.
		 *
		 * @return string
		 */
		public function get_template_path() {
			return apply_filters( 'dowp_template_path', 'ai-content-generate/' );
		}


	}

	/**
	 * Function for external use.
	 *
	 * @return dwpAIC
	 */
	function dwpAIC() {
		return dwpAIC::getInstance();
	}

	// Init app.
	dwpAIC();
}
