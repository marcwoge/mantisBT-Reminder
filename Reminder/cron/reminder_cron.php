<?php
# MantisBT - Open Ticket Reminder Plugin
# Copyright (C) 2026  Marc Woge
# GNU General Public License v2 or later.

/**
 * Command line entry point for the reminder dispatcher.
 *
 * Schedule this with the operating system scheduler so it runs once per
 * hour, e.g.:
 *
 *   # Linux crontab (every hour, on the hour)
 *   0 * * * * php /path/to/mantisbt/plugins/Reminder/cron/reminder_cron.php
 *
 *   # Windows Task Scheduler
 *   php.exe D:\mantisbt\plugins\Reminder\cron\reminder_cron.php
 *
 * The script figures out on its own which users have a digest due (e.g.
 * Monday 09:00) and which single-ticket reminders are within their
 * interval, so running it hourly is safe and will not produce duplicates.
 *
 * Optional argument: pass "force-digest" to send the weekly digest
 * immediately regardless of the configured weekday/hour (useful for a
 * one-off test).
 */

if( php_sapi_name() != 'cli' ) {
	echo "This script must be run from the command line.\n";
	exit( 1 );
}

# Bootstrap MantisBT core (this file lives in plugins/Reminder/cron/).
$g_bypass_headers = 1;
require_once( dirname( __FILE__ ) . '/../../../core.php' );

# Make sure the plugin API is available even if the plugin was not loaded
# during this CLI bootstrap.
require_once( dirname( __FILE__ ) . '/../core/reminder_api.php' );

$t_force = ( isset( $argv[1] ) && $argv[1] === 'force-digest' );

$t_summary = reminder_run( $t_force );

printf( "Reminder run finished: %d users scanned, %d digest mail(s), %d ticket reminder(s).\n",
	$t_summary['users'], $t_summary['digests'], $t_summary['issues'] );

exit( 0 );
