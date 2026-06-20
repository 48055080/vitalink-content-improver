<?php
/**
 * Provider interface — the contract every AI provider must implement.
 *
 * All Vitalink AI plugins speak to providers through this interface.
 * This is the only file a third-party developer needs to read in order
 * to register a custom provider.
 *
 * @package Vitalink\ContentImprover
 */

declare(strict_types=1);

namespace Vitalink\ContentImprover\Providers;

interface ProviderInterface {

	/**
	 * Stable identifier used in options and the factory.
	 *
	 * Examples: "openai", "anthropic", "ollama", "custom-mistral".
	 */
	public function get_id(): string;

	/**
	 * Human-readable label for the admin UI.
	 */
	public function get_label(): string;

	/**
	 * Whether this provider is fully configured and ready to call.
	 *
	 * @return bool True if the provider has all required credentials/settings.
	 */
	public function is_configured(): bool;

	/**
	 * Send a prompt and return the full completion (non-streaming).
	 *
	 * Implementations MUST throw ProviderException on failure.
	 *
	 * @param string $prompt   Fully-rendered prompt text.
	 * @param array  $options  Options bag. Recognized keys:
	 *                         - model      (string) Model ID.
	 *                         - max_tokens (int)    Max output tokens.
	 *                         - temperature(float)  Sampling temperature 0.0-1.0.
	 *                         - system     (string) Optional system message.
	 * @return string The model output.
	 */
	public function complete( string $prompt, array $options = [] ): string;

	/**
	 * Stream the completion as a generator of string chunks.
	 *
	 * Default implementation just wraps complete(). Providers that support
	 * server-sent-events or chunked transfer should override.
	 *
	 * @param string $prompt  Fully-rendered prompt text.
	 * @param array  $options Same options as complete().
	 * @return \Generator<string> String chunks.
	 */
	public function stream( string $prompt, array $options = [] ): \Generator;

	/**
	 * List the model IDs this provider supports. Used for the settings dropdown.
	 *
	 * @return array<int, string>
	 */
	public function get_available_models(): array;
}
