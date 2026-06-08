<?php
# MantisBT - Open Ticket Reminder Plugin
# Copyright (C) 2026  Marc Woge
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License (v2 or later).

/**
 * Core logic for the Open Ticket Reminder plugin.
 *
 * All functions assume MantisBT core has been bootstrapped. The public
 * entry point is {@see reminder_run()} which is called both by the CLI
 * cron script and the web cron endpoint.
 */

require_api( 'config_api.php' );
require_api( 'user_api.php' );
require_api( 'bug_api.php' );
require_api( 'email_api.php' );
require_api( 'database_api.php' );
require_api( 'lang_api.php' );
require_api( 'string_api.php' );
require_api( 'user_pref_api.php' );
require_api( 'project_api.php' );
require_api( 'helper_api.php' );
require_api( 'date_api.php' );

/**
 * Echo a simple checkbox form control. MantisBT has no generic checkbox
 * print helper, so we provide a small one for the plugin's own pages.
 *
 * @param string  $p_name    Field name.
 * @param boolean $p_checked Whether the box is ticked.
 * @return void
 */
function reminder_print_checkbox( $p_name, $p_checked ) {
	echo '<input type="checkbox" id="', $p_name, '" name="', $p_name, '"',
		( $p_checked ? ' checked="checked"' : '' ), ' />';
}

/**
 * Read a configuration option for a specific user, falling back to the
 * global plugin default when the user has no personal override.
 *
 * @param integer $p_user_id User id.
 * @param string  $p_option  Plugin option name (without the plugin prefix).
 * @return mixed
 */
function reminder_user_config( $p_user_id, $p_option ) {
	$t_global = plugin_config_get( $p_option );
	return config_get( 'plugin_Reminder_' . $p_option, $t_global, $p_user_id, ALL_PROJECTS );
}

/**
 * Store a per-user configuration override.
 *
 * @param integer $p_user_id User id.
 * @param string  $p_option  Plugin option name (without the plugin prefix).
 * @param mixed   $p_value   Value to store.
 * @return void
 */
function reminder_user_config_set( $p_user_id, $p_option, $p_value ) {
	config_set( 'plugin_Reminder_' . $p_option, $p_value, $p_user_id, ALL_PROJECTS );
}

/**
 * Resolve the status threshold below which an issue is considered "open"
 * for the given user.
 *
 * @param integer $p_user_id User id.
 * @return integer
 */
function reminder_open_threshold( $p_user_id ) {
	$t_threshold = reminder_user_config( $p_user_id, 'open_status_threshold' );
	if( $t_threshold === '' || $t_threshold === null ) {
		$t_threshold = config_get( 'bug_resolved_status_threshold', RESOLVED, $p_user_id, ALL_PROJECTS );
	}
	return (int)$t_threshold;
}

/**
 * Collect the open issues for a user according to the requested
 * relationships (assigned / reported / monitored).
 *
 * @param integer $p_user_id        User id.
 * @param boolean $p_inc_assigned   Include issues handled by the user.
 * @param boolean $p_inc_reported   Include issues reported by the user.
 * @param boolean $p_inc_monitored  Include issues monitored by the user.
 * @return array Array of bug rows keyed by issue id, each row carries an
 *               extra 'reminder_rel' string describing the relationship.
 */
