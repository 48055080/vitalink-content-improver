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

require_once __DIR__ . '/../Fixtures/FakeProvider.php';

final class ContentImproverTest extends TestCase {

	public function test_improve_uses_provider_and_returns_text(): void {
		$cache    = new class() extends ResponseCache { public function __construct() {} };
		$provider = new \Vitalink\ContentImprover\Tests\Fixtures\FakeProvider( 'Cleaner text.' );
		$feature  = new ContentImprover( $cache, $provider );

		$result = $feature->improve( 'messy text', ContentImprover::STYLE_CLEARER );

		$this->assertSame( 'Cleaner text.', $result );
		$this->assertSame( ContentImprover::STYLE_CLEARER, $provider->last_style_seen );
	}

	public function test_invalid_style_falls_back_to_clearer(): void {
		$provider = new \Vitalink\ContentImprover\Tests\Fixtures\FakeProvider( 'OK' );
		$feature  = new ContentImprover( null, $provider );

		$feature->improve( 'text', 'nonsense' );

		$this->assertSame( ContentImprover::STYLE_CLEARER, $provider->last_style_seen );
	}

	public function test_empty_text_throws(): void {
		$provider = new \Vitalink\ContentImprover\Tests\Fixtures\FakeProvider( '' );
		$feature  = new ContentImprover( null, $provider );

		$this->expectException( \Vitalink\ContentImprover\Providers\ProviderException::class );
		$feature->improve( '   ', ContentImprover::STYLE_SHORTER );
	}
}
