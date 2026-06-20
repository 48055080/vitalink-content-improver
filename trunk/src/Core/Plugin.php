<?php
/**
 * Plugin — singleton orchestrator.
 *
 * Boots feature modules, registers hooks, and wires the WP-CLI command.
 *
 * @package Vitalink\ContentImprover
 */

declare(strict_types=1);

namespace Vitalink\ContentImprover\Core;

use Vitalink\ContentImprover\Admin\GutenbergSidebar;
use Vitalink\ContentImprover\Admin\SettingsPage;
use Vitalink\ContentImprover\Cache\ResponseCache;

final class Plugin {

	private static ?Plugin $instance = null;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function boot(): void {
		( new SettingsPage() )->register();
		( new GutenbergSidebar() )->register();

		add_action( 'vitalink_ci_daily_cleanup', array( $this, 'on_daily_cleanup' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'vitalink ci', \Vitalink\ContentImprover\CLI\ContentCommand::class );
		}
	}

	public function on_daily_cleanup(): void {
		// Reserved for future maintenance tasks (e.g. trimming oversized caches).
		// Cache TTL is already enforced by set_transient(); this hook is a hook
		// point for future logic.
		do_action( 'vitalink_ci_daily_cleanup_run', new ResponseCache() );
	}
}
