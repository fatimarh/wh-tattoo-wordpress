<?php
/**
 * Административный интерфейс отзывов.
 *
 * @package WH_Reviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WH_Reviews_Admin {

	/**
	 * Подключает административные хуки.
	 */
	public static function init(): void {
		add_action(
			'admin_menu',
			array( __CLASS__, 'register_pages' )
		);
	}

	/**
	 * Регистрирует страницу импорта.
	 */
	public static function register_pages(): void {
		add_submenu_page(
			'edit.php?post_type=wh_review',
			'Импорт отзывов',
			'Импорт из Яндекса',
			'manage_options',
			'wh-reviews-import',
			array( __CLASS__, 'render_import_page' )
		);
	}

	/**
	 * Выводит страницу импорта.
	 */
	public static function render_import_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html(
					'Недостаточно прав для импорта отзывов.'
				)
			);
		}

		$validation_result = null;
		$import_result     = null;

		if (
			isset( $_POST['wh_reviews_import'] )
			&& isset( $_POST['wh_reviews_import_nonce'] )
		) {
			$nonce = sanitize_text_field(
				wp_unslash(
					$_POST['wh_reviews_import_nonce']
				)
			);

			if (
				! wp_verify_nonce(
					$nonce,
					'wh_reviews_import'
				)
			) {
				$import_result = new WP_Error(
					'invalid_import_nonce',
					'Не удалось подтвердить импорт. Обновите страницу.'
				);
			} else {
				$stored_data =
					WH_Reviews_Importer::get_validated_data();

				if ( false === $stored_data ) {
					$import_result = new WP_Error(
						'missing_validated_data',
						'Проверенный JSON не найден. Загрузите файл повторно.'
					);
				} else {
					$publish_five = (
						isset(
							$_POST['wh_reviews_publish_five']
						)
						&& '1' === sanitize_text_field(
							wp_unslash(
								$_POST['wh_reviews_publish_five']
							)
						)
					);

					$import_result =
						WH_Reviews_Importer::import_reviews(
							$stored_data,
							$publish_five
						);

					if ( ! is_wp_error( $import_result ) ) {
						WH_Reviews_Importer::clear_validated_data();
					}
				}
			}
		}

		if (
			isset( $_POST['wh_reviews_validate'] )
			&& isset( $_POST['wh_reviews_import_nonce'] )
		) {
			$nonce = sanitize_text_field(
				wp_unslash(
					$_POST['wh_reviews_import_nonce']
				)
			);

			if (
				! wp_verify_nonce(
					$nonce,
					'wh_reviews_import'
				)
			) {
				$validation_result = new WP_Error(
					'invalid_nonce',
					'Не удалось подтвердить запрос. Обновите страницу.'
				);
			} elseif (
				! isset( $_FILES['wh_reviews_file'] )
				|| ! is_array(
					$_FILES['wh_reviews_file']
				)
			) {
				$validation_result = new WP_Error(
					'missing_file',
					'JSON-файл не был передан.'
				);
			} else {
				$validation_result =
					WH_Reviews_Importer::validate_uploaded_file(
						$_FILES['wh_reviews_file']
					);

				if ( is_array( $validation_result ) ) {
					WH_Reviews_Importer::store_validated_data(
						$validation_result
					);
				}
			}
		}
		?>
		<div class="wrap">
			<h1>Импорт отзывов из Яндекса</h1>

			<?php
			if ( is_wp_error( $import_result ) ) :
				?>
				<div class="notice notice-error inline">
					<p>
						<strong>Импорт не выполнен.</strong>
					</p>

					<p>
						<?php
						echo esc_html(
							$import_result->get_error_message()
						);
						?>
					</p>
				</div>
				<?php
			elseif ( is_array( $import_result ) ) :
				?>
				<div class="notice notice-success inline">
					<p>
						<strong>Импорт завершён.</strong>
					</p>

					<p>
						Добавлено:
						<strong>
							<?php
							echo esc_html(
								(string) $import_result['created']
							);
							?>
						</strong>.
						Обновлено:
						<strong>
							<?php
							echo esc_html(
								(string) $import_result['updated']
							);
							?>
						</strong>.
						Без изменений:
						<strong>
							<?php
							echo esc_html(
								(string) $import_result['unchanged']
							);
							?>
						</strong>.
						Ошибок:
						<strong>
							<?php
							echo esc_html(
								(string) $import_result['failed']
							);
							?>
						</strong>.
					</p>

					<p>
						<a
							href="<?php echo esc_url(
								admin_url(
									'edit.php?post_type=wh_review'
								)
							); ?>"
						>
							Открыть импортированные отзывы
						</a>
					</p>
				</div>
				<?php
			endif;
			?>

			<?php
			if ( is_wp_error( $validation_result ) ) :
				?>
				<div class="notice notice-error inline">
					<p>
						<strong>Файл не прошёл проверку.</strong>
					</p>

					<p>
						<?php
						echo esc_html(
							$validation_result->get_error_message()
						);
						?>
					</p>
				</div>
				<?php
			elseif ( is_array( $validation_result ) ) :
				?>
				<div class="notice notice-success inline">
					<p>
						<strong>JSON успешно проверен.</strong>
					</p>
				</div>

				<table class="widefat striped">
					<tbody>
						<tr>
							<th>Организация</th>
							<td>
								<?php
								echo esc_html(
									$validation_result[
										'organization_name'
									]
								);
								?>
							</td>
						</tr>

						<tr>
							<th>ID организации</th>
							<td>
								<code>
									<?php
									echo esc_html(
										$validation_result[
											'business_id'
										]
									);
									?>
								</code>
							</td>
						</tr>

						<tr>
							<th>Дата экспорта</th>
							<td>
								<?php
								echo esc_html(
									$validation_result[
										'exported_at'
									]
								);
								?>
							</td>
						</tr>

						<tr>
							<th>Всего отзывов</th>
							<td>
								<strong>
									<?php
									echo esc_html(
										(string) $validation_result[
											'reviews_count'
										]
									);
									?>
								</strong>
							</td>
						</tr>

						<tr>
							<th>5 звёзд</th>
							<td>
								<?php
								echo esc_html(
									(string) $validation_result[
										'rating_counts'
									][5]
								);
								?>
							</td>
						</tr>

						<tr>
							<th>4 звезды</th>
							<td>
								<?php
								echo esc_html(
									(string) $validation_result[
										'rating_counts'
									][4]
								);
								?>
							</td>
						</tr>

						<tr>
							<th>3 звезды</th>
							<td>
								<?php
								echo esc_html(
									(string) $validation_result[
										'rating_counts'
									][3]
								);
								?>
							</td>
						</tr>

						<tr>
							<th>2 звезды</th>
							<td>
								<?php
								echo esc_html(
									(string) $validation_result[
										'rating_counts'
									][2]
								);
								?>
							</td>
						</tr>

						<tr>
							<th>1 звезда</th>
							<td>
								<?php
								echo esc_html(
									(string) $validation_result[
										'rating_counts'
									][1]
								);
								?>
							</td>
						</tr>
					</tbody>
				</table>

				<p>
					Файл проверен и готов к импорту.
					Данные проверки хранятся 15 минут.
				</p>

				<form method="post">
					<?php
					wp_nonce_field(
						'wh_reviews_import',
						'wh_reviews_import_nonce'
					);
					?>

					<input
						type="hidden"
						name="wh_reviews_publish_five"
						value="<?php echo isset(
							$_POST['wh_reviews_publish_five']
						)
							? '1'
							: '0'; ?>"
					>

					<?php
					submit_button(
						sprintf(
							'Импортировать %d отзывов',
							absint(
								$validation_result['reviews_count']
							)
						),
						'primary',
						'wh_reviews_import',
						false
					);
					?>
				</form>
				<?php
			endif;
			?>

			<p>
				Загрузите JSON-файл, созданный локальным
				экспортёром WH Yandex Reviews Exporter.
			</p>

			<div class="notice notice-info inline">
				<p>
					Разрешённая организация:
					<strong>WH</strong>,
					ID <code>33357510992</code>.
				</p>
			</div>

			<form
				method="post"
				enctype="multipart/form-data"
			>
				<?php
				wp_nonce_field(
					'wh_reviews_import',
					'wh_reviews_import_nonce'
				);
				?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="wh-reviews-file">
								JSON-файл
							</label>
						</th>

						<td>
							<input
								type="file"
								id="wh-reviews-file"
								name="wh_reviews_file"
								accept=".json,application/json"
								required
							>

							<p class="description">
								Выберите файл
								<code>wh-yandex-reviews.json</code>.
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							Публикация
						</th>

						<td>
							<label>
								<input
									type="checkbox"
									name="wh_reviews_publish_five"
									value="1"
									checked
								>
								Автоматически разрешить показ
								отзывов с оценкой 5
							</label>
						</td>
					</tr>
				</table>

				<?php
				submit_button(
					'Проверить JSON',
					'primary',
					'wh_reviews_validate'
				);
				?>
			</form>
		</div>
		<?php
	}
}
