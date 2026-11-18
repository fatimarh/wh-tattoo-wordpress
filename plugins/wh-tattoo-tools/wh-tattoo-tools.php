<?php
/**
 * Plugin Name: WH Tattoo Tools
 * Description: Работы и направления портфолио сайта WH Tattoo.
 * Version: 0.8.1
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
		array(
			'jquery',
			'jquery-ui-sortable',
		),
		'0.2.0',
		true
	);

	wp_enqueue_script( 'wh-tattoo-before-image-admin' );

	wp_register_style(
		'wh-tattoo-portfolio-admin',
		false,
		array(),
		'0.2.0'
	);

	wp_enqueue_style(
		'wh-tattoo-portfolio-admin'
	);

	wp_add_inline_style(
		'wh-tattoo-portfolio-admin',
		'
		.wh-tattoo-gallery-list {
			display: flex;
			flex-wrap: wrap;
			align-items: flex-start;
			gap: 12px;
			margin: 16px 0;
			padding: 0;
			list-style: none;
		}

		.wh-tattoo-gallery-item {
			flex: 0 0 138px;
			width: 138px;
			box-sizing: border-box;
			position: relative;
			display: flex;
			flex-direction: column;
			min-width: 0;
			margin: 0;
			padding: 8px;
			background: #fff;
			border: 1px solid #c3c4c7;
		}

		.wh-tattoo-gallery-item img {
			display: block;
			width: 100%;
			aspect-ratio: 4 / 5;
			margin: 0 0 8px;
			object-fit: cover;
			background: #f0f0f1;
		}

		.wh-tattoo-gallery-item__handle {
			position: absolute;
			z-index: 2;
			top: 8px;
			left: 8px;
			display: flex;
			align-items: center;
			justify-content: center;
			width: 36px;
			height: 36px;
			margin: 0;
			padding: 0;
			cursor: grab;
			touch-action: none;
			color: #fff;
			font-size: 18px;
			line-height: 1;
			background: rgba(0, 0, 0, 0.78);
			border: 0;
			border-radius: 0;
		}

		.wh-tattoo-gallery-item__handle:active {
			cursor: grabbing;
		}

		.wh-tattoo-gallery-item__remove {
			align-self: flex-start;
		}

		.wh-tattoo-gallery-item--placeholder {
			flex: 0 0 138px;
			width: 138px;
			min-height: 210px;
			box-sizing: border-box;
			background: #f0f6fc;
			border: 2px dashed #2271b1;
		}

		.wh-tattoo-gallery-info {
			padding: 12px 14px;
			background: #f6f7f7;
			border-left: 4px solid #2271b1;
		}

		.wh-tattoo-gallery-warning {
			padding: 10px 12px;
			color: #8a2424;
			background: #fcf0f1;
			border-left: 4px solid #d63638;
		}
		'
	);

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

	/*
	 * Дополнительные ракурсы работы.
	 */

	let galleryFrame = null;

	const galleryList = $(
		"#wh-tattoo-gallery-list"
	);

	const galleryInput = $(
		"#wh-tattoo-gallery-image-ids"
	);

	const getGalleryIds = () => {
		return galleryList
			.find("[data-gallery-item]")
			.map(function () {
				return String(
					$(this).data("attachment-id")
				);
			})
			.get()
			.filter(Boolean);
	};

	const updateGalleryInput = () => {
		galleryInput.val(
			getGalleryIds().join(",")
		);

		const count = getGalleryIds().length;

		$(
			"#wh-tattoo-gallery-count"
		).text(
			count === 0
				? "Дополнительные ракурсы не выбраны."
				: `Дополнительных ракурсов: ${count}.`
		);

		$(
			"#wh-tattoo-gallery-warning"
		).prop(
			"hidden",
			count <= 8
		);
	};

	const createGalleryItem = (
		attachment
	) => {
		const previewUrl =
			attachment.sizes &&
			attachment.sizes.medium
				? attachment.sizes.medium.url
				: attachment.url;

		const item = $("<li>", {
			class: "wh-tattoo-gallery-item",
			"data-gallery-item": "",
			"data-attachment-id": attachment.id
		});

		const handle = $("<button>", {
			type: "button",
			class:
				"wh-tattoo-gallery-item__handle",
			"aria-label":
				"Изменить порядок изображения",
			title:
				"Перетащите для изменения порядка"
		}).text("⋮⋮");

		const image = $("<img>", {
			src: previewUrl,
			alt:
				attachment.alt ||
				"Дополнительный ракурс"
		});

		const removeButton = $("<button>", {
			type: "button",
			class:
				"button-link-delete wh-tattoo-gallery-item__remove",
			"data-remove-gallery-item": ""
		}).text("Удалить");

		item.append(
			handle,
			image,
			removeButton
		);

		return item;
	};

	$(
		"#wh-tattoo-add-gallery-images"
	).on("click", function (event) {
		event.preventDefault();

		if (!galleryFrame) {
			galleryFrame = wp.media({
				title:
					"Выберите дополнительные ракурсы",
				button: {
					text:
						"Добавить в галерею"
				},
				library: {
					type: "image"
				},
				multiple: true
			});

			galleryFrame.on(
				"select",
				function () {
					const existingIds =
						new Set(
							getGalleryIds()
						);

					galleryFrame
						.state()
						.get("selection")
						.toJSON()
						.forEach(
							(attachment) => {
								const id =
									String(
										attachment.id
									);

								if (
									existingIds.has(
										id
									)
								) {
									return;
								}

								existingIds.add(id);

								galleryList.append(
									createGalleryItem(
										attachment
									)
								);
							}
						);

					updateGalleryInput();
				}
			);
		}

		galleryFrame.open();
	});

	galleryList.on(
		"click",
		"[data-remove-gallery-item]",
		function (event) {
			event.preventDefault();

			$(this)
				.closest("[data-gallery-item]")
				.remove();

			updateGalleryInput();
		}
	);

	if (
		galleryList.length &&
		typeof galleryList.sortable === "function"
	) {
		galleryList.sortable({
			items: "> [data-gallery-item]",
			handle:
				".wh-tattoo-gallery-item__handle",
			/*
			 * По умолчанию jQuery UI запрещает начало
			 * сортировки с элементов button.
			 * Разрешаем кнопку-ручку и исключаем только удаление.
			 */
			cancel:
				"[data-remove-gallery-item]",
			axis: false,
			tolerance: "pointer",
			distance: 3,
			placeholder:
				"wh-tattoo-gallery-item wh-tattoo-gallery-item--placeholder",
			forcePlaceholderSize: true,
			forceHelperSize: true,
			start: function (
				event,
				ui
			) {
				ui.placeholder
					.width(ui.item.outerWidth())
					.height(ui.item.outerHeight());
			},
			update: function () {
				updateGalleryInput();
			}
		});

		galleryList.disableSelection();
	}

	updateGalleryInput();
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
 * Добавляет галерею дополнительных ракурсов.
 */
