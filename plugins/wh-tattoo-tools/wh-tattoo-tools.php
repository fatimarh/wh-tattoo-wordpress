<?php
/**
 * Plugin Name: WH Tattoo Tools
 * Description: Работы и направления портфолио сайта WH Tattoo.
 * Version: 0.5.0
 * Author: WH Tattoo
 * Requires PHP: 8.1
 * Text Domain: wh-tattoo-tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Регистрирует направления татуировок.
 */
function wh_tattoo_register_style_taxonomy(): void {
	$labels = array(
		'name'              => 'Направления',
		'singular_name'     => 'Направление',
		'search_items'      => 'Найти направление',
		'all_items'         => 'Все направления',
		'edit_item'         => 'Редактировать направление',
		'update_item'       => 'Обновить направление',
		'add_new_item'      => 'Добавить направление',
		'new_item_name'     => 'Название направления',
		'menu_name'         => 'Направления',
	);

	register_taxonomy(
		'tattoo_style',
		array( 'portfolio' ),
		array(
			'labels'            => $labels,
			'public'            => true,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'hierarchical'      => true,
			'rewrite'           => array(
				'slug'       => 'style',
				'with_front' => false,
			),
		)
	);
}

/**
 * Регистрирует работы портфолио.
 */
function wh_tattoo_register_portfolio(): void {
	$labels = array(
		'name'               => 'Работы',
		'singular_name'      => 'Работа',
		'menu_name'          => 'Работы',
		'name_admin_bar'     => 'Работа',
		'add_new'            => 'Добавить работу',
		'add_new_item'       => 'Добавить новую работу',
		'new_item'           => 'Новая работа',
		'edit_item'          => 'Редактировать работу',
		'view_item'          => 'Посмотреть работу',
		'all_items'          => 'Все работы',
		'search_items'       => 'Найти работу',
		'not_found'          => 'Работы не найдены',
		'not_found_in_trash' => 'В корзине работы не найдены',
	);

	register_post_type(
		'portfolio',
		array(
			'labels'             => $labels,
			'public'             => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'publicly_queryable' => true,
			'has_archive'        => true,
			'menu_position'      => 5,
			'menu_icon'          => 'dashicons-format-gallery',
			'query_var'          => true,
			'rewrite'            => array(
				'slug'       => 'portfolio',
				'with_front' => false,
			),
			'supports'           => array(
				'title',
				'editor',
				'thumbnail',
				'excerpt',
			),
			'taxonomies'         => array(
				'tattoo_style',
			),
		)
	);
}

/**
 * Регистрирует функциональность портфолио.
 */
function wh_tattoo_register_content(): void {
	wh_tattoo_register_style_taxonomy();
	wh_tattoo_register_portfolio();
}

add_action( 'init', 'wh_tattoo_register_content' );

/**
 * Обновляет постоянные ссылки при активации.
 */
function wh_tattoo_tools_activate(): void {
	wh_tattoo_register_content();
	flush_rewrite_rules();
}

register_activation_hook(
	__FILE__,
	'wh_tattoo_tools_activate'
);

/**
 * Обновляет постоянные ссылки при деактивации.
 */
function wh_tattoo_tools_deactivate(): void {
	flush_rewrite_rules();
}

register_deactivation_hook(
	__FILE__,
	'wh_tattoo_tools_deactivate'
);

/**
 * Подключает медиатеку WordPress на экране редактирования работы.
 *
 * @param string $hook_suffix Текущий экран административной панели.
 */
function wh_tattoo_before_image_admin_assets( string $hook_suffix ): void {
	if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}

	$screen = get_current_screen();

	if ( ! $screen || 'portfolio' !== $screen->post_type ) {
		return;
	}

	wp_enqueue_media();

	wp_register_script(
		'wh-tattoo-before-image-admin',
		'',
		array( 'jquery' ),
		'0.1.0',
		true
	);

	wp_enqueue_script( 'wh-tattoo-before-image-admin' );

	$script = <<<'JS'
