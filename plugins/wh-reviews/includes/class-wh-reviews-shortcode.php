<?php
/**
 * Вывод отзывов на сайте.
 *
 * @package WH_Reviews
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WH_Reviews_Shortcode {

	/**
	 * Подключает шорткод.
	 */
	public static function init(): void {
		add_shortcode(
			'wh_reviews',
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Выводит выбранные отзывы.
	 *
	 * @param array<string, mixed> $attributes Атрибуты.
	 *
	 * @return string
	 */
	public static function render(
		array $attributes = array()
	): string {
		$attributes = shortcode_atts(
			array(
				'limit' => 6,
			),
			$attributes,
			'wh_reviews'
		);

		$limit = max(
			1,
			min(
				absint( $attributes['limit'] ),
				12
			)
		);

		$query = new WP_Query(
			array(
				'post_type'           => 'wh_review',
				'post_status'         => 'publish',
				'posts_per_page'      => $limit,
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				'meta_key'            => '_wh_review_order',
				'orderby'             => array(
					'meta_value_num' => 'ASC',
					'date'           => 'DESC',
				),
				'meta_query'          => array(
					'relation' => 'AND',
					array(
						'key'     => '_wh_review_rating',
						'value'   => 5,
						'compare' => '=',
						'type'    => 'NUMERIC',
					),
					array(
						'key'     => '_wh_review_visible',
						'value'   => '1',
						'compare' => '=',
					),
					array(
						'key'     => '_wh_review_featured',
						'value'   => '1',
						'compare' => '=',
					),
				),
			)
		);

		if ( ! $query->have_posts() ) {
			return '';
		}

		wp_enqueue_style(
			'wh-reviews',
			WH_REVIEWS_URL . 'assets/css/reviews.css',
			array(),
			WH_REVIEWS_VERSION
		);

		wp_enqueue_script(
			'wh-reviews',
			WH_REVIEWS_URL . 'assets/js/reviews.js',
			array(),
			WH_REVIEWS_VERSION,
			true
		);

		$reviews = array();

		while ( $query->have_posts() ) {
			$query->the_post();

			$post_id = get_the_ID();

			$reviews[] = array(
				'id'         => $post_id,
				'author'     => get_the_title(),
				'text'       => trim(
					wp_strip_all_tags(
						get_the_content()
					)
				),
				'rating'     => absint(
					get_post_meta(
						$post_id,
						'_wh_review_rating',
						true
					)
				),
				'date'       => (string) get_post_meta(
					$post_id,
					'_wh_review_date_raw',
					true
				),
				'source_url' => (string) get_post_meta(
					$post_id,
					'_wh_review_source_url',
					true
				),
			);
		}

		wp_reset_postdata();

		ob_start();
		?>
		<div class="wh-reviews" data-wh-reviews>
			<div class="wh-reviews-list">
				<?php foreach ( $reviews as $index => $review ) : ?>
					<?php
					$excerpt = wp_trim_words(
						$review['text'],
						34,
						'…'
					);

					$has_more = (
						$excerpt !== $review['text']
					);
					?>
					<article class="wh-review-card">
						<div class="wh-review-card__top">
							<div
								class="wh-review-card__stars"
								aria-label="<?php
								echo esc_attr(
									sprintf(
										'Оценка %d из 5',
										$review['rating']
									)
								);
								?>"
							>
								<?php
								echo esc_html(
									str_repeat(
										'★',
										$review['rating']
									)
								);
								?>
							</div>

							<span class="wh-review-card__source">
								Яндекс
							</span>
						</div>

						<blockquote class="wh-review-card__text">
							<?php echo esc_html( $excerpt ); ?>
						</blockquote>

						<?php if ( $has_more ) : ?>
							<button
								type="button"
								class="wh-review-card__open"
								data-review-open="<?php echo esc_attr( (string) $index ); ?>"
							>
								Читать отзыв
							</button>
						<?php endif; ?>

						<footer class="wh-review-card__footer">
							<div>
								<strong class="wh-review-card__author">
									<?php echo esc_html( $review['author'] ); ?>
								</strong>

								<?php if ( $review['date'] ) : ?>
									<span class="wh-review-card__date">
										<?php echo esc_html( $review['date'] ); ?>
									</span>
								<?php endif; ?>
							</div>

							<a
								class="wh-review-card__link"
								href="<?php echo esc_url( $review['source_url'] ); ?>"
								target="_blank"
								rel="noopener noreferrer"
							>
								Источник
							</a>
						</footer>
					</article>
				<?php endforeach; ?>
			</div>

			<?php
			echo '<dialog class="wh-review-dialog" data-review-dialog aria-labelledby="wh-review-dialog-author">';
			echo '<div class="wh-review-dialog__surface">';

			echo '<button type="button" class="wh-review-dialog__close" data-review-close aria-label="Закрыть отзыв">';
			echo '<span aria-hidden="true">×</span>';
			echo '</button>';

			echo '<div class="wh-review-dialog__stars" aria-label="Оценка 5 из 5">';
			echo '★★★★★';
			echo '</div>';

			echo '<blockquote class="wh-review-dialog__text" data-review-dialog-text></blockquote>';

			echo '<footer class="wh-review-dialog__footer">';
			echo '<div>';
			echo '<strong id="wh-review-dialog-author" data-review-dialog-author></strong>';
			echo '<span class="wh-review-dialog__date" data-review-dialog-date></span>';
			echo '</div>';

			echo '<a class="wh-review-dialog__link" data-review-dialog-link href="#" target="_blank" rel="noopener noreferrer">';
			echo 'Открыть в Яндексе';
			echo '</a>';

			echo '</footer>';
			echo '</div>';
			echo '</dialog>';
			?>

			<script type="application/json" data-review-data>
				<?php
				echo wp_json_encode(
					$reviews,
					JSON_UNESCAPED_UNICODE
					| JSON_UNESCAPED_SLASHES
					| JSON_HEX_TAG
					| JSON_HEX_AMP
				);
				?>
			</script>
		</div>
		<?php

		return (string) ob_get_clean();
	}
}