function wh_tattoo_add_portfolio_gallery_meta_box(): void {
	add_meta_box(
		'wh-tattoo-portfolio-gallery',
		'Дополнительные ракурсы',
		'wh_tattoo_render_portfolio_gallery_meta_box',
		'portfolio',
		'normal',
		'high'
	);
}

add_action(
	'add_meta_boxes',
	'wh_tattoo_add_portfolio_gallery_meta_box'
);

/**
 * Возвращает сохранённые ID дополнительных ракурсов.
 *
 * @param int $post_id ID работы.
 *
 * @return array<int, int>
 */
function wh_tattoo_get_portfolio_gallery_ids(
	int $post_id
): array {
	$stored_ids = get_post_meta(
		$post_id,
		'_wh_tattoo_gallery_image_ids',
		true
	);

	if ( ! is_array( $stored_ids ) ) {
		return array();
	}

	$image_ids = array();

	foreach ( $stored_ids as $image_id ) {
		$image_id = absint( $image_id );

		if (
			$image_id
			&& wp_attachment_is_image( $image_id )
		) {
			$image_ids[] = $image_id;
		}
	}

	return array_values(
		array_unique( $image_ids )
	);
}

/**
 * Выводит поле дополнительных ракурсов.
 *
 * @param WP_Post $post Текущая работа.
 */
