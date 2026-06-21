<?php
/**
 * Unit tests for ContentImprover feature.
 *
 * Uses a FakeProvider to avoid hitting a real API in tests.
 *
 * @package Vitalink\ContentImprover
 */

declare(strict_types=1);

namespace Vitalink\ContentImprover\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vitalink\ContentImprover\Cache\ResponseCache;
use Vitalink\ContentImprover\Features\ContentImprover;
use Vitalink\ContentImprover\Providers\ProviderInterface;

require_once __DIR__ . '/../Fixtures/WpStubs.php';
require_once __DIR__ . '/../Fixtures/FakeProvider.php';

final class ContentImproverTest extends TestCase {

	protected function setUp(): void {
		wp_stubs_reset();
		$GLOBALS['__wp_stubs']['options']['vitalink_ci_cache_enabled'] = 'on';
		$GLOBALS['__wp_stubs']['options']['vitalink_ci_cache_ttl']     = 600;
	}

	public function test_improve_uses_provider_and_returns_text(): void {
		$cache    = new ResponseCache();
		$provider = new \Vitalink\ContentImprover\Tests\Fixtures\FakeProvider( 'Cleaner text.' );
		$feature  = new ContentImprover( $cache, $provider );

		$result = $feature->improve( 'messy text', ContentImprover::STYLE_CLEARER );

		$this->assertSame( 'Cleaner text.', $result );
		// The style is encoded in the prompt body, not in $options.
		$this->assertNotNull( $provider->last_prompt_seen );
		$this->assertStringContainsString( 'clearer', $provider->last_prompt_seen );
		$this->assertStringContainsString( 'messy text', $provider->last_prompt_seen );
		$this->assertSame( 'You are a careful editor. Output only the rewritten text — no preamble, no labels, no quotes.', $provider->last_options_seen['system'] ?? null );
	}

	public function test_improve_caches_result_on_repeat_call(): void {
		$cache    = new ResponseCache();
		$provider = new \Vitalink\ContentImprover\Tests\Fixtures\FakeProvider( 'Cached text.' );
		$feature  = new ContentImprover( $cache, $provider );

		$first  = $feature->improve( 'some text', ContentImprover::STYLE_CLEARER );
		$second = $feature->improve( 'some text', ContentImprover::STYLE_CLEARER );

		$this->assertSame( 'Cached text.', $first );
		$this->assertSame( 'Cached text.', $second );
	}

	public function test_invalid_style_falls_back_to_clearer(): void {
		$provider = new \Vitalink\ContentImprover\Tests\Fixtures\FakeProvider( 'OK' );
		$feature  = new ContentImprover( null, $provider );

		$feature->improve( 'text', 'nonsense' );

		// The bogus style should be normalised to "clearer" — we can prove
		// this by checking the prompt body.
		$this->assertNotNull( $provider->last_prompt_seen );
		$this->assertStringContainsString( 'clearer', $provider->last_prompt_seen );
	}

	public function test_empty_text_throws(): void {
		$provider = new \Vitalink\ContentImprover\Tests\Fixtures\FakeProvider( '' );
		$feature  = new ContentImprover( null, $provider );

		$this->expectException( \Vitalink\ContentImprover\Providers\ProviderException::class );
		$feature->improve( '   ', ContentImprover::STYLE_SHORTER );
	}
}
