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

	// ---------- Edge cases ----------

	public function test_text_is_trimmed_before_provider_call(): void {
		$provider = new \Vitalink\ContentImprover\Tests\Fixtures\FakeProvider( 'Reply.' );
		$feature  = new ContentImprover( null, $provider );

		$feature->improve( "  hello  \n\n  ", ContentImprover::STYLE_CLEARER );

		// The provider's prompt should contain the trimmed text, not the
		// surrounding whitespace.
		$this->assertNotNull( $provider->last_prompt_seen );
		$this->assertStringContainsString( 'hello', $provider->last_prompt_seen );
		$this->assertStringNotContainsString( "  hello  ", $provider->last_prompt_seen );
	}

	public function test_provider_response_is_trimmed(): void {
		$provider = new \Vitalink\ContentImprover\Tests\Fixtures\FakeProvider( "  \nResult text.\n  " );
		$feature  = new ContentImprover( null, $provider );

		$result = $feature->improve( 'input', ContentImprover::STYLE_CLEARER );

		$this->assertSame( 'Result text.', $result );
	}

	public function test_different_styles_produce_different_cache_entries(): void {
		$cache    = new ResponseCache();
		$provider = new \Vitalink\ContentImprover\Tests\Fixtures\FakeProvider( 'Reply.' );
		$feature  = new ContentImprover( $cache, $provider );

		$feature->improve( 'Same text.', ContentImprover::STYLE_CLEARER );
		$feature->improve( 'Same text.', ContentImprover::STYLE_SHORTER );
		$feature->improve( 'Same text.', ContentImprover::STYLE_FORMAL );

		// Each call hits the provider because the cache key includes style.
		// The last call (FORMAL) used the formal instruction — check it.
		$this->assertNotNull( $provider->last_prompt_seen );
		$this->assertStringContainsString( 'formal', $provider->last_prompt_seen );
	}

	public function test_provider_throws_propagates_as_provider_exception(): void {
		$provider = new class implements ProviderInterface {
			public function get_id(): string { return 'throwing'; }
			public function get_label(): string { return 'Throwing'; }
			public function is_configured(): bool { return true; }
			public function get_available_models(): array { return array( 'm' ); }
			public function complete( string $prompt, array $options = [] ): string {
				throw new \Vitalink\ContentImprover\Providers\ProviderException(
					'upstream down',
					\Vitalink\ContentImprover\Providers\ProviderException::CODE_SERVER,
					503
				);
			}
			public function stream( string $prompt, array $options = [] ): \Generator { yield ''; }
		};

		$feature = new ContentImprover( null, $provider );

		try {
			$feature->improve( 'text', ContentImprover::STYLE_CLEARER );
			$this->fail( 'Expected ProviderException to propagate.' );
		} catch ( \Vitalink\ContentImprover\Providers\ProviderException $e ) {
			$this->assertSame( 'upstream down', $e->getMessage() );
			$this->assertSame( 'server_error', $e->get_error_code() );
			$this->assertSame( 503, $e->get_http_status() );
		}
	}

	public function test_cache_disabled_calls_provider_every_time(): void {
		$GLOBALS['__wp_stubs']['options']['vitalink_ci_cache_enabled'] = 'off';

		$cache    = new ResponseCache();
		$provider = new class implements ProviderInterface {
			public int $call_count = 0;
			public function get_id(): string { return 'counting'; }
			public function get_label(): string { return 'Counting'; }
			public function is_configured(): bool { return true; }
			public function get_available_models(): array { return array( 'm' ); }
			public function complete( string $prompt, array $options = [] ): string {
				++$this->call_count;
				return 'reply-' . $this->call_count;
			}
			public function stream( string $prompt, array $options = [] ): \Generator { yield ''; }
		};

		$feature = new ContentImprover( $cache, $provider );

		$first  = $feature->improve( 'same input', ContentImprover::STYLE_CLEARER );
		$second = $feature->improve( 'same input', ContentImprover::STYLE_CLEARER );
		$third  = $feature->improve( 'same input', ContentImprover::STYLE_CLEARER );

		$this->assertSame( 3, $provider->call_count, 'Cache off → provider must be hit every call.' );
		$this->assertSame( 'reply-1', $first );
		$this->assertSame( 'reply-2', $second );
		$this->assertSame( 'reply-3', $third );
	}

	public function test_cache_enabled_calls_provider_only_once(): void {
		$cache    = new ResponseCache();
		$provider = new class implements ProviderInterface {
			public int $call_count = 0;
			public function get_id(): string { return 'counting'; }
			public function get_label(): string { return 'Counting'; }
			public function is_configured(): bool { return true; }
			public function get_available_models(): array { return array( 'm' ); }
			public function complete( string $prompt, array $options = [] ): string {
				++$this->call_count;
				return 'reply-' . $this->call_count;
			}
			public function stream( string $prompt, array $options = [] ): \Generator { yield ''; }
		};

		$feature = new ContentImprover( $cache, $provider );

		$first  = $feature->improve( 'same input', ContentImprover::STYLE_CLEARER );
		$second = $feature->improve( 'same input', ContentImprover::STYLE_CLEARER );

		$this->assertSame( 1, $provider->call_count, 'Cache on → provider must be hit only once.' );
		$this->assertSame( 'reply-1', $first );
		$this->assertSame( 'reply-1', $second );
	}

	public function test_default_style_is_clearer(): void {
		$provider = new \Vitalink\ContentImprover\Tests\Fixtures\FakeProvider( 'Reply.' );
		$feature  = new ContentImprover( null, $provider );

		$feature->improve( 'text' );

		$this->assertStringContainsString( 'clearer', $provider->last_prompt_seen );
	}

	public function test_all_three_styles_produce_distinct_prompts(): void {
		$provider = new \Vitalink\ContentImprover\Tests\Fixtures\FakeProvider( 'Reply.' );
		$feature  = new ContentImprover( null, $provider );

		$feature->improve( 'x', ContentImprover::STYLE_CLEARER );
		$prompt_clearer = $provider->last_prompt_seen;

		$feature->improve( 'x', ContentImprover::STYLE_SHORTER );
		$prompt_shorter = $provider->last_prompt_seen;

		$feature->improve( 'x', ContentImprover::STYLE_FORMAL );
		$prompt_formal = $provider->last_prompt_seen;

		$this->assertNotSame( $prompt_clearer, $prompt_shorter );
		$this->assertNotSame( $prompt_shorter, $prompt_formal );
		$this->assertNotSame( $prompt_clearer, $prompt_formal );

		$this->assertStringContainsString( 'clearer', $prompt_clearer );
		$this->assertStringContainsString( 'half as long', $prompt_shorter );
		$this->assertStringContainsString( 'formal', $prompt_formal );
	}

	public function test_unicode_text_is_passed_through_unchanged(): void {
		$provider = new \Vitalink\ContentImprover\Tests\Fixtures\FakeProvider( 'ok' );
		$feature  = new ContentImprover( null, $provider );

		$feature->improve( '你好，世界 🚀', ContentImprover::STYLE_CLEARER );

		$this->assertStringContainsString( '你好，世界 🚀', $provider->last_prompt_seen );
	}

	public function test_very_long_text_is_passed_through(): void {
		$long     = str_repeat( 'Sentence. ', 2000 );
		$provider = new \Vitalink\ContentImprover\Tests\Fixtures\FakeProvider( 'ok' );
		$feature  = new ContentImprover( null, $provider );

		$feature->improve( $long, ContentImprover::STYLE_CLEARER );

		// Don't compare full equality (PHPUnit truncates the failure message
		// and produces noise); instead assert the prompt contains the
		// distinctive start of the long text and is much larger than just
		// the instruction alone.
		$this->assertNotNull( $provider->last_prompt_seen );
		$this->assertGreaterThan( 5000, strlen( $provider->last_prompt_seen ) );
		$this->assertStringContainsString( 'Sentence. Sentence. Sentence.', $provider->last_prompt_seen );
	}
}
