<?php
/**
 * Проверка и импорт отзывов.
 *
 * @package WH_Reviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WH_Reviews_Importer {

	/**
	 * Разрешённый ID организации.
	 */
	private const BUSINESS_ID = '33357510992';

	/**
	 * Максимальный размер JSON, 5 МБ.
	 */
	private const MAX_FILE_SIZE = 5242880;

	/**
	 * Проверяет загруженный JSON.
	 *
	 * @param array<string, mixed> $uploaded_file Данные из $_FILES.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function validate_uploaded_file(
		array $uploaded_file
	) {
		$upload_error = isset( $uploaded_file['error'] )
			? absint( $uploaded_file['error'] )
			: UPLOAD_ERR_NO_FILE;

		if ( UPLOAD_ERR_OK !== $upload_error ) {
			return new WP_Error(
				'upload_error',
				self::get_upload_error_message(
					$upload_error
				)
			);
		}

		$file_size = isset( $uploaded_file['size'] )
			? absint( $uploaded_file['size'] )
			: 0;

		if ( 0 === $file_size ) {
			return new WP_Error(
				'empty_file',
				'Загруженный JSON-файл пуст.'
			);
		}

		if ( $file_size > self::MAX_FILE_SIZE ) {
			return new WP_Error(
				'file_too_large',
				'Размер JSON-файла превышает 5 МБ.'
			);
		}

		$file_name = isset( $uploaded_file['name'] )
			? sanitize_file_name(
				wp_unslash(
					$uploaded_file['name']
				)
			)
			: '';

		$file_extension = strtolower(
			pathinfo(
				$file_name,
				PATHINFO_EXTENSION
			)
		);

		if ( 'json' !== $file_extension ) {
			return new WP_Error(
				'invalid_extension',
				'Разрешены только файлы с расширением .json.'
			);
		}

		$temporary_path = isset( $uploaded_file['tmp_name'] )
			? (string) $uploaded_file['tmp_name']
			: '';

		if (
			'' === $temporary_path
			|| ! is_uploaded_file( $temporary_path )
		) {
			return new WP_Error(
				'invalid_upload',
				'Не удалось подтвердить загруженный файл.'
			);
		}

		$json_content = file_get_contents(
			$temporary_path
		);

		if ( false === $json_content ) {
			return new WP_Error(
				'read_error',
				'Не удалось прочитать JSON-файл.'
			);
		}

		$data = json_decode(
			$json_content,
			true,
			512,
			JSON_INVALID_UTF8_SUBSTITUTE
		);

		if (
			JSON_ERROR_NONE !== json_last_error()
			|| ! is_array( $data )
		) {
			return new WP_Error(
				'invalid_json',
				'Некорректный JSON: '
				. json_last_error_msg()
			);
		}

		return self::validate_payload(
			$data
		);
	}

	/**
	 * Проверяет структуру экспортированного файла.
	 *
	 * @param array<string, mixed> $data Данные JSON.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	private static function validate_payload(
		array $data
	) {
		$schema_version = isset(
			$data['schema_version']
		)
			? absint( $data['schema_version'] )
			: 0;

		if ( 1 !== $schema_version ) {
			return new WP_Error(
				'invalid_schema',
				'Неподдерживаемая версия структуры JSON.'
			);
		}

		$source = isset( $data['source'] )
			? sanitize_key(
				(string) $data['source']
			)
			: '';

		if ( 'yandex_maps' !== $source ) {
			return new WP_Error(
				'invalid_source',
				'Файл создан неизвестным источником.'
			);
		}

		$business_id = isset( $data['business_id'] )
			? sanitize_text_field(
				(string) $data['business_id']
			)
			: '';

		if ( self::BUSINESS_ID !== $business_id ) {
			return new WP_Error(
				'invalid_business',
				'В JSON указан другой ID организации.'
			);
		}

		$organization_name = isset(
			$data['organization_name']
		)
			? sanitize_text_field(
				(string) $data['organization_name']
			)
			: '';

		if ( 'WH' !== strtoupper( $organization_name ) ) {
			return new WP_Error(
				'invalid_organization',
				'В JSON указано другое название организации.'
			);
		}

		$source_url = isset( $data['source_url'] )
			? esc_url_raw(
				(string) $data['source_url']
			)
			: '';

		if (
			'' === $source_url
			|| false === strpos(
				$source_url,
				self::BUSINESS_ID
			)
		) {
			return new WP_Error(
				'invalid_source_url',
				'Ссылка на источник не относится к карточке WH.'
			);
		}

		if (
			! isset( $data['reviews'] )
			|| ! is_array( $data['reviews'] )
		) {
			return new WP_Error(
				'missing_reviews',
				'В JSON отсутствует массив reviews.'
			);
		}

		$reviews = array();
		$errors  = array();
		$ids     = array();

		foreach (
			$data['reviews'] as $index => $review
		) {
			if ( ! is_array( $review ) ) {
				$errors[] = sprintf(
					'Отзыв %d имеет некорректную структуру.',
					$index + 1
				);

				continue;
			}

			$validated_review = self::validate_review(
				$review,
				$index + 1
			);

			if ( is_wp_error( $validated_review ) ) {
				$errors[] = $validated_review->get_error_message();

				continue;
			}

			$external_id = $validated_review['external_id'];

			if ( isset( $ids[ $external_id ] ) ) {
				$errors[] = sprintf(
					'Отзыв %d повторяет ID %s.',
					$index + 1,
					$external_id
				);

				continue;
			}

			$ids[ $external_id ] = true;
			$reviews[]           = $validated_review;
		}

		if ( $errors ) {
			return new WP_Error(
				'invalid_reviews',
				implode(
					' ',
					array_slice( $errors, 0, 10 )
				),
				array(
					'errors' => $errors,
				)
			);
		}

		if ( ! $reviews ) {
			return new WP_Error(
				'empty_reviews',
				'В JSON нет отзывов, пригодных для импорта.'
			);
		}

		$rating_counts = array(
			1 => 0,
			2 => 0,
			3 => 0,
			4 => 0,
			5 => 0,
		);

		foreach ( $reviews as $review ) {
			$rating_counts[
				$review['rating']
			]++;
		}

		return array(
			'schema_version'   => $schema_version,
			'source'           => $source,
			'business_id'      => $business_id,
			'organization_name'=> $organization_name,
			'source_url'       => $source_url,
			'exported_at'      => isset( $data['exported_at'] )
				? sanitize_text_field(
					(string) $data['exported_at']
				)
				: '',
			'reviews_count'    => count( $reviews ),
			'rating_counts'    => $rating_counts,
			'reviews'          => $reviews,
		);
	}

	/**
	 * Проверяет один отзыв.
	 *
	 * @param array<string, mixed> $review Данные отзыва.
	 * @param int                  $number Номер отзыва.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	private static function validate_review(
		array $review,
		int $number
	) {
		$external_id = isset( $review['external_id'] )
			? sanitize_text_field(
				(string) $review['external_id']
			)
			: '';

		if (
			'' === $external_id
			|| 0 !== strpos(
				$external_id,
				'yandex-'
			)
		) {
			return new WP_Error(
				'invalid_review_id',
				sprintf(
					'Отзыв %d: некорректный external_id.',
					$number
				)
			);
		}

		$author = isset( $review['author'] )
			? sanitize_text_field(
				(string) $review['author']
			)
			: '';

		if ( '' === $author ) {
			return new WP_Error(
				'missing_author',
				sprintf(
					'Отзыв %d: отсутствует автор.',
					$number
				)
			);
		}

		$rating = isset( $review['rating'] )
			? absint( $review['rating'] )
			: 0;

		if ( $rating < 1 || $rating > 5 ) {
			return new WP_Error(
				'invalid_rating',
				sprintf(
					'Отзыв %d: оценка должна быть от 1 до 5.',
					$number
				)
			);
		}

		$review_text = isset( $review['text'] )
			? sanitize_textarea_field(
				(string) $review['text']
			)
			: '';

		if ( '' === $review_text ) {
			return new WP_Error(
				'missing_text',
				sprintf(
					'Отзыв %d: отсутствует текст.',
					$number
				)
			);
		}

		$source_url = isset( $review['source_url'] )
			? esc_url_raw(
				(string) $review['source_url']
			)
			: '';

		if (
			'' === $source_url
			|| false === strpos(
				$source_url,
				self::BUSINESS_ID
			)
		) {
			return new WP_Error(
				'invalid_review_source',
				sprintf(
					'Отзыв %d: некорректная ссылка на источник.',
					$number
				)
			);
		}

		return array(
			'external_id'      => $external_id,
			'author'           => $author,
			'rating'           => $rating,
			'published_at_raw' => isset(
				$review['published_at_raw']
			)
				? sanitize_text_field(
					(string) $review['published_at_raw']
				)
				: '',
			'text'             => $review_text,
			'owner_reply'      => isset(
				$review['owner_reply']
			)
				? sanitize_textarea_field(
					(string) $review['owner_reply']
				)
				: '',
			'photos'           => self::sanitize_photos(
				$review['photos'] ?? array()
			),
			'source_url'       => $source_url,
		);
	}

	/**
	 * Проверяет ссылки фотографий.
	 *
	 * @param mixed $photos Фотографии из JSON.
	 *
	 * @return array<int, string>
	 */
	private static function sanitize_photos(
		$photos
	): array {
		if ( ! is_array( $photos ) ) {
			return array();
		}

		$sanitized = array();

		foreach ( $photos as $photo ) {
			$url = esc_url_raw(
				(string) $photo
			);

			if ( '' !== $url ) {
				$sanitized[] = $url;
			}
		}

		return array_values(
			array_unique( $sanitized )
		);
	}

	/**
	 * Возвращает описание ошибки загрузки.
	 *
	 * @param int $error_code Код ошибки PHP.
	 *
	 * @return string
	 */
	private static function get_upload_error_message(
		int $error_code
	): string {
		$messages = array(
			UPLOAD_ERR_INI_SIZE   => 'Файл превышает лимит PHP.',
			UPLOAD_ERR_FORM_SIZE  => 'Файл превышает лимит формы.',
			UPLOAD_ERR_PARTIAL    => 'Файл загружен не полностью.',
			UPLOAD_ERR_NO_FILE    => 'JSON-файл не выбран.',
			UPLOAD_ERR_NO_TMP_DIR => 'Отсутствует временная папка.',
			UPLOAD_ERR_CANT_WRITE => 'Не удалось записать временный файл.',
			UPLOAD_ERR_EXTENSION  => 'Загрузка остановлена расширением PHP.',
		);

		return $messages[ $error_code ]
			?? 'Неизвестная ошибка загрузки файла.';
	}

	/**
	 * Сохраняет проверенные данные во временное хранилище.
	 *
	 * @param array<string, mixed> $validated_data Проверенные данные.
	 *
	 * @return bool
	 */
	public static function store_validated_data(
		array $validated_data
	): bool {
		$user_id = get_current_user_id();

		if ( $user_id <= 0 ) {
			return false;
		}

		return set_transient(
			self::get_transient_key( $user_id ),
			$validated_data,
			15 * MINUTE_IN_SECONDS
		);
	}

	/**
	 * Возвращает временно сохранённые данные.
	 *
	 * @return array<string, mixed>|false
	 */
	public static function get_validated_data() {
		$user_id = get_current_user_id();

		if ( $user_id <= 0 ) {
			return false;
		}

		$data = get_transient(
			self::get_transient_key( $user_id )
		);

		return is_array( $data )
			? $data
			: false;
	}

	/**
	 * Удаляет временно сохранённые данные.
	 */
	public static function clear_validated_data(): void {
		$user_id = get_current_user_id();

		if ( $user_id <= 0 ) {
			return;
		}

		delete_transient(
			self::get_transient_key( $user_id )
		);
	}

	/**
	 * Импортирует проверенные отзывы.
	 *
	 * @param array<string, mixed> $validated_data Проверенные данные.
	 * @param bool                 $publish_five   Публиковать отзывы на 5 звёзд.
	 *
	 * @return array<string, int>|WP_Error
	 */
	public static function import_reviews(
		array $validated_data,
		bool $publish_five
	) {
		if (
			! isset( $validated_data['reviews'] )
			|| ! is_array( $validated_data['reviews'] )
		) {
			return new WP_Error(
				'missing_validated_reviews',
				'Проверенные отзывы отсутствуют.'
			);
		}

		$result = array(
			'created'   => 0,
			'updated'   => 0,
			'unchanged' => 0,
			'failed'    => 0,
		);

		foreach ( $validated_data['reviews'] as $review ) {
			if ( ! is_array( $review ) ) {
				$result['failed']++;
				continue;
			}

			$import_result = self::import_review(
				$review,
				$publish_five
			);

			if ( is_wp_error( $import_result ) ) {
				$result['failed']++;
				continue;
			}

			if ( isset( $result[ $import_result ] ) ) {
				$result[ $import_result ]++;
			}
		}

		update_option(
			'wh_reviews_last_import',
			array(
				'imported_at'        => current_time( 'mysql' ),
				'source_exported_at' => isset(
					$validated_data['exported_at']
				)
					? sanitize_text_field(
						(string) $validated_data['exported_at']
					)
					: '',
				'result'             => $result,
			),
			false
		);

		return $result;
	}

	/**
	 * Импортирует один отзыв.
	 *
	 * @param array<string, mixed> $review      Данные отзыва.
	 * @param bool                 $publish_five Публиковать отзывы на 5 звёзд.
	 *
	 * @return string|WP_Error
	 */
	private static function import_review(
		array $review,
		bool $publish_five
	) {
		$external_id = sanitize_text_field(
			(string) $review['external_id']
		);

		$existing_post_id = self::find_review_by_external_id(
			$external_id
		);

		$rating = absint( $review['rating'] );

		$should_publish = (
			5 === $rating
			&& $publish_five
		);

		$post_status = $should_publish
			? 'publish'
			: 'draft';

		$post_data = array(
			'post_type'    => 'wh_review',
			'post_title'   => sanitize_text_field(
				(string) $review['author']
			),
			'post_content' => sanitize_textarea_field(
				(string) $review['text']
			),
			'post_status'  => $post_status,
		);

		$content_hash = self::create_content_hash(
			$review,
			$post_status
		);

		if ( $existing_post_id > 0 ) {
			$existing_hash = get_post_meta(
				$existing_post_id,
				'_wh_review_content_hash',
				true
			);

			if (
				is_string( $existing_hash )
				&& hash_equals(
					$existing_hash,
					$content_hash
				)
			) {
				return 'unchanged';
			}

			$post_data['ID'] = $existing_post_id;

			$post_id = wp_update_post(
				wp_slash( $post_data ),
				true
			);

			$action = 'updated';
		} else {
			$post_id = wp_insert_post(
				wp_slash( $post_data ),
				true
			);

			$action = 'created';
		}

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( 0 === absint( $post_id ) ) {
			return new WP_Error(
				'review_save_failed',
				'Не удалось сохранить отзыв.'
			);
		}

		self::save_review_meta(
			absint( $post_id ),
			$review,
			$content_hash,
			$should_publish
		);

		return $action;
	}

	/**
	 * Ищет отзыв по внешнему ID.
	 *
	 * @param string $external_id Внешний ID.
	 *
	 * @return int
	 */
	private static function find_review_by_external_id(
		string $external_id
	): int {
		$query = new WP_Query(
			array(
				'post_type'              => 'wh_review',
				'post_status'            => array(
					'publish',
					'draft',
					'pending',
					'private',
				),
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					array(
						'key'     => '_wh_review_external_id',
						'value'   => $external_id,
						'compare' => '=',
					),
				),
			)
		);

		if ( ! $query->posts ) {
			return 0;
		}

		return absint( $query->posts[0] );
	}

	/**
	 * Сохраняет метаданные отзыва.
	 *
	 * @param int                  $post_id        ID записи.
	 * @param array<string, mixed> $review         Данные отзыва.
	 * @param string               $content_hash   Контрольный хеш.
	 * @param bool                 $should_publish Разрешён ли показ.
	 */
	private static function save_review_meta(
		int $post_id,
		array $review,
		string $content_hash,
		bool $should_publish
	): void {
		update_post_meta(
			$post_id,
			'_wh_review_external_id',
			sanitize_text_field(
				(string) $review['external_id']
			)
		);

		update_post_meta(
			$post_id,
			'_wh_review_rating',
			absint( $review['rating'] )
		);

		update_post_meta(
			$post_id,
			'_wh_review_date_raw',
			sanitize_text_field(
				(string) $review['published_at_raw']
			)
		);

		update_post_meta(
			$post_id,
			'_wh_review_source_url',
			esc_url_raw(
				(string) $review['source_url']
			)
		);

		update_post_meta(
			$post_id,
			'_wh_review_owner_reply',
			sanitize_textarea_field(
				(string) $review['owner_reply']
			)
		);

		update_post_meta(
			$post_id,
			'_wh_review_photos',
			is_array( $review['photos'] )
				? $review['photos']
				: array()
		);

		if (
			! metadata_exists(
				'post',
				$post_id,
				'_wh_review_visible'
			)
		) {
			update_post_meta(
				$post_id,
				'_wh_review_visible',
				$should_publish ? '1' : '0'
			);
		}

		if (
			! metadata_exists(
				'post',
				$post_id,
				'_wh_review_featured'
			)
		) {
			update_post_meta(
				$post_id,
				'_wh_review_featured',
				'0'
			);
		}

		if (
			! metadata_exists(
				'post',
				$post_id,
				'_wh_review_order'
			)
		) {
			update_post_meta(
				$post_id,
				'_wh_review_order',
				0
			);
		}

		update_post_meta(
			$post_id,
			'_wh_review_source',
			'yandex_maps'
		);

		update_post_meta(
			$post_id,
			'_wh_review_content_hash',
			$content_hash
		);

		update_post_meta(
			$post_id,
			'_wh_review_synced_at',
			current_time( 'mysql' )
		);
	}

	/**
	 * Создаёт хеш содержимого отзыва.
	 *
	 * @param array<string, mixed> $review      Данные отзыва.
	 * @param string               $post_status Статус записи.
	 *
	 * @return string
	 */
	private static function create_content_hash(
		array $review,
		string $post_status
	): string {
		$hash_data = array(
			'author'          => (string) $review['author'],
			'rating'          => absint( $review['rating'] ),
			'published_at_raw'=> (string) $review['published_at_raw'],
			'text'            => (string) $review['text'],
			'owner_reply'     => (string) $review['owner_reply'],
			'photos'          => $review['photos'],
			'source_url'      => (string) $review['source_url'],
			'post_status'     => $post_status,
		);

		return hash(
			'sha256',
			(string) wp_json_encode(
				$hash_data,
				JSON_UNESCAPED_UNICODE
				| JSON_UNESCAPED_SLASHES
			)
		);
	}

	/**
	 * Возвращает ключ временного хранилища.
	 *
	 * @param int $user_id ID пользователя.
	 *
	 * @return string
	 */
	private static function get_transient_key(
		int $user_id
	): string {
		return 'wh_reviews_import_' . $user_id;
	}
}