<?php
/**
 * Ручной выбор отзывов для сайта.
 *
 * @package WH_Reviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WH_Reviews_Selection {

	/**
	 * Подключает хуки.
	 */
	public static function init(): void {
		add_action(
			'add_meta_boxes_wh_review',
			array( __CLASS__, 'add_meta_box' )
		);

		add_action(
			'save_post_wh_review',
			array( __CLASS__, 'save_meta' )
		);

		add_filter(
			'manage_wh_review_posts_columns',
			array( __CLASS__, 'add_admin_columns' )
		);

		add_action(
			'manage_wh_review_posts_custom_column',
			array( __CLASS__, 'render_admin_column' ),
			10,
			2
		);
	}

	/**
	 * Добавляет настройки показа.
	 */
	public static function add_meta_box(): void {
		add_meta_box(
			'wh-review-display',
			'Показ отзыва',
			array( __CLASS__, 'render_meta_box' ),
			'wh_review',
			'side',
			'high'
		);
	}

	/**
	 * Выводит настройки показа.
	 *
	 * @param WP_Post $post Текущий отзыв.
	 */
	public static function render_meta_box(
		WP_Post $post
	): void {
		$visible = get_post_meta(
			$post->ID,
			'_wh_review_visible',
			true
		);

		$featured = get_post_meta(
			$post->ID,
			'_wh_review_featured',
			true
		);

		$order = absint(
			get_post_meta(
				$post->ID,
				'_wh_review_order',
				true
			)
		);

		wp_nonce_field(
			'wh_reviews_save_selection',
			'wh_reviews_selection_nonce'
		);
		?>
		<p>
			<label>
				<input
					type="checkbox"
					name="wh_review_visible"
					value="1"
					<?php checked( $visible, '1' ); ?>
				>
				Разрешить показ на сайте
			</label>
		</p>

		<p>
			<label>
				<input
					type="checkbox"
					name="wh_review_featured"
					value="1"
					<?php checked( $featured, '1' ); ?>
				>
				Показывать на главной
			</label>
		</p>

		<p>
			<label for="wh-review-order">
				<strong>Порядок на главной</strong>
			</label>
		</p>

		<p>
			<input
				type="number"
				id="wh-review-order"
				name="wh_review_order"
				value="<?php echo esc_attr(
					(string) $order
				); ?>"
				min="0"
				max="999"
				step="1"
				class="small-text"
			>
		</p>

		<p class="description">
			Меньшее число выводится раньше.
			Для шести отзывов можно использовать
			10, 20, 30, 40, 50 и 60.
		</p>
		<?php
	}

	/**
	 * Сохраняет настройки.
	 *
	 * @param int $post_id ID отзыва.
	 */
	public static function save_meta(
		int $post_id
	): void {
		if (
			! isset(
				$_POST['wh_reviews_selection_nonce']
			)
		) {
			return;
		}

		$nonce = sanitize_text_field(
			wp_unslash(
				$_POST['wh_reviews_selection_nonce']
			)
		);

		if (
			! wp_verify_nonce(
				$nonce,
				'wh_reviews_save_selection'
			)
		) {
			return;
		}

		if (
			defined( 'DOING_AUTOSAVE' )
			&& DOING_AUTOSAVE
		) {
			return;
		}

		if (
			! current_user_can(
				'edit_post',
				$post_id
			)
		) {
			return;
		}

		update_post_meta(
			$post_id,
			'_wh_review_visible',
			isset( $_POST['wh_review_visible'] )
				? '1'
				: '0'
		);

		update_post_meta(
			$post_id,
			'_wh_review_featured',
			isset( $_POST['wh_review_featured'] )
				? '1'
				: '0'
		);

		$order = isset(
			$_POST['wh_review_order']
		)
			? absint(
				$_POST['wh_review_order']
			)
			: 0;

		update_post_meta(
			$post_id,
			'_wh_review_order',
			min( $order, 999 )
		);
	}

	/**
	 * Добавляет колонки в список.
	 *
	 * @param array<string, string> $columns Колонки.
	 *
	 * @return array<string, string>
	 */
	public static function add_admin_columns(
		array $columns
	): array {
		$updated = array();

		foreach ( $columns as $key => $label ) {
			$updated[ $key ] = $label;

			if ( 'title' === $key ) {
				$updated['wh_rating'] = 'Оценка';
				$updated['wh_featured'] = 'Главная';
				$updated['wh_order'] = 'Порядок';
			}
		}

		return $updated;
	}

	/**
	 * Выводит данные колонки.
	 *
	 * @param string $column  Колонка.
	 * @param int    $post_id ID отзыва.
	 */
	public static function render_admin_column(
		string $column,
		int $post_id
	): void {
		if ( 'wh_rating' === $column ) {
			$rating = absint(
				get_post_meta(
					$post_id,
					'_wh_review_rating',
					true
				)
			);

			echo esc_html(
				str_repeat( '★', $rating )
			);

			return;
		}

		if ( 'wh_featured' === $column ) {
			$featured = get_post_meta(
				$post_id,
				'_wh_review_featured',
				true
			);

			echo '1' === $featured
				? '<strong style="color:#008a20">Да</strong>'
				: 'Нет';

			return;
		}

		if ( 'wh_order' === $column ) {
			echo esc_html(
				(string) absint(
					get_post_meta(
						$post_id,
						'_wh_review_order',
						true
					)
				)
			);
		}
	}
}