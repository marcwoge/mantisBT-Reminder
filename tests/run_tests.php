<?php
/**
 * Dependency-free test runner for the Open Ticket Reminder plugin.
 *
 * Runs without MantisBT, without a database and without PHPUnit - all it
 * needs is a PHP CLI:
 *
 *     php tests/run_tests.php
 *
 * Exits with status 0 when everything passes, 1 otherwise (so it works in
 * CI / GitHub Actions).
 */

error_reporting( E_ALL );

require __DIR__ . '/stubs.php';
require __DIR__ . '/../Reminder/core/reminder_api.php';

# --- tiny assertion framework --------------------------------------------
$g_passed = 0;
$g_failed = 0;

function check( $p_label, $p_condition ) {
	global $g_passed, $g_failed;
	if( $p_condition ) {
		$g_passed++;
		echo "  PASS  $p_label\n";
	} else {
		$g_failed++;
		echo "  FAIL  $p_label\n";
	}
}

function check_eq( $p_label, $p_expected, $p_actual ) {
	$t_ok = ( $p_expected === $p_actual );
	if( !$t_ok ) {
		$p_label .= ' (expected ' . var_export( $p_expected, true )
			. ', got ' . var_export( $p_actual, true ) . ')';
	}
	check( $p_label, $t_ok );
}

function group( $p_name ) {
	echo "\n$p_name\n";
}

/* =========================================================================
 * 1. Weekly digest schedule decision
 * ====================================================================== */
group( 'reminder_is_digest_due_at()' );

$t_mon9 = strtotime( '2026-06-22 09:00:00' );  # a Monday 09:00 UTC
$t_dow  = (int)date( 'w', $t_mon9 );
$t_other_day = ( $t_dow + 1 ) % 7;

check( 'due on configured day & hour, never sent before',
	reminder_is_digest_due_at( $t_mon9, $t_dow, 9, 0 ) === true );
check( 'not due before the configured hour',
	reminder_is_digest_due_at( $t_mon9, $t_dow, 10, 0 ) === false );
check( 'not due on a different weekday',
	reminder_is_digest_due_at( $t_mon9, $t_other_day, 9, 0 ) === false );
check( 'not due again a few hours after the last digest',
	reminder_is_digest_due_at( $t_mon9, $t_dow, 9, $t_mon9 - 2 * 3600 ) === false );
check( 'due again a week after the last digest',
	reminder_is_digest_due_at( $t_mon9, $t_dow, 9, $t_mon9 - 7 * SECONDS_PER_DAY ) === true );

/* =========================================================================
 * 2. Per-issue reminder decision
 * ====================================================================== */
group( 'reminder_is_issue_due()' );

$t_now = 1750000000;

check( 'due when never reminded before',
	reminder_is_issue_due( $t_now, 0, 7, 0, $t_now ) === true );
check( 'not due before the interval has elapsed',
	reminder_is_issue_due( $t_now, $t_now - 3 * SECONDS_PER_DAY, 7, 0, $t_now ) === false );
check( 'due once the interval has elapsed',
	reminder_is_issue_due( $t_now, $t_now - 8 * SECONDS_PER_DAY, 7, 0, $t_now ) === true );
check( 'staleness window suppresses recently updated tickets',
	reminder_is_issue_due( $t_now, 0, 7, 5, $t_now - 2 * SECONDS_PER_DAY ) === false );
check( 'staleness window allows long-untouched tickets',
	reminder_is_issue_due( $t_now, 0, 7, 5, $t_now - 10 * SECONDS_PER_DAY ) === true );

/* =========================================================================
 * 3. Plain-text issue rendering
 * ====================================================================== */
group( 'reminder_render_issue_text()' );

$t_item = array(
	'id'       => 123,
	'url'      => 'https://mantis.example/view.php?id=123',
	'project'  => 'Webshop',
	'priority' => 'high',
	'summary'  => 'Checkout fails for guests',
	'age_days' => 4,
	'overdue'  => false,
);
$t_text = reminder_render_issue_text( $t_item );

