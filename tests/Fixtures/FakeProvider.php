<?php
/**
 * Test fixture: a fake provider that returns a canned reply.
 *
 * Records the last style/options it was called with so tests can assert
 * on routing behavior.
 *
 * @package Vitalink\ContentImprover
 */

declare(strict_types=1);

namespace Vitalink\ContentImprover\Tests\Fixtures;

use Vitalink\ContentImprover\Providers\ProviderInterface;

final class FakeProvider implements ProviderInterface {

	public ?string $last_style_seen = null;
	public ?array  $last_options_seen = null;

	public function __construct( private string $reply ) {}

	public function get_id(): string {
		return 'fake';
	}

	public function get_label(): string {
		return 'Fake';
	}

	public function is_configured(): bool {
		return true;
	}

	public function get_available_models(): array {
		return array( 'fake-model' );
	}

	public function complete( string $prompt, array $options = [] ): string {
		$this->last_options_seen = $options;
		$this->last_style_seen   = $options['style'] ?? null;
		return $this->reply;
	}

	public function stream( string $prompt, array $options = [] ): \Generator {
		yield $this->reply;
	}
}
