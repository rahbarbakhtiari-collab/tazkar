<?php

namespace Tazkar\Content;

if ( ! defined( 'ABSPATH' ) && ! defined( 'TAZKAR_TESTING' ) ) {
    exit;
}

final class MetaRegistry {
    public const PREFIX          = 'tazkar_';
    public const ARABIC_HASH_KEY = '_tazkar_arabic_hash';

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function definitions(): array {
        $text     = array( self::class, 'sanitize_text' );
        $textarea = array( self::class, 'sanitize_textarea' );
        $integer  = array( self::class, 'sanitize_integer' );

        return array(
            'schema_version'       => self::definition( 'string', $text, false ),
            'record_id'            => self::definition( 'string', $text, false ),
            'batch_id'             => self::definition( 'string', $text, false ),
            'prompt_version'        => self::definition( 'string', $text, false ),
            'source_file'           => self::definition( 'string', $text, false ),
            'book_title'            => self::definition( 'string', $text, false ),
            'source_location'       => self::definition( 'string', $text, false ),
            'content_type'          => self::definition(
                'string',
                array( self::class, 'sanitize_content_type' ),
                true
            ),
            'excerpt_scope'         => self::definition(
                'string',
                array( self::class, 'sanitize_excerpt_scope' ),
                false
            ),
            'translation_language'  => self::definition( 'string', $text, false ),
            'speaker'               => self::definition( 'string', $text, true ),
            'arabic_text'           => self::definition( 'string', array( self::class, 'sanitize_arabic' ), true ),
            'translation_original'  => self::definition( 'string', $textarea, false ),
            'translation_corrected' => self::definition( 'string', $textarea, true ),
            'source_original'       => self::definition( 'string', $textarea, false ),
            'source_corrected'      => self::definition( 'string', $textarea, true ),
            'surah_name'            => self::definition( 'string', $text, true ),
            'surah_number'          => self::definition( 'integer', $integer, true ),
            'ayah_start'            => self::definition( 'integer', $integer, true ),
            'ayah_end'              => self::definition( 'integer', $integer, true ),
            'motivational_text'     => self::definition( 'string', $textarea, true ),
            'extraction_confidence' => self::definition(
                'string',
                array( self::class, 'sanitize_confidence' ),
                false
            ),
            'source_confidence'     => self::definition(
                'string',
                array( self::class, 'sanitize_confidence' ),
                false
            ),
            'meaning_confidence'    => self::definition(
                'string',
                array( self::class, 'sanitize_confidence' ),
                false
            ),
            'context_confidence'    => self::definition(
                'string',
                array( self::class, 'sanitize_confidence' ),
                false
            ),
            'critical_issue'        => self::definition( 'string', array( self::class, 'sanitize_yes_no' ), false ),
            'review_status'         => self::definition(
                'string',
                array( self::class, 'sanitize_review_status' ),
                false
            ),
            'reviewer_notes'        => self::definition( 'string', $textarea, false ),
            'import_status'         => self::definition(
                'string',
                array( self::class, 'sanitize_import_status' ),
                false
            ),
        );
    }

    /**
     * @return string[]
     */
    public static function public_fields(): array {
        $fields = array();

        foreach ( self::definitions() as $field => $definition ) {
            if ( true === $definition['show_in_rest'] ) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    public static function key( string $field ): string {
        return self::PREFIX . $field;
    }

    public static function register(): void {
        foreach ( self::definitions() as $field => $definition ) {
            register_post_meta( ContentType::POST_TYPE, self::key( $field ), $definition );
        }

        register_post_meta(
            ContentType::POST_TYPE,
            self::ARABIC_HASH_KEY,
            self::definition( 'string', array( self::class, 'sanitize_hash' ), false )
        );
    }

    /**
     * @param callable $sanitize_callback Sanitizer callback.
     * @return array<string, mixed>
     */
    private static function definition( string $type, callable $sanitize_callback, bool $show_in_rest ): array {
        return array(
            'type'              => $type,
            'single'            => true,
            'sanitize_callback' => $sanitize_callback,
            'auth_callback'     => array( self::class, 'can_edit_meta' ),
            'show_in_rest'      => $show_in_rest,
        );
    }

    public static function can_edit_meta( bool $allowed, string $meta_key, int $post_id ): bool {
        unset( $allowed, $meta_key );

        return current_user_can( 'edit_post', $post_id );
    }

    /**
     * Preserve the exact Unicode string. Do not trim, normalize, or alter spacing.
     *
     * @param mixed $value Raw value.
     */
    public static function sanitize_arabic( $value ): string {
        return is_string( $value ) ? $value : '';
    }

    /**
     * @param mixed $value Raw value.
     */
    public static function sanitize_text( $value ): string {
        return sanitize_text_field( is_scalar( $value ) ? (string) $value : '' );
    }

    /**
     * @param mixed $value Raw value.
     */
    public static function sanitize_textarea( $value ): string {
        return sanitize_textarea_field( is_scalar( $value ) ? (string) $value : '' );
    }

    /**
     * @param mixed $value Raw value.
     */
    public static function sanitize_integer( $value ): int {
        return absint( $value );
    }

    /**
     * @param mixed $value Raw value.
     */
    public static function sanitize_content_type( $value ): string {
        return self::sanitize_enum( $value, array( 'quran', 'hadith' ) );
    }

    /**
     * @param mixed $value Raw value.
     */
    public static function sanitize_excerpt_scope( $value ): string {
        return self::sanitize_enum( $value, array( 'full', 'partial' ) );
    }

    /**
     * @param mixed $value Raw value.
     */
    public static function sanitize_confidence( $value ): string {
        return self::sanitize_enum( $value, array( 'high', 'medium', 'low' ) );
    }

    /**
     * @param mixed $value Raw value.
     */
    public static function sanitize_yes_no( $value ): string {
        return self::sanitize_enum( $value, array( 'yes', 'no' ) );
    }

    /**
     * @param mixed $value Raw value.
     */
    public static function sanitize_review_status( $value ): string {
        return self::sanitize_enum(
            $value,
            array( 'extracted', 'needs_review', 'approved', 'rejected', 'needs_recheck' )
        );
    }

    /**
     * @param mixed $value Raw value.
     */
    public static function sanitize_import_status( $value ): string {
        return self::sanitize_enum( $value, array( 'not_imported', 'imported', 'skipped', 'error' ) );
    }

    /**
     * @param mixed $value Raw value.
     */
    public static function sanitize_hash( $value ): string {
        $hash = is_scalar( $value ) ? strtolower( (string) $value ) : '';

        return 1 === preg_match( '/^[a-f0-9]{64}$/', $hash ) ? $hash : '';
    }

    /**
     * @param mixed    $value   Raw value.
     * @param string[] $allowed Allowed values.
     */
    private static function sanitize_enum( $value, array $allowed ): string {
        $value = is_scalar( $value ) ? (string) $value : '';

        return in_array( $value, $allowed, true ) ? $value : '';
    }
}
