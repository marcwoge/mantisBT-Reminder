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

/* -------------------------------------------------------------------------
 * Pure decision helpers (no MantisBT/DB dependencies, easily unit-testable).
 * ---------------------------------------------------------------------- */

/**
 * Whether the weekly digest is due at a given moment.
 *
 * @param integer $p_now       Current unix timestamp.
 * @param integer $p_day       Configured weekday (0 = Sunday ... 6 = Saturday).
 * @param integer $p_hour      Configured hour of day (0-23).
 * @param integer $p_last_sent Unix timestamp of the last digest (0 = never).
 * @return boolean
 */
function reminder_is_digest_due_at( $p_now, $p_day, $p_hour, $p_last_sent ) {
	if( (int)date( 'w', $p_now ) !== (int)$p_day ) {
		return false;
	}
	if( (int)date( 'G', $p_now ) < (int)$p_hour ) {
		return false;
	}
	# Only one digest per ~day so re-runs of an hourly cron do not duplicate.
	return ( $p_now - (int)$p_last_sent ) >= ( 20 * SECONDS_PER_HOUR );
}

/**
 * Whether a single-ticket reminder is due for an issue.
 *
 * @param integer $p_now           Current unix timestamp.
 * @param integer $p_last_sent     Last reminder for this issue (0 = never).
 * @param integer $p_interval_days Minimum days between reminders.
 * @param integer $p_stale_days    Only remind if untouched this many days (0 = always).
 * @param integer $p_last_updated  Unix timestamp the issue was last updated.
 * @return boolean
 */
function reminder_is_issue_due( $p_now, $p_last_sent, $p_interval_days, $p_stale_days, $p_last_updated ) {
	if( $p_stale_days > 0 && ( $p_now - (int)$p_last_updated ) < $p_stale_days * SECONDS_PER_DAY ) {
		return false;
	}
	return ( $p_now - (int)$p_last_sent ) >= $p_interval_days * SECONDS_PER_DAY;
}

/* -------------------------------------------------------------------------
 * Rendering. Builders that resolve MantisBT data are separated from the
 * pure renderers so the rendering (incl. HTML) can be unit-tested.
 * ---------------------------------------------------------------------- */

/**
 * Resolve the display data for one issue into a flat, render-ready array.
 *
 * @param array   $p_bug     Bug row.
 * @param integer $p_user_id Recipient user id (for the FQDN link & language).
 * @return array Keys: id, url, project, priority, summary, age_days, overdue.
 */
function reminder_issue_view_data( array $p_bug, $p_user_id ) {
	return array(
		'id'        => (int)$p_bug['id'],
		'url'       => string_get_bug_view_url_with_fqdn( (int)$p_bug['id'], $p_user_id ),
		'project'   => project_get_name( $p_bug['project_id'] ),
		'priority'  => get_enum_element( 'priority', $p_bug['priority'], $p_user_id ),
		'summary'   => $p_bug['summary'],
		'age_days'  => (int)floor( ( time() - (int)$p_bug['last_updated'] ) / SECONDS_PER_DAY ),
		'overdue'   => ( !date_is_null( $p_bug['due_date'] ) && (int)$p_bug['due_date'] < time() ),
	);
}

/**
 * Plain-text rendering of a single issue.
 *
 * @param array $p_item Issue view data (see reminder_issue_view_data()).
 * @return string
 */
function reminder_render_issue_text( array $p_item ) {
	$t_overdue = $p_item['overdue'] ? ' ' . plugin_lang_get( 'issue_overdue' ) : '';
	$t_age = sprintf( plugin_lang_get( 'issue_age_days' ), $p_item['age_days'] );

	return sprintf( "#%d [%s] (%s) %s%s\n    %s\n    %s",
		$p_item['id'],
		$p_item['project'],
		$p_item['priority'],
		$p_item['summary'],
		$t_overdue,
		$t_age,
		$p_item['url']
	);
}

/**
 * HTML rendering of a single issue as a table row.
 *
 * @param array $p_item Issue view data (see reminder_issue_view_data()).
 * @return string
 */
