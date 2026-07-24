<?php

namespace Tazkar\Validation;

final class UnicodeText {
	public static function length( string $value ): int {
		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $value, 'UTF-8' );
		}

		$count = preg_match_all( '/./us', $value, $matches );

		return false === $count ? strlen( $value ) : $count;
	}

	public static function containsHtml( string $value ): bool {
		return strip_tags( $value ) !== $value;
	}

	public static function containsNewline( string $value ): bool {
		return 1 === preg_match( '/\R/u', $value );
	}

	public static function containsEmojiOrHashtag( string $value ): bool {
		return str_contains( $value, '#' )
			|| 1 === preg_match( '/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $value );
	}

	public static function sentenceCount( string $value ): int {
		$parts = preg_split( '/[.!؟?]+/u', trim( $value ), -1, PREG_SPLIT_NO_EMPTY );

		return false === $parts ? 0 : count( $parts );
	}

	private function __construct() {}
}
