<?php
/**
 * Unit tests for OpenAIProvider API-key resolution.
 *
 * The constructor reads the key from $config first, else from the
 * stored option. The stored option is encrypted at rest by
 * SettingsPage::sanitize_secret(), so the provider must decrypt
 * before use.
 *
 * @package Vitalink\ContentImprover
 */

declare(strict_types=1);

namespace Vitalink\ContentImprover\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vitalink\ContentImprover\Providers\OpenAIProvider;
use Vitalink\ContentImprover\Support\Encryption;

require_once __DIR__ . '/../Fixtures/WpStubs.php';

final class OpenAIProviderTest extends TestCase {

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

		$plaintext = 'sk-test-' . bin2hex( random_bytes( 8 ) );
		$blob      = Encryption::encrypt( $plaintext );
		$GLOBALS['__wp_stubs']['options']['vitalink_ci_openai_api_key'] = $blob;

		$provider = new OpenAIProvider();

		$this->assertSame( $plaintext, $this->read_api_key( $provider ) );
		$this->assertTrue( $provider->is_configured() );
	}

	public function test_legacy_plaintext_option_is_used_as_is(): void {
		// Pre-encryption upgrade path: the stored value is raw plaintext.
		// decrypt() returns it unchanged so the user does not lose their key.
		$plaintext = 'sk-legacy-openai';
		$GLOBALS['__wp_stubs']['options']['vitalink_ci_openai_api_key'] = $plaintext;

		$provider = new OpenAIProvider();

		$this->assertSame( $plaintext, $this->read_api_key( $provider ) );
		$this->assertTrue( $provider->is_configured() );
	}

	public function test_empty_option_means_unconfigured(): void {
		$provider = new OpenAIProvider();

		$this->assertSame( '', $this->read_api_key( $provider ) );
		$this->assertFalse( $provider->is_configured() );
	}

	public function test_explicit_config_overrides_stored_option(): void {
		// If a caller (e.g. tests, custom factory wiring) passes a key
		// in $config it must win over the stored option.
		$GLOBALS['__wp_stubs']['options']['vitalink_ci_openai_api_key'] = 'sk-stored-should-be-ignored';

		$provider = new OpenAIProvider( array( 'api_key' => 'sk-from-config' ) );

		$this->assertSame( 'sk-from-config', $this->read_api_key( $provider ) );
	}

	public function test_garbage_stored_blob_yields_empty_key(): void {
		if ( ! function_exists( 'sodium_crypto_secretbox' ) ) {
			$this->markTestSkipped( 'libsodium not available.' );
		}

		// A v1 blob whose payload is too short → decrypt returns false.
		$GLOBALS['__wp_stubs']['options']['vitalink_ci_openai_api_key'] = 'v1:' . base64_encode( 'tiny' );

		$provider = new OpenAIProvider();

		$this->assertSame( '', $this->read_api_key( $provider ) );
		$this->assertFalse( $provider->is_configured() );
	}

	public function test_get_id_and_label_are_stable(): void {
		$provider = new OpenAIProvider();

		$this->assertSame( 'openai', $provider->get_id() );
		$this->assertSame( 'OpenAI', $provider->get_label() );
		$this->assertNotEmpty( $provider->get_available_models() );
	}

	/**
	 * Read the private $api_key via reflection so tests can assert on
	 * the actual decrypted value without making a real HTTP call.
	 */
	private function read_api_key( OpenAIProvider $provider ): string {
		$ref  = new \ReflectionClass( $provider );
		$prop = $ref->getProperty( 'api_key' );
		$prop->setAccessible( true );
		return (string) $prop->getValue( $provider );
	}
}
