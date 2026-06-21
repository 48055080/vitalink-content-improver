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
		$ns = \VITALINK_CI_REST_NAMESPACE;

		register_rest_route( $ns, '/improve', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'improve' ),
			'permission_callback' => array( $this, 'can_edit_posts' ),
			'args'                => array(
				'text'  => array(
					'required' => true,
					'type'     => 'string',
				),
				'style' => array(
					'required' => false,
					'type'     => 'string',
					'default'  => ContentImprover::STYLE_CLEARER,
				),
			),
		) );

		register_rest_route( $ns, '/summarize', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'summarize' ),
			'permission_callback' => array( $this, 'can_edit_posts' ),
			'args'                => array(
				'text'   => array(
					'required' => true,
					'type'     => 'string',
				),
				'length' => array(
					'required' => false,
					'type'     => 'integer',
					'default'  => 3,
				),
			),
		) );

		register_rest_route( $ns, '/translate', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'translate' ),
			'permission_callback' => array( $this, 'can_edit_posts' ),
			'args'                => array(
				'text'   => array(
					'required' => true,
					'type'     => 'string',
				),
				'target' => array(
					'required' => false,
					'type'     => 'string',
				),
			),
		) );

		register_rest_route( $ns, '/alt-text', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'alt_text' ),
			'permission_callback' => array( $this, 'can_edit_posts' ),
			'args'                => array(
				'image' => array(
					'required' => true,
					'type'     => 'string',
				),
			),
		) );
	}

	/**
	 * Capability check shared by every endpoint. Authors and above can
	 * call the AI features; subscribers cannot (would burn API quota).
	 */
	public function can_edit_posts(): bool {
		return current_user_can( 'edit_posts' );
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
