<?php
/**
 * Validated JSON package import.
 *
 * @package Dhikra
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Dhikra_Importer {
	private const PAGE = 'dhikra-import';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_post_dhikra_import_json', array( $this, 'handle_import' ) );
	}

	public function admin_menu(): void {
		add_submenu_page(
			'edit.php?post_type=dhikra_telangor',
			__( 'ورود JSON', 'dhikra' ),
			__( 'ورود JSON', 'dhikra' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap dhikra-settings-wrap">
			<h1><?php esc_html_e( 'ورود گروهی تلنگرها از JSON', 'dhikra' ); ?></h1>
			<?php if ( isset( $_GET['dhikra_import_message'] ) ) : ?><div class="notice notice-info"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['dhikra_import_message'] ) ) ); ?></p></div><?php endif; ?>
			<div class="dhikra-settings-card">
				<p><?php esc_html_e( 'فایل باید UTF-8 و مطابق schema نسخهٔ 1.1 باشد. اعتبارسنجی فنی است؛ بازبینی دینی و ویرایشی بر عهدهٔ ناشر محتواست.', 'dhikra' ); ?></p>
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" enctype="multipart/form-data">
					<input type="hidden" name="action" value="dhikra_import_json">
					<?php wp_nonce_field( 'dhikra_import_json', 'dhikra_nonce' ); ?>
					<table class="form-table" role="presentation">
						<tr><th><label for="dhikra_json_file"><?php esc_html_e( 'فایل JSON', 'dhikra' ); ?></label></th><td><input type="file" id="dhikra_json_file" name="dhikra_json_file" accept="application/json,.json" required></td></tr>
						<tr><th><label for="dhikra_import_status"><?php esc_html_e( 'وضعیت موارد تازه', 'dhikra' ); ?></label></th><td><select id="dhikra_import_status" name="status"><option value="draft"><?php esc_html_e( 'پیش‌نویس', 'dhikra' ); ?></option><option value="publish"><?php esc_html_e( 'انتشار پس از اعتبارسنجی', 'dhikra' ); ?></option></select></td></tr>
						<tr><th><label for="dhikra_duplicate_mode"><?php esc_html_e( 'شناسهٔ تکراری', 'dhikra' ); ?></label></th><td><select id="dhikra_duplicate_mode" name="duplicate_mode"><option value="skip"><?php esc_html_e( 'ردکردن', 'dhikra' ); ?></option><option value="update"><?php esc_html_e( 'به‌روزرسانی محتوا', 'dhikra' ); ?></option></select></td></tr>
					</table>
					<?php submit_button( __( 'اعتبارسنجی و ورود', 'dhikra' ) ); ?>
				</form>
			</div>
			<p><a href="<?php echo esc_url( DHIKRA_URL . 'example-package.json' ); ?>" download><?php esc_html_e( 'نمونهٔ بستهٔ JSON', 'dhikra' ); ?></a> · <a href="<?php echo esc_url( DHIKRA_URL . 'json-schema.json' ); ?>" download><?php esc_html_e( 'JSON Schema', 'dhikra' ); ?></a></p>
		</div>
		<?php
	}

	public function handle_import(): never {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی کافی ندارید.', 'dhikra' ) );
		}
		check_admin_referer( 'dhikra_import_json', 'dhikra_nonce' );
		if ( empty( $_FILES['dhikra_json_file']['tmp_name'] ) ) {
			$this->finish( __( 'فایل JSON دریافت نشد.', 'dhikra' ) );
		}
		$file = $_FILES['dhikra_json_file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$size = isset( $file['size'] ) ? absint( $file['size'] ) : 0;
		if ( $size <= 0 || $size > 4 * MB_IN_BYTES ) {
			$this->finish( __( 'حجم فایل باید کمتر از ۴ مگابایت باشد.', 'dhikra' ) );
		}
		$json = file_get_contents( (string) $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $json ) {
			$this->finish( __( 'خواندن فایل انجام نشد.', 'dhikra' ) );
		}
		$package = json_decode( $json, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			$this->finish( sprintf( __( 'JSON نامعتبر است: %s', 'dhikra' ), json_last_error_msg() ) );
		}
		$status         = isset( $_POST['status'] ) ? Dhikra_Helpers::sanitize_status( sanitize_key( wp_unslash( $_POST['status'] ) ) ) : 'draft';
		$duplicate_mode = isset( $_POST['duplicate_mode'] ) && 'update' === sanitize_key( wp_unslash( $_POST['duplicate_mode'] ) ) ? 'update' : 'skip';
		$validated      = self::validate_package( $package, $status );
		if ( ! empty( $validated['errors'] ) ) {
			$this->finish( sprintf( __( 'ورود متوقف شد؛ %d خطای اعتبارسنجی وجود دارد.', 'dhikra' ), count( $validated['errors'] ) ), $validated['errors'] );
		}

		$created = 0;
		$updated = 0;
		$skipped = 0;
		$errors  = array();
		foreach ( $validated['items'] as $index => $item ) {
			$result = $this->import_item( $item, (string) $validated['language'], $status, $duplicate_mode );
			if ( is_wp_error( $result ) ) {
				$errors[] = sprintf( __( 'ردیف %1$d: %2$s', 'dhikra' ), $index + 1, $result->get_error_message() );
			} elseif ( 'created' === $result ) {
				++$created;
			} elseif ( 'updated' === $result ) {
				++$updated;
			} else {
				++$skipped;
			}
		}
		$message = sprintf( __( 'ورود پایان یافت: %1$d ساخته شد، %2$d به‌روزرسانی شد و %3$d رد شد.', 'dhikra' ), $created, $updated, $skipped );
		$this->finish( $message, $errors );
	}

	public static function validate_package( mixed $package, string $status = 'draft' ): array {
		$errors = array();
		$items  = array();
		if ( ! is_array( $package ) ) {
			return array( 'language' => '', 'items' => array(), 'errors' => array( __( 'ریشهٔ فایل باید یک شیء JSON باشد.', 'dhikra' ) ) );
		}
		$schema_version = (string) ( $package['schema_version'] ?? '' );
		if ( ! in_array( $schema_version, array( '1.1', '1.1.0' ), true ) ) {
			$errors[] = __( 'schema_version باید 1.1 باشد.', 'dhikra' );
		}
		$language = sanitize_text_field( (string) ( $package['language'] ?? '' ) );
		if ( '' === $language ) {
			$errors[] = __( 'زبان بسته الزامی است.', 'dhikra' );
		}
		if ( empty( $package['items'] ) || ! is_array( $package['items'] ) ) {
			$errors[] = __( 'آرایهٔ items خالی یا نامعتبر است.', 'dhikra' );
			return array( 'language' => $language, 'items' => array(), 'errors' => $errors );
		}
		if ( count( $package['items'] ) > 1000 ) {
			$errors[] = __( 'هر بسته حداکثر ۱۰۰۰ تلنگر می‌تواند داشته باشد.', 'dhikra' );
		}
		$seen_ids   = array();
		$seen_codes = array();
		foreach ( $package['items'] as $index => $raw ) {
			if ( ! is_array( $raw ) ) {
				$errors[] = sprintf( __( 'ردیف %d باید شیء باشد.', 'dhikra' ), $index + 1 );
				continue;
			}
			$id = sanitize_key( (string) ( $raw['id'] ?? '' ) );
			if ( '' === $id ) {
				$errors[] = sprintf( __( 'ردیف %d شناسهٔ پایدار ندارد.', 'dhikra' ), $index + 1 );
			} elseif ( isset( $seen_ids[ $id ] ) ) {
				$errors[] = sprintf( __( 'شناسهٔ %s در بسته تکراری است.', 'dhikra' ), $id );
			}
			$seen_ids[ $id ] = true;
			$type = sanitize_key( (string) ( $raw['type'] ?? 'quran' ) );
			$main_topic = sanitize_text_field( (string) ( $raw['main_topic'] ?? '' ) );
			$item = array(
				'id'                => $id,
				'translation_group' => sanitize_key( (string) ( $raw['translation_group'] ?? $id ) ),
				'type'              => in_array( $type, Dhikra_Content::TYPES, true ) ? $type : '',
				'title'             => sanitize_text_field( (string) ( $raw['title'] ?? '' ) ),
				'original'          => Dhikra_Helpers::normalize_text( (string) ( $raw['text'] ?? $raw['original'] ?? '' ) ),
				'translation'       => Dhikra_Helpers::normalize_text( (string) ( $raw['translation'] ?? '' ) ),
				'reflection'        => Dhikra_Helpers::normalize_text( (string) ( $raw['reflection'] ?? '' ) ),
				'speaker'           => sanitize_text_field( (string) ( $raw['speaker'] ?? '' ) ),
				'source'            => sanitize_text_field( (string) ( $raw['source'] ?? '' ) ),
				'main_topic'        => $main_topic,
				'topics'            => array_values( array_unique( array_filter( array_map( 'sanitize_text_field', is_array( $raw['topics'] ?? null ) ? $raw['topics'] : array() ) ) ) ),
				'short_code'        => isset( $raw['short_code'] ) ? Dhikra_Helpers::sanitize_short_code( (string) $raw['short_code'] ) : '',
				'status'            => isset( $raw['status'] ) ? Dhikra_Helpers::sanitize_status( sanitize_key( (string) $raw['status'] ) ) : $status,
			);
			if ( '' === $item['type'] ) {
				$errors[] = sprintf( __( 'ردیف %d نوع محتوای معتبر ندارد.', 'dhikra' ), $index + 1 );
				continue;
			}
			if ( 'quran' === $item['type'] ) {
				$item['speaker'] = '';
			}
			if ( 'story' === $item['type'] ) {
				$item['translation'] = '';
				$item['reflection']  = '';
				$item['speaker']     = '';
			}
			if ( '' === $main_topic ) {
				$errors[] = sprintf( __( 'ردیف %d موضوع اصلی ندارد.', 'dhikra' ), $index + 1 );
			}
			$item['topics'][] = $main_topic;
			$item['topics']    = array_values( array_unique( array_filter( $item['topics'] ) ) );
			$field_errors = Dhikra_Content::validate_fields(
				array(
					'title'         => $item['title'],
					'type'          => $item['type'],
					'original'      => $item['original'],
					'translation'   => $item['translation'],
					'reflection'    => $item['reflection'],
					'speaker'       => $item['speaker'],
					'source'        => $item['source'],
					'main_topic_id' => 1, // Topic existence is resolved during import; content rules still run.
				)
			);
			$field_errors = array_filter( $field_errors, static fn( string $error ): bool => false === strpos( $error, 'موضوع اصلی' ) );
			foreach ( $field_errors as $error ) {
				$errors[] = sprintf( __( 'ردیف %1$d: %2$s', 'dhikra' ), $index + 1, $error );
			}
			if ( isset( $raw['short_code'] ) && '' === $item['short_code'] ) {
				$errors[] = sprintf( __( 'ردیف %d کد کوتاه نامعتبر دارد.', 'dhikra' ), $index + 1 );
			} elseif ( '' !== $item['short_code'] ) {
				if ( isset( $seen_codes[ $item['short_code'] ] ) ) {
					$errors[] = sprintf( __( 'کد کوتاه %s در بسته تکراری است.', 'dhikra' ), $item['short_code'] );
				}
				$seen_codes[ $item['short_code'] ] = true;
			}
			$items[] = $item;
		}
		return array( 'language' => $language, 'items' => $items, 'errors' => array_values( array_unique( $errors ) ) );
	}

	private function import_item( array $item, string $language, string $status, string $duplicate_mode ): string|WP_Error {
		$existing = $this->find_existing( (string) $item['id'] );
		if ( $existing > 0 && 'skip' === $duplicate_mode ) {
			return 'skipped';
		}

		// Resolve immutable/custom short-code conflicts before changing the post or its metadata.
		$current_code  = $existing > 0 ? Dhikra_Data::get_short_code( $existing ) : '';
		$requested_code = (string) $item['short_code'];
		if ( $existing > 0 && '' !== $current_code && '' !== $requested_code && $current_code !== $requested_code ) {
			return new WP_Error( 'dhikra_import_code_immutable', __( 'کد کوتاه رکورد موجود با فایل یکسان نیست و قابل تغییر نیست.', 'dhikra' ) );
		}
		if ( '' !== $requested_code && ! Dhikra_Data::short_code_available( $requested_code, $existing ) ) {
			return new WP_Error( 'dhikra_import_code_unavailable', __( 'کد کوتاه درخواستی قبلاً استفاده شده است.', 'dhikra' ) );
		}

		$main_topic_id = $this->ensure_topic( (string) $item['main_topic'] );
		if ( is_wp_error( $main_topic_id ) ) {
			return $main_topic_id;
		}
		$topic_ids = array();
		foreach ( $item['topics'] as $name ) {
			$term_id = $this->ensure_topic( (string) $name );
			if ( is_wp_error( $term_id ) ) {
				return $term_id;
			}
			$topic_ids[] = $term_id;
		}
		$topic_ids[] = $main_topic_id;
		$topic_ids   = array_values( array_unique( array_map( 'absint', $topic_ids ) ) );

		// An existing legacy record without a code receives its code before any content update.
		if ( $existing > 0 && '' === $current_code ) {
			$assigned = Dhikra_Data::assign_short_code( $existing, $requested_code );
			if ( is_wp_error( $assigned ) ) {
				return $assigned;
			}
		}

		$postarr = array(
			'post_type'   => 'dhikra_telangor',
			'post_status' => Dhikra_Helpers::sanitize_status( (string) ( $item['status'] ?? $status ) ),
			'post_title'  => (string) $item['title'],
		);
		if ( $existing > 0 ) {
			$postarr['ID'] = $existing;
			$post_id       = wp_update_post( $postarr, true );
		} else {
			$post_id = wp_insert_post( $postarr, true );
		}
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		$post_id = absint( $post_id );

		if ( $existing <= 0 ) {
			$assigned = Dhikra_Data::assign_short_code( $post_id, $requested_code );
			if ( is_wp_error( $assigned ) ) {
				wp_delete_post( $post_id, true );
				return $assigned;
			}
		}

		update_post_meta( $post_id, '_dhikra_content_id', $item['id'] );
		update_post_meta( $post_id, '_dhikra_translation_group', $item['translation_group'] );
		update_post_meta( $post_id, '_dhikra_language', $language );
		update_post_meta( $post_id, '_dhikra_type', $item['type'] );
		update_post_meta( $post_id, '_dhikra_original_text', $item['original'] );
		update_post_meta( $post_id, '_dhikra_translation', $item['translation'] );
		update_post_meta( $post_id, '_dhikra_reflection', $item['reflection'] );
		update_post_meta( $post_id, '_dhikra_speaker', $item['speaker'] );
		update_post_meta( $post_id, '_dhikra_source', $item['source'] );
		update_post_meta( $post_id, '_dhikra_main_topic_id', $main_topic_id );
		$term_result = wp_set_object_terms( $post_id, $topic_ids, 'dhikra_topic' );
		if ( is_wp_error( $term_result ) ) {
			if ( $existing <= 0 ) {
				wp_delete_post( $post_id, true );
			}
			return $term_result;
		}

		return $existing > 0 ? 'updated' : 'created';
	}

	private function ensure_topic( string $name ): int|WP_Error {
		$name = sanitize_text_field( $name );
		if ( '' === $name ) {
			return new WP_Error( 'dhikra_empty_topic', __( 'نام موضوع خالی است.', 'dhikra' ) );
		}
		$slug = sanitize_title( $name );
		$term = get_term_by( 'slug', $slug, 'dhikra_topic' );
		if ( ! $term instanceof WP_Term ) {
			$term = get_term_by( 'name', $name, 'dhikra_topic' );
		}
		if ( $term instanceof WP_Term ) {
			return $term->term_id;
		}
		$created = wp_insert_term( $name, 'dhikra_topic', array( 'slug' => $slug ) );
		if ( is_wp_error( $created ) ) {
			return $created;
		}
		$term_id = absint( $created['term_id'] );
		update_term_meta( $term_id, '_dhikra_topic_active', '1' );
		return $term_id;
	}

	private function find_existing( string $content_id ): int {
		$ids = get_posts( array( 'post_type' => 'dhikra_telangor', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => 1, 'meta_key' => '_dhikra_content_id', 'meta_value' => $content_id ) );
		return isset( $ids[0] ) ? absint( $ids[0] ) : 0;
	}

	private function finish( string $summary, array $errors = array() ): never {
		if ( ! empty( $errors ) ) {
			$summary .= ' ' . implode( ' | ', array_slice( array_map( 'sanitize_text_field', $errors ), 0, 12 ) );
		}
		$url = add_query_arg( array( 'post_type' => 'dhikra_telangor', 'page' => self::PAGE, 'dhikra_import_message' => $summary ), admin_url( 'edit.php' ) );
		wp_safe_redirect( $url );
		exit;
	}
}
