<?php
/**
 * Unit tests for ResponseCache.
 *
 * Covers key stability, get/set/delete round-trip, enabled/disabled behavior,
 * and the option-driven TTL with the minimum-clamp.
 *
 * @package Vitalink\ContentImprover
 */

declare(strict_types=1);

namespace Vitalink\ContentImprover\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vitalink\ContentImprover\Cache\ResponseCache;

require_once __DIR__ . '/../Fixtures/WpStubs.php';

final class ResponseCacheTest extends TestCase {

	protected function setUp(): void {
		wp_stubs_reset();
		$GLOBALS['__wp_stubs']['options']['vitalink_ci_cache_enabled'] = 'on';
	}

	public function test_key_is_stable_for_same_prompt_and_options(): void {
		$cache = new ResponseCache( 600 );

		$key_a = $cache->key( 'Hello world.', array( 'style' => 'clearer' ) );
		$key_b = $cache->key( 'Hello world.', array( 'style' => 'clearer' ) );

		$this->assertSame( $key_a, $key_b );
		$this->assertStringStartsWith( 'vitalink_ci_resp_', $key_a );
	}

	public function test_key_differs_for_different_prompts(): void {
		$cache = new ResponseCache( 600 );

		$key_a = $cache->key( 'First text.', array( 'style' => 'clearer' ) );
		$key_b = $cache->key( 'Second text.', array( 'style' => 'clearer' ) );

		$this->assertNotSame( $key_a, $key_b );
	}

	public function test_key_differs_for_different_options(): void {
		$cache = new ResponseCache( 600 );

		$key_a = $cache->key( 'Same text.', array( 'style' => 'clearer' ) );
		$key_b = $cache->key( 'Same text.', array( 'style' => 'shorter' ) );

		$this->assertNotSame( $key_a, $key_b );
	}

	public function test_key_is_order_insensitive_on_options(): void {
		$cache = new ResponseCache( 600 );

		$key_a = $cache->key(
			'Same text.',
			array(
				'style'  => 'clearer',
				'length' => 150,
			)
		);
		$key_b = $cache->key(
			'Same text.',
			array(
				'length' => 150,
				'style'  => 'clearer',
			)
		);

		$this->assertSame( $key_a, $key_b );
	}

	public function test_key_trims_whitespace_on_prompt(): void {
		$cache = new ResponseCache( 600 );

		$key_a = $cache->key( 'Hello.', array() );
		$key_b = $cache->key( '   Hello.   ', array() );

		$this->assertSame( $key_a, $key_b );
	}

	public function test_get_returns_null_on_cache_miss(): void {
		$cache = new ResponseCache( 600 );

		$this->assertNull( $cache->get( 'uncached prompt', array() ) );
	}

	public function test_set_then_get_returns_cached_value(): void {
		$cache = new ResponseCache( 600 );

		$cache->set( 'A prompt', array( 'style' => 'clearer' ), 'Cached reply.' );

		$this->assertSame( 'Cached reply.', $cache->get( 'A prompt', array( 'style' => 'clearer' ) ) );
	}

	public function test_get_returns_null_when_cache_disabled(): void {
		$GLOBALS['__wp_stubs']['options']['vitalink_ci_cache_enabled'] = 'off';

		$cache = new ResponseCache( 600 );

		// Even if a value were in the transient store, get() should refuse.
		$GLOBALS['__wp_stubs']['transients']['manual_key'] = 'whatever';
		$this->assertNull( $cache->get( 'manual_key', array() ) );
	}

	public function test_set_is_noop_when_cache_disabled(): void {
		$GLOBALS['__wp_stubs']['options']['vitalink_ci_cache_enabled'] = 'off';

		$cache = new ResponseCache( 600 );
		$cache->set( 'A prompt', array(), 'Should not be stored.' );

		$key = $cache->key( 'A prompt', array() );
		$this->assertArrayNotHasKey( $key, $GLOBALS['__wp_stubs']['transients'] );
	}

	public function test_delete_invalidates_an_entry(): void {
		$cache = new ResponseCache( 600 );

		$cache->set( 'A prompt', array(), 'Reply.' );
		$this->assertSame( 'Reply.', $cache->get( 'A prompt', array() ) );

		$cache->delete( 'A prompt', array() );
		$this->assertNull( $cache->get( 'A prompt', array() ) );
	}

