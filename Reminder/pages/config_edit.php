<?php
# MantisBT - Open Ticket Reminder Plugin
# Copyright (C) 2026  Marc Woge
# GNU General Public License v2 or later.

/**
 * Persists the global configuration submitted from config.php.
 */

auth_reauthenticate();
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );
form_security_validate( 'plugin_Reminder_config_edit' );

# Boolean flags
$t_flags = array(
	'enabled',
	'digest_enabled',
	'digest_include_assigned',
	'digest_include_reported',
	'digest_include_monitored',
	'digest_skip_empty',
	'per_issue_enabled',
	'per_issue_include_assigned',
	'per_issue_include_reported',
	'per_issue_include_monitored',
);
foreach( $t_flags as $t_flag ) {
	plugin_config_set( $t_flag, gpc_get_bool( $t_flag ) ? ON : OFF );
}

# Integer values (clamped to sane ranges)
plugin_config_set( 'digest_day', min( 6, max( 0, gpc_get_int( 'digest_day', 1 ) ) ) );
plugin_config_set( 'digest_hour', min( 23, max( 0, gpc_get_int( 'digest_hour', 9 ) ) ) );
plugin_config_set( 'per_issue_interval_days', max( 1, gpc_get_int( 'per_issue_interval_days', 7 ) ) );
plugin_config_set( 'per_issue_stale_days', max( 0, gpc_get_int( 'per_issue_stale_days', 0 ) ) );

# Project exclusions (list of project ids)
plugin_config_set( 'excluded_projects', gpc_get_int_array( 'excluded_projects', array() ) );

# String values
$t_format = gpc_get_string( 'email_format', 'html' );
plugin_config_set( 'email_format', $t_format === 'text' ? 'text' : 'html' );

$t_threshold = trim( gpc_get_string( 'open_status_threshold', '' ) );
plugin_config_set( 'open_status_threshold', is_numeric( $t_threshold ) ? (int)$t_threshold : '' );
plugin_config_set( 'cron_token', trim( gpc_get_string( 'cron_token', '' ) ) );

form_security_purge( 'plugin_Reminder_config_edit' );

print_successful_redirect( plugin_page( 'config', true ) );
