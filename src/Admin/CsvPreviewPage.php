<?php

namespace Tazkar\Admin;

use Tazkar\Import\CsvPreviewOptions;
use Tazkar\Import\CsvPreviewResult;
use Tazkar\Import\CsvPreviewService;
use Tazkar\Import\CsvReadException;
use Tazkar\Import\UploadedCsvValidator;
use Tazkar\Validation\ValidationIssue;
use Tazkar\Validation\ValidationResult;

if ( ! defined( 'ABSPATH' ) && ! defined( 'TAZKAR_TESTING' ) ) {
	exit;
}

final class CsvPreviewPage {
	private const NONCE_ACTION = 'tazkar_csv_preview';
	private const NONCE_NAME   = 'tazkar_csv_preview_nonce';
	private const FILE_FIELD   = 'tazkar_csv_file';

	public function __construct(
		private readonly CsvPreviewService $preview_service = new CsvPreviewService(),
		private readonly UploadedCsvValidator $upload_validator = new UploadedCsvValidator()
	) {}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_submenu' ) );
	}

	public function add_submenu(): void {
		add_submenu_page(
			'tazkar',
			__( 'پیش‌نمایش CSV', 'tazkar' ),
			__( 'پیش‌نمایش CSV', 'tazkar' ),
			'import_tazkar_items',
			'tazkar-csv-preview',
			array( $this, 'render' )
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'import_tazkar_items' ) ) {
			wp_die( esc_html__( 'شما اجازه پیش‌نمایش فایل را ندارید.', 'tazkar' ) );
		}

		$result = null;
		$error  = null;

		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );
			try {
				$result = $this->process_request( $_FILES[ self::FILE_FIELD ] ?? array() );
			} catch ( CsvReadException $exception ) {
				$error = $exception;
			}
		}

		$this->render_page( $result, $error );
	}

	/**
	 * @param array<string, mixed> $file Raw upload metadata.
	 */
	private function process_request( array $file ): CsvPreviewResult {
		$path = is_string( $file['tmp_name'] ?? null ) ? $file['tmp_name'] : '';
		$mime = $this->detect_mime( $path );
		$upload = $this->upload_validator->validate(
			$file,
			'' !== $path && is_uploaded_file( $path ),
			$mime
		);

		return $this->preview_service->preview( $upload->path(), $upload->name() );
	}

	private function detect_mime( string $path ): string {
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

	private function render_page( ?CsvPreviewResult $result, ?CsvReadException $error ): void {
		?>
		<div class="wrap tazkar-csv-preview-page">
			<h1><?php echo esc_html__( 'پیش‌نمایش فایل CSV', 'tazkar' ); ?></h1>
			<p><?php echo esc_html__( 'این مرحله فقط فایل را می‌خواند و اعتبارسنجی می‌کند؛ هیچ نوشته یا بسته‌ای ذخیره نمی‌شود.', 'tazkar' ); ?></p>

			<?php if ( null !== $error ) : ?>
				<div class="notice notice-error"><p>
					<strong><?php echo esc_html( $error->issue_code() ); ?></strong>
					— <?php echo esc_html( $error->getMessage() ); ?>
				</p></div>
			<?php endif; ?>

			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
				<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo esc_attr( (string) CsvPreviewOptions::DEFAULT_MAX_BYTES ); ?>">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="tazkar-csv-file"><?php echo esc_html__( 'فایل CSV', 'tazkar' ); ?></label></th>
						<td>
							<input id="tazkar-csv-file" name="<?php echo esc_attr( self::FILE_FIELD ); ?>" type="file" accept=".csv,text/csv" required>
							<p class="description"><?php echo esc_html__( 'حداکثر ۱۰ مگابایت، ۵۰۰ ردیف و ۶۰ ستون.', 'tazkar' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'ساخت پیش‌نمایش', 'tazkar' ) ); ?>
			</form>

			<?php if ( null !== $result ) : ?>
				<?php $this->render_result( $result ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_result( CsvPreviewResult $result ): void {
		?>
		<hr>
		<h2><?php echo esc_html__( 'نتیجه پیش‌نمایش', 'tazkar' ); ?></h2>
		<table class="widefat striped" style="max-width:1000px">
			<tbody>
				<tr><th><?php echo esc_html__( 'فایل', 'tazkar' ); ?></th><td><?php echo esc_html( $result->filename() ); ?></td></tr>
				<tr><th><?php echo esc_html__( 'کل ردیف‌ها', 'tazkar' ); ?></th><td><?php echo esc_html( (string) $result->total_rows() ); ?></td></tr>
				<tr><th><?php echo esc_html__( 'ردیف معتبر', 'tazkar' ); ?></th><td><?php echo esc_html( (string) $result->valid_rows() ); ?></td></tr>
				<tr><th><?php echo esc_html__( 'ردیف نامعتبر', 'tazkar' ); ?></th><td><?php echo esc_html( (string) $result->invalid_rows() ); ?></td></tr>
				<tr><th><?php echo esc_html__( 'ردیف دارای هشدار', 'tazkar' ); ?></th><td><?php echo esc_html( (string) $result->warning_rows() ); ?></td></tr>
			</tbody>
		</table>

		<?php $this->render_issue_list( __( 'مسائل سرستون‌ها', 'tazkar' ), $result->validation()->headers()->issues() ); ?>

		<h3><?php echo esc_html__( 'گزارش ردیف‌ها', 'tazkar' ); ?></h3>
		<table class="widefat striped">
			<thead><tr>
				<th><?php echo esc_html__( 'ردیف', 'tazkar' ); ?></th>
				<th><?php echo esc_html__( 'شناسه', 'tazkar' ); ?></th>
				<th><?php echo esc_html__( 'عنوان', 'tazkar' ); ?></th>
				<th><?php echo esc_html__( 'وضعیت', 'tazkar' ); ?></th>
				<th><?php echo esc_html__( 'کدها', 'tazkar' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $result->validation()->rows() as $row_result ) : ?>
				<?php $this->render_row( $row_result ); ?>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * @param ValidationIssue[] $issues Validation issues.
	 */
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

	private function render_row( ValidationResult $row_result ): void {
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
