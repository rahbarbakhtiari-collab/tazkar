<?php

namespace Tazkar\Validation;

final class ContractSchema {
	public const VERSION = '1.1';

	/**
	 * @return string[]
	 */
	public static function columns(): array {
		return array(
			'schema_version',
			'record_id',
			'batch_id',
			'prompt_version',
			'source_file',
			'book_title',
			'source_location',
			'content_type',
			'excerpt_scope',
			'translation_language',
			'speaker',
			'arabic_text',
			'translation_original',
			'translation_corrected',
			'source_original',
			'source_corrected',
			'surah_name',
			'surah_number',
			'ayah_start',
			'ayah_end',
			'generated_title',
			'motivational_text',
			'primary_topic',
			'secondary_topics',
			'tags',
			'suggested_slug',
			'extraction_confidence',
			'source_confidence',
			'meaning_confidence',
			'context_confidence',
			'critical_issue',
			'review_status',
			'reviewer_notes',
			'import_status',
			'wp_post_id',
		);
	}

	/**
	 * Required file headers. Conditional fields remain required as columns even when their row value is empty.
	 *
	 * @return string[]
	 */
	public static function requiredHeaders(): array {
		return array(
			'schema_version',
			'record_id',
			'batch_id',
			'prompt_version',
			'source_file',
			'source_location',
			'content_type',
			'excerpt_scope',
			'translation_language',
			'speaker',
			'arabic_text',
			'translation_original',
			'translation_corrected',
			'source_original',
			'source_corrected',
			'surah_name',
			'surah_number',
			'ayah_start',
			'generated_title',
			'motivational_text',
			'primary_topic',
			'tags',
			'extraction_confidence',
			'source_confidence',
			'meaning_confidence',
			'context_confidence',
			'critical_issue',
			'review_status',
			'import_status',
		);
	}

	/**
	 * @return string[]
	 */
	public static function requiredValues(): array {
		return array(
			'schema_version',
			'record_id',
			'batch_id',
			'prompt_version',
			'source_file',
			'source_location',
			'content_type',
			'excerpt_scope',
			'translation_language',
			'arabic_text',
			'translation_original',
			'translation_corrected',
			'source_original',
			'source_corrected',
			'generated_title',
			'motivational_text',
			'primary_topic',
			'tags',
			'extraction_confidence',
			'source_confidence',
			'meaning_confidence',
			'context_confidence',
			'critical_issue',
			'review_status',
			'import_status',
		);
	}

	/**
	 * @return array<string, string[]>
	 */
	public static function enums(): array {
		return array(
			'content_type'          => array( 'quran', 'hadith' ),
			'excerpt_scope'         => array( 'full', 'partial' ),
			'extraction_confidence' => array( 'high', 'medium', 'low' ),
			'source_confidence'     => array( 'high', 'medium', 'low' ),
			'meaning_confidence'    => array( 'high', 'medium', 'low' ),
			'context_confidence'    => array( 'high', 'medium', 'low' ),
			'critical_issue'        => array( 'yes', 'no' ),
			'review_status'         => array( 'extracted', 'needs_review', 'approved', 'rejected', 'needs_recheck' ),
			'import_status'         => array( 'not_imported', 'imported', 'skipped', 'error' ),
		);
	}

	/**
	 * @return array<string, int>
	 */
	public static function maximumLengths(): array {
		return array(
			'record_id'        => 64,
			'batch_id'         => 64,
			'book_title'       => 200,
			'generated_title'  => 80,
			'motivational_text' => 280,
			'suggested_slug'   => 100,
			'reviewer_notes'   => 1000,
		);
	}

	/**
	 * @return string[]
	 */
	public static function integerFields(): array {
		return array( 'surah_number', 'ayah_start', 'ayah_end', 'wp_post_id' );
	}

	private function __construct() {}
}
