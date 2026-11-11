<?php
/**
 * Plugin Name: WH Reviews
 * Description: Импорт, хранение и вывод отзывов студии WH.
 * Version: 0.4.3
 * Author: WH Tattoo
 * Requires PHP: 8.1
 * Text Domain: wh-reviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WH_REVIEWS_VERSION', '0.4.3' );
define( 'WH_REVIEWS_FILE', __FILE__ );
define( 'WH_REVIEWS_PATH', plugin_dir_path( __FILE__ ) );
define( 'WH_REVIEWS_URL', plugin_dir_url( __FILE__ ) );

require_once WH_REVIEWS_PATH . 'includes/class-wh-reviews-post-type.php';
require_once WH_REVIEWS_PATH . 'includes/class-wh-reviews-admin.php';
require_once WH_REVIEWS_PATH . 'includes/class-wh-reviews-importer.php';
require_once WH_REVIEWS_PATH . 'includes/class-wh-reviews-shortcode.php';
require_once WH_REVIEWS_PATH . 'includes/class-wh-reviews-selection.php';

/**
 * Запускает компоненты плагина.
 */
function wh_reviews_initialize(): void {
	WH_Reviews_Post_Type::init();
	WH_Reviews_Admin::init();
	WH_Reviews_Shortcode::init();
	WH_Reviews_Selection::init();
}

add_action( 'plugins_loaded', 'wh_reviews_initialize' );

/**
 * Выполняется при активации плагина.
 */
function wh_reviews_activate(): void {
	WH_Reviews_Post_Type::register();
	flush_rewrite_rules();
}

register_activation_hook( WH_REVIEWS_FILE, 'wh_reviews_activate' );

/**
 * Выполняется при деактивации плагина.
 */
function wh_reviews_deactivate(): void {
	flush_rewrite_rules();
}

register_deactivation_hook( WH_REVIEWS_FILE, 'wh_reviews_deactivate' );
