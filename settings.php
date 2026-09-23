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
 * Admin tool "Update check" - Settings
 *
 * @package    tool_updatecheck
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\check\result;
use tool_updatecheck\local\updateinfo;

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Add the plugin's report page to the reports category.
    $reportpage = new admin_externalpage(
        'tool_updatecheck_report',
        get_string('pluginname', 'tool_updatecheck', null, true),
        new moodle_url('/' . $CFG->admin . '/tool/updatecheck/index.php'),
        'moodle/site:config'
    );
    $ADMIN->add('reports', $reportpage);

    // Create the plugin's settings page.
    $settings = new admin_settingpage(
        'tool_updatecheck_settings',
        get_string('settings', 'tool_updatecheck', null, true)
    );

    if ($ADMIN->fulltree) {
        // Create general heading.
        $setting = new admin_setting_heading(
            'tool_updatecheck/generalheading',
            get_string('setting_generalheading', 'tool_updatecheck', null, true),
            get_string('setting_generalheading_desc', 'tool_updatecheck', null, true)
        );
        $settings->add($setting);

        // Create general settings heading.
        $setting = new admin_setting_heading(
            'tool_updatecheck/generalsettingsheading',
            get_string('setting_generalsettingsheading', 'tool_updatecheck', null, true),
            ''
        );
        $settings->add($setting);

        // Create maximum age widget.
        $setting = new admin_setting_configtext(
            'tool_updatecheck/generalmaxage',
            get_string('setting_generalmaxage', 'tool_updatecheck', null, true),
            get_string('setting_generalmaxage_desc', 'tool_updatecheck', null, true),
            updateinfo::DEFAULT_MAXAGE,
            // The value is validated with a regular expression as PARAM_INT would accept 0 and negative values as well.
            '/^[1-9][0-9]*$/'
        );
        $settings->add($setting);

        // Create stale status widget.
        $setting = new admin_setting_configselect(
            'tool_updatecheck/generalstalestatus',
            get_string('setting_generalstalestatus', 'tool_updatecheck', null, true),
            get_string('setting_generalstalestatus_desc', 'tool_updatecheck', null, true),
            result::UNKNOWN,
            updateinfo::get_status_options(updateinfo::STALE_STATUSES)
        );
        $settings->add($setting);

        // Create common checks heading.
        $setting = new admin_setting_heading(
            'tool_updatecheck/checkscommonheading',
            get_string('setting_checkscommonheading', 'tool_updatecheck', null, true),
            get_string('setting_checkscommonheading_desc', 'tool_updatecheck', null, true)
        );
        $settings->add($setting);

        // Create plugin name format widget.
        $setting = new admin_setting_configselect(
            'tool_updatecheck/checkspluginnameformat',
            get_string('setting_checkspluginnameformat', 'tool_updatecheck', null, true),
            get_string('setting_checkspluginnameformat_desc', 'tool_updatecheck', null, true),
            updateinfo::NAMEFORMATS[0],
            updateinfo::get_nameformat_options()
        );
        $settings->add($setting);

        // Create summary lines widget.
        $setting = new admin_setting_configselect(
            'tool_updatecheck/checkssummarylines',
            get_string('setting_checkssummarylines', 'tool_updatecheck', null, true),
            get_string('setting_checkssummarylines_desc', 'tool_updatecheck', null, true),
            1,
            [1 => get_string('yes'), 0 => get_string('no')]
        );
        $settings->add($setting);

        // Create Checks API heading.
        $setting = new admin_setting_heading(
            'tool_updatecheck/checksapiheading',
            get_string('setting_checksapiheading', 'tool_updatecheck', null, true),
            get_string('setting_checksapiheading_desc', 'tool_updatecheck', null, true)
        );
        $settings->add($setting);

        // Create Checks API separator widget.
        $setting = new admin_setting_configselect(
            'tool_updatecheck/checksapiseparator',
            get_string('setting_checksapiseparator', 'tool_updatecheck', null, true),
            get_string('setting_checksapiseparator_desc', 'tool_updatecheck', null, true),
            array_key_first(updateinfo::API_SEPARATORS),
            updateinfo::get_separator_options()
        );
        $settings->add($setting);

        // Create Checks CLI heading.
        $setting = new admin_setting_heading(
            'tool_updatecheck/checkscliheading',
            get_string('setting_checkscliheading', 'tool_updatecheck', null, true),
            get_string('setting_checkscliheading_desc', 'tool_updatecheck', null, true)
        );
        $settings->add($setting);

        // Create Checks CLI separator widget.
        $setting = new admin_setting_configselect(
            'tool_updatecheck/checkscliseparator',
            get_string('setting_checkscliseparator', 'tool_updatecheck', null, true),
            get_string('setting_checkscliseparator_desc', 'tool_updatecheck', null, true),
            array_key_first(updateinfo::CLI_SEPARATORS),
            updateinfo::get_separator_options(true)
        );
        $settings->add($setting);

        // Create Moodle core updates heading.
        $setting = new admin_setting_heading(
            'tool_updatecheck/coreheading',
            get_string('setting_coreheading', 'tool_updatecheck', null, true),
            ''
        );
        $settings->add($setting);

        // Create Moodle core minimum maturity widget.
        $setting = new admin_setting_configselect(
            'tool_updatecheck/coreminmaturity',
            get_string('setting_coreminmaturity', 'tool_updatecheck', null, true),
            get_string('setting_coreminmaturity_desc', 'tool_updatecheck', null, true),
            MATURITY_STABLE,
            updateinfo::get_maturity_options()
        );
        $settings->add($setting);

        // Create Moodle core notify builds widget.
        $setting = new admin_setting_configcheckbox(
            'tool_updatecheck/corenotifybuilds',
            get_string('setting_corenotifybuilds', 'tool_updatecheck', null, true),
            get_string('setting_corenotifybuilds_desc', 'tool_updatecheck', null, true),
            0
        );
        $settings->add($setting);

        // Create Moodle core minor update status widget.
        $setting = new admin_setting_configselect(
            'tool_updatecheck/corestatusminor',
            get_string('setting_corestatusminor', 'tool_updatecheck', null, true),
            get_string('setting_corestatusminor_desc', 'tool_updatecheck', null, true),
            result::WARNING,
            updateinfo::get_status_options()
        );
        $settings->add($setting);

        // Create Moodle core major update status widget.
        $setting = new admin_setting_configselect(
            'tool_updatecheck/corestatusmajor',
            get_string('setting_corestatusmajor', 'tool_updatecheck', null, true),
            get_string('setting_corestatusmajor_desc', 'tool_updatecheck', null, true),
            result::WARNING,
            updateinfo::get_status_options()
        );
        $settings->add($setting);

        // Create Moodle core major update months widget.
        $setting = new admin_setting_configtext(
            'tool_updatecheck/coremajormonths',
            get_string('setting_coremajormonths', 'tool_updatecheck', null, true),
            get_string('setting_coremajormonths_desc', 'tool_updatecheck', null, true),
            updateinfo::DEFAULT_MAJORMONTHS,
            // The value is validated with a regular expression as PARAM_INT would accept negative values as well.
            '/^(0|[1-9][0-9]*)$/'
        );
        $settings->add($setting);

        // Create plugin updates heading.
        $setting = new admin_setting_heading(
            'tool_updatecheck/pluginsheading',
            get_string('setting_pluginsheading', 'tool_updatecheck', null, true),
            ''
        );
        $settings->add($setting);

        // Create plugin minimum maturity widget.
        $setting = new admin_setting_configselect(
            'tool_updatecheck/pluginsminmaturity',
            get_string('setting_pluginsminmaturity', 'tool_updatecheck', null, true),
            get_string('setting_pluginsminmaturity_desc', 'tool_updatecheck', null, true),
            MATURITY_STABLE,
            updateinfo::get_maturity_options()
        );
        $settings->add($setting);

        // Create plugin update status widget.
        $setting = new admin_setting_configselect(
            'tool_updatecheck/pluginsstatus',
            get_string('setting_pluginsstatus', 'tool_updatecheck', null, true),
            get_string('setting_pluginsstatus_desc', 'tool_updatecheck', null, true),
            result::WARNING,
            updateinfo::get_status_options()
        );
        $settings->add($setting);

        // Create ignored plugins widget.
        $setting = new admin_setting_configmulticheckbox(
            'tool_updatecheck/pluginsignored',
            get_string('setting_pluginsignored', 'tool_updatecheck', null, true),
            get_string('setting_pluginsignored_desc', 'tool_updatecheck', null, true),
            [],
            updateinfo::get_ignorable_plugins()
        );
        $settings->add($setting);

        // Create notification mails heading.
        $setting = new admin_setting_heading(
            'tool_updatecheck/notificationsheading',
            get_string('setting_notificationsheading', 'tool_updatecheck', null, true),
            ''
        );
        $settings->add($setting);

        // Create notification recipients widget.
        // The default is an empty selection, which means that nobody is notified.
        $setting = new admin_setting_users_with_capability(
            'tool_updatecheck/notificationsrecipients',
            get_string('setting_notificationsrecipients', 'tool_updatecheck', null, true),
            get_string('setting_notificationsrecipients_desc', 'tool_updatecheck', null, true),
            [],
            updateinfo::NOTIFICATION_CAPABILITY
        );
        $settings->add($setting);
    }

    // Add the plugin's settings page to the admin tools category.
    $ADMIN->add('tools', $settings);
}
