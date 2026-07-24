<?php

namespace Tazkar\Admin;

use Tazkar\Import\CsvImportResult;
use Tazkar\Import\CsvImportRowResult;
use Tazkar\Import\CsvImportService;
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
	private const IMPORT_NONCE_ACTION  = 'tazkar_csv_import';
	private const IMPORT_NONCE_NAME    = 'tazkar_csv_import_nonce';
	private const FILE_FIELD           = 'tazkar_csv_file';
	private const ACTION_FIELD         = 'tazkar_csv_action';

	/**
	 * @param (\Closure(string): string)|null $mime_detector Optional MIME detector for testing.
	 */
	public function __construct(
		private readonly CsvPreviewService $preview_service = new CsvPreviewService(),
		private readonly UploadedCsvValidator $upload_validator = new UploadedCsvValidator(),
		private readonly ?\Closure $mime_detector = null,
		private readonly CsvImportService $import_service = new CsvImportService()
	) {}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_submenu' ) );
	}

	public function add_submenu(): void {
		add_submenu_page(
			'tazkar',
			__( 'پیش‌نمایش و ورود CSV', 'tazkar' ),
			__( 'CSV', 'tazkar' ),
			'import_tazkar_items',
			'tazkar-csv-preview',
			array( $this, 'render' )
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'import_tazkar_items' ) ) {
			wp_die( esc_html__( 'شما اجازه پیش‌نمایش یا ورود فایل را ندارید.', 'tazkar' ) );
		}

		$preview_result = null;
		$import_result  = null;
		$error          = null;

		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			$action = is_string( $_POST[ self::ACTION_FIELD ] ?? null )
				? $_POST[ self::ACTION_FIELD ]
				: 'preview';
			try {
				if ( 'import' === $action ) {
					check_admin_referer( self::IMPORT_NONCE_ACTION, self::IMPORT_NONCE_NAME );
					$import_result = $this->process_import_request(
						$_FILES[ self::FILE_FIELD ] ?? array()
					);
				} else {
					check_admin_referer( self::PREVIEW_NONCE_ACTION, self::PREVIEW_NONCE_NAME );
					$preview_result = $this->process_request(
						$_FILES[ self::FILE_FIELD ] ?? array()
					);
				}
			} catch ( CsvReadException $exception ) {
				$error = $exception;
			}
		}

		$this->render_page( $preview_result, $import_result, $error );
	}

	/** @param array<string, mixed> $file Raw upload metadata. */
	private function process_request( array $file ): CsvPreviewResult {
		$upload = $this->validated_upload( $file );

		return $this->preview_service->preview( $upload->path(), $upload->name() );
	}

	/** @param array<string, mixed> $file Raw upload metadata. */
	private function process_import_request( array $file ): CsvImportResult {
		$upload = $this->validated_upload( $file );

		return $this->import_service->import(
			$upload->path(),
			$upload->name(),
			get_current_user_id()
		);
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
			$mime = ( $this->mime_detector )( $path );

			return strtolower( $mime );
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

	private function render_page(
		?CsvPreviewResult $preview_result,
		?CsvImportResult $import_result,
		?CsvReadException $error
	): void {
		?>
		<div class="wrap tazkar-csv-preview-page">
			<h1><?php echo esc_html__( 'پیش‌نمایش و ورود فایل CSV', 'tazkar' ); ?></h1>
			<p>
				<?php
				echo esc_html__(
					'پیش‌نمایش هیچ داده‌ای ذخیره نمی‌کند. ورود فقط ردیف‌های معتبر را به‌صورت پیش‌نویس ذخیره می‌کند و هیچ نوشته‌ای را منتشر یا تأیید نمی‌کند.',
					'tazkar'
				);
				?>
			</p>

			<?php if ( null !== $error ) : ?>
				<div class="notice notice-error"><p>
					<strong><?php echo esc_html( $error->issue_code() ); ?></strong>
					— <?php echo esc_html( $error->getMessage() ); ?>
				</p></div>
			<?php endif; ?>

			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( self::PREVIEW_NONCE_ACTION, self::PREVIEW_NONCE_NAME ); ?>
				<?php wp_nonce_field( self::IMPORT_NONCE_ACTION, self::IMPORT_NONCE_NAME ); ?>
				<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo esc_attr( (string) CsvPreviewOptions::DEFAULT_MAX_BYTES ); ?>">
				<table class="form-table" role="presentation"><tr>
					<th scope="row">
						<label for="tazkar-csv-file"><?php echo esc_html__( 'فایل CSV', 'tazkar' ); ?></label>
					</th>
					<td>
						<input id="tazkar-csv-file" name="<?php echo esc_attr( self::FILE_FIELD ); ?>" type="file" accept=".csv,text/csv" required>
						<p class="description"><?php echo esc_html__( 'حداکثر ۱۰ مگابایت، ۵۰۰ ردیف و ۶۰ ستون.', 'tazkar' ); ?></p>
					</td>
				</tr></table>
				<p class="submit">
					<button type="submit" class="button button-primary" name="<?php echo esc_attr( self::ACTION_FIELD ); ?>" value="preview">
						<?php echo esc_html__( 'ساخت پیش‌نمایش', 'tazkar' ); ?>
					</button>
					<button type="submit" class="button" name="<?php echo esc_attr( self::ACTION_FIELD ); ?>" value="import">
						<?php echo esc_html__( 'ورود به‌صورت پیش‌نویس', 'tazkar' ); ?>
					</button>
				</p>
			</form>

			<?php if ( null !== $preview_result ) : ?>
				<?php $this->render_preview_result( $preview_result ); ?>
			<?php endif; ?>
			<?php if ( null !== $import_result ) : ?>
				<?php $this->render_import_result( $import_result ); ?>
			<?php endif; ?>
		</div>
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
		<h3><?php echo esc_html__( 'گزارش ردیف‌ها', 'tazkar' ); ?></h3>
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

	private function render_import_result( CsvImportResult $result ): void {
		?>
		<hr>
		<h2><?php echo esc_html__( 'نتیجه ورود', 'tazkar' ); ?></h2>
		<?php if ( $result->repeated_source() ) : ?>
			<div class="notice notice-warning inline"><p>
				<?php echo esc_html__( 'فایلی با محتوای یکسان قبلاً وارد شده است؛ ردیف‌های تکراری دوباره ساخته نشدند.', 'tazkar' ); ?>
			</p></div>
		<?php endif; ?>
		<table class="widefat striped" style="max-width:1000px"><tbody>
			<tr><th><?php echo esc_html__( 'شناسه بسته', 'tazkar' ); ?></th><td><code><?php echo esc_html( $result->batch_uuid() ); ?></code></td></tr>
			<tr><th><?php echo esc_html__( 'وضعیت بسته', 'tazkar' ); ?></th><td><?php echo esc_html( $result->status() ); ?></td></tr>
			<tr><th><?php echo esc_html__( 'کل ردیف‌ها', 'tazkar' ); ?></th><td><?php echo esc_html( (string) $result->total_rows() ); ?></td></tr>
			<tr><th><?php echo esc_html__( 'واردشده', 'tazkar' ); ?></th><td><?php echo esc_html( (string) $result->imported_rows() ); ?></td></tr>
			<tr><th><?php echo esc_html__( 'ردشده یا تکراری', 'tazkar' ); ?></th><td><?php echo esc_html( (string) $result->skipped_rows() ); ?></td></tr>
			<tr><th><?php echo esc_html__( 'نامعتبر', 'tazkar' ); ?></th><td><?php echo esc_html( (string) $result->invalid_rows() ); ?></td></tr>
			<tr><th><?php echo esc_html__( 'خطای عملیاتی', 'tazkar' ); ?></th><td><?php echo esc_html( (string) $result->error_rows() ); ?></td></tr>
		</tbody></table>
		<h3><?php echo esc_html__( 'گزارش ردیف‌های ورود', 'tazkar' ); ?></h3>
		<table class="widefat striped"><thead><tr>
			<th><?php echo esc_html__( 'ردیف', 'tazkar' ); ?></th>
			<th><?php echo esc_html__( 'شناسه', 'tazkar' ); ?></th>
			<th><?php echo esc_html__( 'وضعیت', 'tazkar' ); ?></th>
			<th><?php echo esc_html__( 'کد', 'tazkar' ); ?></th>
			<th><?php echo esc_html__( 'نوشته', 'tazkar' ); ?></th>
		</tr></thead><tbody>
		<?php foreach ( $result->rows() as $row ) : ?>
			<?php $this->render_import_row( $row ); ?>
		<?php endforeach; ?>
		</tbody></table>
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

	private function render_import_row( CsvImportRowResult $row ): void {
		?>
		<tr>
			<td><?php echo esc_html( (string) $row->row_number() ); ?></td>
			<td><code><?php echo esc_html( $row->record_id() ); ?></code></td>
			<td><?php echo esc_html( $row->status() ); ?></td>
			<td><code><?php echo esc_html( $row->issue_code() ); ?></code></td>
			<td><?php echo esc_html( $row->post_id() > 0 ? (string) $row->post_id() : '—' ); ?></td>
		</tr>
		<?php
	}
}
