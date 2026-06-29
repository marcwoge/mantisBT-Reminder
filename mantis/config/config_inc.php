<?php
# MantisBT - A PHP based bugtracking system

# MantisBT is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 2 of the License, or
# (at your option) any later version.
#
# MantisBT is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with MantisBT.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package MantisBT
 * @copyright Copyright (C) 2000 - 2002  Kenzaburo Ito - kenito@300baud.org
 * @copyright Copyright (C) 2002 MantisBT Team - mantisbt-dev@lists.sourceforge.net
 * @link http://www.mantisbt.org
 */

# This sample file contains the essential files that you MUST
# configure to your specific settings.  You may override settings
# from config_defaults_inc.php by uncommenting the config option
# and setting its value in this file.

# Rename this file to config_inc.php after configuration.

# In general the value OFF means the feature is disabled and ON means the
# feature is enabled.  Any other cases will have an explanation.

# Look in http://www.mantisbt.org/docs/ or config_defaults_inc.php for more
# detailed comments.

# --- Database Configuration ---

$g_hostname               = 'localhost';
$g_db_type                = 'mysqli';
$g_database_name          = 'mantis';
$g_db_username            = 'mantis';
$g_db_password            = '09abca6b5fcb932f584c583b7475d34a';

# --- Security ---
#$g_crypto_master_salt = '';	#  Random string of at least 16 chars, unique to the installation
$g_crypto_master_salt     = 'fIWiO3lVYLMyTDuSeFiZ0N53Jvv5cwjuifbD1oRWtts=';

# --- Anonymous Access / Signup ---
$g_allow_signup			= ON;
$g_allow_anonymous_login	= OFF;
$g_anonymous_account		= '';

# --- Email Configuration ---
$g_phpMailer_method		= PHPMAILER_METHOD_SENDMAIL; # or PHPMAILER_METHOD_SMTP, PHPMAILER_METHOD_SENDMAIL, PHPMAILER_METHOD_MAIL
#$g_smtp_host			= '10.100.100.10:1587';			# used with PHPMAILER_METHOD_SMTP
#$g_smtp_username		= '';					# used with PHPMAILER_METHOD_SMTP
#$g_smtp_password		= '';					# used with PHPMAILER_METHOD_SMTP
$g_webmaster_email      = 'marc.woge@ntc-gmbh.com';
$g_from_email           = 'mantis@ntc-gmbh.com';	# the "From: " field in emails
$g_return_path_email    = 'marc.woge@ntc-gmbh.com';	# the return address for bounced mail
$g_from_name			= 'NTC Mantis System';
$g_enable_email_notification = ON;
#$g_enable_email_notification = array('ntc-gmbh.com');
$g_email_receive_own	= ON;
# $g_email_send_using_cronjob = OFF;

# --- Attachments / File Uploads ---
# $g_allow_file_upload	= ON;
# $g_file_upload_method	= DATABASE; # or DISK
# $g_absolute_path_default_upload_folder = ''; # used with DISK, must contain trailing \ or /.
# $g_max_file_size		= 5000000;	# in bytes
# $g_preview_attachments_inline_max_size = 256 * 1024;
# $g_allowed_files		= '';		# extensions comma separated, e.g. 'php,html,java,exe,pl'
# $g_disallowed_files		= '';		# extensions comma separated

# --- Branding ---
$g_window_title			= 'NTC MantisBT';
$g_logo_image			= 'images/mantis_logo_ntc.png';
$g_favicon_image		= 'images/favicon.ico';

# --- Real names ---
# $g_show_realname = ON;
# $g_show_user_realname_threshold = NOBODY;	# Set to access level (e.g. VIEWER, REPORTER, DEVELOPER, MANAGER, etc)

# --- Others ---
# $g_default_home_page = 'my_view_page.php';	# Set to name of page to go to after login

$g_default_timezone       = 'Europe/Berlin';
$g_enable_eta = ON;

/**
 * threshold to update due date submitted
 * @global integer $g_due_date_update_threshold
 */
$g_due_date_update_threshold = VIEWER;

/**
 * threshold to see due date
 * @global integer $g_due_date_view_threshold
 */
$g_due_date_view_threshold = VIEWER;

# --- Bugreport Create Formular fields ---
$g_bug_report_page_fields = array(
		'category_id',
		'view_state',
		'handler',
		'priority',
		'tags',
		'summary',
		'description',
		'additional_info',
		'attachments',
		'due_date',
		'eta',
	);

$g_path                   = '';
#$g_path                   = 'https://dwh.ntc-gmbh.com/mantis/';
$g_short_path = 'mantis/';

# --- API ---
$g_webservice_rest_enabled = ON;
$g_webservice_soap_enabled = ON;
$g_rest_key_enabled = ON;


# --- Notification Rules ---
$g_notify_flags['owner']['assigned'] = ON; // E-Mail an den neuen Besitzer
$g_notify_flags['owner']['status'] = ON;   // E-Mail bei Statusänderung
$g_notify_flags['owner']['updated'] = ON;  // E-Mail bei Ticket-Änderungen

$g_notify_flags['reporter']['status'] = ON;
$g_notify_flags['reporter']['updated'] = ON;




