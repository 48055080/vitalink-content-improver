<?php
/**
 * Encryption helper for storing API keys at rest.
 *
 * API keys are stored encrypted in wp_options using sodium (or
 * OpenSSL fallback for older PHP). On read, we decrypt in memory only
 * — keys never appear in option_name, only in transient-derived form
 * when actually in use.
 *
 * @package Vitalink\ContentImprover
 */

declare(strict_types=1);

namespace Vitalink\ContentImprover\Support;

final class Encryption {

	/**
	 * Encrypt a plaintext string for at-rest storage.
	 *
	 * @param string $plaintext The value to encrypt.
	 * @return string|false Base64-encoded ciphertext, or false on failure.
	 */
	public static function encrypt( string $plaintext ) {
		if ( '' === $plaintext ) {
			return '';
		}

		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			$key = self::key();
			if ( false === $key ) {
				return false;
			}
			$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$cipher = sodium_crypto_secretbox( $plaintext, $nonce, $key );
			$blob   = 'v1:' . base64_encode( $nonce . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			return $blob;
		}

		// Fallback: OpenSSL.
		$key = self::key();
		if ( false === $key ) {
			return false;
		}
		$iv_length = openssl_cipher_iv_length( 'aes-256-cbc' );
		if ( false === $iv_length ) {
			return false;
		}
		$iv     = openssl_random_pseudo_bytes( $iv_length );
		$cipher = openssl_encrypt( $plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
		if ( false === $cipher ) {
			return false;
		}
		return 'v0:' . base64_encode( $iv . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt a value previously produced by self::encrypt().
	 *
	 * @param string $blob Encrypted blob (with version prefix).
	 * @return string|false Plaintext, or false on failure.
	 */
	public static function decrypt( string $blob ) {
		if ( '' === $blob ) {
			return '';
		}

		if ( str_starts_with( $blob, 'v1:' ) ) {
			if ( ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
				return false;
			}
			$key = self::key();
			if ( false === $key ) {
				return false;
			}
			$raw = base64_decode( substr( $blob, 3 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			if ( false === $raw || strlen( $raw ) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
				return false;
			}
			$nonce  = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$cipher = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$plain  = sodium_crypto_secretbox_open( $cipher, $nonce, $key );
			return false === $plain ? false : $plain;
		}

		if ( str_starts_with( $blob, 'v0:' ) ) {
			$key = self::key();
			if ( false === $key ) {
				return false;
			}
			$iv_length = openssl_cipher_iv_length( 'aes-256-cbc' );
			if ( false === $iv_length ) {
				return false;
			}
			$raw = base64_decode( substr( $blob, 3 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			if ( false === $raw || strlen( $raw ) < $iv_length ) {
				return false;
			}
			$iv     = substr( $raw, 0, $iv_length );
			$cipher = substr( $raw, $iv_length );
			$plain  = openssl_decrypt( $cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
			return false === $plain ? false : $plain;
		}

		// Legacy plaintext (pre-encryption). Return as-is so the user does not
		// lose their key on upgrade.
		return $blob;
	}

	/**
	 * Resolve an API key for a provider from explicit config or encrypted option.
	 *
	 * Callers (e.g. provider constructors) prefer an explicit $config['api_key']
	 * when given, otherwise they read the encrypted-at-rest wp_options slot
	 * named by $option_name and decrypt it. Returns '' when neither path
	 * yields a value (caller decides whether that means "unconfigured").
	 *
	 * Centralising the read/decrypt contract here means providers don't
	 * each reinvent the fallback rule.
	 *
	 * @param string $option_name wp_options key holding the encrypted key.
	 * @param array  $config      Optional explicit config (e.g. from a custom factory).
	 * @return string Plaintext key, or '' on miss/failure.
	 */
	public static function resolve_api_key( string $option_name, array $config = array() ): string {
		if ( isset( $config['api_key'] ) && '' !== $config['api_key'] ) {
			return (string) $config['api_key'];
		}
		$stored = (string) get_option( $option_name, '' );
		if ( '' === $stored ) {
			return '';
		}
		$plain = self::decrypt( $stored );
		return false === $plain ? '' : $plain;
	}

	/**
	 * Derive a stable 32-byte key from wp_salt().
	 *
	 * @return string|false 32-byte binary key, or false if salts are missing.
	 */
	private static function key() {
		if ( ! defined( 'LOGGED_IN_KEY' ) || '' === LOGGED_IN_KEY ) {
			return false;
		}
		return hash( 'sha256', LOGGED_IN_KEY . '|vitalink_ci', true );
	}
}
