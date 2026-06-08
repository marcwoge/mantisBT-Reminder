<?php
# MantisBT - Open Ticket Reminder Plugin
# Copyright (C) 2026  Marc Woge
# GNU General Public License v2 or later.

/**
 * Global configuration page (administrators). These values act as the
 * defaults for every user; users may override them on their own
 * "Reminder settings" page.
 */

auth_reauthenticate();
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );
plugin_require_api( 'core/reminder_api.php' );

layout_page_header( plugin_lang_get( 'config_title' ) );
layout_page_begin();

print_manage_menu();

$t_action = plugin_page( 'config_edit' );
?>
<div class="col-md-12 col-xs-12">
<div class="space-10"></div>
<form id="reminder-config-form" method="post" action="<?php echo $t_action; ?>">
<?php echo form_security_field( 'plugin_Reminder_config_edit' ); ?>
<div class="widget-box widget-color-blue2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-bell"></i>
			<?php echo plugin_lang_get( 'config_title' ); ?>
		</h4>
	</div>
	<div class="widget-body">
	<div class="widget-main no-padding">
	<div class="table-responsive">
	<table class="table table-bordered table-condensed table-striped">

		<tr>
			<td class="category"><?php echo plugin_lang_get( 'enabled' ); ?></td>
			<td><?php reminder_print_checkbox( 'enabled', ON == plugin_config_get( 'enabled' ) ); ?></td>
		</tr>

		<tr><td class="category" colspan="2"><strong><?php echo plugin_lang_get( 'digest_section' ); ?></strong></td></tr>
		<tr>
			<td class="category"><?php echo plugin_lang_get( 'digest_enabled' ); ?></td>
			<td><?php reminder_print_checkbox( 'digest_enabled', ON == plugin_config_get( 'digest_enabled' ) ); ?></td>
		</tr>
		<tr>
			<td class="category"><?php echo plugin_lang_get( 'digest_day' ); ?></td>
			<td>
				<select name="digest_day">
					<?php
					$t_days = array( 0, 1, 2, 3, 4, 5, 6 );
					$t_sel = (int)plugin_config_get( 'digest_day' );
					foreach( $t_days as $t_d ) {
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
				value="<?php echo (int)plugin_config_get( 'digest_hour' ); ?>"></td>
		</tr>
		<tr>
			<td class="category"><?php echo plugin_lang_get( 'include_assigned' ); ?></td>
			<td><?php reminder_print_checkbox( 'digest_include_assigned', ON == plugin_config_get( 'digest_include_assigned' ) ); ?></td>
		</tr>
		<tr>
			<td class="category"><?php echo plugin_lang_get( 'include_reported' ); ?></td>
			<td><?php reminder_print_checkbox( 'digest_include_reported', ON == plugin_config_get( 'digest_include_reported' ) ); ?></td>
		</tr>
		<tr>
			<td class="category"><?php echo plugin_lang_get( 'include_monitored' ); ?></td>
			<td><?php reminder_print_checkbox( 'digest_include_monitored', ON == plugin_config_get( 'digest_include_monitored' ) ); ?></td>
		</tr>
		<tr>
			<td class="category"><?php echo plugin_lang_get( 'digest_skip_empty' ); ?></td>
			<td><?php reminder_print_checkbox( 'digest_skip_empty', ON == plugin_config_get( 'digest_skip_empty' ) ); ?></td>
		</tr>

		<tr><td class="category" colspan="2"><strong><?php echo plugin_lang_get( 'per_issue_section' ); ?></strong></td></tr>
		<tr>
			<td class="category"><?php echo plugin_lang_get( 'per_issue_enabled' ); ?></td>
			<td><?php reminder_print_checkbox( 'per_issue_enabled', ON == plugin_config_get( 'per_issue_enabled' ) ); ?></td>
		</tr>
		<tr>
			<td class="category"><?php echo plugin_lang_get( 'per_issue_interval_days' ); ?></td>
			<td><input type="number" name="per_issue_interval_days" min="1" max="365"
				value="<?php echo (int)plugin_config_get( 'per_issue_interval_days' ); ?>"></td>
		</tr>
		<tr>
			<td class="category"><?php echo plugin_lang_get( 'per_issue_stale_days' ); ?></td>
			<td><input type="number" name="per_issue_stale_days" min="0" max="365"
				value="<?php echo (int)plugin_config_get( 'per_issue_stale_days' ); ?>"></td>
		</tr>
		<tr>
			<td class="category"><?php echo plugin_lang_get( 'include_assigned' ); ?></td>
			<td><?php reminder_print_checkbox( 'per_issue_include_assigned', ON == plugin_config_get( 'per_issue_include_assigned' ) ); ?></td>
		</tr>
		<tr>
			<td class="category"><?php echo plugin_lang_get( 'include_reported' ); ?></td>
			<td><?php reminder_print_checkbox( 'per_issue_include_reported', ON == plugin_config_get( 'per_issue_include_reported' ) ); ?></td>
		</tr>
		<tr>
			<td class="category"><?php echo plugin_lang_get( 'include_monitored' ); ?></td>
			<td><?php reminder_print_checkbox( 'per_issue_include_monitored', ON == plugin_config_get( 'per_issue_include_monitored' ) ); ?></td>
		</tr>

		<tr><td class="category" colspan="2"><strong><?php echo plugin_lang_get( 'general_section' ); ?></strong></td></tr>
		<tr>
			<td class="category"><?php echo plugin_lang_get( 'open_status_threshold' ); ?></td>
			<td><input type="text" name="open_status_threshold" size="6"
				value="<?php echo string_attribute( plugin_config_get( 'open_status_threshold' ) ); ?>">
				<span class="lighter"><?php echo plugin_lang_get( 'open_status_threshold_hint' ); ?></span></td>
		</tr>
		<tr>
			<td class="category"><?php echo plugin_lang_get( 'cron_token' ); ?></td>
			<td><input type="text" name="cron_token" size="40"
				value="<?php echo string_attribute( plugin_config_get( 'cron_token' ) ); ?>">
				<span class="lighter"><?php echo plugin_lang_get( 'cron_token_hint' ); ?></span></td>
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
