<?php

use PHPUnit\Framework\TestCase;
use Tazkar\Content\MetaRegistry;
use Tazkar\Import\DraftItemPayloadBuilder;

final class DraftItemPayloadBuilderTest extends TestCase {
	public function test_server_owned_fields_override_csv_values_and_preserve_arabic(): void {
		$row = array(
			'generated_title'  => 'عنوان آزمایشی',
			'suggested_slug'   => 'sample-slug',
			'arabic_text'      => "  نَصٌّ عَرَبِيٌّ  ",
			'review_status'    => 'approved',
			'import_status'    => 'error',
			'wp_post_id'       => '999',
			'primary_topic'    => 'صبر',
			'secondary_topics' => 'اخلاق|صبر| بندگی ',
			'tags'             => 'صبر|عبادت|صبر',
			'record_id'        => 'record-1',
		);

		$payload = ( new DraftItemPayloadBuilder() )->build( $row, 42 );

		self::assertSame( 'draft', $payload->post_data()['post_status'] );
		self::assertSame( 42, $payload->post_data()['post_author'] );
		self::assertArrayNotHasKey( 'ID', $payload->post_data() );
		self::assertSame(
			'needs_review',
			$payload->meta()[ MetaRegistry::key( 'review_status' ) ]
		);
		self::assertSame(
			'imported',
			$payload->meta()[ MetaRegistry::key( 'import_status' ) ]
		);
		self::assertSame(
			$row['arabic_text'],
			$payload->meta()[ MetaRegistry::key( 'arabic_text' ) ]
		);
		self::assertSame(
			hash( 'sha256', $row['arabic_text'] ),
			$payload->meta()[ MetaRegistry::ARABIC_HASH_KEY ]
		);
		self::assertSame( array( 'صبر', 'اخلاق', 'بندگی' ), $payload->topics() );
		self::assertSame( array( 'صبر', 'عبادت' ), $payload->tags() );
	}

	public function test_stricter_source_review_state_is_preserved(): void {
		$builder = new DraftItemPayloadBuilder();

		foreach ( array( 'needs_review', 'needs_recheck', 'rejected' ) as $status ) {
			$payload = $builder->build( array( 'review_status' => $status ), 1 );
			self::assertSame(
				$status,
				$payload->meta()[ MetaRegistry::key( 'review_status' ) ]
			);
		}
	}
}
