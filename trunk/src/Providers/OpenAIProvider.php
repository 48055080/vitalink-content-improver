<?php
/**
 * OpenAI provider — calls the Chat Completions API.
 *
 * @package Vitalink\ContentImprover
 */

declare(strict_types=1);

namespace Vitalink\ContentImprover\Providers;

final class OpenAIProvider implements ProviderInterface {

	private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

	private string $api_key;
	private string $model;
	private ?string $base_url;

	public function __construct( array $config = array() ) {
		$this->api_key  = (string) ( $config['api_key'] ?? get_option( 'vitalink_ci_openai_api_key', '' ) );
		$this->model    = (string) ( $config['model'] ?? get_option( 'vitalink_ci_openai_model', 'gpt-4o-mini' ) );
		$this->base_url = (string) ( $config['base_url'] ?? get_option( 'vitalink_ci_openai_base_url', self::ENDPOINT ) );
	}

	public function get_id(): string {
		return 'openai';
	}

	public function get_label(): string {
		return 'OpenAI';
	}

	public function is_configured(): bool {
		return '' !== $this->api_key;
	}

	public function get_available_models(): array {
		return array( 'gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-3.5-turbo' );
	}

	public function complete( string $prompt, array $options = array() ): string {
		if ( ! $this->is_configured() ) {
			throw new ProviderException( 'OpenAI API key not configured.', ProviderException::CODE_NOT_CONFIGURED );
		}

		$model       = (string) ( $options['model'] ?? $this->model );
		$max_tokens  = (int) ( $options['max_tokens'] ?? 1024 );
		$temperature = (float) ( $options['temperature'] ?? 0.7 );
		$system      = (string) ( $options['system'] ?? 'You are a helpful assistant.' );

		$body = array(
			'model'       => $model,
			'temperature' => $temperature,
			'max_tokens'  => $max_tokens,
			'messages'    => array(
				array( 'role' => 'system', 'content' => $system ),
				array( 'role' => 'user', 'content' => $prompt ),
			),
		);

		$response = wp_remote_post(
			$this->base_url,
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new ProviderException(
				$response->get_error_message(),
				ProviderException::CODE_NETWORK,
				null,
				$response
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$raw    = (string) wp_remote_retrieve_body( $response );
		$data   = json_decode( $raw, true );

		if ( $status >= 400 ) {
			throw new ProviderException(
				sprintf( 'OpenAI API error (%d): %s', $status, $raw ),
				$this->map_status( $status ),
				$status
			);
		}

		$text = $data['choices'][0]['message']['content'] ?? '';
		if ( '' === $text ) {
			throw new ProviderException( 'OpenAI returned an empty response.', ProviderException::CODE_UNKNOWN, $status );
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