check( 'text contains issue id', strpos( $t_text, '#123' ) !== false );
check( 'text contains project', strpos( $t_text, '[Webshop]' ) !== false );
check( 'text contains priority', strpos( $t_text, '(high)' ) !== false );
check( 'text contains summary', strpos( $t_text, 'Checkout fails for guests' ) !== false );
check( 'text contains age', strpos( $t_text, 'updated 4 day(s) ago' ) !== false );
check( 'text contains url', strpos( $t_text, 'view.php?id=123' ) !== false );
check( 'text has no overdue marker when not overdue',
	strpos( $t_text, 'OVERDUE' ) === false );

$t_item_overdue = array_merge( $t_item, array( 'overdue' => true ) );
check( 'text shows overdue marker when overdue',
	strpos( reminder_render_issue_text( $t_item_overdue ), 'OVERDUE' ) !== false );

/* =========================================================================
 * 4. HTML issue rendering (incl. escaping)
 * ====================================================================== */
group( 'reminder_render_issue_html()' );

$t_html = reminder_render_issue_html( $t_item );
check( 'html links to the ticket',
	strpos( $t_html, 'href="https://mantis.example/view.php?id=123"' ) !== false );
check( 'html shows the issue id', strpos( $t_html, '#123' ) !== false );
check( 'html is a table row', strpos( $t_html, '<tr>' ) === 0 );

$t_xss_item = array_merge( $t_item, array( 'summary' => '<script>alert(1)</script>' ) );
$t_xss_html = reminder_render_issue_html( $t_xss_item );
check( 'html escapes the summary (no raw script tag)',
	strpos( $t_xss_html, '<script>' ) === false );
check( 'html keeps the escaped summary',
	strpos( $t_xss_html, '&lt;script&gt;' ) !== false );

/* =========================================================================
 * 5. HTML document wrapper
 * ====================================================================== */
group( 'reminder_render_html_document()' );

$t_doc = reminder_render_html_document( 'Hello Marc', $t_html, 'Footer line' );
check( 'document is valid html', strpos( $t_doc, '<!DOCTYPE html>' ) === 0 );
check( 'document embeds the rows', strpos( $t_doc, $t_html ) !== false );
check( 'document shows the intro', strpos( $t_doc, 'Hello Marc' ) !== false );
check( 'document shows the footer', strpos( $t_doc, 'Footer line' ) !== false );
check( 'document shows the plugin title', strpos( $t_doc, 'Open Ticket Reminder' ) !== false );

/* =========================================================================
 * 6. View-data builder (MantisBT lookups via stubs)
 * ====================================================================== */
group( 'reminder_issue_view_data()' );

$t_bug = array(
	'id'           => 5,
	'project_id'   => 2,
	'priority'     => 30,
	'summary'      => 'Sample',
	'last_updated' => time() - 2 * SECONDS_PER_DAY,
	'due_date'     => 1,           # MantisBT "no due date"
);
$t_view = reminder_issue_view_data( $t_bug, 7 );
check_eq( 'resolves id', 5, $t_view['id'] );
check_eq( 'resolves project name', 'Project-2', $t_view['project'] );
check_eq( 'resolves priority label', 'priority:30', $t_view['priority'] );
check_eq( 'computes age in days', 2, $t_view['age_days'] );
check_eq( 'no due date is not overdue', false, $t_view['overdue'] );

$t_bug_overdue = array_merge( $t_bug, array( 'due_date' => time() - 3600 ) );
check_eq( 'past due date is overdue', true,
	reminder_issue_view_data( $t_bug_overdue, 7 )['overdue'] );

$t_bug_future = array_merge( $t_bug, array( 'due_date' => time() + 3600 ) );
check_eq( 'future due date is not overdue', false,
	reminder_issue_view_data( $t_bug_future, 7 )['overdue'] );

/* =========================================================================
 * Summary
 * ====================================================================== */
echo "\n----------------------------------------\n";
echo "Passed: $g_passed   Failed: $g_failed\n";
exit( $g_failed === 0 ? 0 : 1 );