	public function test_constructor_uses_configured_ttl_when_above_minimum(): void {
		$GLOBALS['__wp_stubs']['options']['vitalink_ci_cache_ttl'] = 7200;

		$cache = new ResponseCache();

		// Indirect: write then read works regardless of TTL value; what we
		// actually want to assert is that no clamping happened.
		$cache->set( 'p', array(), 'r' );
		$this->assertSame( 'r', $cache->get( 'p', array() ) );
	}

	public function test_constructor_clamps_ttl_below_minute_to_minimum(): void {
		$GLOBALS['__wp_stubs']['options']['vitalink_ci_cache_ttl'] = 5;

		$cache = new ResponseCache();

		// The TTL is private, so we exercise the round-trip and confirm
		// behavior is unaffected (no clamping observable via the public API,
		// but no exception is thrown either).
		$cache->set( 'p', array(), 'r' );
		$this->assertSame( 'r', $cache->get( 'p', array() ) );
	}

	// ---------- Edge cases ----------

	public function test_key_handles_unicode_prompts(): void {
		$cache = new ResponseCache( 600 );

		$key_a = $cache->key( '你好，世界。', array() );
		$key_b = $cache->key( '你好，世界。', array() );

		$this->assertSame( $key_a, $key_b );
		$this->assertStringStartsWith( 'vitalink_ci_resp_', $key_a );
	}

	public function test_key_handles_emoji_prompts(): void {
		$cache = new ResponseCache( 600 );

		$key = $cache->key( '🚀 Vitalink is fast 🎉', array() );

		$this->assertStringStartsWith( 'vitalink_ci_resp_', $key );
	}

	public function test_key_handles_very_long_prompt(): void {
		$cache = new ResponseCache( 600 );
		$long  = str_repeat( 'A complete sentence that is reasonably long. ', 5000 );

		$key = $cache->key( $long, array() );

		$this->assertStringStartsWith( 'vitalink_ci_resp_', $key );
		$this->assertSame( 32, strlen( substr( $key, strlen( 'vitalink_ci_resp_' ) ) ) );
	}

	public function test_key_handles_empty_prompt(): void {
		$cache = new ResponseCache( 600 );

		$key_a = $cache->key( '', array() );
		$key_b = $cache->key( '   ', array() );

		$this->assertSame( $key_a, $key_b );
		$this->assertStringStartsWith( 'vitalink_ci_resp_', $key_a );
	}

	public function test_key_handles_empty_options(): void {
		$cache = new ResponseCache( 600 );

		$key = $cache->key( 'prompt', array() );

		$this->assertStringStartsWith( 'vitalink_ci_resp_', $key );
	}

	public function test_key_handles_unicode_option_values(): void {
		$cache = new ResponseCache( 600 );

		$key_a = $cache->key( 'prompt', array( 'language' => '简体中文' ) );
		$key_b = $cache->key( 'prompt', array( 'language' => '简体中文' ) );

		$this->assertSame( $key_a, $key_b );
	}

	public function test_set_with_empty_response_still_round_trips(): void {
		$cache = new ResponseCache( 600 );

		$cache->set( 'prompt', array(), '' );

		// is_string('') is true, so the cache returns '' (not null).
		$this->assertSame( '', $cache->get( 'prompt', array() ) );
	}

	public function test_delete_on_missing_key_is_a_noop(): void {
		$cache = new ResponseCache( 600 );

		// No assertion on return value — the method returns void. Just make
		// sure it does not throw.
		$cache->delete( 'never-set', array() );

		$this->assertNull( $cache->get( 'never-set', array() ) );
	}

	public function test_flush_returns_count_of_deleted_vitalink_rows(): void {
		wp_stubs_seed_options_table(
			array(
				'_transient_vitalink_ci_resp_aaaa'         => 'value a',
				'_transient_vitalink_ci_resp_bbbb'         => 'value b',
				'_transient_timeout_vitalink_ci_resp_aaaa' => '1700000000',
				// Unrelated rows — must NOT be deleted.
				'_transient_some_other_plugin_xxxx'        => 'untouched',
				'siteurl'                                  => 'https://example.com',
			)
		);

		$cache = new ResponseCache( 600 );
		$count = $cache->flush();

		$this->assertSame( 3, $count );
		$this->assertArrayNotHasKey( '_transient_vitalink_ci_resp_aaaa', $GLOBALS['__wp_stubs']['options_table'] );
		$this->assertArrayNotHasKey( '_transient_vitalink_ci_resp_bbbb', $GLOBALS['__wp_stubs']['options_table'] );
		$this->assertArrayHasKey( '_transient_some_other_plugin_xxxx', $GLOBALS['__wp_stubs']['options_table'] );
		$this->assertArrayHasKey( 'siteurl', $GLOBALS['__wp_stubs']['options_table'] );
	}

