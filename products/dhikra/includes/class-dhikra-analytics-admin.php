<?php
/** WordPress dashboard for Eitaa mini-app and daily-message analytics. @package Dhikra */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Dhikra_Analytics_Admin {
	private const PAGE = 'dhikra-analytics';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
	}

	public function admin_menu(): void {
		add_submenu_page(
			'edit.php?post_type=dhikra_telangor',
			__( 'آمار برنامک و پیام روزانه', 'dhikra' ),
			__( 'آمار برنامک', 'dhikra' ),
			'manage_dhikra_rewards',
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	private function page_url( int $range ): string {
		return add_query_arg( array( 'post_type' => 'dhikra_telangor', 'page' => self::PAGE, 'range' => $range ), admin_url( 'edit.php' ) );
	}

	private function date_label( string $date, bool $long = false ): string {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) { return '—'; }
		$tz = new DateTimeZone( 'Asia/Tehran' );
		$dt = new DateTimeImmutable( $date . ' 12:00:00', $tz );
		return wp_date( $long ? 'j F Y' : 'j M', $dt->getTimestamp(), $tz );
	}

	private function pct( int $value, int $max ): float {
		return $max > 0 ? max( 1, min( 100, ( $value / $max ) * 100 ) ) : 0;
	}

	private function rate( int $numerator, int $denominator ): float {
		return $denominator > 0 ? 100 * $numerator / $denominator : 0.0;
	}

	private function utc_datetime_label( string $value ): string {
		if ( '' === $value ) { return '—'; }
		try {
			$utc = new DateTimeZone( 'UTC' );
			$tehran = new DateTimeZone( 'Asia/Tehran' );
			$dt = new DateTimeImmutable( $value, $utc );
			return wp_date( 'Y-m-d H:i', $dt->getTimestamp(), $tehran );
		} catch ( Exception $e ) { return $value; }
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_dhikra_rewards' ) ) { return; }

		$range = absint( $_GET['range'] ?? 7 );
		if ( ! in_array( $range, array( 7, 30, 90 ), true ) ) { $range = 7; }

		$summary          = Dhikra_Analytics::summary( $range );
		$daily            = Dhikra_Analytics::daily_series( $range );
		$hourly           = Dhikra_Analytics::hourly_series( $range );
		$recent           = Dhikra_Analytics::recent_visits( 20 );
		$message_summary  = Dhikra_Analytics::daily_message_summary( $range );
		$message_daily    = Dhikra_Analytics::daily_message_series( $range );
		$message_failures = Dhikra_Analytics::recent_daily_failures( 20 );

		$max_visits = 0;
		$max_hour   = 0;
		$max_message = 0;
		foreach ( $daily as $row ) { $max_visits = max( $max_visits, absint( $row['visits'] ) ); }
		foreach ( $hourly as $row ) { $max_hour = max( $max_hour, absint( $row['visits'] ) ); }
		foreach ( $message_daily as $row ) {
			$max_message = max( $max_message, absint( $row['sent'] ), absint( $row['failed'] ), absint( $row['opened_after'] ) );
		}

		$avg = $summary['period_visitors'] > 0 ? $summary['period_visits'] / $summary['period_visitors'] : 0;
		$return_rate = $this->rate( $summary['period_returning'], $summary['period_visitors'] );
		$delta = 0.0;
		if ( $summary['yesterday_visits'] > 0 ) {
			$delta = 100 * ( $summary['today_visits'] - $summary['yesterday_visits'] ) / $summary['yesterday_visits'];
		}
		$completed_messages = $message_summary['sent'] + $message_summary['failed'];
		$delivery_rate      = $this->rate( $message_summary['sent'], $completed_messages );
		$post_open_rate     = $this->rate( $message_summary['opened_after'], $message_summary['sent'] );
		?>
		<div class="wrap dhikra-settings-wrap dhikra-analytics-wrap">
			<div class="dhikra-analytics-heading">
				<div>
					<h1><?php esc_html_e( 'آمار برنامک و پیام روزانه ایتا', 'dhikra' ); ?></h1>
					<p><?php esc_html_e( 'بازدید برنامک و وضعیت ارسال تلنگرهای روزانه در یک داشبورد؛ بدون ثبت IP یا موقعیت مکانی.', 'dhikra' ); ?></p>
				</div>
				<div class="dhikra-range-tabs" aria-label="بازه گزارش">
					<?php foreach ( array( 7 => '۷ روز', 30 => '۳۰ روز', 90 => '۹۰ روز' ) as $days => $label ) : ?>
						<a class="<?php echo $range === $days ? 'is-active' : ''; ?>" href="<?php echo esc_url( $this->page_url( $days ) ); ?>"><?php echo esc_html( $label ); ?></a>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="dhikra-analytics-section-title">
				<div><span class="dashicons dashicons-chart-area"></span><div><h2>بازدید برنامک</h2><p>فقط ورود واقعی به برنامک ایتا؛ بازدید سایت شمرده نمی‌شود.</p></div></div>
			</div>

			<div class="dhikra-analytics-kpis">
				<div class="dhikra-kpi"><span>بازدید امروز</span><strong><?php echo esc_html( number_format_i18n( $summary['today_visits'] ) ); ?></strong><small><?php echo esc_html( number_format_i18n( $summary['today_visitors'] ) ); ?> بازدیدکننده<?php if ( 0.0 !== $delta ) : ?> · <?php echo $delta > 0 ? '↑' : '↓'; ?> <?php echo esc_html( number_format_i18n( abs( $delta ), 1 ) ); ?>٪ نسبت به دیروز<?php endif; ?></small></div>
				<div class="dhikra-kpi"><span>بازدید <?php echo esc_html( $range ); ?> روز</span><strong><?php echo esc_html( number_format_i18n( $summary['period_visits'] ) ); ?></strong><small>میانگین <?php echo esc_html( number_format_i18n( $avg, 1 ) ); ?> بازدید برای هر نفر</small></div>
				<div class="dhikra-kpi"><span>بازدیدکنندگان یکتا</span><strong><?php echo esc_html( number_format_i18n( $summary['period_visitors'] ) ); ?></strong><small><?php echo esc_html( number_format_i18n( $summary['period_new'] ) ); ?> جدید · <?php echo esc_html( number_format_i18n( $summary['period_returning'] ) ); ?> بازگشتی</small></div>
				<div class="dhikra-kpi"><span>نرخ بازگشت</span><strong><?php echo esc_html( number_format_i18n( $return_rate, 1 ) ); ?>٪</strong><small>از بازدیدکنندگان این بازه</small></div>
			</div>

			<section class="dhikra-settings-card dhikra-analytics-chart-card">
				<div class="dhikra-chart-head">
					<div><h2>روند بازدید روزانه</h2><p>بازدید = هر بار بازشدن یک نشست برنامک؛ یکتا = هر مخاطب یک‌بار در روز.</p></div>
					<div class="dhikra-chart-legend"><span><i class="visit"></i>بازدید</span><span><i class="visitor"></i>یکتا</span></div>
				</div>
				<div class="dhikra-daily-chart" style="--cols:<?php echo esc_attr( (string) count( $daily ) ); ?>">
				<?php foreach ( $daily as $row ) : $v=absint($row['visits']);$u=absint($row['visitors']); ?>
					<div class="dhikra-day-col" title="<?php echo esc_attr( $this->date_label( $row['date'], true ) . ' — بازدید: ' . $v . '، یکتا: ' . $u . '، جدید: ' . absint($row['new']) ); ?>">
						<div class="dhikra-day-bars"><i class="visit" style="height:<?php echo esc_attr( (string) $this->pct($v,$max_visits) ); ?>%"></i><i class="visitor" style="height:<?php echo esc_attr( (string) $this->pct($u,$max_visits) ); ?>%"></i></div>
						<b><?php echo esc_html( number_format_i18n( $v ) ); ?></b><span><?php echo esc_html( $this->date_label( $row['date'] ) ); ?></span>
					</div>
				<?php endforeach; ?>
				</div>
			</section>

			<div class="dhikra-analytics-two-col">
				<section class="dhikra-settings-card">
					<h2>ساعت‌های پرتردد</h2><p class="description">به وقت ایران، در بازه انتخاب‌شده.</p>
					<div class="dhikra-hour-chart">
					<?php foreach ( $hourly as $row ) : $v=absint($row['visits']); ?>
						<div title="<?php echo esc_attr( sprintf('%02d:00 — %d بازدید',absint($row['hour']),$v) ); ?>"><i style="height:<?php echo esc_attr( (string) $this->pct($v,$max_hour) ); ?>%"></i><span><?php echo 0 === absint($row['hour']) % 3 ? esc_html( sprintf('%02d',absint($row['hour'])) ) : ''; ?></span></div>
					<?php endforeach; ?>
					</div>
				</section>
				<section class="dhikra-settings-card">
					<h2>وضعیت مخاطبان فعلی</h2>
					<div class="dhikra-mini-stats">
						<div><span>مخاطبان ثبت‌شده</span><strong><?php echo esc_html(number_format_i18n($summary['audience_total'])); ?></strong></div>
						<div><span>مجوز پیام ایتا</span><strong><?php echo esc_html(number_format_i18n($summary['write_ok'])); ?></strong></div>
						<div><span>تلنگر روزانه روشن</span><strong><?php echo esc_html(number_format_i18n($summary['daily_on'])); ?></strong></div>
						<div><span>کل نشست‌های ثبت‌شده</span><strong><?php echo esc_html(number_format_i18n($summary['all_visits'])); ?></strong></div>
					</div>
					<?php if ( ! empty( $summary['first_date'] ) ) : ?><p class="description">آمار نشست‌ها از <?php echo esc_html($this->date_label($summary['first_date'],true)); ?> در دسترس است.</p><?php endif; ?>
				</section>
			</div>

			<div class="dhikra-analytics-section-title dhikra-message-section-title">
				<div><span class="dashicons dashicons-email-alt"></span><div><h2>تلنگر روزانه ایتا</h2><p>آمار واقعی صف و پاسخ API ارسال پیام؛ ساعت فعلی ارسال: <?php echo esc_html( Dhikra_Audience::daily_send_time() ); ?> به وقت ایران.</p></div></div>
			</div>

			<div class="dhikra-analytics-note">
				<strong>تفاوت مهم:</strong>
				ارسال موفق یعنی API ایتا پیام را با پاسخ موفق پذیرفته است. ایتا برای برنامک‌ها رسید «خوانده‌شدن پیام در چت» در اختیار این افزونه نمی‌گذارد؛ بنابراین خوانده‌شدن مستقیم در ایتا قابل شمارش نیست.
				«بازشدن پس از پیام» فقط نشان می‌دهد همان مخاطب حداکثر تا ۲۴ ساعت بعد برنامک را باز کرده است و به معنی خواندن یا کلیک قطعی روی پیام نیست.
			</div>

			<div class="dhikra-analytics-kpis dhikra-message-kpis">
				<div class="dhikra-kpi is-message"><span>ارسال موفق امروز</span><strong><?php echo esc_html( number_format_i18n( $message_summary['today_sent'] ) ); ?></strong><small>از <?php echo esc_html( number_format_i18n( $message_summary['today_generated'] ) ); ?> پیام ثبت‌شده · <?php echo esc_html(number_format_i18n($message_summary['today_pending'])); ?> در انتظار/تلاش مجدد</small></div>
				<div class="dhikra-kpi is-message"><span>ارسال موفق <?php echo esc_html( $range ); ?> روز</span><strong><?php echo esc_html( number_format_i18n( $message_summary['sent'] ) ); ?></strong><small><?php echo esc_html(number_format_i18n($message_summary['failed'])); ?> ناموفق نهایی · <?php echo esc_html(number_format_i18n($message_summary['retries'])); ?> تلاش مجدد</small></div>
				<div class="dhikra-kpi is-message"><span>نرخ موفقیت نهایی</span><strong><?php echo esc_html( number_format_i18n( $delivery_rate, 1 ) ); ?>٪</strong><small>فقط پیام‌های تعیین‌تکلیف‌شده؛ موارد در انتظار در مخرج نیستند</small></div>
				<div class="dhikra-kpi is-message"><span>بازشدن برنامک پس از پیام</span><strong><?php echo esc_html( number_format_i18n( $message_summary['opened_after'] ) ); ?></strong><small><?php echo esc_html( number_format_i18n( $post_open_rate, 1 ) ); ?>٪ از ارسال‌های موفق، حداکثر تا ۲۴ ساعت بعد</small></div>
			</div>

			<section class="dhikra-settings-card dhikra-analytics-chart-card">
				<div class="dhikra-chart-head">
					<div><h2>روند ارسال پیام روزانه</h2><p>سبز = ارسال موفق، قرمز = ناموفق نهایی، آبی = بازشدن برنامک تا ۲۴ ساعت بعد.</p></div>
					<div class="dhikra-chart-legend dhikra-message-legend"><span><i class="msg-sent"></i>موفق</span><span><i class="msg-failed"></i>ناموفق</span><span><i class="msg-opened"></i>بازشدن</span></div>
				</div>
				<div class="dhikra-daily-chart dhikra-message-chart" style="--cols:<?php echo esc_attr( (string) count( $message_daily ) ); ?>">
				<?php foreach ( $message_daily as $row ) : $sent=absint($row['sent']);$failed=absint($row['failed']);$opened=absint($row['opened_after']); ?>
					<div class="dhikra-day-col" title="<?php echo esc_attr( $this->date_label($row['date'],true) . ' — موفق: ' . $sent . '، ناموفق: ' . $failed . '، در انتظار: ' . absint($row['pending']) . '، بازشدن پس از پیام: ' . $opened ); ?>">
						<div class="dhikra-day-bars">
							<i class="msg-sent" style="height:<?php echo esc_attr( (string) $this->pct($sent,$max_message) ); ?>%"></i>
							<i class="msg-failed" style="height:<?php echo esc_attr( (string) $this->pct($failed,$max_message) ); ?>%"></i>
							<i class="msg-opened" style="height:<?php echo esc_attr( (string) $this->pct($opened,$max_message) ); ?>%"></i>
						</div>
						<b><?php echo esc_html( number_format_i18n( $sent ) ); ?></b><span><?php echo esc_html( $this->date_label( $row['date'] ) ); ?></span>
					</div>
				<?php endforeach; ?>
				</div>
			</section>

			<div class="dhikra-analytics-two-col">
				<section class="dhikra-settings-card">
					<h2>وضعیت تحویل فعلی</h2>
					<div class="dhikra-mini-stats">
						<div><span>روزانه روشن</span><strong><?php echo esc_html(number_format_i18n($message_summary['daily_on'])); ?></strong></div>
						<div><span>قابل ارسال همین حالا</span><strong><?php echo esc_html(number_format_i18n($message_summary['deliverable'])); ?></strong></div>
						<div><span>دارای مجوز پیام</span><strong><?php echo esc_html(number_format_i18n($message_summary['write_ok'])); ?></strong></div>
						<div><span>میانگین تلاش برای ارسال موفق</span><strong><?php echo esc_html(number_format_i18n($message_summary['avg_attempts'],2)); ?></strong></div>
					</div>
					<p class="description">«قابل ارسال» یعنی هم تلنگر روزانه روشن است و هم ایتا مجوز ارسال پیام خصوصی را گزارش کرده است.</p>
				</section>
				<section class="dhikra-settings-card dhikra-read-receipt-card">
					<h2>چه چیزی قابل اندازه‌گیری است؟</h2>
					<div class="dhikra-capability-row is-yes"><span>✓</span><div><strong>ثبت پیام در صف</strong><small>قابل شمارش</small></div></div>
					<div class="dhikra-capability-row is-yes"><span>✓</span><div><strong>پاسخ موفق/ناموفق API ایتا</strong><small>قابل شمارش</small></div></div>
					<div class="dhikra-capability-row is-yes"><span>✓</span><div><strong>بازشدن برنامک بعد از ارسال</strong><small>قابل تخمین تا ۲۴ ساعت</small></div></div>
					<div class="dhikra-capability-row is-no"><span>—</span><div><strong>خوانده‌شدن پیام داخل چت ایتا</strong><small>رسید Read در API فعلی در دسترس افزونه نیست</small></div></div>
				</section>
			</div>

			<section class="dhikra-settings-card">
				<h2>جزئیات روزانه پیام‌ها</h2>
				<div class="dhikra-table-scroll"><table class="widefat striped"><thead><tr><th>تاریخ</th><th>ثبت‌شده</th><th>موفق</th><th>ناموفق نهایی</th><th>در انتظار</th><th>تلاش مجدد</th><th>بازشدن ≤۲۴ساعت</th><th>نرخ بازشدن</th></tr></thead><tbody>
				<?php foreach ( array_reverse( $message_daily ) as $row ) : $open_rate=$this->rate(absint($row['opened_after']),absint($row['sent'])); ?>
					<tr>
						<td><?php echo esc_html($this->date_label($row['date'],true)); ?></td>
						<td><?php echo esc_html(number_format_i18n(absint($row['generated']))); ?></td>
						<td><strong><?php echo esc_html(number_format_i18n(absint($row['sent']))); ?></strong></td>
						<td><?php echo esc_html(number_format_i18n(absint($row['failed']))); ?></td>
						<td><?php echo esc_html(number_format_i18n(absint($row['pending']))); ?></td>
						<td><?php echo esc_html(number_format_i18n(absint($row['retries']))); ?></td>
						<td><?php echo esc_html(number_format_i18n(absint($row['opened_after']))); ?></td>
						<td><?php echo esc_html(number_format_i18n($open_rate,1)); ?>٪</td>
					</tr>
				<?php endforeach; ?>
				</tbody></table></div>
			</section>

			<section class="dhikra-settings-card">
				<h2>خطاهای اخیر پیام روزانه</h2>
				<?php if ( empty( $message_failures ) ) : ?>
					<p>خطای ثبت‌شده‌ای برای پیام روزانه وجود ندارد.</p>
				<?php else : ?>
					<div class="dhikra-table-scroll"><table class="widefat striped"><thead><tr><th>مخاطب</th><th>تاریخ پیام</th><th>تلاش</th><th>وضعیت</th><th>آخرین خطا</th><th>آخرین تغییر</th></tr></thead><tbody>
					<?php foreach ( $message_failures as $row ) : $name=trim((string)$row['first_name'].' '.(string)$row['last_name']);$retrying=absint($row['attempts'])<3 && !empty($row['next_attempt_at']); ?>
						<tr>
							<td><?php echo esc_html($name?:'بدون نام'); ?><br><code><?php echo esc_html((string)$row['eitaa_user_id']); ?></code></td>
							<td><?php echo esc_html($this->date_label((string)$row['daily_send_date'],true)); ?></td>
							<td><?php echo esc_html(number_format_i18n(absint($row['attempts']))); ?></td>
							<td><span class="dhikra-status-pill <?php echo $retrying?'is-retrying':'is-failed'; ?>"><?php echo $retrying?'تلاش مجدد':'ناموفق نهایی'; ?></span></td>
							<td class="dhikra-error-cell"><?php echo esc_html(wp_html_excerpt((string)$row['last_error'],220,'…')); ?></td>
							<td><?php echo esc_html($this->utc_datetime_label((string)$row['updated_at'])); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody></table></div>
				<?php endif; ?>
			</section>

			<section class="dhikra-settings-card">
				<h2>جزئیات روزانه بازدید برنامک</h2>
				<div class="dhikra-table-scroll"><table class="widefat striped"><thead><tr><th>تاریخ</th><th>بازدید</th><th>بازدیدکننده یکتا</th><th>کاربر جدید</th><th>بازگشتی</th></tr></thead><tbody>
				<?php foreach ( array_reverse( $daily ) as $row ) : $ret=max(0,absint($row['visitors'])-absint($row['new'])); ?>
					<tr><td><?php echo esc_html($this->date_label($row['date'],true)); ?></td><td><strong><?php echo esc_html(number_format_i18n(absint($row['visits']))); ?></strong></td><td><?php echo esc_html(number_format_i18n(absint($row['visitors']))); ?></td><td><?php echo esc_html(number_format_i18n(absint($row['new']))); ?></td><td><?php echo esc_html(number_format_i18n($ret)); ?></td></tr>
				<?php endforeach; ?>
				</tbody></table></div>
			</section>

			<section class="dhikra-settings-card">
				<h2>۲۰ ورود اخیر برنامک</h2>
				<?php if ( empty( $recent ) ) : ?><p>هنوز بازدیدی ثبت نشده است.</p><?php else : ?>
				<div class="dhikra-table-scroll"><table class="widefat striped"><thead><tr><th>مخاطب</th><th>شناسه ایتا</th><th>زمان</th><th>کارت آغاز</th></tr></thead><tbody>
				<?php foreach ( $recent as $row ) : $name=trim((string)$row['first_name'].' '.(string)$row['last_name']);$post=absint($row['card_post_id']); ?>
					<tr><td><?php echo esc_html($name?:'بدون نام'); ?></td><td><code><?php echo esc_html((string)$row['eitaa_user_id']); ?></code></td><td><?php echo esc_html($this->utc_datetime_label((string)$row['visited_at'])); ?></td><td><?php echo $post>0?esc_html(get_the_title($post)):'—'; ?></td></tr>
				<?php endforeach; ?>
				</tbody></table></div>
				<?php endif; ?>
			</section>

			<p class="description dhikra-analytics-privacy">این داشبورد برای بازدید، IP، موقعیت مکانی و User-Agent ذخیره نمی‌کند. آمار پیام از رکورد صف و پاسخ API ایتا استخراج می‌شود. خوانده‌شدن پیام در خود چت ایتا قابل تشخیص نیست.</p>
		</div>
		<?php
	}
}
