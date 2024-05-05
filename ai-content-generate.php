<?php
/**
 * Plugin Name: AI Content Generate
 * Plugin URI: https://www.devofwp.com/downloads/ai-content-generate/
 * Description: Write your content seamlessly with the AI Content Generate plugin, integrating ChatGPT into WordPress's Gutenberg editor for effortless AI-generated content creation.
 * Author: DevOfWP
 * Version: 1.0.0
 * Text Domain: ai-content-generate
 * Domain Path: /languages
 * Author URI: https://devofwp.com/
 *
 * @package DOWP
 */

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

define( 'AI_CONTENT_VERSION', '1.0.0' );
define( 'AI_CONTENT_AUTHOR', 'DevOfWP' );
define( 'AI_CONTENT_NAME', 'AI Content Generate' );
define( 'AI_CONTENT_PLUGIN_FILE', __FILE__ );
define( 'AI_CONTENT_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'AI_CONTENT_PLUGIN_ACTIVE_FILE_NAME', plugin_basename( __FILE__ ) );
define( 'AI_CONTENT_PLUGIN_URL', plugins_url( '', __FILE__ ) );
define( 'AI_CONTENT_PLUGIN_SLUG', basename( dirname( __FILE__ ) ) );
define( 'AI_CONTENT_LANGUAGE_PATH', dirname( plugin_basename( __FILE__ ) ) . '/languages' );

add_action( 'plugins_loaded', 'ai_content_load_plugin_textdomain' );
function ai_content_load_plugin_textdomain() {
	load_plugin_textdomain( 'ai-content-generate' );
}

if ( ! class_exists( 'dowpAIC' ) ) {
	require_once 'app/Init.php';
}