function wh_tattoo_render_portfolio_gallery_meta_box(
	WP_Post $post
): void {
	$image_ids = wh_tattoo_get_portfolio_gallery_ids(
		$post->ID
	);

	wp_nonce_field(
		'wh_tattoo_save_portfolio_gallery',
		'wh_tattoo_portfolio_gallery_nonce'
	);
	?>
	<div class="wh-tattoo-gallery-field">
		<p class="wh-tattoo-gallery-info">
			<strong>Основное изображение записи</strong>
			всегда показывается первым.
			Здесь добавляются только дополнительные
			ракурсы той же работы.
		</p>

		<input
			type="hidden"
			id="wh-tattoo-gallery-image-ids"
			name="wh_tattoo_gallery_image_ids"
			value="<?php echo esc_attr(
				implode( ',', $image_ids )
			); ?>"
		>

		<ul
			class="wh-tattoo-gallery-list"
			id="wh-tattoo-gallery-list"
		>
			<?php foreach ( $image_ids as $image_id ) : ?>
				<?php
				$image_url =
					wp_get_attachment_image_url(
						$image_id,
						'medium'
					);

				$image_alt = get_post_meta(
					$image_id,
					'_wp_attachment_image_alt',
					true
				);
				?>

				<?php if ( $image_url ) : ?>
					<li
						class="wh-tattoo-gallery-item"
						data-gallery-item
						data-attachment-id="<?php echo esc_attr(
							(string) $image_id
						); ?>"
					>
						<button
							type="button"
							class="wh-tattoo-gallery-item__handle"
							aria-label="Изменить порядок изображения"
							title="Перетащите для изменения порядка"
						>
							⋮⋮
						</button>

						<img
							src="<?php echo esc_url(
								$image_url
							); ?>"
							alt="<?php echo esc_attr(
								$image_alt
									?: 'Дополнительный ракурс'
							); ?>"
						>

						<button
							type="button"
							class="button-link-delete wh-tattoo-gallery-item__remove"
							data-remove-gallery-item
						>
							Удалить
						</button>
					</li>
				<?php endif; ?>
			<?php endforeach; ?>
		</ul>

		<p>
			<button
				type="button"
				class="button button-secondary"
				id="wh-tattoo-add-gallery-images"
			>
				Добавить фотографии
			</button>
		</p>

		<p
			class="description"
			id="wh-tattoo-gallery-count"
		>
			<?php
			echo $image_ids
				? esc_html(
					sprintf(
						'Дополнительных ракурсов: %d.',
						count( $image_ids )
					)
				)
				: 'Дополнительные ракурсы не выбраны.';
			?>
		</p>

		<p
			class="wh-tattoo-gallery-warning"
			id="wh-tattoo-gallery-warning"
			<?php echo count( $image_ids ) > 8
				? ''
				: 'hidden'; ?>
		>
			Для главной обычно достаточно 2–8 материалов.
			Большое количество не запрещено, но потребует
			больше трафика при активном просмотре.
		</p>

		<details>
			<summary>
				Требования к материалам
			</summary>

			<p class="description">
				Фото: JPG или WebP, желательно вертикальные
				4:5, от 800 × 1000 px. Основная фотография
				задаётся через «Изображение записи».
			</p>
		</details>
	</div>
	<?php
}

/**
 * Сохраняет дополнительные ракурсы работы.
 *
 * @param int $post_id ID работы.
 */
