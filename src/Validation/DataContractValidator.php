<?php

namespace Tazkar\Validation;

final class DataContractValidator {
	/**
	 * Validate one associative row without mutating it.
	 *
	 * @param array<string, mixed> $row        Exact input row.
	 * @param int|null             $row_number One-based source row number, including the header row when applicable.
	 */
	public function validate( array $row, ?int $row_number = null ): ValidationResult {
		$result = new ValidationResult( $row, $row_number );

		$this->validate_required_values( $row, $result );
		$this->validate_types( $row, $result );
		$this->validate_maximum_lengths( $row, $result );
		$this->validate_enums( $row, $result );
		$this->validate_version_and_language( $row, $result );
		$this->validate_content_type_rules( $row, $result );
		$this->validate_title( $row, $result );
		$this->validate_reflection( $row, $result );
		$this->validate_lists( $row, $result );
		$this->validate_slug( $row, $result );
		$this->validate_source_selection( $row, $result );
		$this->validate_system_fields( $row, $result );
		$this->add_warnings( $row, $result );

		return $result;
	}

	/**
	 * @param array<string, mixed> $row Input row.
	 */
	private function validate_required_values( array $row, ValidationResult $result ): void {
		foreach ( ContractSchema::required_values() as $field ) {
			if ( ! array_key_exists( $field, $row ) || $this->is_blank( $row[ $field ] ) ) {
				$result->error( IssueCode::FIELD_REQUIRED, $field, 'Required field is empty.' );
			}
		}
	}

	/**
	 * @param array<string, mixed> $row Input row.
	 */
	private function validate_types( array $row, ValidationResult $result ): void {
		foreach ( ContractSchema::columns() as $field ) {
			if ( ! array_key_exists( $field, $row ) || $this->is_blank( $row[ $field ] ) ) {
				continue;
			}

			if ( in_array( $field, ContractSchema::integer_fields(), true ) ) {
				if ( null === $this->integer_value( $row[ $field ] ) ) {
					$result->error( IssueCode::FIELD_INVALID_TYPE, $field, 'Expected an integer value.' );
				}
				continue;
			}

			if ( ! is_string( $row[ $field ] ) ) {
				$result->error( IssueCode::FIELD_INVALID_TYPE, $field, 'Expected a string value.' );
			}
		}
	}

	/**
	 * @param array<string, mixed> $row Input row.
	 */
	private function validate_maximum_lengths( array $row, ValidationResult $result ): void {
		foreach ( ContractSchema::maximum_lengths() as $field => $maximum ) {
			$value = $row[ $field ] ?? null;
			if ( ! is_string( $value ) ) {
				continue;
			}

			$length = UnicodeText::length( $value );
			if ( $length > $maximum ) {
				$result->error(
					IssueCode::FIELD_MAX_LENGTH,
					$field,
					'Field exceeds the maximum length.',
					array(
						'length'  => $length,
						'maximum' => $maximum,
					)
				);
			}
		}
	}

	/**
	 * @param array<string, mixed> $row Input row.
	 */
	private function validate_enums( array $row, ValidationResult $result ): void {
		foreach ( ContractSchema::enums() as $field => $allowed ) {
			$value = $row[ $field ] ?? null;
			if ( $this->is_blank( $value ) || ! is_string( $value ) ) {
				continue;
			}

			if ( ! in_array( $value, $allowed, true ) ) {
				$result->error(
					IssueCode::FIELD_INVALID_ENUM,
					$field,
					'Value is not in the allowed enum.',
					array( 'value' => $value )
				);
			}
		}
	}

	/**
	 * @param array<string, mixed> $row Input row.
	 */
	private function validate_version_and_language( array $row, ValidationResult $result ): void {
		$version = $row['schema_version'] ?? null;
		if ( is_string( $version ) && ! $this->is_blank( $version ) && ContractSchema::VERSION !== $version ) {
			$result->error(
				IssueCode::SCHEMA_UNSUPPORTED,
				'schema_version',
				'Unsupported data contract version.',
				array( 'supported' => ContractSchema::VERSION )
			);
		}

		$language = $row['translation_language'] ?? null;
		if ( is_string( $language ) && ! $this->is_blank( $language ) && 'fa' !== $language ) {
			$result->error(
				IssueCode::TRANSLATION_LANGUAGE_INVALID,
				'translation_language',
				'MVP accepts Persian translations only.'
			);
		}
	}

	/**
	 * @param array<string, mixed> $row Input row.
	 */
	private function validate_content_type_rules( array $row, ValidationResult $result ): void {
		$content_type = $row['content_type'] ?? null;
		if ( 'quran' === $content_type ) {
			$this->validate_quran_rules( $row, $result );
		} elseif ( 'hadith' === $content_type ) {
			$this->validate_hadith_rules( $row, $result );
		}
	}

