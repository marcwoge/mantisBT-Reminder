<?php
/**
 * Minimal MantisBT stubs so the plugin's core logic can be unit-tested
 * WITHOUT a MantisBT installation (and without a database or web server).
 *
 * Only the symbols actually touched by the functions under test are
 * provided here. Anything mail/DB related is exercised through the higher
 * level functions in the real plugin and intentionally not covered here.
 */

date_default_timezone_set( 'UTC' );

# --- MantisBT constants ---------------------------------------------------
if( !defined( 'ON' ) )                { define( 'ON', 1 ); }
if( !defined( 'OFF' ) )               { define( 'OFF', 0 ); }
if( !defined( 'ALL_PROJECTS' ) )      { define( 'ALL_PROJECTS', 0 ); }
if( !defined( 'RESOLVED' ) )          { define( 'RESOLVED', 80 ); }
# NOTE: MantisBT only defines SECONDS_PER_DAY (not SECONDS_PER_HOUR / _MINUTE).
# We deliberately mirror that here so the tests catch any reliance on
# constants that do not exist in a real MantisBT installation.
if( !defined( 'SECONDS_PER_DAY' ) )   { define( 'SECONDS_PER_DAY', 86400 ); }

# --- MantisBT functions used at include time / inside tested functions ----

# reminder_api.php calls require_api(...) at the top; make it a no-op.
if( !function_exists( 'require_api' ) ) {
	function require_api( $p_file ) { /* no-op for tests */ }
}

# Translation lookup: return predictable strings for the keys we assert on.
if( !function_exists( 'plugin_lang_get' ) ) {
	function plugin_lang_get( $p_name, $p_basename = null ) {
		$t_map = array(
			'title'          => 'Open Ticket Reminder',
			'issue_overdue'  => 'OVERDUE',
			'issue_age_days' => 'updated %d day(s) ago',
		);
		return isset( $t_map[$p_name] ) ? $t_map[$p_name] : $p_name;
	}
}

# Stubs for reminder_issue_view_data() (MantisBT lookups).
if( !function_exists( 'string_get_bug_view_url_with_fqdn' ) ) {
	function string_get_bug_view_url_with_fqdn( $p_id, $p_user_id = null ) {
		return 'https://mantis.example/view.php?id=' . (int)$p_id;
	}
}
if( !function_exists( 'project_get_name' ) ) {
	function project_get_name( $p_project_id ) {
		return 'Project-' . (int)$p_project_id;
	}
}
if( !function_exists( 'get_enum_element' ) ) {
	function get_enum_element( $p_enum, $p_val, $p_user = null, $p_project = null ) {
		return $p_enum . ':' . $p_val;
	}
}
if( !function_exists( 'date_is_null' ) ) {
	# MantisBT represents "no date" as the integer 1.
	function date_is_null( $p_date ) {
		return (int)$p_date <= 1;
	}
}