jQuery(function ($) {
	"use strict";

	let mediaFrame = null;

	$(document).on("click", "#wh-tattoo-select-before-image", function (event) {
		event.preventDefault();

		if (mediaFrame) {
			mediaFrame.open();
			return;
		}

		mediaFrame = wp.media({
			title: "Выберите фотографию до перекрытия",
			button: {
				text: "Использовать фотографию"
			},
			library: {
				type: "image"
			},
			multiple: false
		});

		mediaFrame.on("select", function () {
			const attachment = mediaFrame
				.state()
				.get("selection")
				.first()
				.toJSON();

			const previewUrl =
				attachment.sizes && attachment.sizes.medium
					? attachment.sizes.medium.url
					: attachment.url;

			$("#wh-tattoo-before-image-id").val(attachment.id);

			$("#wh-tattoo-before-image-preview")
				.attr("src", previewUrl)
				.attr("alt", attachment.alt || "Фотография до перекрытия")
				.removeAttr("hidden");

			$("#wh-tattoo-remove-before-image").removeAttr("hidden");
		});

		mediaFrame.open();
	});

	$(document).on("click", "#wh-tattoo-remove-before-image", function (event) {
		event.preventDefault();

		$("#wh-tattoo-before-image-id").val("");

		$("#wh-tattoo-before-image-preview")
			.attr("src", "")
			.attr("alt", "")
			.attr("hidden", true);

		$(this).attr("hidden", true);
	});
});
JS;

	wp_add_inline_script(
		'wh-tattoo-before-image-admin',
		$script
	);
}

add_action(
	'admin_enqueue_scripts',
	'wh_tattoo_before_image_admin_assets'
);

/**
 * Добавляет поле фотографии до перекрытия.
 */
function wh_tattoo_add_before_image_meta_box(): void {
	add_meta_box(
		'wh-tattoo-before-image',
		'Фотография до перекрытия',
		'wh_tattoo_render_before_image_meta_box',
		'portfolio',
		'side',
		'default'
	);
}

add_action(
	'add_meta_boxes',
	'wh_tattoo_add_before_image_meta_box'
);

/**
 * Выводит поле фотографии до перекрытия.
 *
 * @param WP_Post $post Текущая работа.
 */
function wh_tattoo_render_before_image_meta_box( WP_Post $post ): void {
	$image_id = absint(
		get_post_meta(
			$post->ID,
			'_wh_tattoo_before_image_id',
			true
		)
	);

	wp_nonce_field(
		'wh_tattoo_save_before_image',
		'wh_tattoo_before_image_nonce'
	);

	$image_url = '';

	if ( $image_id ) {
		$image_url = wp_get_attachment_image_url(
			$image_id,
			'medium'
		);
	}
	?>
	<div class="wh-tattoo-before-image-field">
		<p>
			Добавляйте фотографию только для работ из раздела
			<strong>«Перекрытие татуировок»</strong>.
		</p>

		<input
			type="hidden"
			id="wh-tattoo-before-image-id"
			name="wh_tattoo_before_image_id"
			value="<?php echo esc_attr( (string) $image_id ); ?>"
		>

		<img
			id="wh-tattoo-before-image-preview"
			src="<?php echo esc_url( $image_url ); ?>"
			alt="Фотография до перекрытия"
			style="
				display: block;
				width: 100%;
				height: auto;
				margin: 0 0 12px;
				border-radius: 2px;
			"
			<?php echo $image_url ? '' : 'hidden'; ?>
		>

		<p>
			<button
				type="button"
				class="button button-secondary"
				id="wh-tattoo-select-before-image"
			>
				<?php
				echo $image_id
					? 'Заменить фотографию'
					: 'Выбрать фотографию до';
				?>
			</button>
		</p>

		<p>
			<button
				type="button"
				class="button-link-delete"
				id="wh-tattoo-remove-before-image"
				<?php echo $image_id ? '' : 'hidden'; ?>
			>
				Удалить фотографию до
			</button>
		</p>

		<p class="description">
			Основное изображение записи должно показывать результат после
			перекрытия.
		</p>
	</div>
	<?php
}

/**
 * Сохраняет фотографию до перекрытия.
 *
 * @param int $post_id ID работы.
 */