	/**
	 * @param array<string, mixed> $row Input row.
	 */
	private function validate_quran_rules( array $row, ValidationResult $result ): void {
		if ( ! $this->is_blank( $row['speaker'] ?? null ) ) {
			$result->error( IssueCode::QURAN_SPEAKER_NOT_EMPTY, 'speaker', 'Quran rows must not have a speaker.' );
		}

		if ( $this->is_blank( $row['surah_name'] ?? null ) ) {
			$result->error( IssueCode::QURAN_SURAH_NAME_REQUIRED, 'surah_name', 'Quran rows require a surah name.' );
		}

		$surah_number = $this->integer_value( $row['surah_number'] ?? null );
		if ( $this->is_blank( $row['surah_number'] ?? null ) ) {
			$result->error( IssueCode::QURAN_SURAH_NUMBER_REQUIRED, 'surah_number', 'Quran rows require a surah number.' );
		} elseif ( null !== $surah_number && ( $surah_number < 1 || $surah_number > 114 ) ) {
			$result->error( IssueCode::QURAN_SURAH_NUMBER_RANGE, 'surah_number', 'Surah number must be between 1 and 114.' );
		}

		$ayah_start = $this->integer_value( $row['ayah_start'] ?? null );
		if ( $this->is_blank( $row['ayah_start'] ?? null ) ) {
			$result->error( IssueCode::QURAN_AYAH_START_REQUIRED, 'ayah_start', 'Quran rows require an ayah start.' );
		} elseif ( null !== $ayah_start && $ayah_start < 1 ) {
			$result->error( IssueCode::QURAN_AYAH_START_POSITIVE, 'ayah_start', 'Ayah start must be positive.' );
		}

		$ayah_end = $this->integer_value( $row['ayah_end'] ?? null );
		if ( null !== $ayah_start && null !== $ayah_end && $ayah_end < $ayah_start ) {
			$result->error( IssueCode::QURAN_AYAH_END_BEFORE_START, 'ayah_end', 'Ayah end must not be below ayah start.' );
		}
	}

	/**
	 * @param array<string, mixed> $row Input row.
	 */
	private function validate_hadith_rules( array $row, ValidationResult $result ): void {
		if ( $this->is_blank( $row['speaker'] ?? null ) ) {
			$result->error( IssueCode::HADITH_SPEAKER_REQUIRED, 'speaker', 'Hadith rows require a speaker.' );
		}

		foreach ( array( 'surah_name', 'surah_number', 'ayah_start', 'ayah_end' ) as $field ) {
			if ( ! $this->is_blank( $row[ $field ] ?? null ) ) {
				$result->error(
					IssueCode::HADITH_SURAH_DATA_NOT_EMPTY,
					$field,
					'Hadith rows must not contain surah or ayah data.'
				);
			}
		}
	}

	/**
	 * @param array<string, mixed> $row Input row.
	 */
	private function validate_title( array $row, ValidationResult $result ): void {
		$title = $row['generated_title'] ?? null;
		if ( ! is_string( $title ) ) {
			return;
		}

		if ( UnicodeText::contains_html( $title ) ) {
			$result->error( IssueCode::TITLE_HTML, 'generated_title', 'Title must not contain HTML.' );
		}
		if ( UnicodeText::contains_newline( $title ) ) {
			$result->error( IssueCode::TITLE_NEWLINE, 'generated_title', 'Title must not contain line breaks.' );
		}
		if ( UnicodeText::contains_emoji_or_hashtag( $title ) ) {
			$result->error( IssueCode::TITLE_SYMBOLS, 'generated_title', 'Title must not contain emoji or hashtags.' );
		}
	}

	/**
	 * @param array<string, mixed> $row Input row.
	 */
	private function validate_reflection( array $row, ValidationResult $result ): void {
		$reflection = $row['motivational_text'] ?? null;
		if ( ! is_string( $reflection ) ) {
			return;
		}

		if ( UnicodeText::contains_html( $reflection ) ) {
			$result->error( IssueCode::REFLECTION_HTML, 'motivational_text', 'Reflection must not contain HTML.' );
		}
		if ( UnicodeText::contains_newline( $reflection ) ) {
			$result->error( IssueCode::REFLECTION_NEWLINE, 'motivational_text', 'Reflection must be one paragraph.' );
		}
		if ( UnicodeText::contains_emoji_or_hashtag( $reflection ) ) {
			$result->error( IssueCode::REFLECTION_SYMBOLS, 'motivational_text', 'Reflection must not contain emoji or hashtags.' );
		}
		if ( UnicodeText::sentence_count( $reflection ) > 2 ) {
			$result->error( IssueCode::REFLECTION_SENTENCE_COUNT, 'motivational_text', 'Reflection must contain at most two sentences.' );
		}
	}

