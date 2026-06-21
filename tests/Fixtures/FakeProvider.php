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

	public ?string $last_prompt_seen = null;
	public ?array $last_options_seen = null;

	/**
	 * @param string|array $reply_or_config
	 *   - As a string: the canned reply to return from complete().
	 *   - As an array: the provider config (passed by ProviderFactory).
	 *                  The reply defaults to ''; tests that need a non-empty
	 *                  reply can pass ['reply' => '...'].
	 */
	public function __construct( string|array $reply_or_config = '' ) {
		if ( is_array( $reply_or_config ) ) {
			$reply = (string) ( $reply_or_config['reply'] ?? '' );
		} else {
			$reply = $reply_or_config;
		}
		// Promote the value into a private slot via a small trick: assign
		// through a closure-free path using a static map keyed by spl_object_id.
		self::$replies[ spl_object_id( $this ) ] = $reply;
	}

	/** @var array<int, string> */
	private static array $replies = array();

	private function my_reply(): string {
		return self::$replies[ spl_object_id( $this ) ] ?? '';
	}

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

	public function complete( string $prompt, array $options = array() ): string {
		$this->last_prompt_seen  = $prompt;
		$this->last_options_seen = $options;
		return $this->my_reply();
	}

	public function stream( string $prompt, array $options = array() ): \Generator {
		yield $this->my_reply();
	}
}
