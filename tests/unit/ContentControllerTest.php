<?php
/**
 * Unit tests for REST\ContentController.
 *
 * Verifies that:
 *   - all 4 routes are registered under the correct namespace,
 *   - every route declares a permission_callback (no public access),
 *   - every route declares args with type/required for known params.
 *
 * @package Vitalink\ContentImprover
 */

declare(strict_types=1);

namespace Vitalink\ContentImprover\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vitalink\ContentImprover\Features\ContentImprover;
use Vitalink\ContentImprover\REST\ContentController;

require_once __DIR__ . '/../Fixtures/WpStubs.php';

final class ContentControllerTest extends TestCase {

	private ContentController $controller;

	protected function setUp(): void {
		wp_stubs_reset();
		$this->controller = new ContentController();
	}

	public function test_register_routes_registers_all_four_routes(): void {
		$this->controller->register_routes();

		$paths = $this->registered_routes();
		$this->assertContains( '/improve', $paths );
		$this->assertContains( '/summarize', $paths );
		$this->assertContains( '/translate', $paths );
		$this->assertContains( '/alt-text', $paths );
	}

	public function test_routes_use_the_vitalink_namespace(): void {
		$this->controller->register_routes();

		$ns = \VITALINK_CI_REST_NAMESPACE;
		foreach ( $GLOBALS['__wp_stubs']['rest_routes'] as $entry ) {
			$this->assertSame( $ns, $entry['namespace'] );
		}
	}

	public function test_every_route_has_a_permission_callback(): void {
		// Regression for C3: previously register_rest_route() was called
		// with only the handler, so WP would fall back to its global
		// default (effectively public on the front end).
		$this->controller->register_routes();

		foreach ( $GLOBALS['__wp_stubs']['rest_routes'] as $entry ) {
			foreach ( $entry['methods'] as $args ) {
				$this->assertArrayHasKey(
					'permission_callback',
					$args,
					"Route {$entry['route']} must declare a permission_callback."
				);
				$this->assertIsCallable(
					$args['permission_callback'],
					"Route {$entry['route']} permission_callback must be callable."
				);
			}
		}
	}

	public function test_every_route_requires_text_or_image(): void {
		$this->controller->register_routes();

		$by_path = $this->routes_by_path();

		$this->assertTrue( $by_path['/improve']['args']['text']['required'] );
		$this->assertSame( 'string', $by_path['/improve']['args']['text']['type'] );

		$this->assertTrue( $by_path['/summarize']['args']['text']['required'] );
		$this->assertSame( 'string', $by_path['/summarize']['args']['text']['type'] );

		$this->assertTrue( $by_path['/translate']['args']['text']['required'] );
		$this->assertSame( 'string', $by_path['/translate']['args']['text']['type'] );

		$this->assertTrue( $by_path['/alt-text']['args']['image']['required'] );
		$this->assertSame( 'string', $by_path['/alt-text']['args']['image']['type'] );
	}

	public function test_improve_route_declares_style_param(): void {
		$this->controller->register_routes();

		$by_path = $this->routes_by_path();
		$args    = $by_path['/improve']['args'];

		$this->assertArrayHasKey( 'style', $args );
		$this->assertSame( 'string', $args['style']['type'] );
		$this->assertFalse( $args['style']['required'] );
		$this->assertSame( ContentImprover::STYLE_CLEARER, $args['style']['default'] );
	}

	public function test_summarize_route_declares_length_param(): void {
		$this->controller->register_routes();

		$args = $this->routes_by_path()['/summarize']['args'];

		$this->assertArrayHasKey( 'length', $args );
		$this->assertSame( 'integer', $args['length']['type'] );
	}

	public function test_translate_route_declares_optional_target_param(): void {
		$this->controller->register_routes();

		$args = $this->routes_by_path()['/translate']['args'];

		$this->assertArrayHasKey( 'target', $args );
		$this->assertFalse( $args['target']['required'] );
		$this->assertSame( 'string', $args['target']['type'] );
	}

	public function test_can_edit_posts_returns_current_user_can(): void {
		$GLOBALS['__wp_stubs']['current_user_can'] = true;
		$this->assertTrue( $this->controller->can_edit_posts() );

		$GLOBALS['__wp_stubs']['current_user_can'] = false;
		$this->assertFalse( $this->controller->can_edit_posts() );
	}

	public function test_can_edit_posts_checks_the_edit_posts_capability(): void {
		$GLOBALS['__wp_stubs']['current_user_can'] = true;
		$this->controller->can_edit_posts();

		$this->assertContains( 'edit_posts', $GLOBALS['__wp_stubs']['current_user_caps'] );
	}

	public function test_callbacks_point_to_controller_methods(): void {
		$this->controller->register_routes();

		$by_path = $this->routes_by_path();

		$this->assertSame( array( $this->controller, 'improve' ),   $by_path['/improve']['callback'] );
		$this->assertSame( array( $this->controller, 'summarize' ), $by_path['/summarize']['callback'] );
		$this->assertSame( array( $this->controller, 'translate' ), $by_path['/translate']['callback'] );
		$this->assertSame( array( $this->controller, 'alt_text' ),  $by_path['/alt-text']['callback'] );
	}

	// ---------- helpers ----------

	/**
	 * @return string[] All route paths registered so far.
	 */
	private function registered_routes(): array {
		return array_map(
			static fn( array $entry ) => $entry['route'],
			$GLOBALS['__wp_stubs']['rest_routes']
		);
	}

	/**
	 * @return array<string, array> Routes keyed by path → first method's args.
	 */
	private function routes_by_path(): array {
		$out = array();
		foreach ( $GLOBALS['__wp_stubs']['rest_routes'] as $entry ) {
			$first_args = reset( $entry['methods'] );
			$out[ $entry['route'] ] = $first_args;
		}
		return $out;
	}
}