function wh_tattoo_save_before_image_meta( int $post_id ): void {
	if (
		! isset( $_POST['wh_tattoo_before_image_nonce'] )
		|| ! wp_verify_nonce(
			sanitize_text_field(
				wp_unslash(
					$_POST['wh_tattoo_before_image_nonce']
				)
			),
			'wh_tattoo_save_before_image'
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
		'portfolio' !== get_post_type( $post_id )
		|| ! current_user_can( 'edit_post', $post_id )
	) {
		return;
	}

	$image_id = isset( $_POST['wh_tattoo_before_image_id'] )
		? absint( $_POST['wh_tattoo_before_image_id'] )
		: 0;

	if (
		$image_id
		&& 'attachment' === get_post_type( $image_id )
		&& wp_attachment_is_image( $image_id )
	) {
		update_post_meta(
			$post_id,
			'_wh_tattoo_before_image_id',
			$image_id
		);

		return;
	}

	delete_post_meta(
		$post_id,
		'_wh_tattoo_before_image_id'
	);
}

add_action(
	'save_post_portfolio',
	'wh_tattoo_save_before_image_meta'
);

/**
 * Регистрирует размеры изображений портфолио.
 */
function wh_tattoo_register_image_sizes(): void {
	add_image_size(
		'wh-tattoo-portfolio-card',
		800,
		1000,
		true
	);

	add_image_size(
		'wh-tattoo-portfolio-large',
		1400,
		1750,
		false
	);
}

add_action(
	'after_setup_theme',
	'wh_tattoo_register_image_sizes'
);

/**
 * Возвращает названия направлений текущей работы.
 *
 * @param int $post_id ID работы.
 *
 * @return string
 */
function wh_tattoo_get_portfolio_terms( int $post_id ): string {
	$terms = get_the_terms(
		$post_id,
		'tattoo_style'
	);

	if (
		! $terms
		|| is_wp_error( $terms )
	) {
		return '';
	}

	$names = wp_list_pluck(
		$terms,
		'name'
	);

	return implode(
		' · ',
		$names
	);
}

/**
 * Выводит карусель работ без модального просмотра.
 *
 * Использование:
 * [wh_portfolio_grid limit="12"]
 *
 * @param array<string, mixed> $attributes Атрибуты шорткода.
 *
 * @return string
 */
function wh_tattoo_portfolio_grid_shortcode(
	array $attributes = array()
): string {
	$attributes = shortcode_atts(
		array(
			'limit' => 12,
		),
		$attributes,
		'wh_portfolio_grid'
	);

	$limit = max(
		1,
		min( absint( $attributes['limit'] ), 50 )
	);

	$query = new WP_Query(
		array(
			'post_type'           => 'portfolio',
			'post_status'         => 'publish',
			'posts_per_page'      => $limit,
			'orderby'             => 'date',
			'order'               => 'DESC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);

	if ( ! $query->have_posts() ) {
		return '<p class="portfolio-empty">Работы пока не опубликованы.</p>';
	}

	$carousel_id = wp_unique_id( 'portfolio-carousel-' );

	ob_start();
	?>
	<div
		class="portfolio-carousel"
		data-portfolio-carousel
	>
		<?php
		echo '<div class="portfolio-carousel__controls">';
		echo '<button type="button" class="portfolio-carousel__arrow portfolio-carousel__arrow--previous" data-portfolio-previous aria-label="Предыдущие работы">';
		echo '<span aria-hidden="true"></span>';
		echo '</button>';
		echo '<button type="button" class="portfolio-carousel__arrow portfolio-carousel__arrow--next" data-portfolio-next aria-label="Следующие работы">';
		echo '<span aria-hidden="true"></span>';
		echo '</button>';
		echo '</div>';
		?>

		<div
			class="portfolio-carousel__track"
			id="<?php echo esc_attr( $carousel_id ); ?>"
			data-portfolio-track
			tabindex="0"
			aria-label="Работы тату-мастера"
		>
			<?php
			while ( $query->have_posts() ) {
				$query->the_post();

				$post_id = get_the_ID();

				$after_image_id = get_post_thumbnail_id(
					$post_id
				);

				if ( ! $after_image_id ) {
					continue;
				}

				$before_image_id = absint(
					get_post_meta(
						$post_id,
						'_wh_tattoo_before_image_id',
						true
					)
				);

				$after_image_url = wp_get_attachment_image_url(
					$after_image_id,
					'wh-tattoo-portfolio-card'
				);

				$before_image_url = $before_image_id
					? wp_get_attachment_image_url(
						$before_image_id,
						'wh-tattoo-portfolio-card'
					)
					: '';

				$after_alt = get_post_meta(
					$after_image_id,
					'_wp_attachment_image_alt',
					true
				);

				$before_alt = $before_image_id
					? get_post_meta(
						$before_image_id,
						'_wp_attachment_image_alt',
						true
					)
					: '';
				?>
				<article
					class="portfolio-slide"
					data-portfolio-slide
				>
					<div class="portfolio-slide__media">
						<img
							class="portfolio-slide__image"
							src="<?php echo esc_url(
								$after_image_url ?: ''
							); ?>"
							alt="<?php echo esc_attr(
								$after_alt ?: get_the_title()
							); ?>"
							loading="lazy"
							decoding="async"
							draggable="false"
							data-portfolio-image
							data-after-src="<?php echo esc_url(
								$after_image_url ?: ''
							); ?>"
							data-after-alt="<?php echo esc_attr(
								$after_alt ?: get_the_title()
							); ?>"
							data-before-src="<?php echo esc_url(
								$before_image_url ?: ''
							); ?>"
							data-before-alt="<?php echo esc_attr(
								$before_alt
									?: 'Фотография до перекрытия'
							); ?>"
						>

						<?php if ( $before_image_url ) : ?>
							<div
								class="portfolio-slide__compare"
								aria-label="Сравнение до и после"
							>
								<button
									type="button"
									class="is-active"
									data-portfolio-mode="after"
								>
									<span>После</span>
								</button>

								<button
									type="button"
									data-portfolio-mode="before"
								>
									<span>До</span>
								</button>
							</div>
						<?php endif; ?>
					</div>
				</article>
				<?php
			}
			?>
		</div>

		<div
			class="portfolio-carousel__progress"
			aria-hidden="true"
		>
			<span data-portfolio-progress></span>
		</div>
	</div>
	<?php

	wp_reset_postdata();

	return (string) ob_get_clean();
}

