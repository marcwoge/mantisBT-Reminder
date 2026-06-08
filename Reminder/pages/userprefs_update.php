<?php
# MantisBT - Open Ticket Reminder Plugin
# Copyright (C) 2026  Marc Woge
# GNU General Public License v2 or later.

/**
 * Persists the per-user reminder settings submitted from userprefs.php.
 */

auth_ensure_user_authenticated();
plugin_require_api( 'core/reminder_api.php' );
form_security_validate( 'plugin_Reminder_userprefs_update' );

$t_user_id = auth_get_current_user_id();

$t_flags = array(
	'digest_enabled',
	'digest_include_assigned',
	'digest_include_reported',
	'digest_include_monitored',
	'per_issue_enabled',
);
foreach( $t_flags as $t_flag ) {
	reminder_user_config_set( $t_user_id, $t_flag, gpc_get_bool( $t_flag ) ? ON : OFF );
}

reminder_user_config_set( $t_user_id, 'digest_day',
	min( 6, max( 0, gpc_get_int( 'digest_day', 1 ) ) ) );
reminder_user_config_set( $t_user_id, 'digest_hour',
	min( 23, max( 0, gpc_get_int( 'digest_hour', 9 ) ) ) );
reminder_user_config_set( $t_user_id, 'per_issue_interval_days',
	max( 1, gpc_get_int( 'per_issue_interval_days', 7 ) ) );
reminder_user_config_set( $t_user_id, 'per_issue_stale_days',
	max( 0, gpc_get_int( 'per_issue_stale_days', 0 ) ) );

form_security_purge( 'plugin_Reminder_userprefs_update' );

print_successful_redirect( plugin_page( 'userprefs', true ) );