function reminder_open_issues( $p_user_id, $p_inc_assigned, $p_inc_reported, $p_inc_monitored ) {
	$t_threshold = reminder_open_threshold( $p_user_id );
	$t_bug_table = db_get_table( 'bug' );
	$t_mon_table = db_get_table( 'bug_monitor' );
	$t_issues = array();

	$f_collect = function( $p_sql, array $p_params ) use ( &$t_issues, $p_user_id ) {
		$t_result = db_query( $p_sql, $p_params );
		while( $t_row = db_fetch_array( $t_result ) ) {
			$t_id = (int)$t_row['id'];
			if( !isset( $t_issues[$t_id] ) ) {
				$t_row['reminder_rel'] = array();
				$t_issues[$t_id] = $t_row;
			}
		}
	};

	if( $p_inc_assigned ) {
		$f_collect(
			'SELECT * FROM ' . $t_bug_table . ' WHERE handler_id = ' . db_param()
				. ' AND status < ' . db_param(),
			array( $p_user_id, $t_threshold )
		);
		foreach( $t_issues as $t_id => $t_row ) {
			if( (int)$t_row['handler_id'] === (int)$p_user_id ) {
				$t_issues[$t_id]['reminder_rel']['assigned'] = true;
			}
		}
	}

	if( $p_inc_reported ) {
		$f_collect(
			'SELECT * FROM ' . $t_bug_table . ' WHERE reporter_id = ' . db_param()
				. ' AND status < ' . db_param(),
			array( $p_user_id, $t_threshold )
		);
		foreach( $t_issues as $t_id => $t_row ) {
			if( (int)$t_row['reporter_id'] === (int)$p_user_id ) {
				$t_issues[$t_id]['reminder_rel']['reported'] = true;
			}
		}
	}

	if( $p_inc_monitored ) {
		$f_collect(
			'SELECT b.* FROM ' . $t_bug_table . ' b'
				. ' JOIN ' . $t_mon_table . ' m ON m.bug_id = b.id'
				. ' WHERE m.user_id = ' . db_param()
				. ' AND b.status < ' . db_param(),
			array( $p_user_id, $t_threshold )
		);
		# mark monitored relationship
		$t_result = db_query(
			'SELECT bug_id FROM ' . $t_mon_table . ' WHERE user_id = ' . db_param(),
			array( $p_user_id ) );
		while( $t_row = db_fetch_array( $t_result ) ) {
			$t_id = (int)$t_row['bug_id'];
			if( isset( $t_issues[$t_id] ) ) {
				$t_issues[$t_id]['reminder_rel']['monitored'] = true;
			}
		}
	}

	# Sort by priority (desc) then last updated (oldest first)
	uasort( $t_issues, function( $a, $b ) {
		if( (int)$a['priority'] !== (int)$b['priority'] ) {
			return (int)$b['priority'] - (int)$a['priority'];
		}
		return (int)$a['last_updated'] - (int)$b['last_updated'];
	} );

	return $t_issues;
}

/**
 * Timestamp of the last reminder of a given type for a user / issue.
 *
 * @param integer $p_user_id  User id.
 * @param integer $p_issue_id Issue id (0 for digests).
 * @param string  $p_type     Reminder type ('digest' or 'issue').
 * @return integer Unix timestamp, 0 if never sent.
 */
function reminder_last_sent( $p_user_id, $p_issue_id, $p_type ) {
	$t_table = plugin_table( 'log' );
	$t_result = db_query(
		'SELECT MAX(sent_at) AS last_sent FROM ' . $t_table
			. ' WHERE user_id = ' . db_param()
			. ' AND issue_id = ' . db_param()
			. ' AND reminder_type = ' . db_param(),
		array( (int)$p_user_id, (int)$p_issue_id, $p_type )
	);
	$t_row = db_fetch_array( $t_result );
	return $t_row ? (int)$t_row['last_sent'] : 0;
}

/**
 * Record that a reminder has been sent.
 *
 * @param integer $p_user_id  User id.
 * @param integer $p_issue_id Issue id (0 for digests).
 * @param string  $p_type     Reminder type.
 * @return void
 */
function reminder_log( $p_user_id, $p_issue_id, $p_type ) {
	$t_table = plugin_table( 'log' );
	db_query(
		'INSERT INTO ' . $t_table . ' ( user_id, issue_id, reminder_type, sent_at )'
			. ' VALUES ( ' . db_param() . ', ' . db_param() . ', ' . db_param() . ', ' . db_param() . ' )',
		array( (int)$p_user_id, (int)$p_issue_id, $p_type, time() )
	);
}

/**
 * Build a single line describing an issue for the plaintext digest.
 *
 * @param array   $p_bug     Bug row.
 * @param integer $p_user_id Recipient user id (for the FQDN link).
 * @return string
 */
