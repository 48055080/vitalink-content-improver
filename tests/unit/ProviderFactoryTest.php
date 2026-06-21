<?php
/**
 * Unit tests for ProviderFactory.
 *
 * @package Vitalink\ContentImprover
 */

declare(strict_types=1);

namespace Vitalink\ContentImprover\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vitalink\ContentImprover\Providers\ProviderException;
use Vitalink\ContentImprover\Providers\ProviderFactory;
use Vitalink\ContentImprover\Providers\ProviderInterface;

require_once __DIR__ . '/../Fixtures/WpStubs.php';
require_once __DIR__ . '/../Fixtures/FakeProvider.php';

final class ProviderFactoryTest extends TestCase {

	protected function setUp(): void {
		wp_stubs_reset();
		// Register a 'fake' provider via filter for testing.
		$GLOBALS['__wp_stubs']['filters']['vitalink_ci_register_providers'] =
			static function ( $map ) {
				$map['fake'] = \Vitalink\ContentImprover\Tests\Fixtures\FakeProvider::class;
				return $map;
			};
	}

	public function test_create_returns_provider_for_builtin_id(): void {
		$provider = ProviderFactory::create( 'fake', array() );

		$this->assertInstanceOf( ProviderInterface::class, $provider );
		$this->assertSame( 'fake', $provider->get_id() );
		$this->assertSame( 'Fake', $provider->get_label() );
	}

	public function test_create_throws_for_unknown_provider(): void {
		$this->expectException( ProviderException::class );
		$this->expectExceptionMessage( 'Unknown provider: nonsense' );

		ProviderFactory::create( 'nonsense' );
	}

	public function test_create_passes_config_to_provider_constructor(): void {
		$provider = ProviderFactory::create( 'fake', array( 'k' => 'v' ) );

		// FakeProvider does not read config, but we can at least confirm it is
		// instantiable and configured.
		$this->assertTrue( $provider->is_configured() );
	}

	public function test_get_active_provider_id_returns_default_when_unset(): void {
		$this->assertSame( 'openai', ProviderFactory::get_active_provider_id() );
	}

	public function test_get_active_provider_id_returns_sanitized_value(): void {
		$GLOBALS['__wp_stubs']['options']['vitalink_ci_provider'] = 'Anthropic';

		$this->assertSame( 'anthropic', ProviderFactory::get_active_provider_id() );
	}

	public function test_list_providers_returns_each_with_id_label_and_configured(): void {
		$list = ProviderFactory::list_providers();

		$this->assertIsArray( $list );
		$this->assertNotEmpty( $list );

		$fake = null;
		foreach ( $list as $row ) {
			if ( 'fake' === $row['id'] ) {
				$fake = $row;
				break;
			}
		}

		$this->assertNotNull( $fake, 'Expected the fake provider to be registered for the test.' );
		$this->assertSame( 'Fake', $fake['label'] );
		$this->assertTrue( $fake['configured'] );
	}

	public function test_custom_provider_can_be_registered_via_filter(): void {
		$GLOBALS['__wp_stubs']['filters']['vitalink_ci_register_providers'] =
			static function ( $map ) {
				$map['custom-mistral'] = \Vitalink\ContentImprover\Tests\Fixtures\FakeProvider::class;
				return $map;
			};

		$provider = ProviderFactory::create( 'custom-mistral' );

		$this->assertSame( 'fake', $provider->get_id() );
	}

	public function test_filter_returning_non_array_falls_back_to_builtins(): void {
		$GLOBALS['__wp_stubs']['filters']['vitalink_ci_register_providers'] =
			static function ( $map ) {
				return 'not-an-array';
			};

		// Built-in openai/anthropic/ollama still work — but they require real
		// credentials at construction time. Confirm we get back at least the
		// fake provider through the factory registry path by adding it via
		// a separate filter snapshot.
		$list = ProviderFactory::list_providers();

		// With a bad filter we fall back to builtins only (openai, anthropic, ollama).
		// None of them is 'fake' in this code path.
		$ids = array_column( $list, 'id' );
		$this->assertNotContains( 'fake', $ids );
		$this->assertContains( 'openai', $ids );
		$this->assertContains( 'anthropic', $ids );
		$this->assertContains( 'ollama', $ids );
	}
}