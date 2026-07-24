<?php

use Tazkar\Content\ContentType;
use Tazkar\Content\MetaRegistry;
use Tazkar\Content\Taxonomies;

$fail = static function ( string $message ): void {
    fwrite( STDERR, $message . PHP_EOL );
    exit( 1 );
};

if ( ! post_type_exists( ContentType::POST_TYPE ) ) {
    $fail( 'tazkar_item is not registered.' );
}

if ( ! taxonomy_exists( Taxonomies::TOPIC ) || ! taxonomy_exists( Taxonomies::TAG ) ) {
    $fail( 'Tazkar taxonomies are not registered.' );
}

$post_type = get_post_type_object( ContentType::POST_TYPE );
if ( ! $post_type || ! $post_type->show_in_rest || ! post_type_supports( ContentType::POST_TYPE, 'revisions' ) ) {
    $fail( 'Post type REST or revisions configuration is invalid.' );
}

$registered = get_registered_meta_keys( 'post', ContentType::POST_TYPE );
foreach ( MetaRegistry::definitions() as $field => $definition ) {
    $key = MetaRegistry::key( $field );
    if ( ! isset( $registered[ $key ] ) ) {
        $fail( 'Missing registered meta: ' . $key );
    }
    if ( (bool) $definition['show_in_rest'] !== (bool) $registered[ $key ]['show_in_rest'] ) {
        $fail( 'Unexpected REST exposure for: ' . $key );
    }
}

foreach ( array( 'batch_id', 'review_status', 'reviewer_notes', 'meaning_confidence', 'import_status' ) as $internal_field ) {
    if ( ! empty( $registered[ MetaRegistry::key( $internal_field ) ]['show_in_rest'] ) ) {
        $fail( 'Internal field exposed in REST: ' . $internal_field );
    }
}

$post_id = wp_insert_post(
    array(
        'post_type'   => ContentType::POST_TYPE,
        'post_status' => 'draft',
        'post_title'  => 'آزمون مدل محتوا',
    )
);

if ( is_wp_error( $post_id ) || ! $post_id ) {
    $fail( 'Could not create tazkar_item.' );
}

$arabic = "  إِنَّ النَّفْسَ لَأَمَّارَةٌ بِالسُّوءِ\n";
update_post_meta( $post_id, MetaRegistry::key( 'arabic_text' ), $arabic );

if ( $arabic !== get_post_meta( $post_id, MetaRegistry::key( 'arabic_text' ), true ) ) {
    $fail( 'Arabic text was mutated.' );
}

if ( hash( 'sha256', $arabic ) !== get_post_meta( $post_id, MetaRegistry::ARABIC_HASH_KEY, true ) ) {
    $fail( 'Arabic SHA-256 hash mismatch.' );
}

update_post_meta( $post_id, MetaRegistry::key( 'review_status' ), 'approved' );
update_post_meta( $post_id, MetaRegistry::key( 'motivational_text' ), 'تلنگر تازه' );
if ( 'approved' !== get_post_meta( $post_id, MetaRegistry::key( 'review_status' ), true ) ) {
    $fail( 'Non-sensitive change reset approval.' );
}

update_post_meta( $post_id, MetaRegistry::key( 'arabic_text' ), $arabic . ' ' );
if ( 'needs_recheck' !== get_post_meta( $post_id, MetaRegistry::key( 'review_status' ), true ) ) {
    $fail( 'Sensitive Arabic change did not require recheck.' );
}

wp_delete_post( $post_id, true );
echo "Tazkar Sprint 1 WordPress content model smoke passed.\n";
