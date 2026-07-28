<?php

namespace Tazkar\Admin;

use RuntimeException;
use Tazkar\Import\ChunkedImportProgress;
use Tazkar\Import\ChunkedImportService;
use Tazkar\Import\CsvPreviewOptions;
use Tazkar\Import\CsvPreviewResult;
use Tazkar\Import\CsvPreviewService;
use Tazkar\Import\CsvReadException;
use Tazkar\Import\UploadedCsv;
use Tazkar\Import\UploadedCsvValidator;
use Tazkar\Validation\ValidationIssue;
use Tazkar\Validation\ValidationResult;

if ( ! defined( 'ABSPATH' ) && ! defined( 'TAZKAR_TESTING' ) ) {
	exit;
}

final class CsvPreviewPage {
	private const PREVIEW_NONCE_ACTION = 'tazkar_csv_preview';
	private const PREVIEW_NONCE_NAME   = 'tazkar_csv_preview_nonce';
	private const START_NONCE_ACTION   = 'tazkar_import_start';
	private const START_NONCE_NAME     = 'tazkar_import_start_nonce';
	private const FILE_FIELD           = 'tazkar_csv_file';
	private const CHUNK_SIZE_FIELD     = 'tazkar_import_chunk_size';
	private const PAGE_SLUG            = 'tazkar-csv-preview';

