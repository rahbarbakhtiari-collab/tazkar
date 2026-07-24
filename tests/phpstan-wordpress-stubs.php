<?php

const ABSPATH = __DIR__ . '/wordpress-stub/';

class WP_Error {
	public function get_error_message(): string {
		return '';
	}
}

class WP_Role {
	public function add_cap( string $capability ): void {}
	public function remove_cap( string $capability ): void {}
}

class wpdb {
	/** @var string */
	public $prefix = 'wp_';
	/** @var int */
	public $insert_id = 1;

	public function get_charset_collate(): string { return ''; }
	public function esc_like( $text ) { return $text; }
	public function get_var( $query ) {}
	public function prepare( $query, ...$args ) { return ''; }
	public function query( $query ) {}
	public function insert( $table, $data, $format = null ) { return 1; }
	public function update( $table, $data, $where, $format = null, $where_format = null ) { return 1; }
}

function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {}
function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {}
function absint( $maybeint ) { return abs( (int) $maybeint ); }
function current_time( $type, $gmt = 0 ) { return '2026-07-24 00:00:00'; }
function current_user_can( $capability, ...$args ) { return true; }
function delete_post_meta( $post_id, $meta_key, $meta_value = '' ) {}
function get_current_user_id() { return 1; }
function get_post_meta( $post_id, $key = '', $single = false ) { return ''; }
function get_post_status( $post_id ) { return 'publish'; }
function get_post_type( $post = null ) { return 'tazkar_item'; }
function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
function register_post_meta( $post_type, $meta_key, array $args ) {}
function register_post_type( $post_type, $args = array() ) {}
function register_taxonomy( $taxonomy, $object_type, $args = array() ) {}
function sanitize_text_field( $str ) { return (string) $str; }
function sanitize_textarea_field( $str ) { return (string) $str; }
function sanitize_title( $title ) { return (string) $title; }
function update_post_meta( $post_id, $meta_key, $meta_value, $prev_value = '' ) { return 1; }
function wp_delete_post( $post_id, $force_delete = false ) { return null; }
function wp_generate_uuid4() { return '00000000-0000-4000-8000-000000000000'; }
function wp_insert_post( $postarr, $wp_error = false, $fire_after_hooks = true ) { return 1; }
function wp_set_object_terms( $object_id, $terms, $taxonomy, $append = false ) { return array(); }
function wp_update_post( $postarr, $wp_error = false, $fire_after_hooks = true ) { return 1; }
function add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $callback = '', $icon_url = '', $position = null ) {}
function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null ) {}
function add_option( $option, $value = '', $deprecated = '', $autoload = 'yes' ) {}
function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) { return 1; }
function dbDelta( $queries = '', $execute = true ) { return array(); }
function deactivate_plugins( $plugins, $silent = false, $network_wide = null ) {}
function delete_option( $option ) {}
function delete_transient( $transient ) {}
function esc_attr( $text ) { return $text; }
function esc_html( $text ) { return $text; }
function esc_html__( $text, $domain = 'default' ) { return $text; }
function get_option( $option, $default = false ) { return $default; }
function get_role( $role ) { return new WP_Role(); }
function is_admin() { return true; }
function is_multisite() { return false; }
function load_plugin_textdomain( $domain, $deprecated = false, $plugin_rel_path = false ) {}
function plugin_basename( $file ) { return basename( $file ); }
function plugin_dir_path( $file ) { return dirname( $file ) . '/'; }
function plugin_dir_url( $file ) { return ''; }
function register_activation_hook( $file, $callback ) {}
function register_deactivation_hook( $file, $callback ) {}
function submit_button( $text = null, $type = 'primary', $name = 'submit', $wrap = true, $other_attributes = null ) {}
function update_option( $option, $value, $autoload = null ) {}
function wp_die( $message = '', $title = '', $args = array() ) { exit; }
function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $display = true ) { return ''; }
function __( $text, $domain = 'default' ) { return $text; }
