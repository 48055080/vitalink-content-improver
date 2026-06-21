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

		register_rest_route(
			$ns,
			'/improve',
			array(
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
			)
		);

		register_rest_route(
			$ns,
			'/summarize',
			array(
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
			)
		);

		register_rest_route(
			$ns,
			'/translate',
			array(
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
			)
		);

		register_rest_route(
			$ns,
			'/alt-text',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'alt_text' ),
				'permission_callback' => array( $this, 'can_edit_posts' ),
				'args'                => array(
					'image' => array(
						'required' => true,
						'type'     => 'string',
					),
				),
			)
		);
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
		return $this->run_feature(
			fn() => ( new ContentImprover() )->improve( $text, $style )
		);
	}

	public function summarize( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$text   = (string) $request->get_param( 'text' );
		$length = (int) $request->get_param( 'length' );
		return $this->run_feature(
			fn() => ( new Summarizer() )->summarize( $text, $length )
		);
	}

	public function translate( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$text   = (string) $request->get_param( 'text' );
		$target = $request->get_param( 'target' );
		return $this->run_feature(
			fn() => ( new Translator() )->translate( $text, is_string( $target ) ? $target : null )
		);
	}

	public function alt_text( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$image = $request->get_param( 'image' );
		return $this->run_feature(
			fn() => ( new AltTextGenerator() )->generate( $image ),
			'alt'
		);
	}

	/**
	 * Run a feature callable, wrap the result in a REST response, and
	 * convert any ProviderException into a structured WP_Error.
	 *
	 * Centralised so all four endpoints fail the same way (same error
	 * code prefix, same status fallback) — change the shape here and
	 * every endpoint follows.
	 *
	 * @param callable(): string $feature     Work callable; returns the result string.
	 * @param string             $result_key  JSON key to wrap the result under. Defaults to 'text'; alt-text uses 'alt'.
	 */
	private function run_feature( callable $feature, string $result_key = 'text' ): WP_REST_Response|WP_Error {
		try {
			return rest_ensure_response( array( $result_key => $feature() ) );
		} catch ( ProviderException $e ) {
			return new WP_Error( 'vitalink_ci_' . $e->get_error_code(), $e->getMessage(), array( 'status' => $e->get_http_status() ?: 500 ) );
		}
	}
}
