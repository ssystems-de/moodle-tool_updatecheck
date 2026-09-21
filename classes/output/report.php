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
 * Admin tool "Update check" - Report renderable
 *
 * @package    tool_updatecheck
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_updatecheck\output;

use tool_updatecheck\check\coreupdates;
use tool_updatecheck\check\pluginupdates;
use tool_updatecheck\local\updateinfo;

/**
 * Renderable which lists the available updates on the report page.
 *
 * @package    tool_updatecheck
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report implements \renderable, \templatable {
    /**
     * Export the data for the mustache template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output) {
        global $CFG;

        // Compose the general data.
        $data = [
            'lastfetchinfo' => updateinfo::get_last_fetch_info(),
            'fresh' => updateinfo::is_fresh(),
            'notfreshinfo' => get_string('reportnotfresh', 'tool_updatecheck', updateinfo::get_maxage()),
            'fetchfailureinfo' => updateinfo::get_last_fetch_failure_info(),
            'fetchbutton' => (new \single_button(
                new \moodle_url('/' . $CFG->admin . '/tool/updatecheck/index.php', ['fetch' => 1]),
                get_string('reportfetchnow', 'tool_updatecheck'),
                'post',
                \single_button::BUTTON_PRIMARY
            ))->export_for_template($output),
            'settingsurl' => (new \moodle_url(
                '/' . $CFG->admin . '/settings.php',
                ['section' => 'tool_updatecheck_settings']
            ))->out(false),
            'statusurl' => (new \moodle_url('/report/status/index.php'))->out(false),
            'installedreleaseinfo' => get_string('reportinstalledrelease', 'tool_updatecheck', $CFG->release),
        ];

        // Compose the Moodle core updates.
        $data['coreupdates'] = self::export_core_updates(updateinfo::get_core_updates());
        $data['hascoreupdates'] = !empty($data['coreupdates']);

        // Get the status of the Moodle core updates directly from the check to make sure that the report always shows
        // the same status as the Checks API. The check does not gather the updates once more as they are cached for the
        // current request. As a side effect, this requests a fetch if the update information is not fresh.
        $coreresult = (new coreupdates())->get_result();
        $data['corestatus'] = $coreresult->get_status();
        $data['corestatusbadge'] = $output->check_result($coreresult);

        // Compose the plugin updates.
        $data['pluginupdates'] = self::export_plugin_updates(updateinfo::get_plugin_updates());
        $data['haspluginupdates'] = !empty($data['pluginupdates']);

        // Get the status of the plugin updates directly from the check as well.
        $pluginresult = (new pluginupdates())->get_result();
        $data['pluginstatus'] = $pluginresult->get_status();
        $data['pluginstatusbadge'] = $output->check_result($pluginresult);

        return $data;
    }

    /**
     * Export the given Moodle core updates as rows for a mustache template.
     *
     * This is used by the report page as well as by the update notification, which lists the updates in the same way.
     *
     * @param \stdClass[] $updates The updates as returned by updateinfo::get_core_updates().
     * @return array[] Array of rows with the keys release, version, ismajor, overdue, maturity and url.
     */
    public static function export_core_updates(array $updates): array {
        $rows = [];
        foreach ($updates as $update) {
            $rows[] = [
                'release' => $update->release,
                'version' => $update->version,
                'ismajor' => $update->type === updateinfo::TYPE_MAJOR,
                'overdue' => $update->overdue,
                'maturity' => updateinfo::get_maturity_name($update->maturity),
                'url' => $update->url,
            ];
        }

        return $rows;
    }

    /**
     * Export the given plugin updates as rows for a mustache template.
     *
     * This is used by the report page as well as by the update notification, which lists the updates in the same way.
     *
     * @param \stdClass[] $updates The updates as returned by updateinfo::get_plugin_updates().
     * @return array[] Array of rows with the keys name, component, installedrelease, installedversion, release, version,
     *                 maturity, url, ignored and missing.
     */
    public static function export_plugin_updates(array $updates): array {
        $rows = [];
        foreach ($updates as $update) {
            $rows[] = [
                'name' => $update->name,
                'component' => $update->component,
                'installedrelease' => $update->installedrelease,
                'installedversion' => $update->installedversion,
                'release' => $update->release,
                'version' => $update->version,
                'maturity' => updateinfo::get_maturity_name($update->maturity),
                'url' => $update->url,
                'ignored' => $update->ignored,
                'missing' => $update->missing,
            ];
        }

        return $rows;
    }
}
