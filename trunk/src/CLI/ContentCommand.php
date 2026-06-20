<?php
/**
 * WP-CLI command — `wp vitalink ci ...`.
 *
 * Subcommands: improve, summarize, translate, alt-text.
 *
 * @package Vitalink\ContentImprover
 */

declare(strict_types=1);

namespace Vitalink\ContentImprover\CLI;

use Vitalink\ContentImprover\Features\AltTextGenerator;
use Vitalink\ContentImprover\Features\ContentImprover;
use Vitalink\ContentImprover\Features\Summarizer;
use Vitalink\ContentImprover\Features\Translator;
use WP_CLI;
use WP_CLI_Command;

if ( ! defined( 'WP_CLI' ) || ! class_exists( 'WP_CLI_Command' ) ) {
	return;
}

final class ContentCommand extends WP_CLI_Command {

	/**
	 * Improve a piece of text in a chosen style.
	 *
	 * ## OPTIONS
	 *
	 * <text>
	 * : The text to improve.
	 *
	 * [--style=<style>]
	 * : One of: clearer, shorter, more_formal. Default: clearer.
	 *
	 * ## EXAMPLES
	 *
	 *     wp vitalink ci improve "Some clunky sentence." --style=clearer
	 */
	public function improve( array $args, array $assoc_args ): void {
		$style = (string) ( $assoc_args['style'] ?? ContentImprover::STYLE_CLEARER );
		$text  = $args[0] ?? '';
		try {
			$out = ( new ContentImprover() )->improve( $text, $style );
			WP_CLI::log( $out );
		} catch ( \Throwable $e ) {
			WP_CLI::error( $e->getMessage() );
		}
	}

	/**
	 * Summarize text to N words.
	 *
	 * ## OPTIONS
	 *
	 * <text>
	 * : The text to summarize.
	 *
	 * [--length=<length>]
	 * : One of 50, 150, 300. Default: 150.
	 */
	public function summarize( array $args, array $assoc_args ): void {
		$length = (int) ( $assoc_args['length'] ?? Summarizer::LENGTH_MEDIUM );
		$text   = $args[0] ?? '';
		try {
			$out = ( new Summarizer() )->summarize( $text, $length );
			WP_CLI::log( $out );
		} catch ( \Throwable $e ) {
			WP_CLI::error( $e->getMessage() );
		}
	}

	/**
	 * Translate text to a target language.
	 *
	 * ## OPTIONS
	 *
	 * <text>
	 * : The text to translate.
	 *
	 * <target>
	 * : Target language (e.g. "Simplified Chinese", "Spanish").
	 */
	public function translate( array $args, array $assoc_args ): void {
		$text   = $args[0] ?? '';
		$target = $args[1] ?? null;
		try {
			$out = ( new Translator() )->translate( $text, $target );
			WP_CLI::log( $out );
		} catch ( \Throwable $e ) {
			WP_CLI::error( $e->getMessage() );
		}
	}

	/**
	 * Generate alt text for an image.
	 *
	 * ## OPTIONS
	 *
	 * <image>
	 * : Attachment ID or image URL.
	 */
	public function alt_text( array $args, array $assoc_args ): void {
		$image = $args[0] ?? 0;
		try {
			$out = ( new AltTextGenerator() )->generate( $image );
			WP_CLI::log( $out );
		} catch ( \Throwable $e ) {
			WP_CLI::error( $e->getMessage() );
		}
	}
}
