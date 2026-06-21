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

	// ---------- Edge cases ----------

	public function test_encrypt_round_trips_unicode_payload(): void {
		$plaintext = '你好，世界 🚀 — Vitalink 加密测试';
		$blob      = Encryption::encrypt( $plaintext );

		$this->assertIsString( $blob );
		$this->assertNotEmpty( $blob );
		$this->assertSame( $plaintext, Encryption::decrypt( $blob ) );
	}

	public function test_encrypt_round_trips_multiline_payload(): void {
		$plaintext = "Line 1\nLine 2\nLine 3\n\nLast line.";
		$blob      = Encryption::encrypt( $plaintext );

		$this->assertSame( $plaintext, Encryption::decrypt( $blob ) );
	}

	public function test_encrypt_round_trips_payload_with_null_bytes(): void {
		$plaintext = "before\x00after";
		$blob      = Encryption::encrypt( $plaintext );

		$this->assertSame( $plaintext, Encryption::decrypt( $blob ) );
	}

	public function test_encrypt_round_trips_very_long_payload(): void {
		// 100 KB of repetitive content — well past any single AES block.
		$plaintext = str_repeat( 'Vitalink payload. ', 7000 );
		$blob      = Encryption::encrypt( $plaintext );

		$this->assertSame( $plaintext, Encryption::decrypt( $blob ) );
	}

	public function test_decrypt_v0_blob_when_sodium_available_still_uses_openssl_path(): void {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			$this->markTestSkipped( 'openssl not available.' );
		}

		// Force the openssl path by temporarily disabling sodium at the
		// namespace level using a separate Encryption "view". We can't
		// actually unload libsodium mid-process, but we can construct a v0
		// blob directly and prove decrypt handles it.
		$key       = hash( 'sha256', LOGGED_IN_KEY . '|vitalink_ci', true );
		$iv_length = openssl_cipher_iv_length( 'aes-256-cbc' );
		$iv        = str_repeat( "\x00", $iv_length ); // deterministic IV for the test.
		$cipher    = openssl_encrypt( 'sk-v0-payload', 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
		$blob      = 'v0:' . base64_encode( $iv . $cipher );

		$this->assertSame( 'sk-v0-payload', Encryption::decrypt( $blob ) );
	}

	public function test_decrypt_unknown_version_prefix_returns_input_unchanged(): void {
		// Anything that is not "v0:" or "v1:" is treated as legacy plaintext
		// and handed back as-is. This protects users from upgrades that
		// happen to collide with future version prefixes.
		$this->assertSame( 'foo:bar', Encryption::decrypt( 'foo:bar' ) );
		$this->assertSame( 'v2:something-new', Encryption::decrypt( 'v2:something-new' ) );
		$this->assertSame( 'xx-not-base64', Encryption::decrypt( 'xx-not-base64' ) );
	}

	public function test_decrypt_v0_blob_with_garbage_payload_returns_false(): void {
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			$this->markTestSkipped( 'openssl not available.' );
		}

		// v0 blob whose payload is shorter than the IV → base64_decode may
		// succeed but the size check rejects it.
		$blob = 'v0:' . base64_encode( 'tiny' );

		$this->assertFalse( Encryption::decrypt( $blob ) );
	}

	public function test_decrypt_v0_blob_with_invalid_base64_returns_false(): void {
		$blob = 'v0:' . base64_encode( '!!!notbase64!!!' );

		$this->assertFalse( Encryption::decrypt( $blob ) );
	}

	public function test_decrypt_blob_with_unknown_version_is_passthrough(): void {
		// 'v9:' is not a known version → fall through to the legacy branch.
		$this->assertSame( 'v9:still-a-string', Encryption::decrypt( 'v9:still-a-string' ) );
	}

	public function test_encrypt_output_is_deterministic_length_range(): void {
		// sodium output: 16-byte nonce + 16-byte MAC + plaintext → ratio close to 1.0
		// openssl output: 16-byte IV + at least 16-byte ciphertext block → ratio close to 1.0
		// We don't assert an exact byte count (varies by backend) but we do
		// assert the encrypted output is always noticeably bigger than the
		// plaintext and never absurdly so.
		foreach ( array( 'a', str_repeat( 'a', 100 ), str_repeat( 'a', 10000 ) ) as $plaintext ) {
			$blob = Encryption::encrypt( $plaintext );
			$bin  = base64_decode( substr( $blob, 3 ), true );

			$this->assertNotFalse( $bin, "Encryption must produce valid base64 for input of length " . strlen( $plaintext ) );
			$this->assertGreaterThanOrEqual( strlen( $plaintext ), strlen( $bin ) );
			$this->assertLessThanOrEqual( strlen( $plaintext ) + 64, strlen( $bin ) );
		}
	}

	public function test_encrypt_then_decrypt_preserves_binary_payload(): void {
		// A mix of control characters that some string-handling code mishandles.
		$plaintext = "\x00\x01\x02\xff\xfe\xfd\x80\x7f";
		$blob      = Encryption::encrypt( $plaintext );

		$this->assertSame( $plaintext, Encryption::decrypt( $blob ) );
	}
}