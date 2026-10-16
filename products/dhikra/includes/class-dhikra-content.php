<?php
/**
 * Telangor content model and administration.
 *
 * @package Dhikra
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Dhikra_Content {
	public const TYPES = array( 'quran', 'hadith', 'prayer', 'wisdom', 'story' );

	public function register(): void {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_taxonomy' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_dhikra_telangor', array( $this, 'save_meta' ), 10, 2 );
		add_filter( 'wp_insert_post_data', array( $this, 'prevent_invalid_publish' ), 10, 2 );
		add_filter( 'manage_dhikra_telangor_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_dhikra_telangor_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'before_delete_post', array( $this, 'before_delete' ) );
		add_action( 'dhikra_topic_add_form_fields', array( $this, 'topic_add_fields' ) );
		add_action( 'dhikra_topic_edit_form_fields', array( $this, 'topic_edit_fields' ) );
		add_action( 'created_dhikra_topic', array( $this, 'save_topic_fields' ) );
		add_action( 'edited_dhikra_topic', array( $this, 'save_topic_fields' ) );
	}

	public function register_post_type(): void {
		$labels = array(
			'name'               => __( 'تلنگرها', 'dhikra' ),
			'singular_name'      => __( 'تلنگر', 'dhikra' ),
			'add_new'            => __( 'افزودن تلنگر', 'dhikra' ),
			'add_new_item'       => __( 'افزودن تلنگر تازه', 'dhikra' ),
			'edit_item'          => __( 'ویرایش تلنگر', 'dhikra' ),
			'new_item'           => __( 'تلنگر تازه', 'dhikra' ),
			'view_item'          => __( 'مشاهدهٔ تلنگر', 'dhikra' ),
			'search_items'       => __( 'جست‌وجوی تلنگرها', 'dhikra' ),
			'not_found'          => __( 'تلنگری پیدا نشد.', 'dhikra' ),
			'not_found_in_trash' => __( 'تلنگری در زباله‌دان نیست.', 'dhikra' ),
			'menu_name'          => Dhikra_Helpers::PRODUCT_NAME,
		);
		register_post_type(
			'dhikra_telangor',
			array(
				'labels'             => $labels,
				'public'             => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_rest'       => false,
				'menu_icon'          => 'dashicons-format-quote',
				'supports'           => array( 'title', 'author', 'revisions' ),
				'capability_type'    => 'post',
				'map_meta_cap'       => true,
				'has_archive'        => false,
				'rewrite'            => false,
				'publicly_queryable' => false,
			)
		);
	}

	public function register_taxonomy(): void {
		register_taxonomy(
			'dhikra_topic',
			array( 'dhikra_telangor' ),
			array(
				'labels' => array(
					'name'          => __( 'موضوعات', 'dhikra' ),
					'singular_name' => __( 'موضوع', 'dhikra' ),
					'search_items'  => __( 'جست‌وجوی موضوعات', 'dhikra' ),
					'all_items'     => __( 'همهٔ موضوعات', 'dhikra' ),
					'edit_item'     => __( 'ویرایش موضوع', 'dhikra' ),
					'update_item'   => __( 'به‌روزرسانی موضوع', 'dhikra' ),
					'add_new_item'  => __( 'افزودن موضوع', 'dhikra' ),
					'new_item_name' => __( 'نام موضوع تازه', 'dhikra' ),
					'menu_name'     => __( 'موضوعات', 'dhikra' ),
				),
				'public'            => false,
				'show_ui'           => true,
				'show_admin_column' => false,
				'show_in_rest'      => false,
				'hierarchical'      => true,
				'rewrite'           => false,
			)
		);
	}

	public static function type_labels(): array {
		return array(
			'quran'  => __( 'آیه', 'dhikra' ),
			'hadith' => __( 'حدیث', 'dhikra' ),
			'prayer' => __( 'دعا/مناجات', 'dhikra' ),
			'wisdom' => __( 'سخن حکیمانه', 'dhikra' ),
			'story'  => __( 'داستان کوتاه', 'dhikra' ),
		);
	}

	public static function length_limits(): array {
		return array(
			'title'       => 110,
			'original'    => 760,
			'translation' => 620,
			'reflection'  => 260,
			'speaker'     => 140,
			'source'      => 220,
			'story'       => 900,
		);
	}

	public static function validate_fields( array $fields ): array {
		$type        = in_array( $fields['type'] ?? '', self::TYPES, true ) ? $fields['type'] : 'quran';
		$title       = trim( (string) ( $fields['title'] ?? '' ) );
		$original    = trim( (string) ( $fields['original'] ?? '' ) );
		$translation = trim( (string) ( $fields['translation'] ?? '' ) );
		$reflection  = trim( (string) ( $fields['reflection'] ?? '' ) );
		$speaker     = trim( (string) ( $fields['speaker'] ?? '' ) );
		$source      = trim( (string) ( $fields['source'] ?? '' ) );
		$main_topic  = absint( $fields['main_topic_id'] ?? 0 );
		$limits      = self::length_limits();
		$errors      = array();

		if ( '' === $title ) {
			$errors[] = __( 'عنوان الزامی است.', 'dhikra' );
		}
		if ( '' === $original ) {
			$errors[] = 'story' === $type ? __( 'متن داستان الزامی است.', 'dhikra' ) : __( 'متن اصلی الزامی است.', 'dhikra' );
		}
		if ( $main_topic <= 0 || ! term_exists( $main_topic, 'dhikra_topic' ) ) {
			$errors[] = __( 'موضوع اصلی معتبر الزامی است.', 'dhikra' );
		}

		if ( 'quran' === $type && ( '' === $translation || '' === $source ) ) {
			$errors[] = __( 'برای آیه، ترجمه و منبع دقیق الزامی است.', 'dhikra' );
		}
		if ( in_array( $type, array( 'hadith', 'prayer' ), true ) && ( '' === $translation || '' === $speaker || '' === $source ) ) {
			$errors[] = __( 'برای حدیث و دعا/مناجات، ترجمه، گوینده و منبع الزامی است.', 'dhikra' );
		}
		if ( 'wisdom' === $type && '' === $speaker && '' === $source ) {
			$errors[] = __( 'برای سخن حکیمانه، ثبت دست‌کم گوینده یا منبع الزامی است.', 'dhikra' );
		}
		if ( 'story' === $type && '' === $source ) {
			$errors[] = __( 'منبع داستان کوتاه الزامی است.', 'dhikra' );
		}

		$checks = array(
			'title'       => array( $title, $limits['title'], __( 'عنوان', 'dhikra' ) ),
			'original'    => array( $original, 'story' === $type ? $limits['story'] : $limits['original'], 'story' === $type ? __( 'متن داستان', 'dhikra' ) : __( 'متن اصلی', 'dhikra' ) ),
			'translation' => array( $translation, $limits['translation'], __( 'ترجمه', 'dhikra' ) ),
			'reflection'  => array( $reflection, $limits['reflection'], __( 'جملهٔ تأمل', 'dhikra' ) ),
			'speaker'     => array( $speaker, $limits['speaker'], __( 'گوینده', 'dhikra' ) ),
			'source'      => array( $source, $limits['source'], __( 'منبع', 'dhikra' ) ),
		);
		foreach ( $checks as $check ) {
			if ( Dhikra_Helpers::unicode_length( (string) $check[0] ) > (int) $check[1] ) {
				$errors[] = sprintf( __( '%1$s از سقف %2$d نویسه بلندتر است و در کارت بدون اسکرول جا نمی‌شود.', 'dhikra' ), $check[2], $check[1] );
			}
		}
		return array_values( array_unique( $errors ) );
	}

	public function prevent_invalid_publish( array $data, array $postarr ): array {
		if ( 'dhikra_telangor' !== ( $data['post_type'] ?? '' ) || ! isset( $_POST['dhikra_telangor_nonce'] ) ) {
			return $data;
		}
		if ( ! in_array( $data['post_status'] ?? '', array( 'publish', 'future' ), true ) ) {
			return $data;
		}
		$fields = $this->posted_fields( (string) ( $data['post_title'] ?? '' ) );
		$errors = self::validate_fields( $fields );
		if ( ! empty( $errors ) ) {
			$data['post_status'] = 'draft';
			$user_id             = get_current_user_id();
			set_transient( 'dhikra_validation_' . $user_id, $errors, MINUTE_IN_SECONDS );
		}
		return $data;
	}

	public function add_meta_boxes(): void {
		add_meta_box( 'dhikra_content_fields', __( 'محتوای تلنگر', 'dhikra' ), array( $this, 'render_meta_box' ), 'dhikra_telangor', 'normal', 'high' );
	}

	public function render_meta_box( WP_Post $post ): void {
		wp_nonce_field( 'dhikra_save_telangor', 'dhikra_telangor_nonce' );
		$type        = sanitize_key( (string) get_post_meta( $post->ID, '_dhikra_type', true ) );
		$type        = in_array( $type, self::TYPES, true ) ? $type : 'quran';
		$main_topic  = absint( get_post_meta( $post->ID, '_dhikra_main_topic_id', true ) );
		$short_code  = Dhikra_Data::get_short_code( $post->ID );
		$terms       = get_terms( array( 'taxonomy' => 'dhikra_topic', 'hide_empty' => false ) );
		$content_id  = (string) get_post_meta( $post->ID, '_dhikra_content_id', true );
		$group       = (string) get_post_meta( $post->ID, '_dhikra_translation_group', true );
		$language    = (string) get_post_meta( $post->ID, '_dhikra_language', true );
		?>
		<div class="dhikra-admin-grid" data-dhikra-content-editor>
			<p><label for="dhikra_type"><strong><?php esc_html_e( 'نوع محتوا', 'dhikra' ); ?></strong></label><select id="dhikra_type" name="dhikra_type"><?php foreach ( self::type_labels() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $type, $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></p>
			<p><label for="dhikra_main_topic"><strong><?php esc_html_e( 'موضوع اصلی', 'dhikra' ); ?></strong></label><select id="dhikra_main_topic" name="dhikra_main_topic"><option value="0"><?php esc_html_e( 'انتخاب کنید', 'dhikra' ); ?></option><?php if ( ! is_wp_error( $terms ) ) : foreach ( $terms as $term ) : ?><option value="<?php echo esc_attr( (string) $term->term_id ); ?>" <?php selected( $main_topic, $term->term_id ); ?>><?php echo esc_html( $term->name ); ?></option><?php endforeach; endif; ?></select><span class="description"><?php esc_html_e( 'موضوع اصلی برای مرتب‌سازی و انتخاب تلنگر است؛ ظاهر همهٔ کارت‌ها یکسان است.', 'dhikra' ); ?></span></p>
			<p class="dhikra-field-wide"><label for="dhikra_original_text"><strong data-dhikra-original-label><?php esc_html_e( 'متن اصلی', 'dhikra' ); ?></strong></label><textarea id="dhikra_original_text" name="dhikra_original_text" rows="7" dir="auto"><?php echo esc_textarea( (string) get_post_meta( $post->ID, '_dhikra_original_text', true ) ); ?></textarea></p>
			<p class="dhikra-field-wide" data-dhikra-field="translation"><label for="dhikra_translation"><strong><?php esc_html_e( 'ترجمه', 'dhikra' ); ?></strong></label><textarea id="dhikra_translation" name="dhikra_translation" rows="4" dir="auto"><?php echo esc_textarea( (string) get_post_meta( $post->ID, '_dhikra_translation', true ) ); ?></textarea></p>
			<p class="dhikra-field-wide" data-dhikra-field="reflection"><label for="dhikra_reflection"><strong><?php esc_html_e( 'جملهٔ تأمل (اختیاری)', 'dhikra' ); ?></strong></label><textarea id="dhikra_reflection" name="dhikra_reflection" rows="3" dir="auto"><?php echo esc_textarea( (string) get_post_meta( $post->ID, '_dhikra_reflection', true ) ); ?></textarea></p>
			<p data-dhikra-field="speaker"><label for="dhikra_speaker"><strong><?php esc_html_e( 'گوینده', 'dhikra' ); ?></strong></label><input id="dhikra_speaker" name="dhikra_speaker" type="text" value="<?php echo esc_attr( (string) get_post_meta( $post->ID, '_dhikra_speaker', true ) ); ?>"></p>
			<p><label for="dhikra_source"><strong><?php esc_html_e( 'منبع', 'dhikra' ); ?></strong></label><input id="dhikra_source" name="dhikra_source" type="text" value="<?php echo esc_attr( (string) get_post_meta( $post->ID, '_dhikra_source', true ) ); ?>"></p>
			<p><label for="dhikra_content_id"><strong><?php esc_html_e( 'شناسهٔ پایدار محتوا', 'dhikra' ); ?></strong></label><input id="dhikra_content_id" name="dhikra_content_id" type="text" value="<?php echo esc_attr( $content_id ); ?>" placeholder="quran-51-55-fa"></p>
			<p><label for="dhikra_translation_group"><strong><?php esc_html_e( 'گروه ترجمه', 'dhikra' ); ?></strong></label><input id="dhikra_translation_group" name="dhikra_translation_group" type="text" value="<?php echo esc_attr( $group ); ?>"></p>
			<p><label for="dhikra_language"><strong><?php esc_html_e( 'زبان', 'dhikra' ); ?></strong></label><input id="dhikra_language" name="dhikra_language" type="text" value="<?php echo esc_attr( '' !== $language ? $language : 'fa_IR' ); ?>"></p>
			<p><label for="dhikra_short_code"><strong><?php esc_html_e( 'کد کوتاه پنج‌نویسه‌ای', 'dhikra' ); ?></strong></label><input id="dhikra_short_code" name="dhikra_short_code" type="text" maxlength="5" dir="ltr" value="<?php echo esc_attr( $short_code ); ?>" <?php disabled( '' !== $short_code ); ?>><span class="description"><?php echo '' !== $short_code ? esc_html__( 'این کد ثابت است و تغییر نمی‌کند.', 'dhikra' ) : esc_html__( 'اختیاری؛ در صورت خالی‌بودن خودکار ساخته می‌شود.', 'dhikra' ); ?></span></p>
		</div>
		<p class="description"><?php esc_html_e( 'انتشار متن بیش‌ازحد بلند برای حفظ کارت بدون اسکرول متوقف می‌شود. پیش‌نمایش نهایی را پیش از انتشار بررسی کنید.', 'dhikra' ); ?></p>
		<?php
	}

	public function save_meta( int $post_id, WP_Post $post ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! isset( $_POST['dhikra_telangor_nonce'] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST['dhikra_telangor_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'dhikra_save_telangor' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = $this->posted_fields( $post->post_title );
		$errors = self::validate_fields( $fields );
		update_post_meta( $post_id, '_dhikra_validation_errors', $errors );
		foreach ( array( 'type', 'original', 'translation', 'reflection', 'speaker', 'source', 'content_id', 'translation_group', 'language' ) as $field ) {
			update_post_meta( $post_id, '_dhikra_' . ( 'original' === $field ? 'original_text' : $field ), $fields[ $field ] ?? '' );
		}
		update_post_meta( $post_id, '_dhikra_main_topic_id', absint( $fields['main_topic_id'] ) );

		$main_topic = absint( $fields['main_topic_id'] );
		if ( $main_topic > 0 ) {
			$current_terms = wp_get_object_terms( $post_id, 'dhikra_topic', array( 'fields' => 'ids' ) );
			$current_terms = is_wp_error( $current_terms ) ? array() : array_map( 'absint', $current_terms );
			if ( ! in_array( $main_topic, $current_terms, true ) ) {
				$current_terms[] = $main_topic;
				wp_set_object_terms( $post_id, $current_terms, 'dhikra_topic' );
			}
		}

		$requested_code = isset( $_POST['dhikra_short_code'] ) ? sanitize_text_field( wp_unslash( $_POST['dhikra_short_code'] ) ) : '';
		$code_result    = Dhikra_Data::assign_short_code( $post_id, $requested_code );
		if ( is_wp_error( $code_result ) ) {
			$errors[] = $code_result->get_error_message();
			update_post_meta( $post_id, '_dhikra_validation_errors', array_values( array_unique( $errors ) ) );
		}
	}

	private function posted_fields( string $title ): array {
		$type = isset( $_POST['dhikra_type'] ) ? sanitize_key( wp_unslash( $_POST['dhikra_type'] ) ) : 'quran';
		$type = in_array( $type, self::TYPES, true ) ? $type : 'quran';

		$fields = array(
			'title'             => sanitize_text_field( $title ),
			'type'              => $type,
			'original'          => isset( $_POST['dhikra_original_text'] ) ? Dhikra_Helpers::normalize_text( wp_unslash( $_POST['dhikra_original_text'] ) ) : '',
			'translation'       => isset( $_POST['dhikra_translation'] ) ? Dhikra_Helpers::normalize_text( wp_unslash( $_POST['dhikra_translation'] ) ) : '',
			'reflection'        => isset( $_POST['dhikra_reflection'] ) ? Dhikra_Helpers::normalize_text( wp_unslash( $_POST['dhikra_reflection'] ) ) : '',
			'speaker'           => isset( $_POST['dhikra_speaker'] ) ? sanitize_text_field( wp_unslash( $_POST['dhikra_speaker'] ) ) : '',
			'source'            => isset( $_POST['dhikra_source'] ) ? sanitize_text_field( wp_unslash( $_POST['dhikra_source'] ) ) : '',
			'main_topic_id'     => isset( $_POST['dhikra_main_topic'] ) ? absint( wp_unslash( $_POST['dhikra_main_topic'] ) ) : 0,
			'content_id'        => isset( $_POST['dhikra_content_id'] ) ? sanitize_key( wp_unslash( $_POST['dhikra_content_id'] ) ) : '',
			'translation_group' => isset( $_POST['dhikra_translation_group'] ) ? sanitize_key( wp_unslash( $_POST['dhikra_translation_group'] ) ) : '',
			'language'          => isset( $_POST['dhikra_language'] ) ? sanitize_text_field( wp_unslash( $_POST['dhikra_language'] ) ) : 'fa_IR',
		);

		// Do not retain fields that are not part of the selected content type.
		if ( 'quran' === $type ) {
			$fields['speaker'] = '';
		}
		if ( 'story' === $type ) {
			$fields['translation'] = '';
			$fields['reflection']  = '';
			$fields['speaker']     = '';
		}

		return $fields;
	}

	public function columns( array $columns ): array {
		$columns['dhikra_type']  = __( 'نوع', 'dhikra' );
		$columns['dhikra_code']  = __( 'کد', 'dhikra' );
		$columns['dhikra_main']  = __( 'موضوع اصلی', 'dhikra' );
		return $columns;
	}

	public function column_content( string $column, int $post_id ): void {
		if ( 'dhikra_type' === $column ) {
			$type = sanitize_key( (string) get_post_meta( $post_id, '_dhikra_type', true ) );
			echo esc_html( self::type_labels()[ $type ] ?? self::type_labels()['quran'] );
		} elseif ( 'dhikra_code' === $column ) {
			echo '<code>' . esc_html( Dhikra_Data::get_short_code( $post_id ) ) . '</code>';
		} elseif ( 'dhikra_main' === $column ) {
			$term = get_term( absint( get_post_meta( $post_id, '_dhikra_main_topic_id', true ) ), 'dhikra_topic' );
			echo $term instanceof WP_Term ? esc_html( $term->name ) : '—';
		}
	}

	public function row_actions( array $actions, WP_Post $post ): array {
		if ( 'dhikra_telangor' !== $post->post_type ) { return $actions; }
		if ( 'publish' === $post->post_status ) {
			$url = Dhikra_Helpers::telangor_url( $post->ID );
			$actions['dhikra_view'] = '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'نمایش', 'dhikra' ) . '</a>';
		} elseif ( current_user_can( 'edit_post', $post->ID ) ) {
			$url = wp_nonce_url( Dhikra_Helpers::app_url( 'home', array( 'dhikra_preview' => $post->ID ) ), 'dhikra_preview_' . $post->ID, 'dhikra_preview_nonce' );
			$actions['dhikra_preview'] = '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'پیش‌نمایش', 'dhikra' ) . '</a>';
		}
		return $actions;
	}

	public function admin_notices(): void {
		if ( ! is_admin() ) {
			return;
		}
		$user_id = get_current_user_id();
		$errors  = get_transient( 'dhikra_validation_' . $user_id );
		if ( is_array( $errors ) && ! empty( $errors ) ) {
			delete_transient( 'dhikra_validation_' . $user_id );
			echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'تلنگر به پیش‌نویس بازگردانده شد:', 'dhikra' ) . '</strong></p><ul>';
			foreach ( $errors as $error ) {
				echo '<li>' . esc_html( (string) $error ) . '</li>';
			}
			echo '</ul></div>';
		}
	}

	public function before_delete( int $post_id ): void {
		if ( 'dhikra_telangor' !== get_post_type( $post_id ) ) {
			return;
		}
		Dhikra_Data::tombstone_short_code( $post_id );
		global $wpdb;
		$wpdb->delete( Dhikra_Data::table( 'favorites' ), array( 'post_id' => $post_id ), array( '%d' ) );
		$wpdb->delete( Dhikra_Data::table( 'history' ), array( 'post_id' => $post_id ), array( '%d' ) );
	}

	public function topic_add_fields(): void {
		$this->render_topic_fields( 0 );
	}

	public function topic_edit_fields( WP_Term $term ): void {
		$this->render_topic_fields( $term->term_id, true );
	}

	private function render_topic_fields( int $term_id, bool $table_rows = false ): void {
		$active = $term_id <= 0 || Dhikra_Themes::is_topic_active( $term_id );
		wp_nonce_field( 'dhikra_topic_active', 'dhikra_topic_active_nonce' );
		if ( $table_rows ) : ?>
		<tr class="form-field"><th scope="row"><?php esc_html_e( 'فعال برای انتخاب', 'dhikra' ); ?></th><td><label><input type="checkbox" name="dhikra_topic_active" value="1" <?php checked( $active ); ?>> <?php esc_html_e( 'این موضوع در انتخاب کاربران و تلنگر تصادفی فعال باشد.', 'dhikra' ); ?></label></td></tr>
		<?php else : ?>
		<div class="form-field"><label><input type="checkbox" name="dhikra_topic_active" value="1" <?php checked( $active ); ?>> <?php esc_html_e( 'فعال برای انتخاب کاربران', 'dhikra' ); ?></label></div>
		<?php endif;
	}

	public function save_topic_fields( int $term_id ): void {
		if ( ! isset( $_POST['dhikra_topic_active_nonce'] ) || ! current_user_can( 'manage_categories' ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST['dhikra_topic_active_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'dhikra_topic_active' ) ) {
			return;
		}
		update_term_meta( $term_id, '_dhikra_topic_active', isset( $_POST['dhikra_topic_active'] ) ? '1' : '0' );
	}
}
