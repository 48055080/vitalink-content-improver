<?php
/**
 * PHPUnit bootstrap for pure unit tests.
 *
 * Unlike tests/bootstrap.php this file does NOT require WP_TESTS_DIR.
 * It only wires up the WP function stubs in tests/Fixtures/WpStubs.php so
 * the production code under trunk/src can run unmodified.
 *
 * Use this for tests in tests/unit/. The integration testsuite still uses
 * tests/bootstrap.php.
 *
 * @package Vitalink\ContentImprover
 */

declare(strict_types=1);

require_once __DIR__ . '/Fixtures/WpStubs.php';

// Composer's autoloader handles trunk/src (psr-4) and tests/ (classmap).
$autoload = dirname( __DIR__ ) . '/vendor/autoload.php';
if ( ! file_exists( $autoload ) ) {
	fwrite( STDERR, "vendor/autoload.php missing — run: composer install\n" );
	exit( 1 );
}
require_once $autoload;