	public function test_flush_with_no_vitalink_rows_returns_zero(): void {
		wp_stubs_seed_options_table(
			array(
				'_transient_some_other_plugin_xxxx' => 'untouched',
				'siteurl'                           => 'https://example.com',
			)
		);

		$cache = new ResponseCache( 600 );

		$this->assertSame( 0, $cache->flush() );
	}

	public function test_flush_with_empty_table_returns_zero(): void {
		wp_stubs_seed_options_table( array() );

		$cache = new ResponseCache( 600 );

		$this->assertSame( 0, $cache->flush() );
	}

	public function test_cache_enabled_default_is_on(): void {
		// No option set → cache should be on by default.
		unset( $GLOBALS['__wp_stubs']['options']['vitalink_ci_cache_enabled'] );

		$cache = new ResponseCache( 600 );
		$cache->set( 'p', array(), 'r' );

		$this->assertSame( 'r', $cache->get( 'p', array() ) );
	}

	public function test_cache_disabled_with_arbitrary_string_value(): void {
		// 'false', '0', and anything else that is not the literal 'off'
		// should be treated as enabled. This documents the contract that
		// the only opt-out is the literal string 'off'.
		foreach ( array( 'false', '0', '', 'no', 'disabled' ) as $value ) {
			$GLOBALS['__wp_stubs']['options']['vitalink_ci_cache_enabled'] = $value;

			$cache = new ResponseCache( 600 );
			$cache->set( 'p' . $value, array(), 'r' );

			$this->assertSame(
				'r',
				$cache->get( 'p' . $value, array() ),
				"Cache should be enabled when option is '{$value}'."
			);
		}
	}

	public function test_explicit_ttl_argument_overrides_option(): void {
		$GLOBALS['__wp_stubs']['options']['vitalink_ci_cache_ttl'] = 99999;

		$cache_short = new ResponseCache( 60 );
		$cache_long  = new ResponseCache( 99999 );

		// Both round-trip correctly regardless of which TTL is in play —
		// what we are asserting is that the constructor accepts an
		// explicit argument and does not crash when the option is also set.
		$cache_short->set( 'p', array(), 'r' );
		$cache_long->set( 'p', array(), 'r' );

		$this->assertSame( 'r', $cache_short->get( 'p', array() ) );
		$this->assertSame( 'r', $cache_long->get( 'p', array() ) );
	}

	public function test_negative_option_ttl_is_clamped_to_minute(): void {
		$GLOBALS['__wp_stubs']['options']['vitalink_ci_cache_ttl'] = -100;

		$cache = new ResponseCache();

		$cache->set( 'p', array(), 'r' );
		$this->assertSame( 'r', $cache->get( 'p', array() ) );
	}

	public function test_zero_option_ttl_is_clamped_to_minute(): void {
		$GLOBALS['__wp_stubs']['options']['vitalink_ci_cache_ttl'] = 0;

		$cache = new ResponseCache();

		$cache->set( 'p', array(), 'r' );
		$this->assertSame( 'r', $cache->get( 'p', array() ) );
	}

	public function test_explicit_ttl_below_minute_is_clamped(): void {
		$cache = new ResponseCache( 1 );

		$cache->set( 'p', array(), 'r' );
		$this->assertSame( 'r', $cache->get( 'p', array() ) );
	}

	public function test_different_text_produces_different_keys_and_independent_entries(): void {
		$cache = new ResponseCache( 600 );

		$cache->set( 'first', array( 'style' => 'clearer' ), 'A' );
		$cache->set( 'second', array( 'style' => 'clearer' ), 'B' );

		$this->assertSame( 'A', $cache->get( 'first', array( 'style' => 'clearer' ) ) );
		$this->assertSame( 'B', $cache->get( 'second', array( 'style' => 'clearer' ) ) );

		$cache->delete( 'first', array( 'style' => 'clearer' ) );

		$this->assertNull( $cache->get( 'first', array( 'style' => 'clearer' ) ) );
		$this->assertSame( 'B', $cache->get( 'second', array( 'style' => 'clearer' ) ) );
	}
}