function reminder_format_issue_line( array $p_bug, $p_user_id ) {
	$t_id = (int)$p_bug['id'];
	$t_url = string_get_bug_view_url_with_fqdn( $t_id, $p_user_id );
	$t_project = project_get_name( $p_bug['project_id'] );
	$t_priority = get_enum_element( 'priority', $p_bug['priority'], $p_user_id );

	$t_days = (int)floor( ( time() - (int)$p_bug['last_updated'] ) / SECONDS_PER_DAY );
	$t_age = sprintf( plugin_lang_get( 'issue_age_days' ), $t_days );

	$t_overdue = '';
	if( !date_is_null( $p_bug['due_date'] ) && (int)$p_bug['due_date'] < time() ) {
		$t_overdue = ' ' . plugin_lang_get( 'issue_overdue' );
	}

	return sprintf( "#%d [%s] (%s) %s%s\n    %s\n    %s",
		$t_id,
		$t_project,
		$t_priority,
		$p_bug['summary'],
		$t_overdue,
		$t_age,
		$t_url
	);
}

/**
 * Send the weekly digest to a user.
 *
 * @param integer $p_user_id User id.
 * @param array   $p_issues  Open issues (as returned by reminder_open_issues()).
 * @return boolean True if a mail was queued.
 */
function reminder_send_digest( $p_user_id, array $p_issues ) {
	$t_email = user_get_email( $p_user_id );
	if( is_blank( $t_email ) ) {
		return false;
	}

	$t_lang = user_pref_get_pref( $p_user_id, 'language' );
	lang_push( $t_lang );

	$t_count = count( $p_issues );
	$t_subject = sprintf( plugin_lang_get( 'digest_subject' ), $t_count );

	$t_lines = array();
	$t_lines[] = sprintf( plugin_lang_get( 'digest_intro' ), user_get_name( $p_user_id ), $t_count );
	$t_lines[] = '';
	foreach( $p_issues as $t_bug ) {
		$t_lines[] = reminder_format_issue_line( $t_bug, $p_user_id );
		$t_lines[] = '';
	}
	$t_lines[] = plugin_lang_get( 'mail_footer' );
	$t_body = implode( "\n", $t_lines );

	email_store( $t_email, $t_subject, $t_body );

	lang_pop();
	return true;
}

/**
 * Send a single ticket reminder.
 *
 * @param integer $p_user_id User id.
 * @param array   $p_bug     Bug row.
 * @return boolean True if a mail was queued.
 */
function reminder_send_issue( $p_user_id, array $p_bug ) {
	$t_email = user_get_email( $p_user_id );
	if( is_blank( $t_email ) ) {
		return false;
	}

	$t_lang = user_pref_get_pref( $p_user_id, 'language' );
	lang_push( $t_lang );

	$t_id = (int)$p_bug['id'];
	$t_subject = sprintf( plugin_lang_get( 'issue_subject' ), $t_id,
		$p_bug['summary'] );

	$t_lines = array();
	$t_lines[] = sprintf( plugin_lang_get( 'issue_intro' ), user_get_name( $p_user_id ) );
	$t_lines[] = '';
	$t_lines[] = reminder_format_issue_line( $p_bug, $p_user_id );
	$t_lines[] = '';
	$t_lines[] = plugin_lang_get( 'mail_footer' );
	$t_body = implode( "\n", $t_lines );

	email_store( $t_email, $t_subject, $t_body );

	lang_pop();
	return true;
}

/**
 * Decide whether the weekly digest is due "now" for the user, taking the
 * configured weekday / hour and the last send time into account.
 *
 * @param integer $p_user_id User id.
 * @param boolean $p_force   Ignore the schedule and send anyway.
 * @return boolean
 */
function reminder_digest_due( $p_user_id, $p_force ) {
	if( $p_force ) {
		return true;
	}

	$t_day  = (int)reminder_user_config( $p_user_id, 'digest_day' );
	$t_hour = (int)reminder_user_config( $p_user_id, 'digest_hour' );

	if( (int)date( 'w' ) !== $t_day || (int)date( 'G' ) < $t_hour ) {
		return false;
	}

	# Only one digest per ~day, so re-runs of an hourly cron do not duplicate.
	$t_last = reminder_last_sent( $p_user_id, 0, 'digest' );
	return ( time() - $t_last ) >= ( 20 * SECONDS_PER_HOUR );
}

