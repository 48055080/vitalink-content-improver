<?php
/**
 * Vitalink Content Improver uninstall script.
 *
 * Runs when the user clicks "Delete" on the plugin (not just deactivate).
 * Removes all options, transients, scheduled events, and custom tables.
 *
 * @package Vitalink\ContentImprover
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// 1. Delete plugin options.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( 'vitalink_ci_' ) . '%',
		$wpdb->esc_like( 'vitalink_content_improver_' ) . '%'
	)
);

// 2. Delete transients (single-site).
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_vitalink_ci_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_vitalink_ci_' ) . '%'
	)
);

// 3. Delete site transients (multisite).
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s OR meta_key LIKE %s",
		$wpdb->esc_like( '_site_transient_vitalink_ci_' ) . '%',
		$wpdb->esc_like( '_site_transient_timeout_vitalink_ci_' ) . '%'
	)
);

// 4. Unschedule cron events.
$timestamp = wp_next_scheduled( 'vitalink_ci_daily_cleanup' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'vitalink_ci_daily_cleanup' );
}
wp_clear_scheduled_hook( 'vitalink_ci_daily_cleanup' );

// 5. Multisite cleanup.
if ( is_multisite() ) {
	$site_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		// Re-run on each site to be safe.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( 'vitalink_ci_' ) . '%'
			)
		);
		restore_current_blog();
	}
}
