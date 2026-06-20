<?php
/**
 * PHPUnit bootstrap — loads the WP test framework and the plugin.
 *
 * WP_TESTS_DIR must be set in the environment. See composer.json scripts
 * and .github/workflows/ci.yml for the canonical CI invocation.
 *
 * @package Vitalink\ContentImprover
 */

declare(strict_types=1);

if ( ! defined( 'WP_TESTS_DIR' ) || ! file_exists( WP_TESTS_DIR . '/includes/functions.php' ) ) {
	fwrite( STDERR, "WP_TESTS_DIR not set. Run: bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version]\n" );
	exit( 1 );
}

require_once WP_TESTS_DIR . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		// Load the plugin into the test environment.
		require_once dirname( __DIR__ ) . '/trunk/vitalink-content-improver.php';
	}
);

require WP_TESTS_DIR . '/includes/bootstrap.php';
