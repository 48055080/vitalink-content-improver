<?php
/**
 * Unit tests for AnthropicProvider API-key resolution.
 *
 * Mirrors OpenAIProviderTest — the decryption behaviour is identical
 * because both providers share the same at-rest scheme.
 *
 * @package Vitalink\ContentImprover
 */

declare(strict_types=1);

namespace Vitalink\ContentImprover\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vitalink\ContentImprover\Providers\AnthropicProvider;
use Vitalink\ContentImprover\Support\Encryption;

require_once __DIR__ . '/../Fixtures/WpStubs.php';

final class AnthropicProviderTest extends TestCase {

	protected function setUp(): void {
		wp_stubs_reset();
		if ( ! defined( 'LOGGED_IN_KEY' ) ) {
			define( 'LOGGED_IN_KEY', 'unit-test-salt-' . bin2hex( random_bytes( 8 ) ) );
		}
	}

	public function test_decrypts_stored_v1_api_key(): void {
		if ( ! function_exists( 'sodium_crypto_secretbox' ) ) {
			$this->markTestSkipped( 'libsodium not available.' );
		}

		$plaintext = 'sk-ant-test-' . bin2hex( random_bytes( 8 ) );
		$blob      = Encryption::encrypt( $plaintext );
		$GLOBALS['__wp_stubs']['options']['vitalink_ci_anthropic_api_key'] = $blob;

		$provider = new AnthropicProvider();

		$this->assertSame( $plaintext, $this->read_api_key( $provider ) );
		$this->assertTrue( $provider->is_configured() );
	}

	public function test_legacy_plaintext_option_is_used_as_is(): void {
		$plaintext = 'sk-ant-legacy-plaintext';
		$GLOBALS['__wp_stubs']['options']['vitalink_ci_anthropic_api_key'] = $plaintext;

		$provider = new AnthropicProvider();

		$this->assertSame( $plaintext, $this->read_api_key( $provider ) );
		$this->assertTrue( $provider->is_configured() );
	}

	public function test_empty_option_means_unconfigured(): void {
		$provider = new AnthropicProvider();

		$this->assertSame( '', $this->read_api_key( $provider ) );
		$this->assertFalse( $provider->is_configured() );
	}

	public function test_explicit_config_overrides_stored_option(): void {
		// The classic C2 scenario: previously sanitize_secret always
		// returned the OpenAI key, so the Anthropic slot could never
		// be cleared. Here we confirm the provider reads from the
		// correct option and not from a hard-coded OpenAI key.
		$GLOBALS['__wp_stubs']['options']['vitalink_ci_openai_api_key']    = 'sk-stored-openai-should-be-ignored';
		$GLOBALS['__wp_stubs']['options']['vitalink_ci_anthropic_api_key'] = 'sk-stored-anthropic-should-be-ignored';

		$provider = new AnthropicProvider( array( 'api_key' => 'sk-ant-from-config' ) );

		$this->assertSame( 'sk-ant-from-config', $this->read_api_key( $provider ) );
	}

	public function test_garbage_stored_blob_yields_empty_key(): void {
		if ( ! function_exists( 'sodium_crypto_secretbox' ) ) {
			$this->markTestSkipped( 'libsodium not available.' );
		}

		$GLOBALS['__wp_stubs']['options']['vitalink_ci_anthropic_api_key'] = 'v1:' . base64_encode( 'tiny' );

		$provider = new AnthropicProvider();

		$this->assertSame( '', $this->read_api_key( $provider ) );
		$this->assertFalse( $provider->is_configured() );
	}

	public function test_anthropic_provider_does_not_read_openai_option(): void {
		// Regression guard for C2: previously the sanitize callback
		// always returned the OpenAI key, so on cold start with only
		// the openai option set, the Anthropic provider would see
		// that key. Confirm AnthropicProvider never reads the openai
		// option, even when both are present and only openai is set.
		$GLOBALS['__wp_stubs']['options']['vitalink_ci_openai_api_key'] = 'sk-only-openai-set';

		$provider = new AnthropicProvider();

		$this->assertSame( '', $this->read_api_key( $provider ) );
		$this->assertFalse( $provider->is_configured() );
	}

	public function test_get_id_and_label_are_stable(): void {
		$provider = new AnthropicProvider();

		$this->assertSame( 'anthropic', $provider->get_id() );
		$this->assertStringContainsString( 'Anthropic', $provider->get_label() );
		$this->assertNotEmpty( $provider->get_available_models() );
	}

	private function read_api_key( AnthropicProvider $provider ): string {
		$ref  = new \ReflectionClass( $provider );
		$prop = $ref->getProperty( 'api_key' );
		$prop->setAccessible( true );
		return (string) $prop->getValue( $provider );
	}
}