add_shortcode(
	'wh_portfolio_grid',
	'wh_tattoo_portfolio_grid_shortcode'
);

/**
 * Выводит фотографии до и после для текущей работы.
 *
 * Использование:
 * [wh_before_after]
 *
 * @return string
 */
function wh_tattoo_before_after_shortcode(): string {
	if (
		! is_singular( 'portfolio' )
		|| ! in_the_loop()
	) {
		return '';
	}

	$post_id = get_the_ID();

	$before_image_id = absint(
		get_post_meta(
			$post_id,
			'_wh_tattoo_before_image_id',
			true
		)
	);

	if (
		! $before_image_id
		|| ! has_post_thumbnail( $post_id )
	) {
		return '';
	}

	$after_image_id = get_post_thumbnail_id(
		$post_id
	);

	ob_start();
	?>
	<section
		class="before-after"
		aria-labelledby="before-after-title"
	>
		<h2
			class="before-after__title"
			id="before-after-title"
		>
			До и после перекрытия
		</h2>

		<div class="before-after__grid">
			<figure class="before-after__item">
				<div class="before-after__label">
					До
				</div>

				<?php
				echo wp_get_attachment_image(
					$before_image_id,
					'wh-tattoo-portfolio-large',
					false,
					array(
						'class'    => 'before-after__image',
						'loading'  => 'lazy',
						'decoding' => 'async',
					)
				);
				?>
			</figure>

			<figure class="before-after__item">
				<div class="before-after__label">
					После
				</div>

				<?php
				echo wp_get_attachment_image(
					$after_image_id,
					'wh-tattoo-portfolio-large',
					false,
					array(
						'class'    => 'before-after__image',
						'loading'  => 'eager',
						'decoding' => 'async',
					)
				);
				?>
			</figure>
		</div>
	</section>
	<?php

	return (string) ob_get_clean();
}

add_shortcode(
	'wh_before_after',
	'wh_tattoo_before_after_shortcode'
);