	/**
	 * @param array<string, mixed> $row Input row.
	 */
	private function validate_lists( array $row, ValidationResult $result ): void {
		$secondary = $row['secondary_topics'] ?? null;
		if ( is_string( $secondary ) && count( $this->list_values( $secondary ) ) > 3 ) {
			$result->error( IssueCode::SECONDARY_TOPICS_COUNT, 'secondary_topics', 'Secondary topics allow at most three values.' );
		}

		$tags = $row['tags'] ?? null;
		if ( is_string( $tags ) && ! $this->is_blank( $tags ) ) {
			$count = count( $this->list_values( $tags ) );
			if ( $count < 2 || $count > 7 ) {
				$result->error(
					IssueCode::TAGS_COUNT,
					'tags',
					'Tags require between two and seven distinct values.',
					array( 'count' => $count )
				);
			}
		}
	}

	/**
	 * @param array<string, mixed> $row Input row.
	 */
	private function validate_slug( array $row, ValidationResult $result ): void {
		$slug = $row['suggested_slug'] ?? null;
		if ( ! is_string( $slug ) || $this->is_blank( $slug ) ) {
			return;
		}

		if ( 1 !== preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug ) ) {
			$result->error( IssueCode::SLUG_INVALID, 'suggested_slug', 'Slug may contain lowercase Latin letters, digits, and single hyphens only.' );
		}
	}

	/**
	 * @param array<string, mixed> $row Input row.
	 */
	private function validate_source_selection( array $row, ValidationResult $result ): void {
		$original  = $row['source_original'] ?? null;
		$corrected = $row['source_corrected'] ?? null;
		if (
			is_string( $original )
			&& is_string( $corrected )
			&& ! $this->is_blank( $corrected )
			&& ! str_contains( $original, $corrected )
		) {
			$result->error(
				IssueCode::SOURCE_NOT_FROM_ORIGINAL,
				'source_corrected',
				'Display source must be copied from source_original.'
			);
		}
	}

	/**
	 * @param array<string, mixed> $row Input row.
	 */
	private function validate_system_fields( array $row, ValidationResult $result ): void {
		if ( array_key_exists( 'wp_post_id', $row ) && ! $this->is_blank( $row['wp_post_id'] ) ) {
			$result->error( IssueCode::WP_POST_ID_NOT_EMPTY, 'wp_post_id', 'wp_post_id must be empty before import.' );
		}
	}

	/**
	 * @param array<string, mixed> $row Input row.
	 */
	private function add_warnings( array $row, ValidationResult $result ): void {
		$title = $row['generated_title'] ?? null;
		if ( is_string( $title ) ) {
			$length = UnicodeText::length( $title );
			if ( $length >= 61 && $length <= 80 ) {
				$result->warning( IssueCode::TITLE_LENGTH_WARNING, 'generated_title', 'Title is above the preferred length.', array( 'length' => $length ) );
			}
		}

		$reflection = $row['motivational_text'] ?? null;
		if ( is_string( $reflection ) && ! $this->is_blank( $reflection ) ) {
			$length = UnicodeText::length( $reflection );
			if ( $length < 130 || ( $length > 240 && $length <= 280 ) ) {
				$result->warning( IssueCode::REFLECTION_LENGTH_WARNING, 'motivational_text', 'Reflection is outside the preferred length.', array( 'length' => $length ) );
			}
		}

		if ( 'medium' === ( $row['source_confidence'] ?? null ) ) {
			$result->warning( IssueCode::SOURCE_CONFIDENCE_MEDIUM, 'source_confidence', 'Source confidence requires review.' );
		}
		if ( 'partial' === ( $row['excerpt_scope'] ?? null ) ) {
			$result->warning( IssueCode::PARTIAL_EXCERPT, 'excerpt_scope', 'Partial excerpts require context review.' );
		}
		if ( $this->is_blank( $row['suggested_slug'] ?? null ) ) {
			$result->warning( IssueCode::SLUG_EMPTY, 'suggested_slug', 'WordPress will generate the final slug.' );
		}
		if ( 'yes' === ( $row['critical_issue'] ?? null ) ) {
			$result->warning( IssueCode::CRITICAL_ISSUE, 'critical_issue', 'Critical issue requires individual review.' );
		}
	}

	/**
	 * @param mixed $value Input value.
	 */
	private function is_blank( $value ): bool {
		return null === $value || ( is_string( $value ) && '' === trim( $value ) );
	}

	/**
	 * @param mixed $value Input value.
	 */
	private function integer_value( $value ): ?int {
		if ( is_int( $value ) ) {
			return $value;
		}
		if ( is_string( $value ) && 1 === preg_match( '/^-?\d+$/D', $value ) ) {
			return (int) $value;
		}

		return null;
	}

	/**
	 * @return string[]
	 */
	private function list_values( string $value ): array {
		$items = array_map( 'trim', explode( '|', $value ) );
		$items = array_filter( $items, static fn ( string $item ): bool => '' !== $item );

		return array_values( array_unique( $items ) );
	}
}
