<?php
/**
 * Equal-chance random selection and card rendering.
 *
 * @package Dhikra
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Dhikra_Random {
	public function register(): void {
		add_action( 'save_post_dhikra_telangor', array( self::class, 'invalidate_pool' ) );
		add_action( 'edited_dhikra_topic', array( self::class, 'invalidate_pool' ) );
		add_action( 'created_dhikra_topic', array( self::class, 'invalidate_pool' ) );
	}

	public static function invalidate_pool(): void {
		update_option( 'dhikra_pool_version', wp_generate_uuid4(), false );
	}

	public static function random_post_id( array $exclude_ids = array(), int $user_id = 0 ): int {
		$active_topics = Dhikra_Data::active_topic_ids();
		if ( empty( $active_topics ) ) {
			return 0;
		}
		$excluded_topics = $user_id > 0 ? Dhikra_Data::excluded_topic_ids( $user_id ) : array();
		$allowed_topics  = array_values( array_diff( $active_topics, $excluded_topics ) );
		if ( empty( $allowed_topics ) ) {
			return 0;
		}

		$pool_version = (string) get_option( 'dhikra_pool_version', 'initial' );
		$cache_key    = 'dhikra_pool_' . md5( implode( ',', $allowed_topics ) . '|' . $pool_version );
		$pool         = get_transient( $cache_key );
		if ( ! is_array( $pool ) ) {
			$pool = get_posts(
				array(
					'post_type'              => 'dhikra_telangor',
					'post_status'            => 'publish',
					'fields'                 => 'ids',
					'posts_per_page'         => -1,
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'meta_query'             => array(
						array(
							'key'     => '_dhikra_main_topic_id',
							'value'   => $allowed_topics,
							'compare' => 'IN',
							'type'    => 'NUMERIC',
						),
					),
				)
			);
			$pool = array_values( array_filter( array_map( 'absint', is_array( $pool ) ? $pool : array() ) ) );
			set_transient( $cache_key, $pool, 5 * MINUTE_IN_SECONDS );
		}
		if ( empty( $pool ) ) {
			return 0;
		}

		$exclude_ids = array_values( array_unique( array_filter( array_map( 'absint', $exclude_ids ) ) ) );
		$candidates  = array_values( array_diff( $pool, $exclude_ids ) );
		if ( empty( $candidates ) ) {
			$half       = array_slice( $exclude_ids, 0, (int) floor( count( $exclude_ids ) / 2 ) );
			$candidates = array_values( array_diff( $pool, $half ) );
		}
		if ( empty( $candidates ) ) {
			$candidates = $pool;
		}
		return $candidates[ random_int( 0, count( $candidates ) - 1 ) ];
	}

	/** Pick a published card whose main topic is in the supplied campaign scope. */
	public static function random_post_id_for_topics( array $topic_ids, array $exclude_ids = array() ): int {
		$active    = Dhikra_Data::active_topic_ids();
		$topic_ids = array_values( array_intersect( $active, array_unique( array_filter( array_map( 'absint', $topic_ids ) ) ) ) );
		if ( empty( $topic_ids ) ) {
			return 0;
		}

		$pool_version = (string) get_option( 'dhikra_pool_version', 'initial' );
		$cache_key    = 'dhikra_scope_' . md5( implode( ',', $topic_ids ) . '|' . $pool_version );
		$pool         = get_transient( $cache_key );
		if ( ! is_array( $pool ) ) {
			$pool = get_posts(
				array(
					'post_type'              => 'dhikra_telangor',
					'post_status'            => 'publish',
					'fields'                 => 'ids',
					'posts_per_page'         => -1,
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'meta_query'             => array(
						array(
							'key'     => '_dhikra_main_topic_id',
							'value'   => $topic_ids,
							'compare' => 'IN',
							'type'    => 'NUMERIC',
						),
					),
				)
			);
			$pool = array_values( array_filter( array_map( 'absint', is_array( $pool ) ? $pool : array() ) ) );
			set_transient( $cache_key, $pool, 5 * MINUTE_IN_SECONDS );
		}

		$exclude_ids = array_values( array_unique( array_filter( array_map( 'absint', $exclude_ids ) ) ) );
		$candidates  = array_values( array_diff( $pool, $exclude_ids ) );
		if ( empty( $candidates ) ) {
			$candidates = $pool;
		}
		return empty( $candidates ) ? 0 : $candidates[ random_int( 0, count( $candidates ) - 1 ) ];
	}

	public static function next_for_topics( array $topic_ids, array $guest_exclude_codes = array() ): int {
		$exclude_ids = array();
		foreach ( array_slice( $guest_exclude_codes, 0, 30 ) as $code ) {
			$post_id = Dhikra_Data::find_post_by_code( (string) $code, true );
			if ( $post_id > 0 ) {
				$exclude_ids[] = $post_id;
			}
		}
		return self::random_post_id_for_topics( $topic_ids, $exclude_ids );
	}

	public static function next_for_request( array $guest_exclude_codes = array() ): int {
		$user_id    = get_current_user_id();
		$window     = absint( Dhikra_Helpers::setting( 'near_repeat_window', 12 ) );
		$exclude_ids = $user_id > 0 ? Dhikra_Data::recent_history_ids( $user_id, $window ) : array();
		foreach ( array_slice( $guest_exclude_codes, 0, 30 ) as $code ) {
			$post_id = Dhikra_Data::find_post_by_code( (string) $code, true );
			if ( $post_id > 0 ) {
				$exclude_ids[] = $post_id;
			}
		}
		$post_id = self::random_post_id( $exclude_ids, $user_id );
		if ( $user_id > 0 && $post_id > 0 ) {
			Dhikra_Data::record_history( $user_id, $post_id );
		}
		return $post_id;
	}

	public static function telangor_data( int $post_id, string $platform = 'general', bool $include_personal = true, bool $include_share = true ): array {
		static $request_cache = array();
		$platform = sanitize_key( $platform );
		$user_id  = $include_personal && is_user_logged_in() ? get_current_user_id() : 0;
		$cache_key = $post_id . '|' . $platform . '|' . $user_id . '|' . ( $include_share ? 'share' : 'plain' );
		if ( isset( $request_cache[ $cache_key ] ) ) {
			return $request_cache[ $cache_key ];
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return array();
		}
		$type          = sanitize_key( (string) get_post_meta( $post_id, '_dhikra_type', true ) );
		$type          = in_array( $type, Dhikra_Content::TYPES, true ) ? $type : 'quran';
		$main_topic_id = absint( get_post_meta( $post_id, '_dhikra_main_topic_id', true ) );
		$term          = $main_topic_id > 0 ? get_term( $main_topic_id, 'dhikra_topic' ) : null;
		$topic_name    = $term instanceof WP_Term ? $term->name : '';
		$topic_slug    = $term instanceof WP_Term ? $term->slug : '';
		$data          = array(
			'id'                  => $post_id,
			'code'                => Dhikra_Data::get_short_code( $post_id ),
			'content_id'          => (string) get_post_meta( $post_id, '_dhikra_content_id', true ),
			'translation_group'   => (string) get_post_meta( $post_id, '_dhikra_translation_group', true ),
			'language'            => (string) get_post_meta( $post_id, '_dhikra_language', true ),
			'type'                => $type,
			'type_label'          => Dhikra_Content::type_labels()[ $type ] ?? '',
			'title'               => get_the_title( $post_id ),
			'original'            => (string) get_post_meta( $post_id, '_dhikra_original_text', true ),
			'translation'         => (string) get_post_meta( $post_id, '_dhikra_translation', true ),
			'reflection'          => (string) get_post_meta( $post_id, '_dhikra_reflection', true ),
			'speaker'             => (string) get_post_meta( $post_id, '_dhikra_speaker', true ),
			'source'              => (string) get_post_meta( $post_id, '_dhikra_source', true ),
			'main_topic_id'       => $main_topic_id,
			'main_topic_name'     => $topic_name,
			'main_topic_slug'     => $topic_slug,
			'theme'               => array(
				'key'       => 'uniform',
				'primary'   => '#1F63C3',
				'secondary' => '#DCEBFF',
				'accent'    => '#D4A72C',
				'ink'       => '#102A43',
				'surface'   => '#F8FAFC',
			),
			'url'                 => Dhikra_Helpers::telangor_url( $post_id ),
			'is_favorite'         => $user_id > 0 ? Dhikra_Data::is_favorite( $user_id, $post_id ) : false,
		);
		$data['share_text'] = '';
		$data['share_texts'] = array();
		$data['share_text_eitaa_full'] = '';
		if ( $include_share ) {
			$data['share_text'] = Dhikra_Helpers::render_share_template( Dhikra_Helpers::share_template( $platform ), $data );
			foreach ( array( 'general', 'eitaa', 'bale', 'telegram' ) as $name ) {
				$data['share_texts'][ $name ] = Dhikra_Helpers::render_share_template( Dhikra_Helpers::share_template( $name ), $data );
			}
			$data['share_text_eitaa_full'] = self::eitaa_full_share_text( $data );
		}
		$request_cache[ $cache_key ] = $data;
		return $data;
	}

	/**
	 * Build the canonical Eitaa share message. This intentionally does not use
	 * the editable share template: the mini-app share action must always carry
	 * the complete card plus the canonical Eitaa mini-app link.
	 */
	private static function eitaa_full_share_text( array $data ): string {
		$parts = array();
		$title = trim( (string) ( $data['title'] ?? '' ) );
		if ( '' !== $title ) {
			$parts[] = $title;
		}

		$speaker = trim( (string) ( $data['speaker'] ?? '' ) );
		if ( '' !== $speaker ) {
			$parts[] = self::speaker_intro( $speaker );
		}

		foreach ( array( 'original', 'translation' ) as $key ) {
			$value = trim( (string) ( $data[ $key ] ?? '' ) );
			if ( '' !== $value ) {
				$parts[] = $value;
			}
		}

		$reflection = trim( (string) ( $data['reflection'] ?? '' ) );
		if ( '' !== $reflection ) {
			$parts[] = "تلنگر:\n" . $reflection;
		}

		$meta = array();
		if ( '' !== trim( (string) ( $data['type_label'] ?? '' ) ) ) {
			$meta[] = 'نوع: ' . trim( (string) $data['type_label'] );
		}
		if ( '' !== trim( (string) ( $data['main_topic_name'] ?? '' ) ) ) {
			$meta[] = 'موضوع: ' . trim( (string) $data['main_topic_name'] );
		}
		if ( '' !== trim( (string) ( $data['source'] ?? '' ) ) ) {
			$meta[] = 'منبع: ' . trim( (string) $data['source'] );
		}
		if ( ! empty( $meta ) ) {
			$parts[] = implode( "\n", $meta );
		}

		$parts[] = Dhikra_Helpers::EITAA_APP_URL;
		return trim( implode( "\n\n", $parts ) );
	}

	private static function speaker_intro( string $speaker ): string {
		$speaker = trim( $speaker );
		if ( '' === $speaker ) {
			return '';
		}
		return preg_match( '/[:：؛،.!؟]$/u', $speaker ) ? $speaker : $speaker . ':';
	}

	public static function render_card( int $post_id, bool $embedded = false ): string {
		$data = self::telangor_data( $post_id );
		if ( empty( $data ) ) {
			return '<div class="dhikra-empty">' . esc_html__( 'تلنگری برای نمایش موجود نیست.', 'dhikra' ) . '</div>';
		}
		$settings = Dhikra_Helpers::settings();
		$style    = sanitize_key( (string) ( $settings['card_style'] ?? 'royal' ) );
		$style    = in_array( $style, array( 'minimal', 'royal', 'paper', 'dark' ), true ) ? $style : 'royal';
		$classes  = 'dhikra-card dhikra-card--' . sanitize_html_class( $data['type'] ) . ' dhikra-theme-' . sanitize_html_class( $style );
		$language = strtolower( (string) $data['language'] );
		$script   = in_array( $data['type'], array( 'quran', 'hadith', 'prayer' ), true ) || str_starts_with( $language, 'ar' ) ? 'arabic' : 'persian';
		if ( $embedded ) {
			$classes .= ' dhikra-card--embedded';
		}

		ob_start();
		?>
		<article class="<?php echo esc_attr( $classes ); ?>" data-dhikra-card data-id="<?php echo esc_attr( (string) $post_id ); ?>" data-code="<?php echo esc_attr( $data['code'] ); ?>" data-url="<?php echo esc_url( $data['url'] ); ?>" data-favorite="<?php echo $data['is_favorite'] ? 'true' : 'false'; ?>" data-share-text="<?php echo esc_attr( $data['share_text'] ); ?>" data-share-text-eitaa="<?php echo esc_attr( $data['share_texts']['eitaa'] ); ?>" data-share-text-eitaa-full="<?php echo esc_attr( $data['share_text_eitaa_full'] ); ?>">
			<div class="dhikra-card__veil" aria-hidden="true"></div>
			<div class="dhikra-card__halo" aria-hidden="true"></div>
			<div class="dhikra-card__content" data-dhikra-fit-content>
				<header class="dhikra-card__masthead">
					<div class="dhikra-card__brand" aria-label="<?php esc_attr_e( 'ذِكْرَىٰ', 'dhikra' ); ?>"><span class="dhikra-card__brand-mark" aria-hidden="true">ذ</span><span class="dhikra-card__brand-name"><?php esc_html_e( 'ذِكْرَىٰ', 'dhikra' ); ?></span></div>
					<div class="dhikra-card__chips" aria-label="<?php esc_attr_e( 'مشخصات تلنگر', 'dhikra' ); ?>">
						<?php if ( '' !== $data['type_label'] ) : ?><span class="dhikra-card__chip dhikra-card__chip--type"><?php echo esc_html( $data['type_label'] ); ?></span><?php endif; ?>
						<?php if ( '' !== $data['main_topic_name'] ) : ?><span class="dhikra-card__chip dhikra-card__chip--topic"><?php echo esc_html( $data['main_topic_name'] ); ?></span><?php endif; ?>
					</div>
				</header>
				<div class="dhikra-card__body">
					<?php if ( ! empty( $settings['display_title'] ) && '' !== $data['title'] ) : ?><h2 class="dhikra-card__title"><?php echo esc_html( $data['title'] ); ?></h2><?php endif; ?>
					<?php if ( ! empty( $settings['display_speaker'] ) && '' !== $data['speaker'] ) : ?><div class="dhikra-card__speaker-intro"><?php echo esc_html( self::speaker_intro( $data['speaker'] ) ); ?></div><?php endif; ?>
					<?php if ( ! empty( $settings['display_original'] ) && '' !== $data['original'] ) : ?><div class="dhikra-card__original" data-script="<?php echo esc_attr( $script ); ?>" dir="rtl" lang="<?php echo esc_attr( 'arabic' === $script ? 'ar' : 'fa' ); ?>"><span class="dhikra-card__quote-mark" aria-hidden="true">✦</span><span class="dhikra-card__original-text"><?php echo nl2br( esc_html( $data['original'] ) ); ?></span></div><?php endif; ?>
					<?php if ( 'story' !== $data['type'] && ! empty( $settings['display_translation'] ) && '' !== $data['translation'] ) : ?><div class="dhikra-card__translation" dir="auto"><?php echo nl2br( esc_html( $data['translation'] ) ); ?></div><?php endif; ?>
					<?php if ( 'story' !== $data['type'] && ! empty( $settings['display_reflection'] ) && '' !== $data['reflection'] ) : ?><div class="dhikra-card__reflection" dir="auto"><span class="dhikra-card__reflection-label"><?php esc_html_e( 'تلنگر', 'dhikra' ); ?></span><span class="dhikra-card__reflection-text"><?php echo nl2br( esc_html( $data['reflection'] ) ); ?></span></div><?php endif; ?>
				</div>
				<?php if ( ! empty( $settings['display_source'] ) && '' !== $data['source'] ) : ?>
					<footer class="dhikra-card__meta">
						<span class="dhikra-card__meta-icon" aria-hidden="true">◇</span>
						<div class="dhikra-card__meta-text">
							<?php if ( ! empty( $settings['display_source'] ) && '' !== $data['source'] ) : ?><span class="dhikra-card__source"><?php echo esc_html( $data['source'] ); ?></span><?php endif; ?>
						</div>
					</footer>
				<?php endif; ?>
			</div>
		</article>
		<?php
		return (string) ob_get_clean();
	}
}