/**
 * Process the weekly digest for a single user.
 *
 * @param integer $p_user_id User id.
 * @param boolean $p_force   Force send regardless of schedule.
 * @return integer Number of mails queued (0 or 1).
 */
function reminder_process_digest( $p_user_id, $p_force ) {
	if( OFF == reminder_user_config( $p_user_id, 'digest_enabled' ) ) {
		return 0;
	}
	if( !reminder_digest_due( $p_user_id, $p_force ) ) {
		return 0;
	}

	$t_issues = reminder_open_issues(
		$p_user_id,
		ON == reminder_user_config( $p_user_id, 'digest_include_assigned' ),
		ON == reminder_user_config( $p_user_id, 'digest_include_reported' ),
		ON == reminder_user_config( $p_user_id, 'digest_include_monitored' )
	);

	if( empty( $t_issues ) && ON == reminder_user_config( $p_user_id, 'digest_skip_empty' ) ) {
		# Log anyway so we do not re-evaluate every hour for the same day.
		reminder_log( $p_user_id, 0, 'digest' );
		return 0;
	}

	if( reminder_send_digest( $p_user_id, $t_issues ) ) {
		reminder_log( $p_user_id, 0, 'digest' );
		return 1;
	}
	return 0;
}

/**
 * Process per-issue reminders for a single user.
 *
 * @param integer $p_user_id User id.
 * @return integer Number of mails queued.
 */
function reminder_process_issues( $p_user_id ) {
	if( OFF == reminder_user_config( $p_user_id, 'per_issue_enabled' ) ) {
		return 0;
	}

	$t_interval = (int)reminder_user_config( $p_user_id, 'per_issue_interval_days' ) * SECONDS_PER_DAY;
	$t_stale    = (int)reminder_user_config( $p_user_id, 'per_issue_stale_days' ) * SECONDS_PER_DAY;

	$t_issues = reminder_open_issues(
		$p_user_id,
		ON == reminder_user_config( $p_user_id, 'per_issue_include_assigned' ),
		ON == reminder_user_config( $p_user_id, 'per_issue_include_reported' ),
		ON == reminder_user_config( $p_user_id, 'per_issue_include_monitored' )
	);

	$t_sent = 0;
	foreach( $t_issues as $t_bug ) {
		$t_id = (int)$t_bug['id'];

		# Skip tickets with recent activity when a staleness window is set.
		if( $t_stale > 0 && ( time() - (int)$t_bug['last_updated'] ) < $t_stale ) {
			continue;
		}

		$t_last = reminder_last_sent( $p_user_id, $t_id, 'issue' );
		if( ( time() - $t_last ) < $t_interval ) {
			continue;
		}

		if( reminder_send_issue( $p_user_id, $t_bug ) ) {
			reminder_log( $p_user_id, $t_id, 'issue' );
			$t_sent++;
		}
	}
	return $t_sent;
}

/**
 * Main entry point. Iterates over all enabled users and dispatches the
 * digest and per-issue reminders that are currently due.
 *
 * @param boolean $p_force_digest Send the digest regardless of schedule
 *                                (useful for "send test"/manual runs).
 * @return array Summary: array( 'digests' => n, 'issues' => n, 'users' => n ).
 */
function reminder_run( $p_force_digest = false ) {
	plugin_push_current( 'Reminder' );

	$t_summary = array( 'digests' => 0, 'issues' => 0, 'users' => 0 );

	if( OFF == plugin_config_get( 'enabled' ) ) {
		plugin_pop_current();
		return $t_summary;
	}

	$t_user_table = db_get_table( 'user' );
	$t_result = db_query( 'SELECT id FROM ' . $t_user_table . ' WHERE enabled = ' . db_param(),
		array( 1 ) );

	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_user_id = (int)$t_row['id'];
		$t_summary['users']++;
		$t_summary['digests'] += reminder_process_digest( $t_user_id, $p_force_digest );
		$t_summary['issues']  += reminder_process_issues( $t_user_id );
	}

	# Flush the mail queue we just filled.
	if( $t_summary['digests'] > 0 || $t_summary['issues'] > 0 ) {
		email_send_all();
	}

	plugin_pop_current();
	return $t_summary;
}