	/**
	 * @param (\Closure(string): string)|null $mime_detector Optional MIME detector for testing.
	 */
	public function __construct(
		private readonly CsvPreviewService $preview_service = new CsvPreviewService(),
		private readonly UploadedCsvValidator $upload_validator = new UploadedCsvValidator(),
		private readonly ?\Closure $mime_detector = null,
		private readonly ChunkedImportService $import_service = new ChunkedImportService()
	) {}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_submenu' ) );
		add_action( 'admin_post_tazkar_import_start', array( $this, 'start_import' ) );
		add_action( 'admin_post_tazkar_import_process', array( $this, 'process_import' ) );
		add_action( 'admin_post_tazkar_import_resume', array( $this, 'resume_import' ) );
		add_action( 'admin_post_tazkar_import_rollback_preview', array( $this, 'preview_rollback' ) );
		add_action( 'admin_post_tazkar_import_rollback', array( $this, 'rollback_import' ) );
	}

	public function add_submenu(): void {
		add_submenu_page(
			'tazkar',
			__( 'پیش‌نمایش و ورود CSV', 'tazkar' ),
			__( 'CSV', 'tazkar' ),
			'import_tazkar_items',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	public function render(): void {
		$this->require_capability();
		$preview_result   = null;
		$progress         = null;
		$rollback_preview = null;
		$error            = null;

		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			try {
				check_admin_referer( self::PREVIEW_NONCE_ACTION, self::PREVIEW_NONCE_NAME );
				$preview_result = $this->process_preview_request( $_FILES[ self::FILE_FIELD ] ?? array() );
			} catch ( CsvReadException $exception ) {
				$error = $exception;
			}
		}

		$batch_uuid = $this->request_batch_uuid( $_GET );
		if ( '' !== $batch_uuid ) {
			try {
				$progress = $this->import_service->progress( $batch_uuid, get_current_user_id() );
				if ( $this->request_rollback_preview() ) {
					$rollback_preview = $this->import_service->rollback_preview( $batch_uuid, get_current_user_id() );
				}
			} catch ( \Throwable $exception ) {
				$error = $exception;
			}
		}

		$this->render_page( $preview_result, $progress, $rollback_preview, $error );
	}

	public function start_import(): void {
		$this->require_capability();
		check_admin_referer( self::START_NONCE_ACTION, self::START_NONCE_NAME );
		$upload   = null;
		$progress = null;

		try {
			$chunk_size = $this->request_chunk_size();
			update_option( self::CHUNK_SIZE_FIELD, $chunk_size, false );
			$upload   = $this->validated_upload( $_FILES[ self::FILE_FIELD ] ?? array() );
			$progress = $this->import_service->stage(
				$upload->path(),
				$upload->name(),
				get_current_user_id(),
				$chunk_size
			);
		} catch ( \Throwable $exception ) {
			$this->render_action_error( $exception );
			return;
		} finally {
			if ( $upload instanceof UploadedCsv && is_file( $upload->path() ) ) {
				wp_delete_file( $upload->path() );
			}
		}

		$this->redirect_to_batch( $progress->batch_uuid() );
	}

	public function process_import(): void {
		$this->handle_batch_action( 'process' );
	}

	public function resume_import(): void {
		$this->handle_batch_action( 'resume' );
	}

	public function preview_rollback(): void {
		$this->handle_batch_action( 'rollback_preview' );
	}

	public function rollback_import(): void {
		if ( ! $this->request_rollback_confirmation() ) {
			$this->render_action_error( new RuntimeException( 'Rollback requires explicit administrator confirmation.' ) );
			return;
		}
		$this->handle_batch_action( 'rollback' );
	}

	/** @param array<string, mixed> $file Raw upload metadata. */
	private function process_preview_request( array $file ): CsvPreviewResult {
		$upload = $this->validated_upload( $file );

		return $this->preview_service->preview( $upload->path(), $upload->name() );
	}

	/** @param array<string, mixed> $file Raw upload metadata. */
	private function validated_upload( array $file ): UploadedCsv {
		$path        = is_string( $file['tmp_name'] ?? null ) ? $file['tmp_name'] : '';
		$is_uploaded = '' !== $path && is_uploaded_file( $path );
		$mime        = $is_uploaded ? $this->detect_mime( $path ) : '';

		return $this->upload_validator->validate( $file, $is_uploaded, $mime );
	}

	private function detect_mime( string $path ): string {
		if ( null !== $this->mime_detector ) {
			return strtolower( ( $this->mime_detector )( $path ) );
		}
		if ( '' === $path || ! is_readable( $path ) ) {
			return '';
		}

		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		if ( false === $finfo ) {
			return '';
		}
		$mime = finfo_file( $finfo, $path );
		finfo_close( $finfo );

		return is_string( $mime ) ? strtolower( $mime ) : '';
	}

	private function handle_batch_action( string $action ): void {
		$this->require_capability();
		$batch_uuid = $this->request_batch_uuid( $_POST );
		if ( '' === $batch_uuid ) {
			$this->render_action_error( new RuntimeException( 'Import batch UUID is required.' ) );
			return;
		}
		check_admin_referer( 'tazkar_import_' . $action . '_' . $batch_uuid, 'tazkar_import_nonce' );

		try {
			if ( 'rollback_preview' === $action ) {
				$this->import_service->rollback_preview( $batch_uuid, get_current_user_id() );
			} elseif ( 'rollback' === $action ) {
				$this->import_service->rollback( $batch_uuid, get_current_user_id() );
			} elseif ( 'resume' === $action ) {
				$this->import_service->resume( $batch_uuid, get_current_user_id() );
			} else {
				$this->import_service->process( $batch_uuid, get_current_user_id() );
			}
		} catch ( \Throwable $exception ) {
			$this->render_action_error( $exception );
			return;
		}

		$this->redirect_to_batch( $batch_uuid, 'rollback_preview' === $action );
	}

	private function request_rollback_preview(): bool {
		$value = $_GET['rollback_preview'] ?? '';

		return is_scalar( $value ) && '1' === sanitize_text_field( wp_unslash( (string) $value ) );
	}

	private function request_rollback_confirmation(): bool {
		$value = $_POST['tazkar_confirm_rollback'] ?? '';

		return is_scalar( $value ) && '1' === sanitize_text_field( wp_unslash( (string) $value ) );
	}

	/** @param array<string, mixed> $source Request source. */
	private function request_batch_uuid( array $source ): string {
		$value = $source['batch_uuid'] ?? '';
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = sanitize_text_field( wp_unslash( (string) $value ) );

		return 1 === preg_match( '/^[a-f0-9-]{36}$/i', $value ) ? strtolower( $value ) : '';
	}

	private function request_chunk_size(): int {
		$value = $_POST[ self::CHUNK_SIZE_FIELD ] ?? get_option(
			self::CHUNK_SIZE_FIELD,
			ChunkedImportService::DEFAULT_CHUNK_SIZE
		);
		$value = is_scalar( $value ) ? absint( wp_unslash( (string) $value ) ) : 0;

		return max( 1, min( ChunkedImportService::MAX_CHUNK_SIZE, $value ) );
	}

	private function require_capability(): void {
		if ( ! current_user_can( 'import_tazkar_items' ) ) {
			wp_die( esc_html__( 'شما اجازه پیش‌نمایش یا ورود فایل را ندارید.', 'tazkar' ) );
		}
	}

	private function redirect_to_batch( string $batch_uuid, bool $rollback_preview = false ): void {
		$query = array(
			'page'       => self::PAGE_SLUG,
			'batch_uuid' => $batch_uuid,
		);
		if ( $rollback_preview ) {
			$query['rollback_preview'] = '1';
		}
		$url = add_query_arg(
			$query,
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	private function render_action_error( \Throwable $exception ): void {
		wp_die( esc_html( $exception->getMessage() ) );
	}

	private function render_page(
		?CsvPreviewResult $preview_result,
		?ChunkedImportProgress $progress,
		?array $rollback_preview,
		?\Throwable $error
	): void {
		?>
		<div class="wrap tazkar-csv-preview-page">
			<h1><?php echo esc_html__( 'پیش‌نمایش و ورود فایل CSV', 'tazkar' ); ?></h1>
			<p><?php echo esc_html__( 'پیش‌نمایش داده‌ای ذخیره نمی‌کند. ورود، فایل را به یک بسته پایدار تبدیل می‌کند و هر درخواست فقط یک قطعه محدود را به‌صورت پیش‌نویس پردازش می‌کند.', 'tazkar' ); ?></p>

			<?php if ( null !== $error ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $error->getMessage() ); ?></p></div>
			<?php endif; ?>

			<h2><?php echo esc_html__( 'پیش‌نمایش امن', 'tazkar' ); ?></h2>
			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( self::PREVIEW_NONCE_ACTION, self::PREVIEW_NONCE_NAME ); ?>
				<?php $this->render_file_field( 'preview' ); ?>
				<?php submit_button( __( 'ساخت پیش‌نمایش', 'tazkar' ) ); ?>
			</form>

			<h2><?php echo esc_html__( 'شروع ورود قطعه‌ای', 'tazkar' ); ?></h2>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="tazkar_import_start">
				<?php wp_nonce_field( self::START_NONCE_ACTION, self::START_NONCE_NAME ); ?>
				<?php $this->render_file_field( 'import' ); ?>
				<?php $this->render_chunk_size_field(); ?>
				<?php submit_button( __( 'مرحله‌بندی فایل', 'tazkar' ), 'secondary' ); ?>
			</form>

			<?php if ( null !== $preview_result ) : ?>
				<?php $this->render_preview_result( $preview_result ); ?>
			<?php endif; ?>
			<?php if ( null !== $progress ) : ?>
				<?php $this->render_progress( $progress, $rollback_preview ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_file_field( string $context ): void {
		$field_id = 'tazkar-csv-file-' . $context;
		?>
		<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo esc_attr( (string) CsvPreviewOptions::DEFAULT_MAX_BYTES ); ?>">
		<table class="form-table" role="presentation"><tr>
			<th scope="row"><label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html__( 'فایل CSV', 'tazkar' ); ?></label></th>
			<td>
				<input id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( self::FILE_FIELD ); ?>" type="file" accept=".csv,text/csv" required>
				<p class="description"><?php echo esc_html__( 'حداکثر ۱۰ مگابایت، ۵۰۰ ردیف و ۶۰ ستون.', 'tazkar' ); ?></p>
			</td>
		</tr></table>
		<?php
	}

	private function render_chunk_size_field(): void {
		$chunk_size = (int) get_option( self::CHUNK_SIZE_FIELD, ChunkedImportService::DEFAULT_CHUNK_SIZE );
		$chunk_size = max( 1, min( ChunkedImportService::MAX_CHUNK_SIZE, $chunk_size ) );
		?>
		<table class="form-table" role="presentation"><tr>
			<th scope="row"><label for="tazkar-import-chunk-size"><?php echo esc_html__( 'اندازه هر قطعه', 'tazkar' ); ?></label></th>
			<td>
				<input id="tazkar-import-chunk-size" name="<?php echo esc_attr( self::CHUNK_SIZE_FIELD ); ?>" type="number" min="1" max="<?php echo esc_attr( (string) ChunkedImportService::MAX_CHUNK_SIZE ); ?>" value="<?php echo esc_attr( (string) $chunk_size ); ?>">
				<p class="description"><?php echo esc_html__( 'پیش‌فرض ۲۵ ردیف است؛ حد سخت سرور ۱۰۰ ردیف در هر درخواست است.', 'tazkar' ); ?></p>
			</td>
		</tr></table>
		<?php
	}

	private function render_preview_result( CsvPreviewResult $result ): void {
		?>
		<hr>
		<h2><?php echo esc_html__( 'نتیجه پیش‌نمایش', 'tazkar' ); ?></h2>
		<table class="widefat striped" style="max-width:1000px"><tbody>
			<tr><th><?php echo esc_html__( 'فایل', 'tazkar' ); ?></th><td><?php echo esc_html( $result->filename() ); ?></td></tr>
			<tr><th><?php echo esc_html__( 'کل ردیف‌ها', 'tazkar' ); ?></th><td><?php echo esc_html( (string) $result->total_rows() ); ?></td></tr>
			<tr><th><?php echo esc_html__( 'ردیف معتبر', 'tazkar' ); ?></th><td><?php echo esc_html( (string) $result->valid_rows() ); ?></td></tr>
			<tr><th><?php echo esc_html__( 'ردیف نامعتبر', 'tazkar' ); ?></th><td><?php echo esc_html( (string) $result->invalid_rows() ); ?></td></tr>
			<tr><th><?php echo esc_html__( 'ردیف دارای هشدار', 'tazkar' ); ?></th><td><?php echo esc_html( (string) $result->warning_rows() ); ?></td></tr>
		</tbody></table>
		<?php $this->render_issue_list( __( 'مسائل سرستون‌ها', 'tazkar' ), $result->validation()->headers()->issues() ); ?>
		<table class="widefat striped"><thead><tr>
			<th><?php echo esc_html__( 'ردیف', 'tazkar' ); ?></th>
			<th><?php echo esc_html__( 'شناسه', 'tazkar' ); ?></th>
			<th><?php echo esc_html__( 'عنوان', 'tazkar' ); ?></th>
			<th><?php echo esc_html__( 'وضعیت', 'tazkar' ); ?></th>
			<th><?php echo esc_html__( 'کدها', 'tazkar' ); ?></th>
		</tr></thead><tbody>
		<?php foreach ( $result->validation()->rows() as $row_result ) : ?>
			<?php $this->render_preview_row( $row_result ); ?>
		<?php endforeach; ?>
		</tbody></table>
		<?php
	}

	/** @param array<string, int>|null $rollback_preview */
	private function render_progress( ChunkedImportProgress $progress, ?array $rollback_preview ): void {
		?>
		<hr>
		<h2><?php echo esc_html__( 'وضعیت بسته ورود', 'tazkar' ); ?></h2>
		<table class="widefat striped" style="max-width:1000px"><tbody>
			<tr><th><?php echo esc_html__( 'شناسه بسته', 'tazkar' ); ?></th><td><code><?php echo esc_html( $progress->batch_uuid() ); ?></code></td></tr>
			<tr><th><?php echo esc_html__( 'وضعیت', 'tazkar' ); ?></th><td><?php echo esc_html( $progress->status() ); ?></td></tr>
			<tr><th><?php echo esc_html__( 'پردازش‌شده / کل', 'tazkar' ); ?></th><td><?php echo esc_html( $progress->count( 'processed' ) . ' / ' . $progress->count( 'total' ) ); ?></td></tr>
			<tr><th><?php echo esc_html__( 'واردشده', 'tazkar' ); ?></th><td><?php echo esc_html( (string) $progress->count( 'imported' ) ); ?></td></tr>
			<tr><th><?php echo esc_html__( 'ردشده', 'tazkar' ); ?></th><td><?php echo esc_html( (string) $progress->count( 'skipped' ) ); ?></td></tr>
			<tr><th><?php echo esc_html__( 'نامعتبر', 'tazkar' ); ?></th><td><?php echo esc_html( (string) $progress->count( 'invalid' ) ); ?></td></tr>
			<tr><th><?php echo esc_html__( 'خطا', 'tazkar' ); ?></th><td><?php echo esc_html( (string) $progress->count( 'error' ) ); ?></td></tr>
			<tr><th><?php echo esc_html__( 'در انتظار', 'tazkar' ); ?></th><td><?php echo esc_html( (string) $progress->count( 'pending' ) ); ?></td></tr>
			<tr><th><?php echo esc_html__( 'تعارض بازگردانی', 'tazkar' ); ?></th><td><?php echo esc_html( (string) $progress->count( 'rollback_conflicts' ) ); ?></td></tr>
		</tbody></table>
		<div style="display:flex;gap:8px;align-items:center;margin:16px 0">
			<?php if ( $progress->can_resume() ) : ?>
				<?php $this->render_batch_action_form( $progress->batch_uuid(), 'process', __( 'پردازش قطعه بعدی', 'tazkar' ), 'primary' ); ?>
				<?php $this->render_batch_action_form( $progress->batch_uuid(), 'resume', __( 'ادامه پردازش', 'tazkar' ), 'secondary' ); ?>
			<?php endif; ?>
			<?php if ( $progress->can_rollback() && null === $rollback_preview ) : ?>
				<?php $this->render_batch_action_form( $progress->batch_uuid(), 'rollback_preview', __( 'پیش‌نمایش بازگردانی', 'tazkar' ), 'secondary' ); ?>
			<?php endif; ?>
		</div>
		<?php if ( null !== $rollback_preview ) : ?>
			<?php $this->render_rollback_preview( $progress->batch_uuid(), $rollback_preview ); ?>
		<?php endif; ?>
		<h3><?php echo esc_html__( 'نتیجه ردیف‌ها', 'tazkar' ); ?></h3>
		<table class="widefat striped"><thead><tr>
			<th><?php echo esc_html__( 'ردیف', 'tazkar' ); ?></th>
			<th><?php echo esc_html__( 'شناسه', 'tazkar' ); ?></th>
			<th><?php echo esc_html__( 'وضعیت', 'tazkar' ); ?></th>
			<th><?php echo esc_html__( 'کد', 'tazkar' ); ?></th>
			<th><?php echo esc_html__( 'نوشته', 'tazkar' ); ?></th>
			<th><?php echo esc_html__( 'بازگردانی', 'tazkar' ); ?></th>
		</tr></thead><tbody>
		<?php foreach ( $progress->rows() as $row ) : ?>
			<tr>
				<td><?php echo esc_html( (string) ( $row['row_number'] ?? '' ) ); ?></td>
				<td><code><?php echo esc_html( (string) ( $row['record_id'] ?? '' ) ); ?></code></td>
				<td><?php echo esc_html( (string) ( $row['status'] ?? '' ) ); ?></td>
				<td><code><?php echo esc_html( (string) ( $row['error_code'] ?? '' ) ); ?></code></td>
				<td><?php echo esc_html( (string) ( (int) ( $row['wp_post_id'] ?? 0 ) > 0 ? (int) $row['wp_post_id'] : '—' ) ); ?></td>
				<td><?php echo esc_html( trim( (string) ( $row['rollback_status'] ?? '' ) . ' ' . (string) ( $row['rollback_error_code'] ?? '' ) ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody></table>
		<?php
	}


	/** @param array<string, int> $preview */
	private function render_rollback_preview( string $batch_uuid, array $preview ): void {
		?>
		<div class="notice notice-warning inline" style="max-width:1000px;padding:12px">
			<h3><?php echo esc_html__( 'پیش‌نمایش بازگردانی — هنوز هیچ تغییری اعمال نشده است', 'tazkar' ); ?></h3>
			<table class="widefat striped"><tbody>
				<tr><th><?php echo esc_html__( 'پیش‌نویس قابل انتقال به زباله‌دان', 'tazkar' ); ?></th><td><?php echo esc_html( (string) ( $preview['eligible_drafts'] ?? 0 ) ); ?></td></tr>
				<tr><th><?php echo esc_html__( 'ردیف در انتظار لغو', 'tazkar' ); ?></th><td><?php echo esc_html( (string) ( $preview['pending'] ?? 0 ) ); ?></td></tr>
				<tr><th><?php echo esc_html__( 'منتشرشده — حفظ می‌شود', 'tazkar' ); ?></th><td><?php echo esc_html( (string) ( $preview['published'] ?? 0 ) ); ?></td></tr>
				<tr><th><?php echo esc_html__( 'تأییدشده — حفظ می‌شود', 'tazkar' ); ?></th><td><?php echo esc_html( (string) ( $preview['approved'] ?? 0 ) ); ?></td></tr>
				<tr><th><?php echo esc_html__( 'ویرایش‌شده — حفظ می‌شود', 'tazkar' ); ?></th><td><?php echo esc_html( (string) ( $preview['edited'] ?? 0 ) ); ?></td></tr>
				<tr><th><?php echo esc_html__( 'گم‌شده یا با مالکیت متفاوت — تعارض', 'tazkar' ); ?></th><td><?php echo esc_html( (string) ( ( $preview['missing'] ?? 0 ) + ( $preview['foreign'] ?? 0 ) + ( $preview['other_conflicts'] ?? 0 ) ) ); ?></td></tr>
				<tr><th><?php echo esc_html__( 'زباله‌دان غیرفعال — بازگردانی رد می‌شود', 'tazkar' ); ?></th><td><?php echo esc_html( (string) ( $preview['trash_disabled'] ?? 0 ) ); ?></td></tr>
				<tr><th><?php echo esc_html__( 'تعامل‌های ثبت‌شده', 'tazkar' ); ?></th><td><?php echo esc_html( (string) ( $preview['interactions'] ?? 0 ) ); ?></td></tr>
			</tbody></table>
			<p><?php echo esc_html__( 'با تأیید زیر، فقط پیش‌نویس‌های بدون تغییر و متعلق به همین بسته به زباله‌دان منتقل می‌شوند؛ موارد منتشرشده، تأییدشده، ویرایش‌شده یا دارای تعارض حفظ خواهند شد.', 'tazkar' ); ?></p>
			<?php if ( 0 === (int) ( $preview['trash_disabled'] ?? 0 ) ) : ?>
				<?php $this->render_batch_action_form( $batch_uuid, 'rollback', __( 'تأیید و اجرای بازگردانی', 'tazkar' ), 'delete', true ); ?>
			<?php else : ?>
				<p><strong><?php echo esc_html__( 'تا زمانی که WordPress Trash غیرفعال است، اجرای بازگردانی برای جلوگیری از حذف دائمی مسدود می‌ماند.', 'tazkar' ); ?></strong></p>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_batch_action_form( string $batch_uuid, string $action, string $label, string $button_class, bool $confirmed = false ): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0">
			<input type="hidden" name="action" value="<?php echo esc_attr( 'tazkar_import_' . $action ); ?>">
			<input type="hidden" name="batch_uuid" value="<?php echo esc_attr( $batch_uuid ); ?>">
			<?php
			if ( $confirmed ) :
				?>
				<input type="hidden" name="tazkar_confirm_rollback" value="1"><?php endif; ?>
			<?php wp_nonce_field( 'tazkar_import_' . $action . '_' . $batch_uuid, 'tazkar_import_nonce' ); ?>
			<?php submit_button( $label, $button_class, 'submit', false ); ?>
		</form>
		<?php
	}

	/** @param ValidationIssue[] $issues Validation issues. */
	private function render_issue_list( string $title, array $issues ): void {
		if ( array() === $issues ) {
			return;
		}
		?>
		<h3><?php echo esc_html( $title ); ?></h3>
		<ul>
			<?php foreach ( $issues as $issue ) : ?>
				<li><code><?php echo esc_html( $issue->code() ); ?></code> — <?php echo esc_html( $issue->message() ); ?></li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	private function render_preview_row( ValidationResult $row_result ): void {
		$row   = $row_result->row();
		$codes = array_map(
			static fn ( ValidationIssue $issue ): string => $issue->code(),
			$row_result->issues()
		);
		?>
		<tr>
			<td><?php echo esc_html( (string) $row_result->row_number() ); ?></td>
			<td><code><?php echo esc_html( is_scalar( $row['record_id'] ?? null ) ? (string) $row['record_id'] : '' ); ?></code></td>
			<td><?php echo esc_html( is_scalar( $row['generated_title'] ?? null ) ? (string) $row['generated_title'] : '' ); ?></td>
			<td><?php echo $row_result->is_valid() ? esc_html__( 'معتبر', 'tazkar' ) : esc_html__( 'نامعتبر', 'tazkar' ); ?></td>
			<td><?php echo esc_html( implode( '، ', $codes ) ); ?></td>
		</tr>
		<?php
	}
}
