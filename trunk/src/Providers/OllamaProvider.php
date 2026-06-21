<?php
/**
 * Ollama provider — calls a local Ollama instance.
 *
 * Ollama runs open-weight models on your own server. No API key, no
 * data leaves the network. The default endpoint is http://localhost:11434.
 *
 * @package Vitalink\ContentImprover
 */

declare(strict_types=1);

namespace Vitalink\ContentImprover\Providers;

final class OllamaProvider implements ProviderInterface {

	private const ENDPOINT = 'http://localhost:11434';

	private string $base_url;
	private string $model;

	public function __construct( array $config = array() ) {
		$this->base_url = (string) ( $config['base_url'] ?? get_option( 'vitalink_ci_ollama_base_url', self::ENDPOINT ) );
		$this->model    = (string) ( $config['model'] ?? get_option( 'vitalink_ci_ollama_model', 'llama3.1' ) );
		$this->base_url = rtrim( $this->base_url, '/' );
	}

	public function get_id(): string {
		return 'ollama';
	}

	public function get_label(): string {
		return 'Ollama (self-hosted)';
	}

	public function is_configured(): bool {
		// Ollama needs no API key, but we should verify the endpoint is reachable.
		return '' !== $this->base_url;
	}

	public function get_available_models(): array {
		// Common models. Users typically run `ollama pull <model>` and set it in settings.
		return array( 'llama3.1', 'llama3.2', 'mistral', 'mixtral', 'qwen2.5', 'gemma2', 'phi3', 'codellama' );
	}

	public function complete( string $prompt, array $options = array() ): string {
		$model       = (string) ( $options['model'] ?? $this->model );
		$temperature = (float) ( $options['temperature'] ?? 0.7 );
		$system      = (string) ( $options['system'] ?? 'You are a helpful assistant.' );

		$body = array(
			'model'   => $model,
			'prompt'  => $system . "\n\n" . $prompt,
			'stream'  => false,
			'options' => array(
				'temperature' => $temperature,
			),
		);

		$response = wp_remote_post(
			$this->base_url . '/api/generate',
			array(
				'timeout' => 60, // Local models can be slow.
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new ProviderException(
				'Ollama unreachable at ' . $this->base_url . ': ' . $response->get_error_message(),
				ProviderException::CODE_NETWORK
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$raw    = (string) wp_remote_retrieve_body( $response );
		$data   = json_decode( $raw, true );

		if ( $status >= 400 ) {
			throw new ProviderException(
				sprintf( 'Ollama error (%d): %s', $status, $raw ),
				ProviderException::CODE_SERVER,
				$status
			);
		}

		$text = $data['response'] ?? '';
		if ( '' === $text ) {
			throw new ProviderException( 'Ollama returned an empty response. Is the model pulled? Try: ollama pull ' . $model, ProviderException::CODE_UNKNOWN, $status );
		}

		return (string) $text;
	}

	public function stream( string $prompt, array $options = array() ): \Generator {
		// Ollama supports streaming via newline-delimited JSON, but for the
		// MVP we yield the full response. Override here when streaming is needed.
		yield $this->complete( $prompt, $options );
	}
}
