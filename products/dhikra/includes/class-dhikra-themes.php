<?php
/**
 * Topic defaults and activation state.
 *
 * Visual themes were intentionally removed in 1.2.0. Topics now affect only
 * classification and random-selection preferences; every card uses one shared
 * visual component.
 *
 * @package Dhikra
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Dhikra_Themes {
	public static function default_topics(): array {
		return array(
			array( 'name' => 'یادآوری و ایمان', 'slug' => 'remembrance' ),
			array( 'name' => 'رحمت و امید', 'slug' => 'hope' ),
			array( 'name' => 'بهشت و نعمت', 'slug' => 'paradise' ),
			array( 'name' => 'آرامش و توکل', 'slug' => 'serenity' ),
			array( 'name' => 'دعا و مناجات', 'slug' => 'prayer' ),
			array( 'name' => 'توبه و بازگشت', 'slug' => 'repentance' ),
			array( 'name' => 'هشدار و بیدارباش', 'slug' => 'warning' ),
			array( 'name' => 'قیامت و حساب', 'slug' => 'judgement' ),
			array( 'name' => 'عذاب و جهنم', 'slug' => 'hell' ),
			array( 'name' => 'صبر و استقامت', 'slug' => 'patience' ),
			array( 'name' => 'شکر و نعمت', 'slug' => 'gratitude' ),
			array( 'name' => 'اخلاق و رفتار', 'slug' => 'ethics' ),
			array( 'name' => 'خانواده و مهربانی', 'slug' => 'family' ),
			array( 'name' => 'رزق و برکت', 'slug' => 'provision' ),
			array( 'name' => 'مرگ و عبرت', 'slug' => 'mortality' ),
		);
	}

	public static function is_topic_active( int $term_id ): bool {
		$value = get_term_meta( $term_id, '_dhikra_topic_active', true );
		return '' === $value || '1' === (string) $value;
	}
}
