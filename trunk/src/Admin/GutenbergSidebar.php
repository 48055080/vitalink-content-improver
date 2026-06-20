<?php
/**
 * Gutenberg sidebar — registers the editor JS and exposes data to it.
 *
 * The actual UI lives in assets/js/sidebar.js (built with @wordpress/scripts).
 *
 * @package Vitalink\ContentImprover
 */

declare(strict_types=1);

namespace Vitalink\ContentImprover\Admin;

use Vitalink\ContentImprover\Features\AltTextGenerator;
use Vitalink\ContentImprover\Features\ContentImprover;
use Vitalink\ContentImprover\Features\Summarizer;
use Vitalink\ContentImprover\Features\Translator;
use Vitalink\ContentImprover\Providers\ProviderException;
use Vitalink\ContentImprover\Providers\ProviderFactory;

final class GutenbergSidebar {

	public function register(): void {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function enqueue(): void {
		$asset_file = VITALINK_CI_PATH . 'build/sidebar.asset.php';
		$asset      = file_exists( $asset_file )
			? include $asset_file
			: array(
				'dependencies' => array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch', 'wp-i18n' ),
				'version'      => VITALINK_CI_VERSION,
			);

		wp_enqueue_script(
			'vitalink-ci-sidebar',
			VITALINK_CI_URL . 'build/sidebar.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations( 'vitalink-ci-sidebar', 'vitalink-content-improver' );

		wp_localize_script(
			'vitalink-ci-sidebar',
			'VitalinkCi',
			array(
				'restNamespace' => VITALINK_CI_REST_NAMESPACE,
				'restNonce'     => wp_create_nonce( 'wp_rest' ),
				'restRoot'      => esc_url_raw( rest_url() ),
				'features'      => array(
					'improve'   => array_keys( ContentImprover::STYLES ),
					'summarize' => array_keys( Summarizer::LENGTHS ),
				),
				'provider'      => ProviderFactory::get_active_provider_id(),
				'hasAlt'        => current_theme_supports( 'post-thumbnails' ),
			)
		);
	}

	public function register_routes(): void {
		$controller = new \Vitalink\ContentImprover\REST\ContentController();
		$controller->register_routes();
	}
}
