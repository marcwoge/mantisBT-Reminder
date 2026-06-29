<?php
# MantisBT - Open Ticket Reminder Plugin
# Copyright (C) 2026  Marc Woge
# GNU General Public License v2 or later.

/**
 * Per-user reminder settings. Reachable from the account preferences menu.
 * Any value left untouched keeps inheriting the global administrator default.
 */

auth_ensure_user_authenticated();
plugin_require_api( 'core/reminder_api.php' );

$t_user_id = auth_get_current_user_id();

layout_page_header( plugin_lang_get( 'account_title' ) );
layout_page_begin();

print_account_menu( plugin_page( 'userprefs' ) );

$t_action = plugin_page( 'userprefs_update' );
?>
<div class="col-md-12 col-xs-12">
<div class="space-10"></div>
<form id="reminder-userprefs-form" method="post" action="<?php echo $t_action; ?>">
<?php echo form_security_field( 'plugin_Reminder_userprefs_update' ); ?>
<div class="widget-box widget-color-blue2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-bell"></i>
			<?php echo plugin_lang_get( 'account_title' ); ?>
		</h4>
	</div>
	<div class="widget-body">
	<div class="widget-main no-padding">
	<div class="table-responsive">
	<table class="table table-bordered table-condensed table-striped">

		<tr><td class="category" colspan="2"><strong><?php echo plugin_lang_get( 'digest_section' ); ?></strong></td></tr>
		<tr>
			<td class="category"><?php echo plugin_lang_get( 'digest_enabled' ); ?></td>
			<td><?php reminder_print_checkbox( 'digest_enabled', ON == reminder_user_config( $t_user_id, 'digest_enabled' ) ); ?></td>
		</tr>
		<tr>
			<td class="category"><?php echo plugin_lang_get( 'digest_day' ); ?></td>
			<td>
				<select name="digest_day">
					<?php
					$t_sel = (int)reminder_user_config( $t_user_id, 'digest_day' );
					for( $t_d = 0; $t_d <= 6; $t_d++ ) {
						echo '<option value="' . $t_d . '"' . check_selected( $t_sel, $t_d ) . '>'
							. plugin_lang_get( 'weekday_' . $t_d ) . '</option>';
					}
					?>
				</select>
			</td>
		</tr>
		<tr>
			<td class="category"><?php echo plugin_lang_get( 'digest_hour' ); ?></td>
			<td><input type="number" name="digest_hour" min="0" max="23"
				value="<?php echo (int)reminder_user_config( $t_user_id, 'digest_hour' ); ?>"></td>
		</tr>
		<tr>
			<td class="category"><?php echo plugin_lang_get( 'include_assigned' ); ?></td>
			<td><?php reminder_print_checkbox( 'digest_include_assigned', ON == reminder_user_config( $t_user_id, 'digest_include_assigned' ) ); ?></td>
		</tr>
		<tr>
			<td class="category"><?php echo plugin_lang_get( 'include_reported' ); ?></td>
			<td><?php reminder_print_checkbox( 'digest_include_reported', ON == reminder_user_config( $t_user_id, 'digest_include_reported' ) ); ?></td>
		</tr>
		<tr>
			<td class="category"><?php echo plugin_lang_get( 'include_monitored' ); ?></td>
			<td><?php reminder_print_checkbox( 'digest_include_monitored', ON == reminder_user_config( $t_user_id, 'digest_include_monitored' ) ); ?></td>
		</tr>

		<tr><td class="category" colspan="2"><strong><?php echo plugin_lang_get( 'per_issue_section' ); ?></strong></td></tr>
		<tr>
			<td class="category"><?php echo plugin_lang_get( 'per_issue_enabled' ); ?></td>
			<td><?php reminder_print_checkbox( 'per_issue_enabled', ON == reminder_user_config( $t_user_id, 'per_issue_enabled' ) ); ?></td>
		</tr>
		<tr>
			<td class="category"><?php echo plugin_lang_get( 'per_issue_interval_days' ); ?></td>
			<td><input type="number" name="per_issue_interval_days" min="1" max="365"
				value="<?php echo (int)reminder_user_config( $t_user_id, 'per_issue_interval_days' ); ?>"></td>
		</tr>
		<tr>
			<td class="category"><?php echo plugin_lang_get( 'per_issue_stale_days' ); ?></td>
			<td><input type="number" name="per_issue_stale_days" min="0" max="365"
				value="<?php echo (int)reminder_user_config( $t_user_id, 'per_issue_stale_days' ); ?>"></td>
		</tr>

		<tr><td class="category" colspan="2"><strong><?php echo plugin_lang_get( 'general_section' ); ?></strong></td></tr>
		<tr>
			<td class="category"><?php echo plugin_lang_get( 'email_format' ); ?></td>
			<td>
				<select name="email_format">
					<?php $t_fmt = reminder_user_config( $t_user_id, 'email_format' ); ?>
					<option value="html"<?php echo check_selected( $t_fmt, 'html' ); ?>><?php echo plugin_lang_get( 'email_format_html' ); ?></option>
					<option value="text"<?php echo check_selected( $t_fmt, 'text' ); ?>><?php echo plugin_lang_get( 'email_format_text' ); ?></option>
				</select>
			</td>
		</tr>
		<tr>
			<td class="category"><?php echo plugin_lang_get( 'excluded_projects' ); ?></td>
			<td>
				<?php
					$t_global_excluded = plugin_config_get( 'excluded_projects' );
					if( !is_array( $t_global_excluded ) ) { $t_global_excluded = array(); }
					$t_global_excluded = array_map( 'intval', $t_global_excluded );
					$t_personal = reminder_user_excluded_personal( $t_user_id );
					# Only list projects that are not already excluded globally.
					$t_projects = array();
					foreach( reminder_user_projects( $t_user_id ) as $t_pid => $t_pname ) {
						if( !in_array( $t_pid, $t_global_excluded, true ) ) {
							$t_projects[$t_pid] = $t_pname;
						}
					}
				?>
				<select name="excluded_projects[]" multiple="multiple" size="<?php echo min( 8, max( 3, count( $t_projects ) ) ); ?>">
					<?php foreach( $t_projects as $t_pid => $t_pname ) { ?>
						<option value="<?php echo $t_pid; ?>"<?php echo in_array( $t_pid, $t_personal, true ) ? ' selected="selected"' : ''; ?>><?php echo string_attribute( $t_pname ); ?></option>
					<?php } ?>
				</select>
				<div class="lighter"><?php echo plugin_lang_get( 'excluded_projects_user_hint' ); ?></div>
			</td>
		</tr>

	</table>
	</div>
	</div>
	<div class="widget-toolbox padding-8 clearfix">
		<input type="submit" class="btn btn-primary btn-white btn-round"
			value="<?php echo plugin_lang_get( 'save' ); ?>">
	</div>
	</div>
</div>
</form>
</div>

<?php
layout_page_end();
