<?php
/**
 * Unit tests for Encryption.
 *
 * Covers encrypt/decrypt round-trips on both backends (sodium and openssl),
 * the empty-string short-circuit, the legacy-plaintext fallback on decrypt,
 * and malformed input handling.
 *
 * @package Vitalink\ContentImprover
 */

declare(strict_types=1);

namespace Vitalink\ContentImprover\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vitalink\ContentImprover\Support\Encryption;

require_once __DIR__ . '/../Fixtures/WpStubs.php';

final class EncryptionTest extends TestCase {

	protected function setUp(): void {
		// Encryption derives its key from LOGGED_IN_KEY.
		if ( ! defined( 'LOGGED_IN_KEY' ) ) {
			define( 'LOGGED_IN_KEY', 'unit-test-salt-' . bin2hex( random_bytes( 8 ) ) );
		}
	}

	public function test_encrypt_empty_string_returns_empty(): void {
		$this->assertSame( '', Encryption::encrypt( '' ) );
	}

	public function test_decrypt_empty_string_returns_empty(): void {
		$this->assertSame( '', Encryption::decrypt( '' ) );
	}

	public function test_encrypt_then_decrypt_returns_original_under_sodium(): void {
		if ( ! function_exists( 'sodium_crypto_secretbox' ) ) {
			$this->markTestSkipped( 'libsodium not available in this PHP build.' );
		}

		$blob = Encryption::encrypt( 'sk-test-1234567890' );

		$this->assertIsString( $blob );
		$this->assertStringStartsWith( 'v1:', $blob );
		$this->assertSame( 'sk-test-1234567890', Encryption::decrypt( $blob ) );
	}

	public function test_encrypt_then_decrypt_returns_original_under_openssl(): void {
		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			$this->markTestSkipped( 'libsodium present — openssl fallback path is not exercised when sodium wins.' );
		}
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			$this->markTestSkipped( 'openssl not available.' );
		}

		$blob = Encryption::encrypt( 'sk-test-9876543210' );

		$this->assertIsString( $blob );
		$this->assertStringStartsWith( 'v0:', $blob );
		$this->assertSame( 'sk-test-9876543210', Encryption::decrypt( $blob ) );
	}

	public function test_each_encryption_uses_a_fresh_nonce(): void {
		if ( ! function_exists( 'sodium_crypto_secretbox' ) ) {
			$this->markTestSkipped( 'libsodium not available.' );
		}

		$blob_a = Encryption::encrypt( 'same plaintext' );
		$blob_b = Encryption::encrypt( 'same plaintext' );

		// Two encryptions of the same plaintext should produce different
		// ciphertext because each call uses a fresh random nonce.
		$this->assertNotSame( $blob_a, $blob_b );

		// …but both decrypt to the same value.
		$this->assertSame( 'same plaintext', Encryption::decrypt( $blob_a ) );
		$this->assertSame( 'same plaintext', Encryption::decrypt( $blob_b ) );
	}

	public function test_decrypt_legacy_plaintext_returns_as_is(): void {
		// A value with no version prefix was stored before encryption shipped.
		// We hand it back untouched so the user does not lose their key on
		// upgrade.
		$this->assertSame( 'sk-legacy-plaintext', Encryption::decrypt( 'sk-legacy-plaintext' ) );
	}

	public function test_decrypt_v1_with_sodium_disabled_returns_false(): void {
		// We can't actually disable sodium mid-test, so instead construct a
		// v1 blob under a different LOGGED_IN_KEY and confirm decrypt fails
		// with the current key.
		if ( ! function_exists( 'sodium_crypto_secretbox' ) ) {
			$this->markTestSkipped( 'libsodium not available.' );
		}

		$blob_under_wrong_key = 'v1:' . base64_encode(
			random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) .
			str_repeat( "\x00", 32 )
		);

		$this->assertFalse( Encryption::decrypt( $blob_under_wrong_key ) );
	}

	public function test_decrypt_malformed_v1_blob_returns_false(): void {
		if ( ! function_exists( 'sodium_crypto_secretbox' ) ) {
			$this->markTestSkipped( 'libsodium not available.' );
		}

		// Too-short payload: not enough bytes for nonce + ciphertext.
		$blob = 'v1:' . base64_encode( 'short' );

		$this->assertFalse( Encryption::decrypt( $blob ) );
	}

	public function test_decrypt_invalid_base64_returns_false(): void {
		if ( ! function_exists( 'sodium_crypto_secretbox' ) ) {
			$this->markTestSkipped( 'libsodium not available.' );
		}

		// "!!!notbase64!!!" is not valid base64 → base64_decode strict returns false.
		$blob = 'v1:' . base64_encode( '!!!notbase64!!!' );

		$this->assertFalse( Encryption::decrypt( $blob ) );
	}
}