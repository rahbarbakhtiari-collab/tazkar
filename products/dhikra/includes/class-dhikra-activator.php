<?php
/**
 * Activation and deactivation routines.
 *
 * @package Dhikra
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Dhikra_Activator {
	public static function activate(): void {
		$defaults = Dhikra_Helpers::defaults();
		$options  = array_intersect_key( wp_parse_args( get_option( 'dhikra_options', array() ), $defaults ), $defaults );
		update_option( 'dhikra_options', $options, false );
		Dhikra_Data::install_schema();
		Dhikra_Rewards::install_capabilities();
		// Record the schema version only after columns and legacy award links are verified.
		if ( Dhikra_Coupons::storage_ready( false ) && Dhikra_Analytics::storage_ready() && Dhikra_Coupons::backfill_legacy_assignments() ) {
			update_option( 'dhikra_db_version', DHIKRA_DB_VERSION, false );
		}

		$content = new Dhikra_Content();
		$content->register_post_type();
		$content->register_taxonomy();
		self::insert_topics();
		self::insert_samples();
		( new Dhikra_Router() )->rewrite_rules();
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'dhikra_daily_telangor_dispatch' );
		wp_clear_scheduled_hook( 'dhikra_broadcast_dispatch' );
		flush_rewrite_rules();
	}

	public static function insert_topics(): void {
		foreach ( Dhikra_Themes::default_topics() as $topic ) {
			$existing = term_exists( $topic['slug'], 'dhikra_topic' );
			if ( is_array( $existing ) ) {
				$term_id = absint( $existing['term_id'] );
			} elseif ( is_int( $existing ) ) {
				$term_id = $existing;
			} else {
				$created = wp_insert_term(
					$topic['name'],
					'dhikra_topic',
					array( 'slug' => $topic['slug'] )
				);
				if ( is_wp_error( $created ) ) {
					continue;
				}
				$term_id = absint( $created['term_id'] );
			}
			if ( '' === (string) get_term_meta( $term_id, '_dhikra_topic_active', true ) ) {
				update_term_meta( $term_id, '_dhikra_topic_active', '1' );
			}
		}
	}

	private static function insert_samples(): void {
		if ( get_option( 'dhikra_samples_created_110', false ) ) {
			return;
		}

		$samples = array(
			array(
				'id'          => 'sample-quran-51-55-fa',
				'type'        => 'quran',
				'title'       => 'یادآوری سودمند است',
				'original'    => 'وَذَكِّرْ فَإِنَّ الذِّكْرَىٰ تَنفَعُ الْمُؤْمِنِينَ',
				'source'      => 'سورهٔ ذاریات، آیهٔ ۵۵',
				'translation' => 'یادآوری کن؛ زیرا یادآوری مؤمنان را سود می‌بخشد.',
				'reflection'  => 'گاهی یک جملهٔ کوتاه، دل را به حقیقتی که می‌دانست بازمی‌گرداند.',
				'main_topic'  => 'remembrance',
				'topics'      => array( 'remembrance', 'hope' ),
			),
			array(
				'id'          => 'sample-quran-94-5-fa',
				'type'        => 'quran',
				'title'       => 'گشایش در دل سختی است',
				'original'    => 'فَإِنَّ مَعَ الْعُسْرِ يُسْرًا',
				'source'      => 'سورهٔ شرح، آیهٔ ۵',
				'translation' => 'پس به‌راستی همراه دشواری، آسانی است.',
				'reflection'  => 'سختی همهٔ افق نیست؛ در همان مسیر، راه گشایش نیز شکل می‌گیرد.',
				'main_topic'  => 'hope',
				'topics'      => array( 'hope', 'patience' ),
			),
			array(
				'id'          => 'sample-quran-13-28-fa',
				'type'        => 'quran',
				'title'       => 'آرامش دل نزدیک است',
				'original'    => 'أَلَا بِذِكْرِ اللَّهِ تَطْمَئِنُّ الْقُلُوبُ',
				'source'      => 'سورهٔ رعد، آیهٔ ۲۸',
				'translation' => 'آگاه باشید که دل‌ها با یاد خدا آرام می‌گیرند.',
				'reflection'  => 'بازگشت آگاهانه به یاد خدا می‌تواند نقطهٔ شروع آرامش باشد.',
				'main_topic'  => 'serenity',
				'topics'      => array( 'serenity', 'remembrance' ),
			),
			array(
				'id'          => 'sample-hadith-intention-fa',
				'type'        => 'hadith',
				'title'       => 'ارزش عمل به نیت است',
				'original'    => 'إِنَّمَا الْأَعْمَالُ بِالنِّيَّاتِ',
				'speaker'     => 'پیامبر اکرم ﷺ',
				'source'      => 'صحیح بخاری، کتاب بدء الوحی، حدیث ۱',
				'translation' => 'همانا ارزش اعمال به نیت‌هاست.',
				'reflection'  => 'پیش از آغاز، جهت دل را روشن کنیم.',
				'main_topic'  => 'ethics',
				'topics'      => array( 'ethics', 'remembrance' ),
			),
			array(
				'id'          => 'sample-prayer-knowledge-fa',
				'type'        => 'prayer',
				'title'       => 'درخواست دانشی سودمند',
				'original'    => 'رَبِّ زِدْنِي عِلْمًا',
				'speaker'     => 'دعای قرآنی',
				'source'      => 'سورهٔ طه، آیهٔ ۱۱۴',
				'translation' => 'پروردگارا، بر دانشم بیفزا.',
				'reflection'  => '',
				'main_topic'  => 'prayer',
				'topics'      => array( 'prayer', 'hope' ),
			),
		);

		$all_ready = true;
		foreach ( $samples as $sample ) {
			$existing = get_posts(
				array(
					'post_type'      => 'dhikra_telangor',
					'post_status'    => 'any',
					'fields'         => 'ids',
					'posts_per_page' => 1,
					'meta_key'       => '_dhikra_content_id',
					'meta_value'     => $sample['id'],
				)
			);

			$is_new  = empty( $existing );
			$post_id = $is_new ? 0 : absint( $existing[0] );
			if ( $is_new ) {
				$post_id = wp_insert_post(
					array(
						'post_type'   => 'dhikra_telangor',
						'post_status' => 'publish',
						'post_title'  => $sample['title'],
					),
					true
				);
				if ( is_wp_error( $post_id ) ) {
					$all_ready = false;
					continue;
				}
				$post_id = absint( $post_id );

				update_post_meta( $post_id, '_dhikra_content_id', $sample['id'] );
				update_post_meta( $post_id, '_dhikra_translation_group', str_replace( '-fa', '', $sample['id'] ) );
				update_post_meta( $post_id, '_dhikra_language', 'fa_IR' );
				update_post_meta( $post_id, '_dhikra_type', $sample['type'] );
				update_post_meta( $post_id, '_dhikra_original_text', $sample['original'] );
				update_post_meta( $post_id, '_dhikra_translation', $sample['translation'] );
				update_post_meta( $post_id, '_dhikra_reflection', $sample['reflection'] );
				update_post_meta( $post_id, '_dhikra_speaker', $sample['speaker'] ?? '' );
				update_post_meta( $post_id, '_dhikra_source', $sample['source'] );
				update_post_meta( $post_id, '_dhikra_is_sample', 1 );

				$term_ids = array();
				foreach ( $sample['topics'] as $slug ) {
					$term = get_term_by( 'slug', $slug, 'dhikra_topic' );
					if ( $term instanceof WP_Term ) {
						$term_ids[] = $term->term_id;
					}
				}
				$main        = get_term_by( 'slug', $sample['main_topic'], 'dhikra_topic' );
				$term_result = wp_set_object_terms( $post_id, $term_ids, 'dhikra_topic' );
				if ( is_wp_error( $term_result ) || ! $main instanceof WP_Term || empty( $term_ids ) ) {
					$all_ready = false;
					wp_delete_post( $post_id, true );
					continue;
				}
				update_post_meta( $post_id, '_dhikra_main_topic_id', $main->term_id );
			}

			$code = Dhikra_Data::get_short_code( $post_id );
			if ( '' === $code ) {
				$code = Dhikra_Data::assign_short_code( $post_id );
			}
			if ( is_wp_error( $code ) ) {
				$all_ready = false;
				if ( $is_new ) {
					wp_delete_post( $post_id, true );
				}
				continue;
			}
		}

		if ( $all_ready ) {
			update_option( 'dhikra_samples_created_110', 1, false );
		} else {
			delete_option( 'dhikra_samples_created_110' );
		}
	}
}
