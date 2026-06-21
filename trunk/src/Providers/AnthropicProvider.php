<?php
/**
 * Anthropic provider — calls the Messages API.
 *
 * @package Vitalink\ContentImprover
 */

declare(strict_types=1);

namespace Vitalink\ContentImprover\Providers;

use Vitalink\ContentImprover\Support\Encryption;

final class AnthropicProvider implements ProviderInterface {

	private const ENDPOINT       = 'https://api.anthropic.com/v1/messages';
	private const API_VERSION    = '2023-06-01';
	private const OPTION_API_KEY = 'vitalink_ci_anthropic_api_key';

	private string $api_key;
	private string $model;

	public function __construct( array $config = array() ) {
		$this->api_key = $this->resolve_api_key( $config );
		$this->model   = (string) ( $config['model'] ?? get_option( 'vitalink_ci_anthropic_model', 'claude-sonnet-4-5' ) );
	}

	/**
	 * Pick the API key from explicit config first, else fall back to the
	 * stored option (which is encrypted at rest).
	 */
	private function resolve_api_key( array $config ): string {
		if ( isset( $config['api_key'] ) && '' !== $config['api_key'] ) {
			return (string) $config['api_key'];
		}
		$stored = (string) get_option( self::OPTION_API_KEY, '' );
		if ( '' === $stored ) {
			return '';
		}
		$plain = Encryption::decrypt( $stored );
		return false === $plain ? '' : $plain;
	}

	public function get_id(): string {
		return 'anthropic';
	}

	public function get_label(): string {
		return 'Anthropic (Claude)';
	}

	public function is_configured(): bool {
		return '' !== $this->api_key;
	}

	public function get_available_models(): array {
		return array( 'claude-sonnet-4-5', 'claude-opus-4-1', 'claude-3-5-sonnet-latest', 'claude-3-5-haiku-latest' );
	}

	public function complete( string $prompt, array $options = array() ): string {
		if ( ! $this->is_configured() ) {
			throw new ProviderException( 'Anthropic API key not configured.', ProviderException::CODE_NOT_CONFIGURED );
		}

		$model       = (string) ( $options['model'] ?? $this->model );
		$max_tokens  = (int) ( $options['max_tokens'] ?? 1024 );
		$temperature = (float) ( $options['temperature'] ?? 0.7 );
		$system      = (string) ( $options['system'] ?? 'You are a helpful assistant.' );

		$body = array(
			'model'       => $model,
			'max_tokens'  => $max_tokens,
			'temperature' => $temperature,
			'system'      => $system,
			'messages'    => array(
				array(
					'role'    => 'user',
					'content' => $prompt,
				),
			),
		);

		$response = wp_remote_post(
			self::ENDPOINT,
			array(
				'timeout' => 30,
				'headers' => array(
					'x-api-key'         => $this->api_key,
					'anthropic-version' => self::API_VERSION,
					'Content-Type'      => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new ProviderException(
				$response->get_error_message(),
				ProviderException::CODE_NETWORK
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$raw    = (string) wp_remote_retrieve_body( $response );
		$data   = json_decode( $raw, true );

		if ( $status >= 400 ) {
			throw new ProviderException(
				sprintf( 'Anthropic API error (%d): %s', $status, $raw ),
				$this->map_status( $status ),
				$status
			);
		}

		$text = $data['content'][0]['text'] ?? '';
		if ( '' === $text ) {
			throw new ProviderException( 'Anthropic returned an empty response.', ProviderException::CODE_UNKNOWN, $status );
		}

		return (string) $text;
	}

	public function stream( string $prompt, array $options = array() ): \Generator {
		yield $this->complete( $prompt, $options );
	}

	private function map_status( int $status ): string {
		if ( 401 === $status || 403 === $status ) {
			return ProviderException::CODE_AUTH;
		}
		if ( 429 === $status ) {
			return ProviderException::CODE_RATE_LIMIT;
		}
		if ( $status >= 500 ) {
			return ProviderException::CODE_SERVER;
		}
		return ProviderException::CODE_INVALID_REQUEST;
	}
}
