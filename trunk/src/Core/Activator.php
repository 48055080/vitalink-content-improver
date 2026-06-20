<?php
/**
 * Activator — runs on plugin activation.
 *
 * @package Vitalink\ContentImprover
 */

declare(strict_types=1);

namespace Vitalink\ContentImprover\Core;

final class Activator {

	public static function activate(): void {
		self::set_defaults();
		self::schedule_events();
	}

	private static function set_defaults(): void {
		$defaults = array(
			'vitalink_ci_provider'                  => 'openai',
			'vitalink_ci_openai_model'              => 'gpt-4o-mini',
			'vitalink_ci_anthropic_model'           => 'claude-sonnet-4-5',
			'vitalink_ci_ollama_base_url'           => 'http://localhost:11434',
			'vitalink_ci_ollama_model'              => 'llama3.1',
			'vitalink_ci_cache_enabled'             => 'on',
			'vitalink_ci_cache_ttl'                 => DAY_IN_SECONDS * 7,
			'vitalink_ci_default_target_language'   => 'English',
		);
		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				add_option( $key, $value );
			}
		}
	}

	private static function schedule_events(): void {
		if ( ! wp_next_scheduled( 'vitalink_ci_daily_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'vitalink_ci_daily_cleanup' );
		}
	}
}
