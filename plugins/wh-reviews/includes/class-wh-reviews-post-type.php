<?php
/**
 * Регистрация типа записей отзывов.
 *
 * @package WH_Reviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WH_Reviews_Post_Type {

	/**
	 * Подключает хуки.
	 */
	public static function init(): void {
		add_action(
			'init',
			array( __CLASS__, 'register' )
		);
	}

	/**
	 * Регистрирует отзывы.
	 */
	public static function register(): void {
		$labels = array(
			'name'               => 'Отзывы',
			'singular_name'      => 'Отзыв',
			'menu_name'          => 'Отзывы',
			'name_admin_bar'     => 'Отзыв',
			'add_new'            => 'Добавить вручную',
			'add_new_item'       => 'Добавить отзыв',
			'edit_item'          => 'Редактировать отзыв',
			'new_item'           => 'Новый отзыв',
			'view_item'          => 'Посмотреть отзыв',
			'all_items'          => 'Все отзывы',
			'search_items'       => 'Найти отзыв',
			'not_found'          => 'Отзывы не найдены',
			'not_found_in_trash' => 'В корзине отзывов нет',
		);

		register_post_type(
			'wh_review',
			array(
				'labels'              => $labels,
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => false,
				'menu_icon'           => 'dashicons-star-filled',
				'menu_position'       => 6,
				'supports'            => array(
					'title',
					'editor',
					'thumbnail',
				),
			)
		);
	}
}