function reminder_render_issue_html( array $p_item ) {
	$t_e = function( $p_text ) {
		return htmlspecialchars( (string)$p_text, ENT_QUOTES, 'UTF-8' );
	};

	$t_overdue = '';
	if( $p_item['overdue'] ) {
		$t_overdue = ' <span style="color:#ffffff;background:#d9534f;border-radius:3px;'
			. 'padding:1px 6px;font-size:11px;">' . $t_e( plugin_lang_get( 'issue_overdue' ) ) . '</span>';
	}

	$t_age = sprintf( plugin_lang_get( 'issue_age_days' ), $p_item['age_days'] );

	return '<tr>'
		. '<td style="padding:8px 10px;border-bottom:1px solid #eee;white-space:nowrap;vertical-align:top;">'
		. '<a href="' . $t_e( $p_item['url'] ) . '" style="color:#1c6ea4;text-decoration:none;font-weight:bold;">#'
		. $t_e( $p_item['id'] ) . '</a></td>'
		. '<td style="padding:8px 10px;border-bottom:1px solid #eee;vertical-align:top;">'
		. $t_e( $p_item['summary'] ) . $t_overdue
		. '<div style="color:#888;font-size:12px;margin-top:2px;">' . $t_e( $p_item['project'] )
		. ' &middot; ' . $t_e( $t_age ) . '</div></td>'
		. '<td style="padding:8px 10px;border-bottom:1px solid #eee;white-space:nowrap;vertical-align:top;color:#555;">'
		. $t_e( $p_item['priority'] ) . '</td>'
		. '</tr>';
}

/**
 * Wrap rendered issue rows / blocks into a complete HTML mail document.
 *
 * @param string $p_intro  Already-translated intro paragraph.
 * @param string $p_rows   Concatenated reminder_render_issue_html() output.
 * @param string $p_footer Already-translated footer text.
 * @return string
 */
function reminder_render_html_document( $p_intro, $p_rows, $p_footer ) {
	$t_e = function( $p_text ) {
		return htmlspecialchars( (string)$p_text, ENT_QUOTES, 'UTF-8' );
	};

	return '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#f4f4f4;">'
		. '<div style="max-width:640px;margin:0 auto;padding:20px;font-family:'
		. 'Helvetica,Arial,sans-serif;color:#333;font-size:14px;line-height:1.5;">'
		. '<div style="background:#1c6ea4;color:#fff;padding:14px 18px;border-radius:6px 6px 0 0;'
		. 'font-size:18px;font-weight:bold;">' . $t_e( plugin_lang_get( 'title' ) ) . '</div>'
		. '<div style="background:#fff;padding:18px;border:1px solid #e5e5e5;border-top:0;">'
		. '<p style="margin:0 0 14px;">' . nl2br( $t_e( $p_intro ) ) . '</p>'
		. '<table style="width:100%;border-collapse:collapse;font-size:14px;">' . $p_rows . '</table>'
		. '</div>'
		. '<div style="color:#999;font-size:12px;padding:12px 4px;">' . nl2br( $t_e( $p_footer ) ) . '</div>'
		. '</div></body></html>';
}

/**
 * Send a mail either as styled HTML (with a plain-text alternative) or as a
 * plain-text mail through the MantisBT mail queue.
 *
 * @param string $p_to      Recipient e-mail address.
 * @param string $p_subject Subject line.
 * @param string $p_html    HTML body.
 * @param string $p_text    Plain-text body (also used as HTML alt body).
 * @param string $p_format  'html' or 'text'.
 * @return boolean True on success / queued.
 */
