<?php
/**
 * WP function stubs for pure unit tests.
 *
 * Only defines functions that don't already exist — when running under the
 * WP test framework (integration mode) the real WP versions win.
 *
 * Keep this file small and stateless. State lives in the
 * $GLOBALS['__wp_stubs'] array, accessed via the helper closures below.
 *
 * @package Vitalink\ContentImprover
 */

declare(strict_types=1);

// State: a flat bag of WP "global" values, keyed by name.
$GLOBALS['__wp_stubs'] = array(
	'options'    => array(),   // get_option / update_option backing
	'transients' => array(),   // get_transient / set_transient / delete_transient backing
	'filters'    => array(),   // apply_filters backing
	'last_filter_args' => array(),
);

// Reset all stubs to a clean state. Call from setUp().
function wp_stubs_reset(): void {
	$GLOBALS['__wp_stubs']['options']         = array();
	$GLOBALS['__wp_stubs']['transients']      = array();
	$GLOBALS['__wp_stubs']['filters']         = array();
	$GLOBALS['__wp_stubs']['last_filter_args'] = array();
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		return $GLOBALS['__wp_stubs']['options'][ $key ] ?? $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $key, $value ) {
		$GLOBALS['__wp_stubs']['options'][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $key ) {
		unset( $GLOBALS['__wp_stubs']['options'][ $key ] );
		return true;
	}
}
if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $key ) {
		return $GLOBALS['__wp_stubs']['transients'][ $key ] ?? false;
	}
}
if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $key, $value, $expiration = 0 ) {
		$GLOBALS['__wp_stubs']['transients'][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $key ) {
		unset( $GLOBALS['__wp_stubs']['transients'][ $key ] );
		return true;
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value, ...$args ) {
		$callback = $GLOBALS['__wp_stubs']['filters'][ $tag ] ?? null;
		if ( null === $callback ) {
			return $value;
		}
		$GLOBALS['__wp_stubs']['last_filter_args'][ $tag ] = $args;
		return $callback( $value, ...$args );
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}

// WordPress time constants used by ResponseCache.
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}