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
	'options'         => array(),   // get_option / update_option backing
	'transients'      => array(),   // get_transient / set_transient / delete_transient backing
	'filters'         => array(),   // apply_filters backing
	'last_filter_args' => array(),
	// Simulated wp_options rows used by ResponseCache::flush().
	// The stubbed $wpdb counts how many rows were "deleted" so tests can
	// assert on the return value of $wpdb->query().
	'options_table'   => array(),
	'last_query_rows_affected' => 0,
	// REST route registrations captured by register_rest_route().
	// Each entry: [ 'namespace' => '...', 'route' => '...', 'args' => array(...) ]
	'rest_routes'     => array(),
	'current_user_can' => false,    // what current_user_can() returns in tests
	'current_user_caps' => array(), // which caps have been checked
);

// Reset all stubs to a clean state. Call from setUp().
function wp_stubs_reset(): void {
	$GLOBALS['__wp_stubs']['options']                 = array();
	$GLOBALS['__wp_stubs']['transients']              = array();
	$GLOBALS['__wp_stubs']['filters']                 = array();
	$GLOBALS['__wp_stubs']['last_filter_args']        = array();
	$GLOBALS['__wp_stubs']['options_table']           = array();
	$GLOBALS['__wp_stubs']['last_query_rows_affected'] = 0;
	$GLOBALS['__wp_stubs']['rest_routes']             = array();
	$GLOBALS['__wp_stubs']['current_user_can']        = false;
	$GLOBALS['__wp_stubs']['current_user_caps']       = array();
}

/**
 * Seed the simulated wp_options table for flush() tests.
 *
 * Rows whose option_name starts with "_transient_vitalink_ci_resp_" will
 * be counted by the stubbed $wpdb->query() in a way that mirrors what the
 * real flush() deletes.
 */
function wp_stubs_seed_options_table( array $rows ): void {
	$GLOBALS['__wp_stubs']['options_table'] = $rows;
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
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability, ...$args ) {
		$GLOBALS['__wp_stubs']['current_user_caps'][] = $capability;
		return (bool) $GLOBALS['__wp_stubs']['current_user_can'];
	}
}
if ( ! function_exists( 'register_rest_route' ) ) {
	function register_rest_route( $namespace, $route, $args ) {
		// $args can be a single options array, or an array of methods→args.
		// Normalise both shapes to [ method => args ] for assertion.
		$methods = array();
		if ( isset( $args['methods'] ) || isset( $args['callback'] ) || isset( $args['permission_callback'] ) ) {
			$methods[ $args['methods'] ?? 'GET' ] = $args;
		} else {
			$methods = $args;
		}
		$GLOBALS['__wp_stubs']['rest_routes'][] = array(
			'namespace' => (string) $namespace,
			'route'     => (string) $route,
			'methods'   => $methods,
		);
		return true;
	}
}

// WordPress time constants used by ResponseCache.
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

// Plugin-defined constants.
if ( ! defined( 'VITALINK_CI_REST_NAMESPACE' ) ) {
	define( 'VITALINK_CI_REST_NAMESPACE', 'vitalink-ci/v1' );
}

// Minimal WP_REST_Server stub. Only the method constants are referenced
// at route registration time.
if ( ! class_exists( 'WP_REST_Server' ) ) {
	final class WP_REST_Server {
		const READABLE   = 'GET';
		const CREATABLE  = 'POST';
		const EDITABLE   = 'POST, PUT, PATCH';
		const DELETABLE  = 'DELETE';
	}
}

// Minimal $wpdb stub used by ResponseCache::flush(). Tests can seed
// $GLOBALS['__wp_stubs']['options_table'] to simulate the wp_options rows
// the production code will DELETE.
if ( ! isset( $GLOBALS['wpdb'] ) ) {
	$GLOBALS['wpdb'] = new class {
		public string $options = 'wp_options';
		public function esc_like( $text ) {
			return addcslashes( (string) $text, '_%\\' );
		}
		public function prepare( $sql, ...$args ) {
			// Substitute %s placeholders with literal strings (no quoting
			// needed because the stub's seeded data is already trusted).
			$out = $sql;
			foreach ( $args as $arg ) {
				$out = preg_replace( '/%s/', (string) $arg, $out, 1 );
			}
			return $out;
		}
		public function query( $sql ) {
			// Mirror the LIKE pattern built by ResponseCache::flush().
			$rows = $GLOBALS['__wp_stubs']['options_table'] ?? array();
			$matched = 0;
			foreach ( $rows as $name => $_value ) {
				if ( str_starts_with( $name, '_transient_vitalink_ci_resp_' )
					|| str_starts_with( $name, '_transient_timeout_vitalink_ci_resp_' )
				) {
					unset( $rows[ $name ] );
					$matched++;
				}
			}
			$GLOBALS['__wp_stubs']['options_table'] = $rows;
			$GLOBALS['__wp_stubs']['last_query_rows_affected'] = $matched;
			return $matched;
		}
	};
}