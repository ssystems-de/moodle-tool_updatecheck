<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Admin tool "Update check" - Language pack
 *
 * @package    tool_updatecheck
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['cachedef_updates'] = 'Lists of available updates';
$string['checkcoreupdates'] = 'Moodle core updates';
$string['checkcoreupdatesavailable'] = 'Moodle core updates available: {$a}';
$string['checkcoreupdatesmajor'] = '{$a->release} - Major release';
$string['checkcoreupdatesmajoroverdue'] = '{$a->release} - Major release, overdue';
$string['checkcoreupdatesminor'] = '{$a->release} - Minor release';
$string['checkcoreupdatesok'] = 'There are no Moodle core updates available.';
$string['checkpluginupdates'] = 'Plugin updates';
$string['checkpluginupdatesavailable'] = 'Plugins with available updates: {$a}';
$string['checkpluginupdatesignored'] = 'Ignored plugins with available updates: {$a}';
$string['checkpluginupdatesitem'] = '{$a->plugin}: {$a->installed} to {$a->available}';
$string['checkpluginupdatesmissing'] = 'Plugins missing from disk with available updates: {$a}';
$string['checkpluginupdatesok'] = 'There are no plugin updates available.';
$string['checkunknown'] = 'The update information is missing or outdated.';
$string['checkunknowndetails'] = 'The update information will be fetched from the Moodle update server by cron as soon as possible. If this status persists, please check if cron is running and if this Moodle site is able to connect to the Moodle update server.';
$string['lastfetch'] = 'Last successful fetch of the update information: {$a}';
$string['lastfetchfailure'] = 'The last attempt to fetch the update information ({$a->time}) has failed: {$a->message}. Cron will retry it.';
$string['notificationdetailslink'] = 'See {$a->url} for more details';
$string['notificationfooter'] = 'Your Moodle site {$a->siteurl} has sent you this message as you are selected as recipient of the update notifications in the settings of the update check plugin. An administrator can change the recipients in {$a->settingspath}. You can customise the delivery of this message via your preferences page.';
$string['notificationintro'] = 'There are new updates available for this Moodle site. This is the list of all available updates.';
$string['notificationlegendignored'] = '[{$a}] marks plugins which are ignored according to the settings of the update check plugin. Their updates do not affect the status and do not trigger this message.';
$string['notificationlegendmissing'] = '[{$a}] marks plugins which are only known in the database, but whose code is missing from disk. Their updates do not affect the status and do not trigger this message, as the plugin code would have to be restored first.';
$string['notificationlegendnew'] = '[{$a}] marks updates which have become available since the update information has been fetched for the last time and which have triggered this message.';
$string['notificationnew'] = 'New';
$string['pluginname'] = 'Update check';
$string['privacy:metadata'] = 'The update check plugin provides extended functionality to Moodle admins, but does not store any personal data.';
$string['reportavailablerelease'] = 'Available release';
$string['reportavailableversion'] = 'Available version';
$string['reportfetcherror_err_response_empty'] = 'The Moodle update server returned an empty response.';
$string['reportfetcherror_err_response_format'] = 'The Moodle update server returned a response in an unexpected format.';
$string['reportfetcherror_err_response_status'] = 'The Moodle update server returned an unexpected status.';
$string['reportfetcherror_err_response_target_version'] = 'The Moodle update server returned update information for another Moodle branch.';
$string['reportfetchnow'] = 'Check for available updates now';
$string['reportfetchsuccess'] = 'The update information has been fetched successfully.';
$string['reportignored'] = 'Ignored';
$string['reportinstalledrelease'] = 'Installed Moodle release: {$a}';
$string['reportinstalledversion'] = 'Installed version';
$string['reportmaturity'] = 'Maturity';
$string['reportmissing'] = 'Missing from disk';
$string['reportnocoreupdates'] = 'There are no Moodle core updates available.';
$string['reportnopluginupdates'] = 'There are no plugin updates available.';
$string['reportnotfresh'] = 'The update information is missing or older than {$a} hours. It will be fetched by cron as soon as possible. Until then, the checks will return the status \'Unknown\'.';
$string['reportoverdue'] = 'Overdue';
$string['reportstatus'] = 'System status';
$string['reporttype'] = 'Type';
$string['reporttypemajor'] = 'Major release';
$string['reporttypeminor'] = 'Minor release';
$string['setting_checksapiheading'] = 'Checks API';
$string['setting_checksapipluginnameformat'] = 'Plugin name format';
$string['setting_checksapipluginnameformat_desc'] = 'With this setting, you control how the plugins are named in the list of available plugin updates which is part of the details of the plugin updates check. This setting does not have any effect on the report page, where the plugin name and the component name are always shown.';
$string['setting_checksapiseparator'] = 'Checks API separator';
$string['setting_checksapiseparator_desc'] = 'With this setting, you control the separator which is put between the individual pieces of information in the Checks API details, for example between the plugins with available updates.<br>Background: In the Moodle GUI, each piece of information is shown on its own line. However, the CLI script admin/cli/checks.php, which is normally used by monitoring systems to query the checks, converts the Checks API details to plain text and is not able to output details which consist of multiple lines properly. Thus, the Checks API details are output as one single line in plain text and the individual pieces of information are separated with this separator. In the Moodle GUI, the separator is not visible.<br>If you are parsing the Checks API details in your monitoring system, you can pick the separator which fits best to your parsing. Please note that the separator characters might also be part of the information itself, for example a slash within a plugin name. This is especially true for the hyphen, which is a common part of release names like \'v4.5-r1\'. The hyphen is always surrounded by one space on each side when it is used as separator. Thus, if you pick the hyphen, your parsing has to include these spaces and has to split the details at \' - \' instead of \'-\'. The same applies to the slash, the double colon and the hash, which are surrounded by spaces as well, whereas the semicolon is just followed by a space.';
$string['setting_checksapiseparator_doublecolon'] = ':: (Double colon)';
$string['setting_checksapiseparator_hash'] = '# (Hash)';
$string['setting_checksapiseparator_hyphen'] = '- (Hyphen)';
$string['setting_checksapiseparator_semicolon'] = '; (Semicolon)';
$string['setting_checksapiseparator_slash'] = '/ (Slash)';
$string['setting_coreheading'] = 'Moodle core updates';
$string['setting_coremajormonths'] = 'Escalate major updates after';
$string['setting_coremajormonths_desc'] = 'With this setting, you control the number of months after which an available Moodle major update is escalated. As long as the oldest major release which is newer than the installed major release is younger than this number of months, the Moodle core updates check will just return the status \'Info\'. As soon as this major release is older, the check will return the status which is configured for major updates. If you set this setting to 0, a major update is escalated immediately as soon as it is available. Negative values are not allowed. If you are running a LTS release and want to stay on it for a longer time, you should set this setting to a higher value like 24 or 36 months instead. And if you do not want major updates to be escalated at all, simply set the status for major updates to \'Info\'.';
$string['setting_coreminmaturity'] = 'Required maturity of Moodle core updates';
$string['setting_coreminmaturity_desc'] = 'With this setting, you control the minimum maturity of available Moodle core updates. Available Moodle core updates which are less mature will not be considered by the Moodle core updates check.';
$string['setting_corenotifybuilds'] = 'Consider new builds';
$string['setting_corenotifybuilds_desc'] = 'With this setting, you control if a new weekly build of the installed Moodle release should be considered as available update by the Moodle core updates check.';
$string['setting_corestatusmajor'] = 'Status for major updates';
$string['setting_corestatusmajor_desc'] = 'With this setting, you control the status which is returned by the Moodle core updates check if there is a Moodle major update available which is overdue (for example an update from Moodle 4.5 to Moodle 5.0).';
$string['setting_corestatusminor'] = 'Status for minor updates';
$string['setting_corestatusminor_desc'] = 'With this setting, you control the status which is returned by the Moodle core updates check if there is a Moodle minor update available (for example an update from Moodle 4.5.1 to Moodle 4.5.2).';
$string['setting_generalheading'] = 'General';
$string['setting_generalheading_desc'] = 'Please note: This plugin works independently from the update notification settings in Moodle core. It provides its checks even if update notifications are disabled in Moodle core and it does not respect the update notification settings of Moodle core, but uses its own settings instead.';
$string['setting_generalmaxage'] = 'Maximum age of the update information';
$string['setting_generalmaxage_desc'] = 'With this setting, you control the maximum age of the update information in hours. If the update information is older or if it is not available at all, the checks will trigger a fetch of the update information by cron and will return the status \'Unknown\' until the update information has been fetched. Please note that the update information is fetched once a day by a scheduled task anyway. The default value of 25 hours gives this scheduled task a tolerance of one hour, for example if cron is delayed. Thus, you should not set this setting to a lower value unless you let the scheduled task run more frequently as well.';
$string['setting_generalsettingsheading'] = 'General settings';
$string['setting_generalstalestatus'] = 'Status for missing or outdated update information';
$string['setting_generalstalestatus_desc'] = 'With this setting, you control the status which both checks return as long as the update information is missing or older than the configured maximum age, for example because this Moodle site is not able to connect to the Moodle update server or because cron is not running. By default, the checks return the status \'Unknown\' in this case, which the CLI script admin/cli/checks.php reports with the exit code 3. If your monitoring system should treat this case like a real problem, you can pick a more severe status here.';
$string['setting_notificationsheading'] = 'Notification mails';
$string['setting_notificationsrecipients'] = 'Notification recipients';
$string['setting_notificationsrecipients_desc'] = 'With this setting, you control who is notified by this plugin about updates which have become available. You can select particular users, everyone who is allowed to change the site configuration, or nobody. By default, nobody is selected and the plugin does not send any notifications at all. The users who can be selected are the site administrators and all other users who have the capability moodle/site:config in the system context, as this capability is required to receive this kind of notification. A selected user who loses this capability later is not notified anymore.<br>If at least one user is selected, the plugin notifies the selected users each time when its scheduled task or its adhoc task has fetched the update information and has found new updates. The notification is a plain text message which is built in the same way as the report of this plugin: It lists all available updates, marks the new ones and links to the report. The notification is sent with the \'Available update notifications\' message provider of Moodle core, so each recipient receives it according to the own notification preferences, by default as mail and as popup notification.<br>Please note: The notifications are sent regardless of the update notification settings in Moodle core, i.e. even if update notifications are disabled with $CFG->disableupdatenotifications in config.php or if the setting \'Automatically check for available updates\' is disabled. The notifications respect the settings of this plugin instead: The required maturity of Moodle core and plugin updates, the consideration of new builds and the ignored plugins.<br>Background: As soon as this plugin is installed, Moodle core does not send its own update notifications anymore, regardless of this setting. Moodle core only sends an update notification if its own scheduled task fetches the update information and finds a difference to the previously fetched update information. As this plugin fetches the update information once a day by itself, the scheduled task of Moodle core always considers the update information as fresh enough and skips its run. This is a technical limitation in Moodle core which this plugin is not able to influence. Thus, as long as nobody is selected here, the admins will not receive any update notifications at all and you should make sure that the checks of this plugin are monitored otherwise.';
$string['setting_pluginsheading'] = 'Plugin updates';
$string['setting_pluginsignored'] = 'Ignored plugins';
$string['setting_pluginsignored_desc'] = 'With this setting, you can select plugins which should be ignored by the plugin updates check. Available updates for these plugins will not affect the status of the check anymore. This is useful if you want or have to stay with the installed version of a particular plugin or if the plugin has been forked locally. On the report page, the available updates for ignored plugins are still listed, but are marked as ignored.';
$string['setting_pluginsminmaturity'] = 'Required maturity of plugin updates';
$string['setting_pluginsminmaturity_desc'] = 'With this setting, you control the minimum maturity of available plugin updates. Available plugin updates which are less mature will not be considered by the plugin updates check.';
$string['setting_pluginsstatus'] = 'Status for plugin updates';
$string['setting_pluginsstatus_desc'] = 'With this setting, you control the status which is returned by the plugin updates check if there is at least one plugin update available.';
$string['settings'] = 'Update check settings';
$string['taskfetchupdates'] = 'Fetch update information';
$string['taskfetchupdatesadhoc'] = 'Fetch update information (triggered by a check)';
