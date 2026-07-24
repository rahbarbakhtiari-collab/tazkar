<?php

namespace Tazkar\Validation;

final class IssueCode {
	public const HEADER_MISSING_REQUIRED      = 'header.missing_required';
	public const HEADER_UNKNOWN               = 'header.unknown';
	public const HEADER_DUPLICATE             = 'header.duplicate';
	public const FIELD_REQUIRED               = 'field.required';
	public const FIELD_INVALID_TYPE           = 'field.invalid_type';
	public const FIELD_MAX_LENGTH             = 'field.max_length';
	public const FIELD_INVALID_ENUM           = 'field.invalid_enum';
	public const SCHEMA_UNSUPPORTED           = 'schema.unsupported';
	public const TRANSLATION_LANGUAGE_INVALID = 'translation_language.invalid';
	public const QURAN_SPEAKER_NOT_EMPTY      = 'quran.speaker_not_empty';
	public const QURAN_SURAH_NAME_REQUIRED    = 'quran.surah_name_required';
	public const QURAN_SURAH_NUMBER_REQUIRED  = 'quran.surah_number_required';
	public const QURAN_SURAH_NUMBER_RANGE     = 'quran.surah_number_range';
	public const QURAN_AYAH_START_REQUIRED    = 'quran.ayah_start_required';
	public const QURAN_AYAH_START_POSITIVE    = 'quran.ayah_start_positive';
	public const QURAN_AYAH_END_BEFORE_START  = 'quran.ayah_end_before_start';
	public const HADITH_SPEAKER_REQUIRED      = 'hadith.speaker_required';
	public const HADITH_SURAH_DATA_NOT_EMPTY  = 'hadith.surah_data_not_empty';
	public const TITLE_HTML                   = 'title.html';
	public const TITLE_NEWLINE                = 'title.newline';
	public const TITLE_SYMBOLS                = 'title.emoji_or_hashtag';
	public const REFLECTION_HTML              = 'reflection.html';
	public const REFLECTION_NEWLINE           = 'reflection.newline';
	public const REFLECTION_SYMBOLS           = 'reflection.emoji_or_hashtag';
	public const REFLECTION_SENTENCE_COUNT    = 'reflection.too_many_sentences';
	public const TAGS_COUNT                   = 'tags.count';
	public const SECONDARY_TOPICS_COUNT       = 'secondary_topics.count';
	public const SLUG_INVALID                 = 'slug.invalid';
	public const SOURCE_NOT_FROM_ORIGINAL     = 'source_corrected.not_in_original';
	public const WP_POST_ID_NOT_EMPTY         = 'wp_post_id.not_empty';
	public const DUPLICATE_RECORD_ID          = 'record_id.duplicate';
	public const TITLE_LENGTH_WARNING         = 'title.length_warning';
	public const REFLECTION_LENGTH_WARNING    = 'reflection.length_warning';
	public const SOURCE_CONFIDENCE_MEDIUM     = 'source_confidence.medium';
	public const PARTIAL_EXCERPT              = 'excerpt.partial';
	public const SLUG_EMPTY                   = 'slug.empty';
	public const CRITICAL_ISSUE               = 'critical_issue.review_required';

	private function __construct() {}
}
