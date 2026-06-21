<?php
/**
 * PHPStan-only stubs for symbols outside the WordPress core stubs.
 *
 * Loaded by phpstan.neon (scanFiles). Not used at runtime — production
 * code runs under real WordPress and real WP-CLI.
 *
 * @package Vitalink\ContentImprover
 */

declare(strict_types=1);

// Plugin constants defined in trunk/vitalink-content-improver.php.
// We re-declare them here so PHPStan can resolve them without executing
// the main plugin file (which short-circuits on the missing ABSPATH).
if ( ! defined( 'VITALINK_CI_VERSION' ) ) {
	define( 'VITALINK_CI_VERSION', '0.1.0' );
}
if ( ! defined( 'VITALINK_CI_FILE' ) ) {
	define( 'VITALINK_CI_FILE', __FILE__ );
}
if ( ! defined( 'VITALINK_CI_PATH' ) ) {
	define( 'VITALINK_CI_PATH', __DIR__ . '/../../trunk/' );
}
if ( ! defined( 'VITALINK_CI_URL' ) ) {
	define( 'VITALINK_CI_URL', 'https://example.com/wp-content/plugins/vitalink-content-improver/' );
}
if ( ! defined( 'VITALINK_CI_SLUG' ) ) {
	define( 'VITALINK_CI_SLUG', 'vitalink-content-improver' );
}
if ( ! defined( 'VITALINK_CI_REST_NAMESPACE' ) ) {
	define( 'VITALINK_CI_REST_NAMESPACE', 'vitalink-ci/v1' );
}
if ( ! defined( 'VITALINK_CI_CACHE_GROUP' ) ) {
	define( 'VITALINK_CI_CACHE_GROUP', 'vitalink_ci' );
}
if ( ! defined( 'VITALINK_CI_OPTION_PREFIX' ) ) {
	define( 'VITALINK_CI_OPTION_PREFIX', 'vitalink_ci_' );
}

if ( ! class_exists( 'WP_CLI' ) ) {
	/**
	 * Minimal WP_CLI facade. Real WP-CLI ships this at runtime; PHPStan's
	 * WordPress extension does not include it.
	 */
	final class WP_CLI {
		public static function log( string $message ): void {}
		public static function error( string $message, bool $exit = true ): void {}
		public static function success( string $message ): void {}
		public static function warning( string $message ): void {}

		/**
		 * WP-CLI accepts either a callable or a class name string. The
		 * class name is the conventional shape for $callback and is
		 * what Plugin::boot() uses.
		 *
		 * @param string          $name     Command name, e.g. "vitalink ci".
		 * @param callable|string $callback Handler callable, or class name to instantiate.
		 * @param array           $args     Optional command metadata.
		 */
		public static function add_command( string $name, $callback, array $args = array() ): bool {
			return true; }
	}
}
