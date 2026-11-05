<?php
/**
 * WH Tattoo theme functions.
 *
 * @package WH_Tattoo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Подключение стилей и скриптов темы.
 */
function wh_tattoo_enqueue_assets(): void {
	$theme = wp_get_theme();

	wp_enqueue_style(
		'wh-tattoo-theme',
		get_theme_file_uri( '/assets/css/theme.css' ),
		array(),
		$theme->get( 'Version' )
	);

	wp_enqueue_script(
		'wh-tattoo-theme',
		get_theme_file_uri( '/assets/js/theme.js' ),
		array(),
		$theme->get( 'Version' ),
		true
	);
}

add_action( 'wp_enqueue_scripts', 'wh_tattoo_enqueue_assets' );

/**
 * Настройки и возможности темы.
 */
function wh_tattoo_setup(): void {
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );

	add_editor_style( 'assets/css/editor.css' );
}

add_action( 'after_setup_theme', 'wh_tattoo_setup' );