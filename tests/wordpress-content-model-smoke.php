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
if (
    ! $post_type
    || ! $post_type->show_in_rest
    || ! post_type_supports( ContentType::POST_TYPE, 'revisions' )
    || ! post_type_supports( ContentType::POST_TYPE, 'custom-fields' )
) {
    $fail( 'Post type REST, revisions or custom-fields configuration is invalid.' );
}

if (
    $post_type->cap->edit_post === $post_type->cap->read_post
    || $post_type->cap->edit_post === $post_type->cap->delete_post
    || $post_type->cap->read_post === $post_type->cap->delete_post
) {
    $fail( 'Post type meta capabilities must be distinct.' );
}

foreach ( array( Taxonomies::TOPIC, Taxonomies::TAG ) as $taxonomy_name ) {
    $taxonomy = get_taxonomy( $taxonomy_name );
    if ( ! $taxonomy || 'manage_tazkar' !== $taxonomy->cap->manage_terms ) {
        $fail( 'Taxonomy capability scope is invalid: ' . $taxonomy_name );
    }
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

$administrator = get_user_by( 'login', 'admin' );
if ( ! $administrator ) {
    $fail( 'Administrator fixture was not found.' );
}
wp_set_current_user( $administrator->ID );

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

if ( '' !== get_post_field( 'post_content', $post_id ) ) {
    $fail( 'post_content must remain outside the Tazkar data model.' );
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

update_post_meta( $post_id, MetaRegistry::key( 'batch_id' ), 'internal-batch' );
wp_update_post(
    array(
        'ID'          => $post_id,
        'post_status' => 'publish',
    )
);

wp_set_current_user( 0 );
$request  = new WP_REST_Request( 'GET', '/wp/v2/tazkar_item/' . $post_id );
$response = rest_do_request( $request );
if ( is_wp_error( $response ) || 200 !== $response->get_status() ) {
    $fail( 'Approved public REST request failed.' );
}

$data = $response->get_data();
if ( ! isset( $data['meta'][ MetaRegistry::key( 'arabic_text' ) ] ) ) {
    $fail( 'Public REST meta allowlist is not active.' );
}
if ( $arabic !== $data['meta'][ MetaRegistry::key( 'arabic_text' ) ] ) {
    $fail( 'REST response mutated the Arabic value.' );
}
if ( isset( $data['meta'][ MetaRegistry::key( 'batch_id' ) ] ) ) {
    $fail( 'Internal batch metadata leaked through REST.' );
}
if ( isset( $data['meta'][ MetaRegistry::ARABIC_HASH_KEY ] ) ) {
    $fail( 'Internal Arabic hash leaked through REST.' );
}

wp_set_current_user( $administrator->ID );
$changed_arabic = $arabic . ' ';
update_post_meta( $post_id, MetaRegistry::key( 'arabic_text' ), $changed_arabic );

if ( 'needs_recheck' !== get_post_meta( $post_id, MetaRegistry::key( 'review_status' ), true ) ) {
    $fail( 'Sensitive Arabic change did not require recheck.' );
}
if ( 'draft' !== get_post_status( $post_id ) ) {
    $fail( 'Sensitive change did not remove the item from public view.' );
}
if ( hash( 'sha256', $changed_arabic ) !== get_post_meta( $post_id, MetaRegistry::ARABIC_HASH_KEY, true ) ) {
    $fail( 'Changed Arabic SHA-256 hash mismatch.' );
}

wp_set_current_user( 0 );
$draft_response = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/tazkar_item/' . $post_id ) );
if ( ! is_wp_error( $draft_response ) && 200 === $draft_response->get_status() ) {
    $fail( 'Needs-recheck draft remained publicly readable.' );
}

wp_set_current_user( $administrator->ID );
wp_delete_post( $post_id, true );
echo "Tazkar Sprint 1 WordPress content model smoke passed.\n";