function wh_tattoo_save_portfolio_gallery_meta(
	int $post_id
): void {
	if (
		! isset(
			$_POST['wh_tattoo_portfolio_gallery_nonce']
		)
	) {
		return;
	}

	$nonce = sanitize_text_field(
		wp_unslash(
			$_POST[
				'wh_tattoo_portfolio_gallery_nonce'
			]
		)
	);

	if (
		! wp_verify_nonce(
			$nonce,
			'wh_tattoo_save_portfolio_gallery'
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

	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}

	if (
		'portfolio' !== get_post_type( $post_id )
		|| ! current_user_can(
			'edit_post',
			$post_id
		)
	) {
		return;
	}

	$raw_ids = isset(
		$_POST['wh_tattoo_gallery_image_ids']
	)
		? sanitize_text_field(
			wp_unslash(
				$_POST[
					'wh_tattoo_gallery_image_ids'
				]
			)
		)
		: '';

	$image_ids = array();

	if ( '' !== $raw_ids ) {
		foreach (
			explode( ',', $raw_ids ) as $raw_id
		) {
			$image_id = absint( $raw_id );

			if (
				$image_id
				&& wp_attachment_is_image(
					$image_id
				)
			) {
				$image_ids[] = $image_id;
			}
		}
	}

	$image_ids = array_values(
		array_unique( $image_ids )
	);

	if ( $image_ids ) {
		update_post_meta(
			$post_id,
			'_wh_tattoo_gallery_image_ids',
			$image_ids
		);

		return;
	}

	delete_post_meta(
		$post_id,
		'_wh_tattoo_gallery_image_ids'
	);
}

add_action(
	'save_post_portfolio',
	'wh_tattoo_save_portfolio_gallery_meta'
);

/**
 * Добавляет настройки показа работы на главной странице.
 */
function wh_tattoo_add_portfolio_featured_meta_box(): void {
	add_meta_box(
		'wh-tattoo-portfolio-featured',
		'Витрина главной страницы',
		'wh_tattoo_render_portfolio_featured_meta_box',
		'portfolio',
		'side',
		'high'
	);
}

add_action(
	'add_meta_boxes',
	'wh_tattoo_add_portfolio_featured_meta_box'
);

/**
 * Выводит настройки витрины.
 *
 * @param WP_Post $post Текущая работа.
 */
function wh_tattoo_render_portfolio_featured_meta_box(
	WP_Post $post
): void {
	$is_featured = get_post_meta(
		$post->ID,
		'_wh_tattoo_featured',
		true
	);

	$featured_order = absint(
		get_post_meta(
			$post->ID,
			'_wh_tattoo_featured_order',
			true
		)
	);

	wp_nonce_field(
		'wh_tattoo_save_portfolio_featured',
		'wh_tattoo_portfolio_featured_nonce'
	);
	?>
	<p>
		<label>
			<input
				type="checkbox"
				name="wh_tattoo_featured"
				value="1"
				<?php checked( $is_featured, '1' ); ?>
			>
			Показывать работу на главной
		</label>
	</p>

	<p>
		<label for="wh-tattoo-featured-order">
			<strong>Порядок показа</strong>
		</label>
	</p>

	<p>
		<input
			type="number"
			id="wh-tattoo-featured-order"
			name="wh_tattoo_featured_order"
			value="<?php echo esc_attr(
				(string) $featured_order
			); ?>"
			min="0"
			max="999"
			step="1"
			class="small-text"
		>
	</p>

	<p class="description">
		Меньшее число показывается раньше.
		Для 12 работ используйте:
		10, 20, 30 … 120.
	</p>

	<p class="description">
		Новые работы не попадут на главную,
		пока этот флажок не будет включён.
	</p>
	<?php
}

/**
 * Сохраняет настройки витрины.
 *
 * @param int $post_id ID работы.
 */
