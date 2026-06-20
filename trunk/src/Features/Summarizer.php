<?php
/**
 * Summarizer feature — condenses long text into N words.
 *
 * @package Vitalink\ContentImprover
 */

declare(strict_types=1);

namespace Vitalink\ContentImprover\Features;

use Vitalink\ContentImprover\Cache\ResponseCache;
use Vitalink\ContentImprover\Providers\ProviderException;
use Vitalink\ContentImprover\Providers\ProviderFactory;
use Vitalink\ContentImprover\Providers\ProviderInterface;

final class Summarizer {

	public const LENGTH_SHORT  = 50;
	public const LENGTH_MEDIUM = 150;
	public const LENGTH_LONG   = 300;

	public const LENGTHS = array(
		self::LENGTH_SHORT  => 'Short (≈50 words)',
		self::LENGTH_MEDIUM => 'Medium (≈150 words)',
		self::LENGTH_LONG   => 'Long (≈300 words)',
	);

	private ResponseCache $cache;
	private ProviderInterface $provider;

	public function __construct( ?ResponseCache $cache = null, ?ProviderInterface $provider = null ) {
		$this->cache    = $cache ?? new ResponseCache();
		$this->provider = $provider ?? ProviderFactory::create( ProviderFactory::get_active_provider_id() );
	}

	public function summarize( string $text, int $length = self::LENGTH_MEDIUM ): string {
		$text   = trim( $text );
		$length = $this->validate_length( $length );

		if ( '' === $text ) {
			throw new ProviderException( 'Empty text.', ProviderException::CODE_INVALID_REQUEST );
		}

		$options = array( 'length' => $length );

		$cached = $this->cache->get( $text, $options );
		if ( null !== $cached ) {
			return $cached;
		}

		$prompt = sprintf(
			"Summarize the following text in approximately %d words. Keep the key facts and the original language. Output only the summary — no preamble.\n\n---\n\n%s",
			$length,
			$text
		);

		$result = $this->provider->complete( $prompt );
		$result = trim( $result );
		$this->cache->set( $text, $options, $result );
		return $result;
	}

	private function validate_length( int $length ): int {
		return in_array( $length, array( self::LENGTH_SHORT, self::LENGTH_MEDIUM, self::LENGTH_LONG ), true )
			? $length
			: self::LENGTH_MEDIUM;
	}
}
