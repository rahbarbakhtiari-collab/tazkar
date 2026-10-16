<?php
/**
 * User-facing printable card builder.
 *
 * @package Dhikra
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Dhikra_Print {
	private const TRANSIENT_TTL = 2 * HOUR_IN_SECONDS;

	public function register(): void {
		add_action( 'template_redirect', array( $this, 'handle_actions' ), 7 );
	}

	public function handle_actions(): void {
		if ( ! Dhikra_Helpers::is_app_request() || ! is_user_logged_in() || 'print' !== Dhikra_Helpers::request_route() ) {
			return;
		}
		if ( 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) ) {
			return;
		}
		$action = isset( $_POST['dhikra_print_action'] ) ? sanitize_key( wp_unslash( $_POST['dhikra_print_action'] ) ) : '';
		if ( 'generate' !== $action ) {
			return;
		}

		check_admin_referer( 'dhikra_generate_print', 'dhikra_print_nonce' );
		$topic_ids = isset( $_POST['dhikra_print_topics'] ) && is_array( $_POST['dhikra_print_topics'] ) ? array_map( 'absint', wp_unslash( $_POST['dhikra_print_topics'] ) ) : array();
		$count = isset( $_POST['dhikra_print_count'] ) ? absint( wp_unslash( $_POST['dhikra_print_count'] ) ) : 0;
		$result = self::create_preview( get_current_user_id(), $topic_ids, $count );
		if ( is_wp_error( $result ) ) { $this->redirect_error( $result->get_error_message() ); }
		$token = $result['token'];
		wp_safe_redirect( Dhikra_Helpers::app_url( 'print-preview', array( 'token' => $token ) ) );
		exit;
	}

	public static function create_preview( int $user_id, array $topic_ids, int $count ): array|WP_Error {
		if ( $user_id <= 0 || ! get_user_by( 'id', $user_id ) ) { return new WP_Error( 'dhikra_print_auth', __( 'هویت کاربر معتبر نیست.', 'dhikra' ), array( 'status' => 403 ) ); }
		$active = Dhikra_Data::active_topic_ids(); $topic_ids = array_values( array_unique( array_intersect( $active, array_map( 'absint', $topic_ids ) ) ) );
		$max = max( 1, absint( Dhikra_Helpers::setting( 'print_max_cards', 500 ) ) );
		if ( empty( $topic_ids ) ) { return new WP_Error( 'dhikra_print_topics', __( 'دست‌کم یک دسته را برای چاپ انتخاب کنید.', 'dhikra' ), array( 'status' => 400 ) ); }
		if ( $count < 1 || $count > $max ) { return new WP_Error( 'dhikra_print_count', sprintf( __( 'تعداد کارت باید بین ۱ و %d باشد.', 'dhikra' ), $max ), array( 'status' => 400 ) ); }
		$selection = self::balanced_selection( $topic_ids, $count ); if ( is_wp_error( $selection ) ) { return $selection; }
		$token = strtolower( wp_generate_password( 24, false, false ) );
		set_transient( self::transient_key( $user_id, $token ), array( 'user_id' => $user_id, 'topic_ids' => $topic_ids, 'post_ids' => $selection['post_ids'], 'unique' => $selection['unique'], 'repeats' => $selection['repeats'], 'created_at' => time() ), self::TRANSIENT_TTL );
		return array( 'token' => $token, 'preview_url' => Dhikra_Helpers::app_url( 'print-preview', array( 'token' => $token ) ) );
	}

	public static function preview_for_user( int $user_id, string $token ): array|WP_Error {
		if ( $user_id <= 0 || '' === $token ) { return new WP_Error( 'dhikra_print_token', __( 'پیش‌نمایش چاپ معتبر نیست.', 'dhikra' ), array( 'status' => 400 ) ); }
		$payload = get_transient( self::transient_key( $user_id, $token ) );
		if ( ! is_array( $payload ) || absint( $payload['user_id'] ?? 0 ) !== $user_id || empty( $payload['post_ids'] ) ) { return new WP_Error( 'dhikra_print_expired', __( 'این پیش‌نمایش منقضی شده است. کارت‌ها را دوباره ایجاد کنید.', 'dhikra' ), array( 'status' => 404 ) ); }
		return $payload;
	}

	private function redirect_error( string $message ): never {
		wp_safe_redirect( add_query_arg( 'dhikra_error', $message, Dhikra_Helpers::app_url( 'print' ) ) );
		exit;
	}

	public static function topic_options(): array {
		$active_ids = Dhikra_Data::active_topic_ids();
		if ( empty( $active_ids ) ) {
			return array();
		}
		$terms = get_terms(
			array(
				'taxonomy'   => 'dhikra_topic',
				'hide_empty' => false,
				'include'    => $active_ids,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
		return is_wp_error( $terms ) ? array() : array_values( array_filter( $terms, fn( $term ) => $term instanceof WP_Term ) );
	}

	/**
	 * Build a balanced, shuffled list from the selected topics.
	 * Posts are unique inside each cycle. Repetition starts only after the
	 * complete eligible pool has been consumed.
	 */
	public static function balanced_selection( array $topic_ids, int $count ): array|WP_Error {
		$topic_ids = array_values( array_unique( array_filter( array_map( 'absint', $topic_ids ) ) ) );
		if ( empty( $topic_ids ) || $count <= 0 ) {
			return new WP_Error( 'dhikra_print_invalid_selection', __( 'انتخاب چاپ معتبر نیست.', 'dhikra' ) );
		}

		$post_ids = get_posts(
			array(
				'post_type'              => 'dhikra_telangor',
				'post_status'            => 'publish',
				'fields'                 => 'ids',
				'posts_per_page'         => -1,
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => true,
				'tax_query'              => array(
					array(
						'taxonomy' => 'dhikra_topic',
						'field'    => 'term_id',
						'terms'    => $topic_ids,
						'operator' => 'IN',
					),
				),
			)
		);
		$post_ids = array_values( array_filter( array_map( 'absint', is_array( $post_ids ) ? $post_ids : array() ), array( self::class, 'is_printable_post' ) ) );
		if ( empty( $post_ids ) ) {
			return new WP_Error( 'dhikra_print_no_content', __( 'در دسته‌های انتخاب‌شده محتوای مناسب چاپ پیدا نشد.', 'dhikra' ) );
		}

		$groups = array_fill_keys( $topic_ids, array() );
		foreach ( $post_ids as $post_id ) {
			$assigned_terms = wp_get_object_terms( $post_id, 'dhikra_topic', array( 'fields' => 'ids' ) );
			if ( is_wp_error( $assigned_terms ) ) {
				continue;
			}
			$eligible_terms = array_values( array_intersect( $topic_ids, array_map( 'absint', $assigned_terms ) ) );
			if ( empty( $eligible_terms ) ) {
				continue;
			}
			$main_topic = absint( get_post_meta( $post_id, '_dhikra_main_topic_id', true ) );
			$bucket     = in_array( $main_topic, $eligible_terms, true ) ? $main_topic : $eligible_terms[0];
			$groups[ $bucket ][] = $post_id;
		}
		$groups = array_filter( $groups );
		if ( empty( $groups ) ) {
			return new WP_Error( 'dhikra_print_no_groups', __( 'محتوای قابل چاپ برای دسته‌های انتخاب‌شده آماده نیست.', 'dhikra' ) );
		}

		$unique_total = array_sum( array_map( 'count', $groups ) );
		$selected     = array();
		while ( count( $selected ) < $count ) {
			$cycle = $groups;
			foreach ( $cycle as &$items ) {
				self::secure_shuffle( $items );
			}
			unset( $items );
			$topic_order = array_keys( $cycle );
			self::secure_shuffle( $topic_order );

			$progress = true;
			while ( $progress && count( $selected ) < $count ) {
				$progress = false;
				foreach ( $topic_order as $topic_id ) {
					if ( empty( $cycle[ $topic_id ] ) ) {
						continue;
					}
					$selected[] = array_pop( $cycle[ $topic_id ] );
					$progress   = true;
					if ( count( $selected ) >= $count ) {
						break;
					}
				}
			}
		}
		self::secure_shuffle( $selected );

		return array(
			'post_ids' => $selected,
			'unique'   => min( $count, $unique_total ),
			'repeats'  => max( 0, $count - $unique_total ),
		);
	}

	public static function is_printable_post( int $post_id ): bool {
		$title       = Dhikra_Helpers::normalize_text( get_the_title( $post_id ) );
		$original    = Dhikra_Helpers::normalize_text( (string) get_post_meta( $post_id, '_dhikra_original_text', true ) );
		$translation = Dhikra_Helpers::normalize_text( (string) get_post_meta( $post_id, '_dhikra_translation', true ) );
		$reflection  = Dhikra_Helpers::normalize_text( (string) get_post_meta( $post_id, '_dhikra_reflection', true ) );
		$total       = Dhikra_Helpers::unicode_length( $title . $original . $translation . $reflection );
		return '' !== $title && $total > 0 && $total <= 1800 && Dhikra_Helpers::unicode_length( $original ) <= 800 && Dhikra_Helpers::unicode_length( $translation ) <= 800;
	}

	private static function secure_shuffle( array &$items ): void {
		for ( $i = count( $items ) - 1; $i > 0; $i-- ) {
			$j = random_int( 0, $i );
			if ( $i !== $j ) {
				$tmp         = $items[ $i ];
				$items[ $i ] = $items[ $j ];
				$items[ $j ] = $tmp;
			}
		}
	}

	private static function transient_key( int $user_id, string $token ): string {
		return 'dhikra_print_' . $user_id . '_' . preg_replace( '/[^a-z0-9]/', '', strtolower( $token ) );
	}

	public static function preview_payload(): array|WP_Error {
		if ( ! is_user_logged_in() ) { return new WP_Error( 'dhikra_print_auth', __( 'برای مشاهده پیش‌نمایش وارد حساب شوید.', 'dhikra' ) ); }
		$token = isset( $_GET['token'] ) ? sanitize_key( wp_unslash( $_GET['token'] ) ) : '';
		return self::preview_for_user( get_current_user_id(), $token );
	}

	public static function card_density_class( array $data ): string {
		$length = Dhikra_Helpers::unicode_length( (string) ( $data['title'] ?? '' ) . (string) ( $data['original'] ?? '' ) . (string) ( $data['translation'] ?? '' ) . (string) ( $data['reflection'] ?? '' ) );
		if ( $length > 1150 ) {
			return 'dhikra-print-card--very-dense';
		}
		if ( $length > 750 ) {
			return 'dhikra-print-card--dense';
		}
		return '';
	}

	public static function render_print_card( int $post_id ): string {
		$data = Dhikra_Random::telangor_data( $post_id, 'general', false, false );
		if ( empty( $data ) ) {
			return '';
		}
		$density = self::card_density_class( $data );
		$classes = trim( 'dhikra-print-card ' . $density );
		$qr_url  = DHIKRA_URL . 'assets/images/eitaa-zekra-qr.png';
		ob_start();
		?>
		<article class="<?php echo esc_attr( $classes ); ?>">
			<div class="dhikra-print-card__ornament" aria-hidden="true"></div>
			<header class="dhikra-print-card__header">
				<div class="dhikra-print-card__brand"><span>ذ</span><strong><?php echo esc_html( Dhikra_Helpers::PRODUCT_NAME ); ?></strong></div>
				<?php if ( '' !== $data['main_topic_name'] ) : ?><span class="dhikra-print-card__topic"><?php echo esc_html( $data['main_topic_name'] ); ?></span><?php endif; ?>
			</header>
			<div class="dhikra-print-card__body">
				<?php if ( '' !== $data['title'] ) : ?><h2><?php echo esc_html( $data['title'] ); ?></h2><?php endif; ?>
				<?php if ( '' !== $data['original'] ) : ?><div class="dhikra-print-card__original" lang="ar" dir="rtl"><?php echo nl2br( esc_html( $data['original'] ) ); ?></div><?php endif; ?>
				<?php if ( '' !== $data['translation'] ) : ?><div class="dhikra-print-card__translation" dir="rtl"><?php echo nl2br( esc_html( $data['translation'] ) ); ?></div><?php endif; ?>
				<?php if ( '' !== $data['reflection'] ) : ?><div class="dhikra-print-card__reflection" dir="rtl"><?php echo nl2br( esc_html( $data['reflection'] ) ); ?></div><?php endif; ?>
			</div>
			<footer class="dhikra-print-card__footer">
				<div class="dhikra-print-card__meta">
					<?php if ( '' !== $data['speaker'] ) : ?><span><?php echo esc_html( $data['speaker'] ); ?></span><?php endif; ?>
					<?php if ( '' !== $data['source'] ) : ?><span><?php echo esc_html( $data['source'] ); ?></span><?php endif; ?>
				</div>
				<div class="dhikra-print-card__qr">
					<img src="<?php echo esc_url( $qr_url ); ?>" alt="<?php esc_attr_e( 'QR ورود به برنامک ایتا ذکری', 'dhikra' ); ?>" width="180" height="180">
					<div><strong><?php esc_html_e( 'ذکری را در ایتا باز کنید', 'dhikra' ); ?></strong><span><?php esc_html_e( 'برای دریافت تلنگرهای بیشتر، کد را اسکن کنید.', 'dhikra' ); ?></span></div>
				</div>
			</footer>
		</article>
		<?php
		return (string) ob_get_clean();
	}

}