function wh_tattoo_save_portfolio_featured_meta(
	int $post_id
): void {
	if (
		! isset(
			$_POST['wh_tattoo_portfolio_featured_nonce']
		)
	) {
		return;
	}

	$nonce = sanitize_text_field(
		wp_unslash(
			$_POST['wh_tattoo_portfolio_featured_nonce']
		)
	);

	if (
		! wp_verify_nonce(
			$nonce,
			'wh_tattoo_save_portfolio_featured'
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

	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}

	if (
		'portfolio' !== get_post_type( $post_id )
		|| ! current_user_can(
			'edit_post',
			$post_id
		)
	) {
		return;
	}

	update_post_meta(
		$post_id,
		'_wh_tattoo_featured',
		isset( $_POST['wh_tattoo_featured'] )
			? '1'
			: '0'
	);

	$featured_order = isset(
		$_POST['wh_tattoo_featured_order']
	)
		? absint(
			$_POST['wh_tattoo_featured_order']
		)
		: 0;

	update_post_meta(
		$post_id,
		'_wh_tattoo_featured_order',
		min( $featured_order, 999 )
	);
}

add_action(
	'save_post_portfolio',
	'wh_tattoo_save_portfolio_featured_meta'
);

/**
 * Добавляет информацию о витрине в список работ.
 *
 * @param array<string, string> $columns Колонки списка.
 *
 * @return array<string, string>
 */
function wh_tattoo_add_portfolio_admin_columns(
	array $columns
): array {
	$updated_columns = array();

	foreach ( $columns as $key => $label ) {
		$updated_columns[ $key ] = $label;

		if ( 'title' === $key ) {
			$updated_columns['wh_tattoo_featured']
				= 'Главная';

			$updated_columns['wh_tattoo_featured_order']
				= 'Порядок';
		}
	}

	return $updated_columns;
}

add_filter(
	'manage_portfolio_posts_columns',
	'wh_tattoo_add_portfolio_admin_columns'
);

/**
 * Выводит значения колонок витрины.
 *
 * @param string $column  Название колонки.
 * @param int    $post_id ID работы.
 */
function wh_tattoo_render_portfolio_admin_column(
	string $column,
	int $post_id
): void {
	if ( 'wh_tattoo_featured' === $column ) {
		$is_featured = get_post_meta(
			$post_id,
			'_wh_tattoo_featured',
			true
		);

		if ( '1' === $is_featured ) {
			echo '<strong style="color:#008a20;">Да</strong>';
		} else {
			echo '<span style="color:#777;">Нет</span>';
		}

		return;
	}

	if ( 'wh_tattoo_featured_order' === $column ) {
		$is_featured = get_post_meta(
			$post_id,
			'_wh_tattoo_featured',
			true
		);

		if ( '1' !== $is_featured ) {
			echo '—';
			return;
		}

		echo esc_html(
			(string) absint(
				get_post_meta(
					$post_id,
					'_wh_tattoo_featured_order',
					true
				)
			)
		);
	}
}

add_action(
	'manage_portfolio_posts_custom_column',
	'wh_tattoo_render_portfolio_admin_column',
	10,
	2
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
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,

			'meta_key'            => '_wh_tattoo_featured_order',

			'orderby'             => array(
				'meta_value_num' => 'ASC',
				'date'           => 'DESC',
			),

			'meta_query'          => array(
				array(
					'key'     => '_wh_tattoo_featured',
					'value'   => '1',
					'compare' => '=',
				),
			),
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

				$gallery_image_ids =
					wh_tattoo_get_portfolio_gallery_ids(
						$post_id
					);

				$after_items = array();

				/*
				 * Основное изображение записи всегда первое.
				 */
				$after_items[] = array(
					'id'  => $after_image_id,
					'url' => $after_image_url ?: '',
					'alt' => $after_alt ?: get_the_title(),
				);

				foreach (
					$gallery_image_ids as $gallery_image_id
				) {
					/*
					 * Не допускаем повтор основного изображения.
					 */
					if (
						$gallery_image_id
						=== $after_image_id
					) {
						continue;
					}

					$gallery_image_url =
						wp_get_attachment_image_url(
							$gallery_image_id,
							'wh-tattoo-portfolio-card'
						);

					if ( ! $gallery_image_url ) {
						continue;
					}

					$gallery_image_alt = get_post_meta(
						$gallery_image_id,
						'_wp_attachment_image_alt',
						true
					);

					$after_items[] = array(
						'id'  => $gallery_image_id,
						'url' => $gallery_image_url,
						'alt' => $gallery_image_alt
							?: get_the_title(),
					);
				}

				$gallery_json = wp_json_encode(
					$after_items,
					JSON_UNESCAPED_UNICODE
					| JSON_UNESCAPED_SLASHES
					| JSON_HEX_TAG
					| JSON_HEX_AMP
				);

				$gallery_data = $gallery_json
					? base64_encode( $gallery_json )
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
								$after_items[0]['url']
							); ?>"
							alt="<?php echo esc_attr(
								$after_items[0]['alt']
							); ?>"
							loading="lazy"
							decoding="async"
							draggable="false"
							data-portfolio-image
							data-after-src="<?php echo esc_url(
								$after_items[0]['url']
							); ?>"
							data-after-alt="<?php echo esc_attr(
								$after_items[0]['alt']
							); ?>"
							data-before-src="<?php echo esc_url(
								$before_image_url ?: ''
							); ?>"
							data-before-alt="<?php echo esc_attr(
								$before_alt
									?: 'Фотография до перекрытия'
							); ?>"
						>

						<?php if ( count( $after_items ) > 1 ) : ?>
							<div
								class="portfolio-slide__gallery-data"
								data-portfolio-gallery-data="<?php echo esc_attr(
									$gallery_data
								); ?>"
								hidden
							></div>

							<div
								class="portfolio-slide__dots"
								data-portfolio-dots
								aria-label="Дополнительные ракурсы работы"
							></div>
						<?php endif; ?>

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