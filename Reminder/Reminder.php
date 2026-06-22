<?php
# MantisBT - Open Ticket Reminder Plugin
# Copyright (C) 2026  Marc Woge
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 2 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.

/**
 * Open Ticket Reminder plugin for MantisBT.
 *
 * Sends a periodic digest of the issues that are still open for a user
 * (weekly, Monday 09:00 by default) and optionally individual reminder
 * mails for single tickets on a configurable interval.
 */
class ReminderPlugin extends MantisPlugin {

	/**
	 * Plugin registration / metadata.
	 */
	function register() {
		$this->name        = plugin_lang_get( 'title' );
		$this->description  = plugin_lang_get( 'description' );
		$this->page         = 'config';

		$this->version  = '1.0.0';
		$this->requires  = array(
			'MantisCore' => '2.0.0',
		);

		$this->author  = 'Marc Woge';
		$this->contact  = '';
		$this->url      = 'https://github.com/marcwoge/mantisBT-Reminder';
	}

	/**
	 * Default (global) configuration. Every option can be overridden per
	 * user via the "Reminder settings" page in the account preferences.
	 */
	function config() {
		return array(
			# --- master switch ---------------------------------------------
			'enabled'                  => ON,

			# --- weekly digest ---------------------------------------------
			'digest_enabled'            => ON,
			# Day of week for the digest, 0 = Sunday ... 6 = Saturday (1 = Monday)
			'digest_day'                => 1,
			# Hour of day (0-23) the digest should go out
			'digest_hour'               => 9,
			# Which relationships to include in the digest
			'digest_include_assigned'   => ON,
			'digest_include_reported'   => OFF,
			'digest_include_monitored'  => OFF,
			# Do not send an empty digest when the user has no open issues
			'digest_skip_empty'         => ON,

			# --- per issue reminders ---------------------------------------
			'per_issue_enabled'         => OFF,
			# Send a reminder for the same ticket at most every N days
			'per_issue_interval_days'   => 7,
			# Only remind about tickets that had no activity for N days
			# (0 = remind regardless of recent activity)
			'per_issue_stale_days'      => 0,
			# Relationships considered for single ticket reminders
			'per_issue_include_assigned'  => ON,
			'per_issue_include_reported'  => OFF,
			'per_issue_include_monitored' => OFF,

			# --- general ----------------------------------------------------
			# E-mail format: 'html' (styled) or 'text' (plain, via mail queue)
			'email_format'              => 'html',
			# Status threshold below which an issue counts as "open".
			# Empty means: use the standard "bug_resolved_status_threshold".
			'open_status_threshold'     => '',
			# Shared secret for the web cron endpoint (plugin.php?page=Reminder/cron)
			'cron_token'                => '',
		);
	}

	/**
	 * Event hooks.
	 */
	function hooks() {
		return array(
			'EVENT_MENU_ACCOUNT' => 'menu_account',
		);
	}

	/**
	 * Add a "Reminder settings" entry to the account preferences menu.
	 * @return array
	 */
	function menu_account() {
		return array(
			'<a href="' . plugin_page( 'userprefs' ) . '">'
				. plugin_lang_get( 'account_menu' ) . '</a>',
		);
	}

	/**
	 * Database schema. A small log table keeps track of what has already
	 * been sent so we honour the configured intervals and never spam.
	 */
	function schema() {
		return array(
			array( 'CreateTableSQL', array( plugin_table( 'log' ), "
				id            I       UNSIGNED NOTNULL AUTOINCREMENT PRIMARY,
				user_id       I       UNSIGNED NOTNULL,
				issue_id      I       UNSIGNED NOTNULL DEFAULT '0',
				reminder_type C(32)   NOTNULL,
				sent_at       I       UNSIGNED NOTNULL DEFAULT '0'
				" ) ),
			array( 'CreateIndexSQL', array( 'idx_reminder_lookup',
				plugin_table( 'log' ), 'user_id,issue_id,reminder_type' ) ),
		);
	}
}
