<?php

namespace DOWP\AiContentGenerate\Admin;

class AdminHooks {

	public function __construct() {
		add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
		add_action( 'in_admin_header', [ __CLASS__, 'remove_all_notices' ], 9999 );
	}

	public static function register_menu() {
		add_menu_page(
			esc_html__( 'AI Content Generate', 'ai-content-generate' ),
			esc_html__( 'AI Content Generate', 'ai-content-generate' ),
			'manage_options',
			'ai-content-generate-settings',
			[ __CLASS__, 'ai_content_settings' ],
			'dashicons-welcome-write-blog', //AI_CONTENT_PLUGIN_URL.'/assets/img/icon.png',
			6
		);
	}

	public static function ai_content_settings() {
//		require_once trailingslashit(AI_CONTENT_PLUGIN_PATH) . 'views/html-admin-ai-content-generates.php';
	?>
		<div class="wrap ai-content-generate-wrap">
			<div id="ai-content-generate-app"></div>
		</div>
<?php
	}

	public static function remove_all_notices() {
		$screen = get_current_screen();
		if ( isset( $screen->base ) && ( 'ai-content-generates_page_ai-content-generate-settings' == $screen->base || 'toplevel_page_ai-content-generate' == $screen->base ) ) {
			remove_all_actions( 'admin_notices' );
			remove_all_actions( 'all_admin_notices' );
		}
	}

}