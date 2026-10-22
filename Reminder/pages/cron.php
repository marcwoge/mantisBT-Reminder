<?php
# MantisBT - Open Ticket Reminder Plugin
# Copyright (c) 2026 Marc-Philipp Woge
# Licensed under the MIT License. See the LICENSE file for details.

/**
 * Web cron endpoint for hosts without shell access.
 *
 *   https://your-mantis/plugin.php?page=Reminder/cron&token=YOUR_SECRET
 *
 * Configure a non-empty "cron token" on the plugin configuration page and
 * have an external scheduler / uptime service hit this URL once per hour.
 * Without a matching token the endpoint refuses to do anything.
 */

plugin_require_api( 'core/reminder_api.php' );

header( 'Content-Type: text/plain; charset=utf-8' );

$t_expected = trim( (string)plugin_config_get( 'cron_token' ) );
$t_provided = trim( (string)gpc_get_string( 'token', '' ) );

if( $t_expected === '' || !hash_equals( $t_expected, $t_provided ) ) {
	http_response_code( 403 );
	echo "Forbidden: invalid or missing token.\n";
	exit;
}

$t_force = ( gpc_get_string( 'force', '' ) === 'digest' );

$t_summary = reminder_run( $t_force );

printf( "OK: %d users scanned, %d digest mail(s), %d ticket reminder(s).\n",
	$t_summary['users'], $t_summary['digests'], $t_summary['issues'] );
exit;
