<?php
/**
 * Plugin settings.
 *
 * @package Dhikra
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Dhikra_Settings {
	private const PAGE = 'dhikra-settings';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
	}

	public function admin_menu(): void {
		add_submenu_page(
			'edit.php?post_type=dhikra_telangor',
			__( 'تنظیمات ذِكْرَىٰ', 'dhikra' ),
			__( 'تنظیمات', 'dhikra' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	public function register_settings(): void {
		register_setting(
			'dhikra_settings_group',
			'dhikra_options',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => Dhikra_Helpers::defaults(),
			)
		);
	}

	public function admin_assets( string $hook ): void {
		if ( false === strpos( $hook, 'dhikra' ) && ! in_array( $hook, array( 'post.php', 'post-new.php', 'edit-tags.php', 'term.php' ), true ) ) {
			return;
		}
		wp_enqueue_style( 'dhikra-admin', DHIKRA_URL . 'assets/css/admin.css', array(), DHIKRA_VERSION );
		Dhikra_Helpers::add_font_faces( 'dhikra-admin' );
		wp_enqueue_script( 'dhikra-admin', DHIKRA_URL . 'assets/js/admin.js', array(), DHIKRA_VERSION, true );
		wp_localize_script(
			'dhikra-admin',
			'DhikraAdmin',
			array(
				'storyText' => __( 'متن داستان', 'dhikra' ),
				'mainText'  => __( 'متن اصلی', 'dhikra' ),
			)
		);
	}

	public function sanitize_settings( mixed $input ): array {
		$defaults = Dhikra_Helpers::defaults();
		$old      = Dhikra_Helpers::settings();
		$input    = is_array( $input ) ? $input : array();
		$output   = $defaults;

		$slug = sanitize_title( (string) ( $input['base_slug'] ?? $defaults['base_slug'] ) );
		if ( '' === $slug || in_array( $slug, Dhikra_Helpers::RESERVED_ROUTES, true ) || 'z' === $slug ) {
			$slug = $defaults['base_slug'];
			add_settings_error( 'dhikra_options', 'invalid_slug', __( 'زیرمسیر نامعتبر بود و به مقدار پیش‌فرض بازگشت.', 'dhikra' ), 'error' );
		}
		$output['base_slug']     = $slug;
		$output['root_url_mode'] = empty( $input['root_url_mode'] ) ? 0 : 1;
		$output['button_label']  = sanitize_text_field( (string) ( $input['button_label'] ?? $defaults['button_label'] ) );
		if ( '' === $output['button_label'] ) {
			$output['button_label'] = $defaults['button_label'];
		}
		$card_style = sanitize_key( (string) ( $input['card_style'] ?? $defaults['card_style'] ) );
		$output['card_style'] = in_array( $card_style, array( 'minimal', 'royal', 'paper', 'dark' ), true ) ? $card_style : $defaults['card_style'];
		$output['delete_data_on_uninstall'] = empty( $input['delete_data_on_uninstall'] ) ? 0 : 1;
		$output['api_public_enabled']       = empty( $input['api_public_enabled'] ) ? 0 : 1;
		$output['print_max_cards']          = min( 2000, max( 1, absint( $input['print_max_cards'] ?? $defaults['print_max_cards'] ) ) );
		$submitted_token = trim( sanitize_text_field( (string) ( $input['eitaa_bot_token'] ?? '' ) ) );
		$output['eitaa_bot_token'] = '' !== $submitted_token ? $submitted_token : (string) ( $old['eitaa_bot_token'] ?? '' );
		$output['eitaa_init_max_age'] = min( 604800, max( 300, absint( $input['eitaa_init_max_age'] ?? $defaults['eitaa_init_max_age'] ) ) );

		$daily_send_time = trim( (string) ( $input['daily_send_time'] ?? $defaults['daily_send_time'] ) );
		if ( ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $daily_send_time ) ) {
			$daily_send_time = (string) $defaults['daily_send_time'];
			add_settings_error( 'dhikra_options', 'invalid_daily_send_time', __( 'ساعت ارسال تلنگر روزانه نامعتبر بود و به مقدار پیش‌فرض بازگشت.', 'dhikra' ), 'warning' );
		}
		$output['daily_send_time'] = $daily_send_time;

		foreach ( array( 'display_title', 'display_original', 'display_translation', 'display_reflection', 'display_speaker', 'display_source' ) as $key ) {
			$output[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}

		$allowed_placeholders = Dhikra_Helpers::valid_share_placeholders();
		foreach ( array( 'general', 'eitaa', 'bale', 'telegram' ) as $platform ) {
			$key      = 'share_template_' . $platform;
			$template = isset( $input[ $key ] ) ? sanitize_textarea_field( (string) $input[ $key ] ) : '';
			preg_match_all( '/\[[a-z_]+\]/', $template, $matches );
			$unknown = array_diff( array_unique( $matches[0] ?? array() ), $allowed_placeholders );
			if ( ! empty( $unknown ) ) {
				add_settings_error( 'dhikra_options', 'unknown_placeholder_' . $platform, sprintf( __( 'جای‌نگهدارهای ناشناخته در الگوی %1$s حذف شدند: %2$s', 'dhikra' ), $platform, implode( '، ', $unknown ) ), 'warning' );
				$template = str_replace( $unknown, '', $template );
			}
			$output[ $key ] = $template;
		}
		if ( '' === trim( $output['share_template_general'] ) ) {
			$output['share_template_general'] = $defaults['share_template_general'];
		}

		$window = absint( $input['near_repeat_window'] ?? $defaults['near_repeat_window'] );
		$output['near_repeat_window'] = min( 30, max( 1, $window ) );

		if ( $old['base_slug'] !== $output['base_slug'] || (int) $old['root_url_mode'] !== (int) $output['root_url_mode'] ) {
			update_option( 'dhikra_flush_rewrite', 1, false );
		}
		return $output;
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$options       = Dhikra_Helpers::settings();
		$otp_available = Dhikra_Mobile_Auth::provider_available();
		$otp_enabled   = Dhikra_Mobile_Auth::provider_enabled();
		$otp_ready     = Dhikra_Mobile_Auth::provider_ready();
		$preview_data  = array(
			'title'           => 'یک یادآوری روشن',
			'original'        => 'فَإِنَّ مَعَ الْعُسْرِ يُسْرًا',
			'translation'     => 'همراه دشواری، آسانی است.',
			'reflection'      => 'امید را در دل مسیر حفظ کنیم.',
			'speaker'         => '',
			'source'          => 'سورهٔ شرح، آیهٔ ۵',
			'main_topic_name' => 'رحمت و امید',
			'url'              => home_url( '/z/7K4M2/' ),
		);
		?>
		<div class="wrap dhikra-settings-wrap">
			<h1><?php echo esc_html( Dhikra_Helpers::PRODUCT_NAME . ' — ' . __( 'تنظیمات', 'dhikra' ) ); ?></h1>
			<?php settings_errors( 'dhikra_options' ); ?>
			<form action="options.php" method="post">
				<?php settings_fields( 'dhikra_settings_group' ); ?>
				<div class="dhikra-settings-card">
					<h2><?php esc_html_e( 'نشانی‌ها و رفتار اصلی', 'dhikra' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr><th><label for="dhikra_base_slug"><?php esc_html_e( 'زیرمسیر صفحهٔ اصلی', 'dhikra' ); ?></label></th><td><input class="regular-text" dir="ltr" id="dhikra_base_slug" name="dhikra_options[base_slug]" value="<?php echo esc_attr( $options['base_slug'] ); ?>"><p class="description"><?php echo esc_html( home_url( '/' . $options['base_slug'] . '/' ) ); ?></p></td></tr>
						<tr><th><?php esc_html_e( 'ساختار نشانی‌ها', 'dhikra' ); ?></th><td><label><input type="checkbox" name="dhikra_options[root_url_mode]" value="1" <?php checked( ! empty( $options['root_url_mode'] ) ); ?>> <?php esc_html_e( 'استفاده از ریشهٔ سایت', 'dhikra' ); ?></label><p class="description dhikra-warning-text"><?php esc_html_e( 'فقط برای سایتی که عمدتاً به ذِكْرَىٰ اختصاص دارد. این حالت ممکن است با برگه‌ها و نشانی‌های موجود تداخل ایجاد کند.', 'dhikra' ); ?></p></td></tr>
						<tr><th><label for="dhikra_button_label"><?php esc_html_e( 'عنوان دکمهٔ تلنگر تازه', 'dhikra' ); ?></label></th><td><input class="regular-text" id="dhikra_button_label" name="dhikra_options[button_label]" value="<?php echo esc_attr( $options['button_label'] ); ?>"></td></tr>
						<tr><th><label for="dhikra_repeat_window"><?php esc_html_e( 'پنجرهٔ جلوگیری از تکرار', 'dhikra' ); ?></label></th><td><input type="number" min="1" max="30" id="dhikra_repeat_window" name="dhikra_options[near_repeat_window]" value="<?php echo esc_attr( (string) $options['near_repeat_window'] ); ?>"><p class="description"><?php esc_html_e( 'تلنگرهای اخیر تا این تعداد، در صورت کافی‌بودن محتوا، دوباره انتخاب نمی‌شوند.', 'dhikra' ); ?></p></td></tr>
					</table>
				</div>

				<div class="dhikra-settings-card">
					<h2><?php esc_html_e( 'برنامک ایتا', 'dhikra' ); ?></h2>
					<p><?php esc_html_e( 'نسخهٔ عمومی افزونه با SDK رسمی ایتا سازگار است و در WebView ایتا به‌صورت تمام‌ارتفاع اجرا می‌شود.', 'dhikra' ); ?></p>
					<table class="form-table" role="presentation">
						<tr><th><?php esc_html_e( 'لینک برنامک', 'dhikra' ); ?></th><td><a href="<?php echo esc_url( Dhikra_Helpers::EITAA_APP_URL ); ?>" target="_blank" rel="noopener noreferrer"><code><?php echo esc_html( Dhikra_Helpers::EITAA_APP_URL ); ?></code></a></td></tr>
						<tr><th><?php esc_html_e( 'آدرس مقصد HTTPS', 'dhikra' ); ?></th><td><code><?php echo esc_html( Dhikra_Helpers::app_url( 'home' ) ); ?></code><p class="description"><?php esc_html_e( 'همین آدرس باید بدون ریدایرکت در فیلد «آدرس مقصد» پنل ایتایار ثبت شود.', 'dhikra' ); ?></p></td></tr>
						<tr><th><label for="dhikra_eitaa_token"><?php esc_html_e( 'توکن برنامه ایتا', 'dhikra' ); ?></label></th><td><input type="password" autocomplete="new-password" class="regular-text" dir="ltr" id="dhikra_eitaa_token" name="dhikra_options[eitaa_bot_token]" value="" placeholder="<?php echo ! empty( $options['eitaa_bot_token'] ) ? esc_attr__( 'توکن ذخیره شده است؛ برای تغییر مقدار تازه وارد کنید', 'dhikra' ) : ''; ?>"><p class="description"><?php esc_html_e( 'توکن همان «برنامه» ثبت‌شده در ایتایار است و برای اعتبارسنجی initData و API پیام برنامک استفاده می‌شود؛ توکن فرستنده/کانال را وارد نکنید. مقدار ذخیره‌شده در صفحه نمایش داده نمی‌شود.', 'dhikra' ); ?></p></td></tr>
						<tr><th><label for="dhikra_eitaa_age"><?php esc_html_e( 'حداکثر عمر initData', 'dhikra' ); ?></label></th><td><input type="number" min="300" max="604800" id="dhikra_eitaa_age" name="dhikra_options[eitaa_init_max_age]" value="<?php echo esc_attr( (string) $options['eitaa_init_max_age'] ); ?>"> <span><?php esc_html_e( 'ثانیه', 'dhikra' ); ?></span></td></tr>
						<tr><th><label for="dhikra_daily_send_time"><?php esc_html_e( 'ساعت ارسال تلنگر روزانه', 'dhikra' ); ?></label></th><td><input type="time" id="dhikra_daily_send_time" name="dhikra_options[daily_send_time]" value="<?php echo esc_attr( (string) $options['daily_send_time'] ); ?>" step="300"> <span><?php esc_html_e( 'به وقت ایران', 'dhikra' ); ?></span><p class="description"><?php esc_html_e( 'این ساعت برای همه دریافت‌کنندگان تلنگر روزانه یکسان است. کاربر در برنامک فقط دریافت را فعال یا متوقف می‌کند.', 'dhikra' ); ?></p></td></tr>
					</table>
				</div>

				<div class="dhikra-settings-card">
					<h2><?php esc_html_e( 'عناصر عمومی کارت', 'dhikra' ); ?></h2>
					<p><?php esc_html_e( 'این تنظیم برای همهٔ کارت‌ها یکسان است. فیلد خالی به‌طور خودکار حذف می‌شود.', 'dhikra' ); ?></p>
					<p><label for="dhikra_card_style"><strong><?php esc_html_e( 'سبک عمومی کارت', 'dhikra' ); ?></strong></label><br><select id="dhikra_card_style" name="dhikra_options[card_style]"><option value="minimal" <?php selected( $options['card_style'], 'minimal' ); ?>><?php esc_html_e( 'روشن مینیمال', 'dhikra' ); ?></option><option value="royal" <?php selected( $options['card_style'], 'royal' ); ?>><?php esc_html_e( 'فاخر آبی–طلایی', 'dhikra' ); ?></option><option value="paper" <?php selected( $options['card_style'], 'paper' ); ?>><?php esc_html_e( 'کاغذی کلاسیک', 'dhikra' ); ?></option><option value="dark" <?php selected( $options['card_style'], 'dark' ); ?>><?php esc_html_e( 'تاریک', 'dhikra' ); ?></option></select><span class="description"><?php esc_html_e( 'این انتخاب روی همه کارت‌های برنامک اعمال می‌شود.', 'dhikra' ); ?></span></p>
					<div class="dhikra-check-grid">
					<?php
					$elements = array(
						'display_title'       => __( 'عنوان', 'dhikra' ),
						'display_original'    => __( 'متن اصلی', 'dhikra' ),
						'display_translation' => __( 'ترجمه', 'dhikra' ),
						'display_reflection'  => __( 'جملهٔ تأمل', 'dhikra' ),
						'display_speaker'     => __( 'گوینده', 'dhikra' ),
						'display_source'      => __( 'منبع', 'dhikra' ),
					);
					foreach ( $elements as $key => $label ) : ?>
						<label><input type="checkbox" name="dhikra_options[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $options[ $key ] ) ); ?>> <?php echo esc_html( $label ); ?></label>
					<?php endforeach; ?>
					</div>
				</div>

				<div class="dhikra-settings-card">
					<h2><?php esc_html_e( 'متن اشتراک‌گذاری', 'dhikra' ); ?></h2>
					<p><?php esc_html_e( 'الگوی عمومی همیشه موجود است. الگوی خالی هر پیام‌رسان از الگوی عمومی استفاده می‌کند.', 'dhikra' ); ?></p>
					<p><code><?php echo esc_html( implode( ' ', Dhikra_Helpers::valid_share_placeholders() ) ); ?></code></p>
					<?php foreach ( array( 'general' => __( 'عمومی', 'dhikra' ), 'eitaa' => __( 'ایتا', 'dhikra' ), 'bale' => __( 'بله', 'dhikra' ), 'telegram' => __( 'تلگرام', 'dhikra' ) ) as $platform => $label ) : $key = 'share_template_' . $platform; ?>
						<p><label for="dhikra_share_<?php echo esc_attr( $platform ); ?>"><strong><?php echo esc_html( $label ); ?></strong></label><textarea class="large-text code" rows="5" id="dhikra_share_<?php echo esc_attr( $platform ); ?>" name="dhikra_options[<?php echo esc_attr( $key ); ?>]" data-dhikra-share-template><?php echo esc_textarea( $options[ $key ] ); ?></textarea></p>
					<?php endforeach; ?>
					<h3><?php esc_html_e( 'پیش‌نمایش الگوی عمومی', 'dhikra' ); ?></h3>
					<pre class="dhikra-share-preview" data-dhikra-share-preview data-sample="<?php echo esc_attr( wp_json_encode( $preview_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ?: '{}' ); ?>"><?php echo esc_html( Dhikra_Helpers::render_share_template( $options['share_template_general'], $preview_data ) ); ?></pre>
				</div>

				<div class="dhikra-settings-card">
					<h2><?php esc_html_e( 'چاپ کارت‌ها', 'dhikra' ); ?></h2>
					<table class="form-table" role="presentation"><tr><th><label for="dhikra_print_max_cards"><?php esc_html_e( 'حداکثر کارت در هر خروجی', 'dhikra' ); ?></label></th><td><input type="number" min="1" max="2000" id="dhikra_print_max_cards" name="dhikra_options[print_max_cards]" value="<?php echo esc_attr( (string) $options['print_max_cards'] ); ?>"><p class="description"><?php esc_html_e( 'برای جلوگیری از فشار روی سرور. چاپ هیچ ارتباطی با سیستم جایزه ندارد.', 'dhikra' ); ?></p></td></tr></table>
				</div>

				<div class="dhikra-settings-card">
					<h2><?php esc_html_e( 'حساب و API', 'dhikra' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th><?php esc_html_e( 'ورود و ثبت‌نام', 'dhikra' ); ?></th>
							<td>
								<strong><?php esc_html_e( 'فقط شماره موبایل و رمز یک‌بارمصرف', 'dhikra' ); ?></strong>
								<p class="description"><?php esc_html_e( 'حساب موجود با شماره موبایل شناسایی می‌شود و برای شماره تازه، پس از تأیید OTP حساب وردپرس ساخته می‌شود.', 'dhikra' ); ?></p>
								<?php if ( $otp_available ) : ?>
									<?php $otp_status = $otp_ready ? __( 'ورود پیامکی آماده استفاده است.', 'dhikra' ) : ( $otp_enabled ? __( 'افزونه پیامکی فعال است اما کلید API، فرستنده یا پترن کامل نیست.', 'dhikra' ) : __( 'افزونه پیامکی نصب است اما ورود OTP در تنظیمات آن غیرفعال است.', 'dhikra' ) ); ?>
									<p><span class="dashicons <?php echo $otp_ready ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>" aria-hidden="true"></span> <?php echo esc_html( $otp_status ); ?> <a href="<?php echo esc_url( admin_url( 'options-general.php?page=manahel-otp' ) ); ?>"><?php esc_html_e( 'تنظیمات ورود پیامکی', 'dhikra' ); ?></a></p>
								<?php else : ?>
									<p class="dhikra-warning-text"><?php esc_html_e( 'افزونه «ورود پیامکی مناهل با IPPanel» نصب یا فعال نیست.', 'dhikra' ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
						<tr><th><?php esc_html_e( 'API عمومی', 'dhikra' ); ?></th><td><label><input type="checkbox" name="dhikra_options[api_public_enabled]" value="1" <?php checked( ! empty( $options['api_public_enabled'] ) ); ?>> <?php esc_html_e( 'خواندن تلنگرهای منتشرشده بدون کلید فعال باشد.', 'dhikra' ); ?></label><p class="description"><code><?php echo esc_html( rest_url( 'dhikra/v1/telangors' ) ); ?></code></p></td></tr>
					</table>
				</div>

				<div class="dhikra-settings-card dhikra-danger-zone">
					<h2><?php esc_html_e( 'حذف داده', 'dhikra' ); ?></h2>
					<label><input type="checkbox" name="dhikra_options[delete_data_on_uninstall]" value="1" <?php checked( ! empty( $options['delete_data_on_uninstall'] ) ); ?>> <?php esc_html_e( 'هنگام حذف افزونه، همهٔ تنظیمات و داده‌های اختصاصی ذِكْرَىٰ حذف شوند.', 'dhikra' ); ?></label>
					<p class="description dhikra-warning-text"><?php esc_html_e( 'پیش‌فرض خاموش است. غیرفعال‌سازی هیچ داده‌ای را حذف نمی‌کند.', 'dhikra' ); ?></p>
				</div>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