function reminder_send_mail( $p_to, $p_subject, $p_html, $p_text, $p_format ) {
	if( $p_format !== 'html' ) {
		email_store( $p_to, $p_subject, $p_text );
		return true;
	}

	$t_class = null;
	if( class_exists( 'PHPMailer\\PHPMailer\\PHPMailer' ) ) {
		$t_class = 'PHPMailer\\PHPMailer\\PHPMailer';
	} else if( class_exists( 'PHPMailer' ) ) {
		$t_class = 'PHPMailer';
	}

	if( $t_class === null ) {
		# PHPMailer not available - fall back to the plain-text queue.
		email_store( $p_to, $p_subject, $p_text );
		return true;
	}

	try {
		$t_mail = new $t_class( true );
		$t_mail->CharSet = 'UTF-8';

		switch( config_get( 'phpMailer_method' ) ) {
			case PHPMAILER_METHOD_SENDMAIL:
				$t_mail->IsSendmail();
				break;
			case PHPMAILER_METHOD_SMTP:
				$t_mail->IsSMTP();
				$t_mail->Host = config_get( 'smtp_host' );
				$t_username = config_get( 'smtp_username' );
				if( !is_blank( $t_username ) ) {
					$t_mail->SMTPAuth = true;
					$t_mail->Username = $t_username;
					$t_mail->Password = config_get( 'smtp_password' );
				}
				$t_secure = config_get( 'smtp_connection_mode' );
				if( !is_blank( $t_secure ) ) {
					$t_mail->SMTPSecure = $t_secure;
				}
				$t_mail->Port = config_get( 'smtp_port' );
				break;
			default:
				$t_mail->IsMail();
		}

		$t_mail->setFrom( config_get( 'from_email' ), config_get( 'from_name' ) );
		$t_mail->Sender = config_get( 'return_path_email' );
		$t_mail->addAddress( $p_to );
		$t_mail->Subject = $p_subject;
		$t_mail->isHTML( true );
		$t_mail->Body = $p_html;
		$t_mail->AltBody = $p_text;
		$t_mail->send();
		return true;
	} catch( \Exception $e ) {
		# On any sending error, fall back to the queued plain-text mail.
		email_store( $p_to, $p_subject, $p_text );
		return true;
	}
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
	$t_intro = sprintf( plugin_lang_get( 'digest_intro' ), user_get_name( $p_user_id ), $t_count );
	$t_footer = plugin_lang_get( 'mail_footer' );

	$t_text_lines = array( $t_intro, '' );
	$t_html_rows = '';
	foreach( $p_issues as $t_bug ) {
		$t_item = reminder_issue_view_data( $t_bug, $p_user_id );
		$t_text_lines[] = reminder_render_issue_text( $t_item );
		$t_text_lines[] = '';
		$t_html_rows .= reminder_render_issue_html( $t_item );
	}
	$t_text_lines[] = $t_footer;

	$t_text = implode( "\n", $t_text_lines );
	$t_html = reminder_render_html_document( $t_intro, $t_html_rows, $t_footer );

	$t_format = reminder_user_config( $p_user_id, 'email_format' );
	reminder_send_mail( $t_email, $t_subject, $t_html, $t_text, $t_format );

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
	$t_subject = sprintf( plugin_lang_get( 'issue_subject' ), $t_id, $p_bug['summary'] );
	$t_intro = sprintf( plugin_lang_get( 'issue_intro' ), user_get_name( $p_user_id ) );
	$t_footer = plugin_lang_get( 'mail_footer' );

	$t_item = reminder_issue_view_data( $p_bug, $p_user_id );

	$t_text = implode( "\n", array(
		$t_intro, '', reminder_render_issue_text( $t_item ), '', $t_footer ) );
	$t_html = reminder_render_html_document( $t_intro, reminder_render_issue_html( $t_item ), $t_footer );

	$t_format = reminder_user_config( $p_user_id, 'email_format' );
	reminder_send_mail( $t_email, $t_subject, $t_html, $t_text, $t_format );

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
	$t_last = reminder_last_sent( $p_user_id, 0, 'digest' );

	return reminder_is_digest_due_at( time(), $t_day, $t_hour, $t_last );
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

	$t_interval_days = (int)reminder_user_config( $p_user_id, 'per_issue_interval_days' );
	$t_stale_days    = (int)reminder_user_config( $p_user_id, 'per_issue_stale_days' );

	$t_issues = reminder_open_issues(
		$p_user_id,
		ON == reminder_user_config( $p_user_id, 'per_issue_include_assigned' ),
		ON == reminder_user_config( $p_user_id, 'per_issue_include_reported' ),
		ON == reminder_user_config( $p_user_id, 'per_issue_include_monitored' )
	);

	$t_sent = 0;
	foreach( $t_issues as $t_bug ) {
		$t_id = (int)$t_bug['id'];
		$t_last = reminder_last_sent( $p_user_id, $t_id, 'issue' );

		if( !reminder_is_issue_due( time(), $t_last, $t_interval_days, $t_stale_days,
				(int)$t_bug['last_updated'] ) ) {
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
