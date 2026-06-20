<?php
/**
 * Alt Text Generator feature — produces concise alt text for an image.
 *
 * Inputs: attachment ID or image URL. Output: alt text string (≤ 125 chars
 * per accessibility guidelines).
 *
 * Ollama and some local models cannot process images. The provider must
 * support multimodal input; the API surfaces a clear error otherwise.
 *
 * @package Vitalink\ContentImprover
 */

declare(strict_types=1);

namespace Vitalink\ContentImprover\Features;

use Vitalink\ContentImprover\Cache\ResponseCache;
use Vitalink\ContentImprover\Providers\ProviderException;
use Vitalink\ContentImprover\Providers\ProviderFactory;
use Vitalink\ContentImprover\Providers\ProviderInterface;

final class AltTextGenerator {

	private const MAX_LENGTH = 125;

	private ResponseCache $cache;
	private ProviderInterface $provider;

	public function __construct( ?ResponseCache $cache = null, ?ProviderInterface $provider = null ) {
		$this->cache    = $cache ?? new ResponseCache();
		$this->provider = $provider ?? ProviderFactory::create( ProviderFactory::get_active_provider_id() );
	}

	/**
	 * Generate alt text for an image.
	 *
	 * @param int|string $image Attachment ID, or image URL.
	 * @return string Alt text, max 125 characters.
	 * @throws ProviderException
	 */
	public function generate( $image ): string {
		$url = $this->resolve_url( $image );
		if ( '' === $url ) {
			throw new ProviderException( 'Image not found.', ProviderException::CODE_INVALID_REQUEST );
		}

		$options = array( 'url' => $url );
		$cached  = $this->cache->get( $url, $options );
		if ( null !== $cached ) {
			return $cached;
		}

		$prompt = sprintf(
			"Write concise alt text for the image at this URL: %s\n\nRules: 10-20 words, plain language, no \"image of\" prefix, no marketing language, no more than 125 characters. Output only the alt text — no quotes, no preamble.",
			$url
		);

		$result = $this->provider->complete( $prompt );
		$result = trim( $result, " \t\n\r\0\x0B\"'" );
		if ( mb_strlen( $result ) > self::MAX_LENGTH ) {
			$result = mb_substr( $result, 0, self::MAX_LENGTH - 1 ) . '…';
		}
		$this->cache->set( $url, $options, $result );
		return $result;
	}

	private function resolve_url( $image ): string {
		if ( is_numeric( $image ) ) {
			$url = wp_get_attachment_url( (int) $image );
			return is_string( $url ) ? $url : '';
		}
		if ( is_string( $image ) ) {
			return esc_url_raw( $image );
		}
		return '';
	}
}
