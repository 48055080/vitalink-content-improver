<?php
/**
 * REST API controller — exposes the four features to the editor and CLI.
 *
 * Routes are registered under VITALINK_CI_REST_NAMESPACE ("vitalink-ci/v1").
 *
 * @package Vitalink\ContentImprover
 */

declare(strict_types=1);

namespace Vitalink\ContentImprover\REST;

use Vitalink\ContentImprover\Features\AltTextGenerator;
use Vitalink\ContentImprover\Features\ContentImprover;
use Vitalink\ContentImprover\Features\Summarizer;
use Vitalink\ContentImprover\Features\Translator;
use Vitalink\ContentImprover\Providers\ProviderException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class ContentController {

	public function register_routes(): void {
		$ns = \Vitalink\ContentImprover\VITALINK_CI_REST_NAMESPACE;
		register_rest_route( $ns, '/improve',   array( $this, 'improve' ) );
		register_rest_route( $ns, '/summarize', array( $this, 'summarize' ) );
		register_rest_route( $ns, '/translate', array( $this, 'translate' ) );
		register_rest_route( $ns, '/alt-text',  array( $this, 'alt_text' ) );
	}

	public function improve( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$text  = (string) $request->get_param( 'text' );
		$style = (string) $request->get_param( 'style' );
		try {
			$result = ( new ContentImprover() )->improve( $text, $style );
			return rest_ensure_response( array( 'text' => $result ) );
		} catch ( ProviderException $e ) {
			return new WP_Error( 'vitalink_ci_' . $e->get_error_code(), $e->getMessage(), array( 'status' => $e->get_http_status() ?: 500 ) );
		}
	}

	public function summarize( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$text   = (string) $request->get_param( 'text' );
		$length = (int) $request->get_param( 'length' );
		try {
			$result = ( new Summarizer() )->summarize( $text, $length );
			return rest_ensure_response( array( 'text' => $result ) );
		} catch ( ProviderException $e ) {
			return new WP_Error( 'vitalink_ci_' . $e->get_error_code(), $e->getMessage(), array( 'status' => $e->get_http_status() ?: 500 ) );
		}
	}

	public function translate( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$text   = (string) $request->get_param( 'text' );
		$target = $request->get_param( 'target' );
		try {
			$result = ( new Translator() )->translate( $text, is_string( $target ) ? $target : null );
			return rest_ensure_response( array( 'text' => $result ) );
		} catch ( ProviderException $e ) {
			return new WP_Error( 'vitalink_ci_' . $e->get_error_code(), $e->getMessage(), array( 'status' => $e->get_http_status() ?: 500 ) );
		}
	}

	public function alt_text( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$image = $request->get_param( 'image' );
		try {
			$result = ( new AltTextGenerator() )->generate( $image );
			return rest_ensure_response( array( 'alt' => $result ) );
		} catch ( ProviderException $e ) {
			return new WP_Error( 'vitalink_ci_' . $e->get_error_code(), $e->getMessage(), array( 'status' => $e->get_http_status() ?: 500 ) );
		}
	}
}
