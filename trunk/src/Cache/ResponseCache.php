<?php
/**
 * Response cache — stores provider responses keyed by a stable prompt hash.
 *
 * Backed by WordPress transients. The TTL is configurable in settings.
 * Identical repeat requests (same prompt + options) return the cached
 * result without hitting the provider — zero API cost.
 *
 * @package Vitalink\ContentImprover
 */

declare(strict_types=1);

namespace Vitalink\ContentImprover\Cache;

final class ResponseCache {

	private int $ttl;

	public function __construct( ?int $ttl = null ) {
		$configured = (int) get_option( 'vitalink_ci_cache_ttl', DAY_IN_SECONDS * 7 );
		$this->ttl  = $ttl ?? max( MINUTE_IN_SECONDS, $configured );
	}

	/**
	 * Build a stable cache key from prompt + options.
	 */
	public function key( string $prompt, array $options = array() ): string {
		$normalized = array(
			'prompt'  => trim( $prompt ),
			'options' => $this->normalize_options( $options ),
		);
		$blob       = wp_json_encode( $normalized );
		return 'vitalink_ci_resp_' . md5( (string) $blob );
	}

	/**
	 * Retrieve a cached response, or null on miss.
	 */
	public function get( string $prompt, array $options = array() ): ?string {
		if ( ! $this->is_enabled() ) {
			return null;
		}
		$key   = $this->key( $prompt, $options );
		$value = get_transient( $key );
		return is_string( $value ) ? $value : null;
	}

	/**
	 * Store a response in the cache. Silent no-op if caching is disabled.
	 */
	public function set( string $prompt, array $options, string $response ): void {
		if ( ! $this->is_enabled() ) {
			return;
		}
		$key = $this->key( $prompt, $options );
		set_transient( $key, $response, $this->ttl );
	}

	/**
	 * Invalidate a single key.
	 */
	public function delete( string $prompt, array $options = array() ): void {
		delete_transient( $this->key( $prompt, $options ) );
	}

	/**
	 * Invalidate all Vitalink cache entries (used by settings page "Clear cache" button).
	 */
	public function flush(): int {
		global $wpdb;
		$like   = $wpdb->esc_like( '_transient_vitalink_ci_resp_' ) . '%';
		$like_t = $wpdb->esc_like( '_transient_timeout_vitalink_ci_resp_' ) . '%';

		$deleted = (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$like,
				$like_t
			)
		);
		return $deleted;
	}

	private function is_enabled(): bool {
		return 'off' !== get_option( 'vitalink_ci_cache_enabled', 'on' );
	}

	private function normalize_options( array $options ): array {
		// Sort to make order-insensitive.
		ksort( $options );
		return $options;
	}
}
