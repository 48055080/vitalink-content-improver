<?php
/**
 * Content Improver feature — rewrites user-selected text in a chosen style.
 *
 * Three modes: "clearer", "shorter", "more formal".
 *
 * @package Vitalink\ContentImprover
 */

declare(strict_types=1);

namespace Vitalink\ContentImprover\Features;

use Vitalink\ContentImprover\Cache\ResponseCache;
use Vitalink\ContentImprover\Providers\ProviderException;
use Vitalink\ContentImprover\Providers\ProviderFactory;
use Vitalink\ContentImprover\Providers\ProviderInterface;

final class ContentImprover {

	public const STYLE_CLEARER = 'clearer';
	public const STYLE_SHORTER = 'shorter';
	public const STYLE_FORMAL  = 'more_formal';

	public const STYLES = array(
		self::STYLE_CLEARER => 'Clearer',
		self::STYLE_SHORTER => 'Shorter',
		self::STYLE_FORMAL  => 'More formal',
	);

	private ResponseCache $cache;
	private ProviderInterface $provider;

	public function __construct( ?ResponseCache $cache = null, ?ProviderInterface $provider = null ) {
		$this->cache    = $cache ?? new ResponseCache();
		$this->provider = $provider ?? ProviderFactory::create( ProviderFactory::get_active_provider_id() );
	}

	/**
	 * Improve the given text in the given style.
	 *
	 * @param string $text  The text to improve.
	 * @param string $style One of self::STYLE_*.
	 * @return string The improved text.
	 * @throws ProviderException
	 */
	public function improve( string $text, string $style = self::STYLE_CLEARER ): string {
		$text  = trim( $text );
		$style = $this->validate_style( $style );

		if ( '' === $text ) {
			throw new ProviderException( 'Empty text.', ProviderException::CODE_INVALID_REQUEST );
		}

		$options = array(
			'style'  => $style,
			'length' => mb_strlen( $text ),
		);

		$cached = $this->cache->get( $text, $options );
		if ( null !== $cached ) {
			return $cached;
		}

		$prompt = $this->build_prompt( $text, $style );
		$result = $this->provider->complete(
			$prompt,
			array(
				'system' => 'You are a careful editor. Output only the rewritten text — no preamble, no labels, no quotes.',
			)
		);

		$result = trim( $result );
		$this->cache->set( $text, $options, $result );

		return $result;
	}

	private function build_prompt( string $text, string $style ): string {
		$instruction = match ( $style ) {
			self::STYLE_CLEARER => 'Rewrite the following text so it is clearer and easier to read. Keep the meaning. Use shorter sentences. Keep the same language.',
			self::STYLE_SHORTER => 'Rewrite the following text to be roughly half as long, while keeping the key information. Keep the same language.',
			self::STYLE_FORMAL  => 'Rewrite the following text in a more formal, professional register. Keep the same language.',
			default             => 'Rewrite the following text. Keep the same language.',
		};

		return $instruction . "\n\n---\n\n" . $text;
	}

	private function validate_style( string $style ): string {
		return isset( self::STYLES[ $style ] ) ? $style : self::STYLE_CLEARER;
	}
}
