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
