<?php
/**
 * Public and authenticated REST API.
 *
 * @package Dhikra
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Dhikra_REST {
	private const NS = 'dhikra/v1';

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			self::NS,
			'/telangors',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_telangors' ),
				'permission_callback' => array( $this, 'public_permission' ),
				'args'                => array(
					'page'     => array( 'sanitize_callback' => 'absint', 'default' => 1 ),
					'per_page' => array( 'sanitize_callback' => 'absint', 'default' => 20 ),
					'type'     => array( 'sanitize_callback' => 'sanitize_key' ),
					'topic'    => array( 'sanitize_callback' => 'sanitize_title' ),
					'search'   => array( 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ),
					'platform' => array( 'sanitize_callback' => 'sanitize_key', 'default' => 'general' ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/telangors/random',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'random_telangor' ),
				'permission_callback' => array( $this, 'public_permission' ),
				'args'                => array(
					'exclude'  => array( 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ),
					'platform' => array( 'sanitize_callback' => 'sanitize_key', 'default' => 'general' ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/telangors/(?P<code>[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{5})',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'single_telangor' ),
				'permission_callback' => array( $this, 'public_permission' ),
				'args'                => array( 'platform' => array( 'sanitize_callback' => 'sanitize_key', 'default' => 'general' ) ),
			)
		);
		register_rest_route(
			self::NS,
			'/topics',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'topics' ),
				'permission_callback' => array( $this, 'public_permission' ),
			)
		);
		register_rest_route(
			self::NS,
			'/favorites',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'favorites' ),
					'permission_callback' => array( $this, 'logged_in_permission' ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/favorites/(?P<code>[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{5})',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'toggle_favorite' ),
				'permission_callback' => array( $this, 'logged_in_permission' ),
				'args'                => array( 'favorite' => array( 'type' => 'boolean', 'required' => true ) ),
			)
		);
		register_rest_route(
			self::NS,
			'/notes/(?P<code>[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{5})',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'note' ),
					'permission_callback' => array( $this, 'logged_in_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save_note' ),
					'permission_callback' => array( $this, 'logged_in_permission' ),
					'args'                => array( 'note' => array( 'type' => 'string', 'required' => true ) ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/preferences',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'preferences' ),
					'permission_callback' => array( $this, 'logged_in_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save_preferences' ),
					'permission_callback' => array( $this, 'logged_in_permission' ),
					'args'                => array( 'selected_topic_ids' => array( 'type' => 'array', 'required' => true, 'items' => array( 'type' => 'integer' ) ) ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/history',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'history' ),
				'permission_callback' => array( $this, 'logged_in_permission' ),
				'args'                => array( 'page' => array( 'sanitize_callback' => 'absint', 'default' => 1 ) ),
			)
		);

		register_rest_route(
			self::NS,
			'/eitaa/session',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'eitaa_session' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'init_data'    => array( 'type' => 'string', 'required' => true ),
					'card_post_id' => array( 'type' => 'integer', 'required' => false ),
					'visit_token'  => array( 'type' => 'string', 'required' => false, 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/eitaa/random',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'eitaa_random' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'init_data' => array( 'type' => 'string', 'required' => true ),
					'exclude'   => array( 'type' => 'string', 'required' => false, 'default' => '' ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/eitaa/card/(?P<code>[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{5})',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'eitaa_card' ),
				'permission_callback' => '__return_true',
				'args'                => array( 'init_data' => array( 'type' => 'string', 'required' => true ) ),
			)
		);
		register_rest_route(
			self::NS,
			'/eitaa/favorites',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'eitaa_favorites' ),
				'permission_callback' => '__return_true',
				'args'                => array( 'init_data' => array( 'type' => 'string', 'required' => true ) ),
			)
		);
		register_rest_route(
			self::NS,
			'/eitaa/favorites/(?P<code>[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{5})',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'eitaa_toggle_favorite' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'init_data' => array( 'type' => 'string', 'required' => true ),
					'favorite'  => array( 'type' => 'boolean', 'required' => true ),
				),
			)
		);
		foreach ( array(
			'account' => 'eitaa_account',
			'preferences' => 'eitaa_preferences',
			'history' => 'eitaa_history',
			'print' => 'eitaa_print',
			'print-preview' => 'eitaa_print_preview',
		) as $path => $callback ) {
			register_rest_route(
				self::NS,
				'/eitaa/' . $path,
				array(
					'methods' => WP_REST_Server::CREATABLE,
					'callback' => array( $this, $callback ),
					'permission_callback' => '__return_true',
					'args' => array( 'init_data' => array( 'type' => 'string', 'required' => true ) ),
				)
			);
		}
		register_rest_route(
			self::NS,
			'/eitaa/note/(?P<code>[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{5})',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'eitaa_note' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'init_data' => array( 'type' => 'string', 'required' => true ),
					'action'    => array( 'type' => 'string', 'required' => false, 'default' => 'get', 'sanitize_callback' => 'sanitize_key' ),
					'note'      => array( 'type' => 'string', 'required' => false, 'default' => '' ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/eitaa/reminder/(?P<code>[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{5})',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'eitaa_reminder' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'init_data' => array( 'type' => 'string', 'required' => true ),
					'preset'    => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_key' ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/eitaa/write-access',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'eitaa_write_access' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'init_data' => array( 'type' => 'string', 'required' => true ),
					'granted'   => array( 'type' => 'boolean', 'required' => true ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/eitaa/daily',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'eitaa_daily' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'init_data'            => array( 'type' => 'string', 'required' => true ),
					'enabled'              => array( 'type' => 'boolean', 'required' => true ),
					'write_access_granted' => array( 'type' => 'boolean', 'required' => false ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/eitaa/daily/dismiss',
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'eitaa_daily_dismiss' ),
				'permission_callback' => '__return_true',
				'args' => array( 'init_data' => array( 'type' => 'string', 'required' => true ) ),
			)
		);
		register_rest_route(
			self::NS,
			'/identity/link',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'link_identity' ),
				'permission_callback' => array( $this, 'logged_in_permission' ),
				'args'                => array(
					'provider'          => array( 'sanitize_callback' => 'sanitize_key', 'required' => true ),
					'verification_data' => array( 'required' => true ),
				),
			)
		);
	}

	public function public_permission(): bool|WP_Error {
		return ! empty( Dhikra_Helpers::setting( 'api_public_enabled', 1 ) ) ? true : new WP_Error( 'dhikra_api_disabled', __( 'API عمومی غیرفعال است.', 'dhikra' ), array( 'status' => 403 ) );
	}

	public function logged_in_permission(): bool|WP_Error {
		return is_user_logged_in() ? true : new WP_Error( 'dhikra_login_required', __( 'برای این عملیات باید وارد شوید.', 'dhikra' ), array( 'status' => 401 ) );
	}

	public function list_telangors( WP_REST_Request $request ): WP_REST_Response {
		$page      = max( 1, absint( $request['page'] ) );
		$per_page  = min( 50, max( 1, absint( $request['per_page'] ) ) );
		$type      = sanitize_key( (string) $request['type'] );
		$topic     = sanitize_title( (string) $request['topic'] );
		$search    = trim( sanitize_text_field( (string) $request['search'] ) );
		$platform  = sanitize_key( (string) $request['platform'] );
		$active_topics = Dhikra_Data::active_topic_ids();
		if ( empty( $active_topics ) ) {
			return new WP_REST_Response( array( 'items' => array(), 'page' => $page, 'per_page' => $per_page, 'total' => 0, 'total_pages' => 0 ) );
		}
		$args = array(
			'post_type'              => 'dhikra_telangor',
			'post_status'            => 'publish',
			'posts_per_page'         => '' !== $search ? -1 : $per_page,
			'paged'                  => '' !== $search ? 1 : $page,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'fields'                 => 'ids',
			'no_found_rows'          => '' !== $search,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
			'meta_query'             => array(
				array(
					'key'     => '_dhikra_main_topic_id',
					'value'   => $active_topics,
					'compare' => 'IN',
					'type'    => 'NUMERIC',
				),
			),
		);
		if ( in_array( $type, Dhikra_Content::TYPES, true ) ) {
			$args['meta_query'][] = array( 'key' => '_dhikra_type', 'value' => $type );
		}
		if ( '' !== $topic ) {
			$args['tax_query'] = array( array( 'taxonomy' => 'dhikra_topic', 'field' => 'slug', 'terms' => $topic ) );
		}
		$query = new WP_Query( $args );
		$ids = array_map( 'absint', $query->posts );
		$total = absint( $query->found_posts );
		$total_pages = absint( $query->max_num_pages );

		if ( '' !== $search ) {
			$matches = array();
			foreach ( $ids as $post_id ) {
				$main_topic_id = absint( get_post_meta( $post_id, '_dhikra_main_topic_id', true ) );
				$term = $main_topic_id > 0 ? get_term( $main_topic_id, 'dhikra_topic' ) : null;
				$haystack = implode( "\n", array(
					get_the_title( $post_id ),
					(string) get_post_meta( $post_id, '_dhikra_original_text', true ),
					(string) get_post_meta( $post_id, '_dhikra_translation', true ),
					(string) get_post_meta( $post_id, '_dhikra_reflection', true ),
					(string) get_post_meta( $post_id, '_dhikra_speaker', true ),
					(string) get_post_meta( $post_id, '_dhikra_source', true ),
					$term instanceof WP_Term ? $term->name : '',
				) );
				$found = function_exists( 'mb_stripos' ) ? false !== mb_stripos( $haystack, $search, 0, 'UTF-8' ) : false !== stripos( $haystack, $search );
				if ( $found ) { $matches[] = $post_id; }
			}
			$total = count( $matches );
			$total_pages = (int) ceil( $total / $per_page );
			$ids = array_slice( $matches, ( $page - 1 ) * $per_page, $per_page );
		}

		$items = array_map( static fn( int $post_id ): array => Dhikra_Random::telangor_data( $post_id, $platform, false, false ), $ids );
		$response = new WP_REST_Response( array( 'items' => $items, 'page' => $page, 'per_page' => $per_page, 'total' => $total, 'total_pages' => $total_pages ) );
		$response->header( 'Cache-Control', '' !== $search ? 'public, max-age=60' : 'public, max-age=120' );
		return $response;
	}

	public function random_telangor( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! Dhikra_Helpers::rate_limit( 'random', 120, MINUTE_IN_SECONDS, get_current_user_id() ) ) {
			return new WP_Error( 'dhikra_rate_limit', __( 'درخواست‌ها بیش از حد سریع است.', 'dhikra' ), array( 'status' => 429 ) );
		}
		$exclude = array_filter( array_map( 'trim', explode( ',', (string) $request['exclude'] ) ) );
		$post_id = Dhikra_Random::next_for_request( $exclude );
		if ( $post_id <= 0 ) {
			return new WP_Error( 'dhikra_no_content', __( 'تلنگر واجد شرایطی وجود ندارد.', 'dhikra' ), array( 'status' => 404 ) );
		}
		$data         = Dhikra_Random::telangor_data( $post_id, sanitize_key( (string) $request['platform'] ) );
		$data['html'] = Dhikra_Random::render_card( $post_id );
		$response     = new WP_REST_Response( $data );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		return $response;
	}

	public function single_telangor( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post_id = Dhikra_Data::find_post_by_code( (string) $request['code'], true );
		if ( $post_id <= 0 ) {
			return new WP_Error( 'dhikra_not_found', __( 'تلنگر پیدا نشد.', 'dhikra' ), array( 'status' => 404 ) );
		}
		if ( is_user_logged_in() ) {
			Dhikra_Data::record_history( get_current_user_id(), $post_id );
		}
		$data = Dhikra_Random::telangor_data( $post_id, sanitize_key( (string) $request['platform'] ) );
		$data['html'] = Dhikra_Random::render_card( $post_id );
		$data['is_favorite'] = is_user_logged_in() && Dhikra_Data::is_favorite( get_current_user_id(), $post_id );
		$response = new WP_REST_Response( $data );
		$response->header( 'Cache-Control', is_user_logged_in() ? 'private, no-store, max-age=0' : 'public, max-age=120' );
		return $response;
	}

	public function topics(): WP_REST_Response {
		$terms = get_terms( array( 'taxonomy' => 'dhikra_topic', 'hide_empty' => false ) );
		$items = array();
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( ! Dhikra_Themes::is_topic_active( $term->term_id ) ) {
					continue;
				}
				$items[] = array( 'id' => $term->term_id, 'name' => $term->name, 'slug' => $term->slug );
			}
		}
		return new WP_REST_Response( array( 'items' => $items ) );
	}

	/** Build the compact payload used by the saved collection UI. */
	private function favorite_item_payload( int $post_id, string $saved_at, string $platform ): array {
		$item = Dhikra_Random::telangor_data( $post_id, $platform, false, false );
		if ( empty( $item ) ) {
			return array();
		}
		$preview = trim( (string) ( $item['translation'] ?: ( $item['reflection'] ?: $item['original'] ) ) );
		$timestamp = '' !== $saved_at ? strtotime( $saved_at . ' UTC' ) : false;
		return array(
			'id'          => $post_id,
			'code'        => (string) $item['code'],
			'title'       => (string) $item['title'],
			'topic'       => (string) $item['main_topic_name'],
			'type_label'  => (string) $item['type_label'],
			'excerpt'     => wp_trim_words( wp_strip_all_tags( $preview ), 24, '…' ),
			'source'      => (string) $item['source'],
			'url'         => (string) $item['url'],
			'saved_at'    => $saved_at,
			'saved_ts'    => false !== $timestamp ? (int) $timestamp : 0,
			'saved_label' => false !== $timestamp ? get_date_from_gmt( $saved_at, get_option( 'date_format' ) ) : '',
		);
	}

	public function favorites(): WP_REST_Response {
		$user_id = get_current_user_id();
		$rows    = Dhikra_Data::favorite_rows( $user_id, 1, 100 );
		$items   = array();
		foreach ( $rows as $row ) {
			$item = $this->favorite_item_payload( (int) $row['post_id'], (string) $row['created_at'], 'general' );
			if ( ! empty( $item ) ) {
				$items[] = $item;
			}
		}
		$response = new WP_REST_Response( array( 'items' => $items, 'total' => Dhikra_Data::favorite_count( $user_id ) ) );
		$response->header( 'Cache-Control', 'private, no-store, max-age=0' );
		return $response;
	}

	public function toggle_favorite( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post_id = Dhikra_Data::find_post_by_code( (string) $request['code'], true );
		if ( $post_id <= 0 ) {
			return new WP_Error( 'dhikra_not_found', __( 'تلنگر پیدا نشد.', 'dhikra' ), array( 'status' => 404 ) );
		}
		$favorite = rest_sanitize_boolean( $request['favorite'] );
		if ( ! Dhikra_Data::set_favorite( get_current_user_id(), $post_id, $favorite ) ) {
			return new WP_Error( 'dhikra_favorite_failed', __( 'تغییر علاقه‌مندی ذخیره نشد.', 'dhikra' ), array( 'status' => 500 ) );
		}
		return new WP_REST_Response( array( 'favorite' => $favorite, 'code' => (string) $request['code'] ) );
	}

	public function preferences(): WP_REST_Response {
		$user_id = get_current_user_id();
		$active  = Dhikra_Data::active_topic_ids();
		$excluded = Dhikra_Data::excluded_topic_ids( $user_id );
		return new WP_REST_Response( array( 'selected_topic_ids' => array_values( array_diff( $active, $excluded ) ), 'excluded_topic_ids' => $excluded ) );
	}

	public function save_preferences( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$active   = Dhikra_Data::active_topic_ids();
		$selected = array_values( array_intersect( $active, array_map( 'absint', (array) $request['selected_topic_ids'] ) ) );
		if ( empty( $selected ) ) {
			return new WP_Error( 'dhikra_topic_required', __( 'دست‌کم یک موضوع را انتخاب کنید.', 'dhikra' ), array( 'status' => 400 ) );
		}
		Dhikra_Data::save_excluded_topic_ids( get_current_user_id(), array_diff( $active, $selected ) );
		return new WP_REST_Response( array( 'selected_topic_ids' => $selected ) );
	}

	public function history( WP_REST_Request $request ): WP_REST_Response {
		$page    = max( 1, absint( $request['page'] ) );
		$user_id = get_current_user_id();
		$ids     = Dhikra_Data::history_ids( $user_id, $page, 10 );
		$items   = array();
		foreach ( $ids as $post_id ) {
			$items[] = array( 'title' => get_the_title( $post_id ), 'url' => Dhikra_Helpers::telangor_url( $post_id ), 'code' => Dhikra_Data::get_short_code( $post_id ) );
		}
		return new WP_REST_Response( array( 'items' => $items, 'page' => $page, 'per_page' => 10, 'total' => Dhikra_Data::history_count( $user_id ) ) );
	}


	public function eitaa_session( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! Dhikra_Helpers::rate_limit( 'eitaa_session', 30, MINUTE_IN_SECONDS ) ) {
			return new WP_Error( 'dhikra_rate_limit', __( 'درخواست‌ها بیش از حد سریع است.', 'dhikra' ), array( 'status' => 429 ) );
		}
		$auth = $this->eitaa_user( $request );
		if ( is_wp_error( $auth ) ) { return $auth; }
		$identity = $auth['identity'];
		$user_id = $auth['user_id'];
		if ( absint( $request['card_post_id'] ) > 0 ) { Dhikra_Data::record_history( $user_id, absint( $request['card_post_id'] ) ); }
		$audience = Dhikra_Audience::upsert( $identity );
		if ( is_wp_error( $audience ) ) { return $audience; }
		Dhikra_Analytics::record_visit( absint( $audience['id'] ), $identity, (string) $request['visit_token'], absint( $request['card_post_id'] ) );
		// Daily delivery is Dhikra's default state. A new/legacy undecided
		// audience is subscribed immediately, even before native Eitaa write
		// permission is available; the dispatcher still requires that permission
		// before it can send. Only an explicit in-app opt-out (daily_enabled=0
		// with daily_prompt_decided_at set) is preserved across launches.
		if ( empty( $audience['daily_enabled'] ) && empty( $audience['daily_prompt_decided_at'] ) ) {
			if ( Dhikra_Audience::ensure_daily_default( absint( $audience['id'] ) ) ) {
				$audience['daily_enabled'] = 1;
			}
		}
		$reward = Dhikra_Rewards::browse_card( absint( $audience['id'] ), (string) $audience['eitaa_user_id'], $user_id, absint( $request['card_post_id'] ), ! empty( $audience['allows_write_to_pm'] ), true );
		if ( is_wp_error( $reward ) ) { return $reward; }
		return new WP_REST_Response( array(
			'authenticated' => true,
			'eitaa_authenticated' => true,
			'is_favorite' => absint( $request['card_post_id'] ) > 0 && Dhikra_Data::is_favorite( $user_id, absint( $request['card_post_id'] ) ),
			'audience_id' => absint( $audience['id'] ),
			'daily_enabled' => ! empty( $audience['daily_enabled'] ),
			'daily_prompt_decided' => ! empty( $audience['daily_prompt_decided_at'] ),
			'daily_opted_out' => empty( $audience['daily_enabled'] ) && ! empty( $audience['daily_prompt_decided_at'] ),
			'daily_send_time' => Dhikra_Audience::daily_send_time(),
			'allows_write_to_pm' => ! empty( $audience['allows_write_to_pm'] ),
			'reward' => $reward,
		) );
	}


	private function eitaa_user( WP_REST_Request $request ): array|WP_Error {
		return Dhikra_Accounts::resolve_eitaa_user( (string) $request['init_data'] );
	}

	public function eitaa_random( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! Dhikra_Helpers::rate_limit( 'eitaa_random', 120, MINUTE_IN_SECONDS ) ) {
			return new WP_Error( 'dhikra_rate_limit', __( 'درخواست‌ها بیش از حد سریع است.', 'dhikra' ), array( 'status' => 429 ) );
		}
		$auth = $this->eitaa_user( $request );
		if ( is_wp_error( $auth ) ) { return $auth; }
		$exclude = array_filter( array_map( 'trim', explode( ',', (string) $request['exclude'] ) ) );
		$audience = Dhikra_Audience::upsert( $auth['identity'] );
		if ( is_wp_error( $audience ) ) { return $audience; }
		$campaign = Dhikra_Data::active_reward_campaign();
		if ( ! empty( $campaign ) ) {
			$seen_ids = Dhikra_Rewards::seen_cards( absint( $audience['id'] ) );
			$topic_ids = Dhikra_Rewards::campaign_topic_ids( $campaign );
			// Use server-recorded progress to choose a genuinely new campaign card;
			// browser-provided exclusions alone must never determine prize progress.
			$post_id = Dhikra_Random::random_post_id_for_topics( $topic_ids, $seen_ids );
		} else {
			$post_id = Dhikra_Random::next_for_request( $exclude );
		}
		if ( $post_id <= 0 ) {
			return new WP_Error( 'dhikra_no_content', __( 'تلنگر واجد شرایطی وجود ندارد.', 'dhikra' ), array( 'status' => 404 ) );
		}
		Dhikra_Data::record_history( $auth['user_id'], $post_id );
		$data = Dhikra_Random::telangor_data( $post_id, 'eitaa', false );
		$data['reward'] = Dhikra_Rewards::browse_card( absint( $audience['id'] ), (string) $audience['eitaa_user_id'], $auth['user_id'], $post_id, ! empty( $audience['allows_write_to_pm'] ) );
		if ( is_wp_error( $data['reward'] ) ) { return $data['reward']; }
		$data['is_favorite'] = Dhikra_Data::is_favorite( $auth['user_id'], $post_id );
		$data['html'] = Dhikra_Random::render_card( $post_id );
		$response = new WP_REST_Response( $data );
		$response->header( 'Cache-Control', 'private, no-store, max-age=0' );
		return $response;
	}

	public function eitaa_card( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$auth = $this->eitaa_user( $request );
		if ( is_wp_error( $auth ) ) { return $auth; }
		$post_id = Dhikra_Data::find_post_by_code( (string) $request['code'], true );
		if ( $post_id <= 0 ) {
			return new WP_Error( 'dhikra_not_found', __( 'تلنگر پیدا نشد.', 'dhikra' ), array( 'status' => 404 ) );
		}
		Dhikra_Data::record_history( $auth['user_id'], $post_id );
		$data = Dhikra_Random::telangor_data( $post_id, 'eitaa', false );
		$data['is_favorite'] = Dhikra_Data::is_favorite( $auth['user_id'], $post_id );
		$data['html'] = Dhikra_Random::render_card( $post_id );
		$response = new WP_REST_Response( $data );
		$response->header( 'Cache-Control', 'private, no-store, max-age=0' );
		return $response;
	}

	public function eitaa_favorites( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$auth = $this->eitaa_user( $request );
		if ( is_wp_error( $auth ) ) { return $auth; }
		$rows  = Dhikra_Data::favorite_rows( $auth['user_id'], 1, 100 );
		$items = array();
		foreach ( $rows as $row ) {
			$item = $this->favorite_item_payload( (int) $row['post_id'], (string) $row['created_at'], 'eitaa' );
			if ( ! empty( $item ) ) {
				$items[] = $item;
			}
		}
		$response = new WP_REST_Response( array( 'items' => $items, 'total' => Dhikra_Data::favorite_count( $auth['user_id'] ) ) );
		$response->header( 'Cache-Control', 'private, no-store, max-age=0' );
		return $response;
	}

	public function eitaa_toggle_favorite( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$auth = $this->eitaa_user( $request );
		if ( is_wp_error( $auth ) ) { return $auth; }
		$post_id = Dhikra_Data::find_post_by_code( (string) $request['code'], true );
		if ( $post_id <= 0 ) {
			return new WP_Error( 'dhikra_not_found', __( 'تلنگر پیدا نشد.', 'dhikra' ), array( 'status' => 404 ) );
		}
		$favorite = rest_sanitize_boolean( $request['favorite'] );
		if ( ! Dhikra_Data::set_favorite( $auth['user_id'], $post_id, $favorite ) ) {
			return new WP_Error( 'dhikra_favorite_failed', __( 'تغییر ذخیره‌شده انجام نشد.', 'dhikra' ), array( 'status' => 500 ) );
		}
		return new WP_REST_Response( array( 'favorite' => $favorite, 'code' => (string) $request['code'] ) );
	}


	public function note( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post_id = Dhikra_Data::find_post_by_code( (string) $request['code'], true );
		if ( $post_id <= 0 ) {
			return new WP_Error( 'dhikra_not_found', __( 'تلنگر پیدا نشد.', 'dhikra' ), array( 'status' => 404 ) );
		}
		return new WP_REST_Response( array( 'note' => Dhikra_Data::card_note( get_current_user_id(), $post_id ) ) );
	}

	public function save_note( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post_id = Dhikra_Data::find_post_by_code( (string) $request['code'], true );
		if ( $post_id <= 0 ) {
			return new WP_Error( 'dhikra_not_found', __( 'تلنگر پیدا نشد.', 'dhikra' ), array( 'status' => 404 ) );
		}
		$note = (string) $request['note'];
		if ( ! Dhikra_Data::save_card_note( get_current_user_id(), $post_id, $note ) ) {
			return new WP_Error( 'dhikra_note_failed', __( 'ذخیره یادداشت انجام نشد.', 'dhikra' ), array( 'status' => 500 ) );
		}
		return new WP_REST_Response( array( 'note' => Dhikra_Data::card_note( get_current_user_id(), $post_id ), 'saved' => true ) );
	}

	public function eitaa_note( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$auth = $this->eitaa_user( $request );
		if ( is_wp_error( $auth ) ) { return $auth; }
		$post_id = Dhikra_Data::find_post_by_code( (string) $request['code'], true );
		if ( $post_id <= 0 ) {
			return new WP_Error( 'dhikra_not_found', __( 'تلنگر پیدا نشد.', 'dhikra' ), array( 'status' => 404 ) );
		}
		if ( 'save' === sanitize_key( (string) $request['action'] ) ) {
			if ( ! Dhikra_Data::save_card_note( $auth['user_id'], $post_id, (string) $request['note'] ) ) {
				return new WP_Error( 'dhikra_note_failed', __( 'ذخیره یادداشت انجام نشد.', 'dhikra' ), array( 'status' => 500 ) );
			}
		}
		return new WP_REST_Response( array( 'note' => Dhikra_Data::card_note( $auth['user_id'], $post_id ), 'saved' => true ) );
	}

	public function eitaa_reminder( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! Dhikra_Helpers::rate_limit( 'eitaa_reminder', 20, MINUTE_IN_SECONDS ) ) {
			return new WP_Error( 'dhikra_rate_limit', __( 'درخواست‌ها بیش از حد سریع است.', 'dhikra' ), array( 'status' => 429 ) );
		}
		$auth = $this->eitaa_user( $request );
		if ( is_wp_error( $auth ) ) { return $auth; }
		$post_id = Dhikra_Data::find_post_by_code( (string) $request['code'], true );
		if ( $post_id <= 0 ) {
			return new WP_Error( 'dhikra_not_found', __( 'تلنگر پیدا نشد.', 'dhikra' ), array( 'status' => 404 ) );
		}
		$audience = Dhikra_Audience::upsert( $auth['identity'] );
		if ( is_wp_error( $audience ) ) { return $audience; }
		$result = Dhikra_Audience::schedule_personal_reminder( $auth['user_id'], absint( $audience['id'] ), $post_id, (string) $request['preset'] );
		if ( is_wp_error( $result ) ) { return $result; }
		return new WP_REST_Response( array( 'scheduled' => true, 'label' => (string) $result['label'], 'local_iso' => (string) $result['local_iso'] ) );
	}

	private function eitaa_topics(): array {
		$terms = get_terms( array( 'taxonomy' => 'dhikra_topic', 'hide_empty' => false, 'include' => Dhikra_Data::active_topic_ids() ) );
		$items = array();
		if ( ! is_wp_error( $terms ) ) { foreach ( $terms as $term ) { $items[] = array( 'id' => absint( $term->term_id ), 'name' => (string) $term->name ); } }
		return $items;
	}

	public function eitaa_account( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$auth = $this->eitaa_user( $request ); if ( is_wp_error( $auth ) ) { return $auth; }
		$user = get_user_by( 'id', $auth['user_id'] );
		$active = Dhikra_Data::active_topic_ids();
		return new WP_REST_Response( array(
			'display_name' => $user instanceof WP_User ? $user->display_name : '',
			'favorite_count' => Dhikra_Data::favorite_count( $auth['user_id'] ),
			'history_count' => Dhikra_Data::history_count( $auth['user_id'] ),
			'selected_topic_ids' => array_values( array_diff( $active, Dhikra_Data::excluded_topic_ids( $auth['user_id'] ) ) ),
			'active_topics' => $this->eitaa_topics(), 'external_only' => true,
		) );
	}

	public function eitaa_preferences( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$auth = $this->eitaa_user( $request ); if ( is_wp_error( $auth ) ) { return $auth; }
		$active = Dhikra_Data::active_topic_ids();
		$selected = array_values( array_unique( array_intersect( $active, array_map( 'absint', (array) $request['selected_topic_ids'] ) ) ) );
		if ( empty( $selected ) ) { return new WP_Error( 'dhikra_topic_required', __( 'دست‌کم یک موضوع را انتخاب کنید.', 'dhikra' ), array( 'status' => 400 ) ); }
		Dhikra_Data::save_excluded_topic_ids( $auth['user_id'], array_diff( $active, $selected ) );
		return new WP_REST_Response( array( 'selected_topic_ids' => $selected ) );
	}

	public function eitaa_history( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$auth = $this->eitaa_user( $request ); if ( is_wp_error( $auth ) ) { return $auth; }
		$page = max( 1, absint( $request['page'] ) ); $ids = Dhikra_Data::history_ids( $auth['user_id'], $page, 10 ); $items = array();
		foreach ( $ids as $post_id ) { $items[] = array( 'title' => get_the_title( $post_id ), 'url' => Dhikra_Helpers::telangor_url( $post_id ), 'code' => Dhikra_Data::get_short_code( $post_id ) ); }
		return new WP_REST_Response( array( 'items' => $items, 'page' => $page, 'per_page' => 10, 'total' => Dhikra_Data::history_count( $auth['user_id'] ) ) );
	}

	public function eitaa_print( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$auth = $this->eitaa_user( $request ); if ( is_wp_error( $auth ) ) { return $auth; }
		$result = Dhikra_Print::create_preview( $auth['user_id'], (array) $request['topic_ids'], absint( $request['count'] ) );
		return is_wp_error( $result ) ? $result : new WP_REST_Response( $result );
	}

	public function eitaa_print_preview( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$auth = $this->eitaa_user( $request ); if ( is_wp_error( $auth ) ) { return $auth; }
		$payload = Dhikra_Print::preview_for_user( $auth['user_id'], sanitize_key( (string) $request['token'] ) );
		if ( is_wp_error( $payload ) ) { return $payload; }
		$html = ''; foreach ( array_chunk( $payload['post_ids'], 4 ) as $page_ids ) { $html .= '<section class="dhikra-print-page">'; foreach ( $page_ids as $post_id ) { $html .= '<div class="dhikra-print-cell">' . Dhikra_Print::render_print_card( absint( $post_id ) ) . '</div>'; } $html .= '</section>'; }
		return new WP_REST_Response( array( 'count' => count( $payload['post_ids'] ), 'unique' => $payload['unique'], 'repeats' => $payload['repeats'], 'cards_html' => $html ) );
	}


	public function eitaa_write_access( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! Dhikra_Helpers::rate_limit( 'eitaa_write_access', 15, MINUTE_IN_SECONDS ) ) {
			return new WP_Error( 'dhikra_rate_limit', __( 'درخواست‌ها بیش از حد سریع است.', 'dhikra' ), array( 'status' => 429 ) );
		}
		$auth = $this->eitaa_user( $request );
		if ( is_wp_error( $auth ) ) { return $auth; }
		$identity = $auth['identity'];
		$audience = Dhikra_Audience::upsert( $identity );
		if ( is_wp_error( $audience ) ) { return $audience; }
		$granted = rest_sanitize_boolean( $request['granted'] );
		if ( ! Dhikra_Audience::set_write_access( absint( $audience['id'] ), $granted ) ) {
			return new WP_Error( 'dhikra_write_access_save_failed', __( 'ذخیره وضعیت مجوز پیام ایتا انجام نشد.', 'dhikra' ), array( 'status' => 500 ) );
		}
		// Native message permission and the daily preference are related but
		// not identical. Daily is on by default; denying/revoking Eitaa write
		// access must not silently become an in-app opt-out. Likewise, granting
		// permission for another feature must not override a user's explicit
		// manual daily opt-out.
		$explicit_opt_out = empty( $audience['daily_enabled'] ) && ! empty( $audience['daily_prompt_decided_at'] );
		if ( $granted && ! $explicit_opt_out && empty( $audience['daily_enabled'] ) ) {
			if ( ! Dhikra_Audience::ensure_daily_default( absint( $audience['id'] ) ) ) {
				return new WP_Error( 'dhikra_daily_save_failed', __( 'ذخیره وضعیت تلنگر روزانه انجام نشد.', 'dhikra' ), array( 'status' => 500 ) );
			}
			$audience['daily_enabled'] = 1;
		}
		$deliveries = $granted ? Dhikra_Rewards::deliver_pending_winner_messages( absint( $audience['id'] ), (string) $audience['eitaa_user_id'] ) : array();
		return new WP_REST_Response( array(
			'allows_write_to_pm' => $granted,
			'daily_enabled' => ! empty( $audience['daily_enabled'] ),
			'daily_opted_out' => $explicit_opt_out,
			'deliveries' => $deliveries,
		) );
	}

	public function eitaa_daily( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! Dhikra_Helpers::rate_limit( 'eitaa_daily', 20, MINUTE_IN_SECONDS ) ) {
			return new WP_Error( 'dhikra_rate_limit', __( 'درخواست‌ها بیش از حد سریع است.', 'dhikra' ), array( 'status' => 429 ) );
		}
		$auth = $this->eitaa_user( $request );
		if ( is_wp_error( $auth ) ) { return $auth; }
		$identity = $auth['identity'];
		$audience = Dhikra_Audience::upsert( $identity );
		if ( is_wp_error( $audience ) ) { return $audience; }
		$enabled = rest_sanitize_boolean( $request['enabled'] );
		$write_access_granted = $enabled && rest_sanitize_boolean( $request['write_access_granted'] ?? false );
		$has_permission = ! empty( $audience['allows_write_to_pm'] ) || ! empty( $identity['allows_write_to_pm'] ) || $write_access_granted;
		if ( $enabled && ! $has_permission ) {
			return new WP_Error( 'dhikra_daily_permission_required', __( 'ابتدا اجازه دریافت پیام ایتا را تأیید کنید.', 'dhikra' ), array( 'status' => 403 ) );
		}
		if ( $write_access_granted ) {
			// A native client callback is user consent, not server-verified grant.
			// Real delivery still depends on the Eitaa API, which may reject it.
			if ( ! Dhikra_Audience::set_write_access( absint( $audience['id'] ), true ) ) {
				return new WP_Error( 'dhikra_daily_write_access_failed', __( 'ثبت درخواست مجوز انجام نشد.', 'dhikra' ), array( 'status' => 500 ) );
			}
			Dhikra_Rewards::deliver_pending_winner_messages( absint( $audience['id'] ), (string) $audience['eitaa_user_id'] );
		}
		if ( ! Dhikra_Audience::set_daily( absint( $audience['id'] ), $enabled ) ) {
			return new WP_Error( 'dhikra_daily_save_failed', __( 'ذخیره وضعیت تلنگر روزانه انجام نشد.', 'dhikra' ), array( 'status' => 500 ) );
		}
		return new WP_REST_Response( array(
			'enabled'              => $enabled,
			'allows_write_to_pm'   => $has_permission,
			'daily_send_time'      => Dhikra_Audience::daily_send_time(),
			'daily_prompt_decided' => true,
		) );
	}

	public function eitaa_daily_dismiss( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		// Backward-compatible no-op for cached pre-1.6.26 clients. Rejecting the
		// native Eitaa permission dialog is no longer interpreted as opting out
		// of Dhikra's default-on daily subscription. Only /eitaa/daily enabled=0
		// records a manual opt-out.
		if ( ! Dhikra_Helpers::rate_limit( 'eitaa_daily_dismiss', 15, MINUTE_IN_SECONDS ) ) {
			return new WP_Error( 'dhikra_rate_limit', __( 'درخواست‌ها بیش از حد سریع است.', 'dhikra' ), array( 'status' => 429 ) );
		}
		$auth = $this->eitaa_user( $request );
		if ( is_wp_error( $auth ) ) { return $auth; }
		$audience = Dhikra_Audience::upsert( $auth['identity'] );
		if ( is_wp_error( $audience ) ) { return $audience; }
		if ( empty( $audience['daily_enabled'] ) && empty( $audience['daily_prompt_decided_at'] ) ) {
			Dhikra_Audience::ensure_daily_default( absint( $audience['id'] ) );
		}
		return new WP_REST_Response( array(
			'daily_enabled' => ! ( empty( $audience['daily_enabled'] ) && ! empty( $audience['daily_prompt_decided_at'] ) ),
			'daily_prompt_decided' => ! empty( $audience['daily_prompt_decided_at'] ),
		) );
	}

	public function link_identity( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$provider = sanitize_key( (string) $request['provider'] );
		$verified = apply_filters( 'dhikra_verify_external_identity', false, $provider, $request['verification_data'], get_current_user_id() );
		if ( ! is_array( $verified ) || empty( $verified['external_user_id'] ) ) {
			return new WP_Error( 'dhikra_identity_unverified', __( 'افزونهٔ جانبی نتوانست هویت بیرونی را تأیید کند.', 'dhikra' ), array( 'status' => 403 ) );
		}
		$result = Dhikra_Accounts::link_external_identity( get_current_user_id(), $provider, (string) $verified['external_user_id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( array( 'linked' => true, 'provider' => $provider ) );
	}
}
