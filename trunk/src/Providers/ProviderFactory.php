<?php
/**
 * Provider factory — central registry for AI provider implementations.
 *
 * Use the `vitalink_ci_register_providers` filter to add your own provider.
 *
 * @package Vitalink\ContentImprover
 */

declare(strict_types=1);

namespace Vitalink\ContentImprover\Providers;

final class ProviderFactory {

	/**
	 * Built-in provider class map.
	 *
	 * @var array<string, class-string<ProviderInterface>>
	 */
	private const BUILTIN = array(
		'openai'    => OpenAIProvider::class,
		'anthropic' => AnthropicProvider::class,
		'ollama'    => OllamaProvider::class,
	);

	/**
	 * Build a provider instance by ID.
	 *
	 * @param string $provider_id  Provider identifier.
	 * @param array  $config       Per-provider config (api_key, model, base_url, etc.).
	 * @return ProviderInterface
	 * @throws ProviderException If the provider ID is unknown.
	 */
	public static function create( string $provider_id, array $config = array() ): ProviderInterface {
		$map = self::get_provider_map();

		if ( ! isset( $map[ $provider_id ] ) ) {
			throw new ProviderException(
				sprintf( 'Unknown provider: %s', $provider_id ),
				ProviderException::CODE_INVALID_REQUEST
			);
		}

		$class = $map[ $provider_id ];
		return new $class( $config );
	}

	/**
	 * Return the active provider ID from options, or a sensible default.
	 *
	 * @return string Provider ID, e.g. "openai".
	 */
	public static function get_active_provider_id(): string {
		$id = (string) get_option( 'vitalink_ci_provider', 'openai' );
		return sanitize_key( $id );
	}

	/**
	 * Return all available providers for the admin UI dropdown.
	 *
	 * @return array<int, array{id: string, label: string, configured: bool}>
	 */
	public static function list_providers(): array {
		$out = array();
		foreach ( self::get_provider_map() as $id => $class ) {
			$instance = self::create( $id );
			$out[]    = array(
				'id'         => $id,
				'label'      => $instance->get_label(),
				'configured' => $instance->is_configured(),
			);
		}
		return $out;
	}

	/**
	 * Get the full provider map (built-in + custom registered).
	 *
	 * @return array<string, class-string<ProviderInterface>>
	 */
	private static function get_provider_map(): array {
		/**
		 * Filter the map of provider ID to class name.
		 *
		 * @param array<string, class-string<ProviderInterface>> $map
		 */
		$map = apply_filters( 'vitalink_ci_register_providers', self::BUILTIN );
		if ( ! is_array( $map ) ) {
			return self::BUILTIN;
		}
		return $map;
	}
}
