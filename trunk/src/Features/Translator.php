<?php
/**
 * Translator feature — translates text to a target language.
 *
 * Default target language is the site's WPLANG. Override per-call.
 *
 * @package Vitalink\ContentImprover
 */

declare(strict_types=1);

namespace Vitalink\ContentImprover\Features;

use Vitalink\ContentImprover\Cache\ResponseCache;
use Vitalink\ContentImprover\Providers\ProviderException;
use Vitalink\ContentImprover\Providers\ProviderFactory;
use Vitalink\ContentImprover\Providers\ProviderInterface;

final class Translator {

	private ResponseCache $cache;
	private ProviderInterface $provider;

	public function __construct( ?ResponseCache $cache = null, ?ProviderInterface $provider = null ) {
		$this->cache    = $cache ?? new ResponseCache();
		$this->provider = $provider ?? ProviderFactory::create( ProviderFactory::get_active_provider_id() );
	}

	public function translate( string $text, ?string $target_language = null ): string {
		$text = trim( $text );
		if ( '' === $text ) {
			throw new ProviderException( 'Empty text.', ProviderException::CODE_INVALID_REQUEST );
		}

		$target = $target_language ?: $this->get_default_target();
		$target = sanitize_text_field( $target );

		$options = array( 'target' => $target );

		$cached = $this->cache->get( $text, $options );
		if ( null !== $cached ) {
			return $cached;
		}

		$prompt = sprintf(
			"Translate the following text to %s. Keep technical terms, brand names, and code samples unchanged. Output only the translation — no preamble.\n\n---\n\n%s",
			$target,
			$text
		);

		$result = $this->provider->complete( $prompt );
		$result = trim( $result );
		$this->cache->set( $text, $options, $result );
		return $result;
	}

	private function get_default_target(): string {
		$lang = get_option( 'vitalink_ci_default_target_language', get_bloginfo( 'language' ) ?: 'English' );
		return is_string( $lang ) && '' !== $lang ? $lang : 'English';
	}
}
