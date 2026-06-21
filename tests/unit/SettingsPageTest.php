<?php
/**
 * Unit tests for SettingsPage secret sanitization.
 *
 * The sanitize callback is the gate that turns a form input into the
 * value stored in wp_options. It must:
 *   - encrypt non-empty inputs,
 *   - leave an existing stored value alone when the form input is empty,
 *   - read the *correct* option when "leaving existing" — i.e. the
 *     Anthropic sanitizer must not return the OpenAI key (regression
 *     guard for C2).
 *
 * @package Vitalink\ContentImprover
 */

declare(strict_types=1);

namespace Vitalink\ContentImprover\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vitalink\ContentImprover\Admin\SettingsPage;
use Vitalink\ContentImprover\Support\Encryption;

require_once __DIR__ . '/../Fixtures/WpStubs.php';

final class SettingsPageTest extends TestCase {

	private SettingsPage $page;

	protected function setUp(): void {
		wp_stubs_reset();
		if ( ! defined( 'LOGGED_IN_KEY' ) ) {
			define( 'LOGGED_IN_KEY', 'unit-test-salt-' . bin2hex( random_bytes( 8 ) ) );
		}
		$this->page = new SettingsPage();
	}

	public function test_empty_input_returns_empty_when_option_unset(): void {
		$sanitizer = $this->make_sanitizer( 'vitalink_ci_openai_api_key' );

		$this->assertSame( '', $sanitizer( '' ) );
	}

	public function test_non_empty_input_is_encrypted(): void {
		if ( ! function_exists( 'sodium_crypto_secretbox' ) ) {
			$this->markTestSkipped( 'libsodium not available.' );
		}

		$sanitizer = $this->make_sanitizer( 'vitalink_ci_openai_api_key' );

		$stored = $sanitizer( 'sk-new-key' );

		$this->assertIsString( $stored );
		$this->assertNotSame( 'sk-new-key', $stored, 'Stored value must be encrypted, not plaintext.' );
		$this->assertSame( 'sk-new-key', Encryption::decrypt( $stored ) );
	}

	public function test_empty_input_preserves_existing_value_for_openai(): void {
		$GLOBALS['__wp_stubs']['options']['vitalink_ci_openai_api_key'] = 'v1:existing-openai-blob';
		$sanitizer = $this->make_sanitizer( 'vitalink_ci_openai_api_key' );

		$this->assertSame( 'v1:existing-openai-blob', $sanitizer( '' ) );
	}

	public function test_empty_input_preserves_existing_value_for_anthropic(): void {
		// Regression for C2: previously the hard-coded "leave existing"
		// always read vitalink_ci_openai_api_key, so the Anthropic
		// key could never be cleared by re-submitting the form blank.
		$GLOBALS['__wp_stubs']['options']['vitalink_ci_anthropic_api_key'] = 'v1:existing-anthropic-blob';
		$GLOBALS['__wp_stubs']['options']['vitalink_ci_openai_api_key']    = 'v1:existing-openai-blob';
		$sanitizer = $this->make_sanitizer( 'vitalink_ci_anthropic_api_key' );

		$this->assertSame(
			'v1:existing-anthropic-blob',
			$sanitizer( '' ),
			'Anthropic sanitizer must preserve the Anthropic slot, not the OpenAI one.'
		);
	}

	public function test_anthropic_sanitizer_does_not_read_openai_option(): void {
		// Even with no Anthropic key set, the Anthropic sanitizer must
		// not fall back to the OpenAI one when given an empty form input.
		$GLOBALS['__wp_stubs']['options']['vitalink_ci_openai_api_key'] = 'v1:openai-blob';
		$sanitizer = $this->make_sanitizer( 'vitalink_ci_anthropic_api_key' );

		$this->assertSame( '', $sanitizer( '' ) );
	}

	public function test_openai_sanitizer_does_not_read_anthropic_option(): void {
		$GLOBALS['__wp_stubs']['options']['vitalink_ci_anthropic_api_key'] = 'v1:anthropic-blob';
		$sanitizer = $this->make_sanitizer( 'vitalink_ci_openai_api_key' );

		$this->assertSame( '', $sanitizer( '' ) );
	}

	public function test_independent_sanitizers_round_trip_separate_values(): void {
		if ( ! function_exists( 'sodium_crypto_secretbox' ) ) {
			$this->markTestSkipped( 'libsodium not available.' );
		}

		$openai    = $this->make_sanitizer( 'vitalink_ci_openai_api_key' );
		$anthropic = $this->make_sanitizer( 'vitalink_ci_anthropic_api_key' );

		$openai_blob    = $openai( 'sk-openai-value' );
		$anthropic_blob = $anthropic( 'sk-anthropic-value' );

		// Each call produces a distinct ciphertext blob.
		$this->assertNotSame( $openai_blob, $anthropic_blob );
		// Each blob decrypts back to its own plaintext.
		$this->assertSame( 'sk-openai-value', Encryption::decrypt( $openai_blob ) );
		$this->assertSame( 'sk-anthropic-value', Encryption::decrypt( $anthropic_blob ) );
	}

	/**
	 * Reach the private make_secret_sanitizer() via reflection.
	 */
	private function make_sanitizer( string $option_name ): \Closure {
		$ref  = new \ReflectionClass( $this->page );
		$meth = $ref->getMethod( 'make_secret_sanitizer' );
		$meth->setAccessible( true );
		$closure = $meth->invoke( $this->page, $option_name );
		$this->assertInstanceOf( \Closure::class, $closure );
		return $closure;
	}
}
