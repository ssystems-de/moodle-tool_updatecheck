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
 * Admin tool "Update check" - Update notification composer
 *
 * @package    tool_updatecheck
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_updatecheck\local;

use tool_updatecheck\output\report;

/**
 * Helper class which composes the text of the update notification.
 *
 * The notification is a plain text message which is built in the same way as the report page: It lists all available
 * Moodle core updates and plugin updates together with their status, and it additionally marks the updates which are
 * new, i.e. the updates which have triggered the notification.
 *
 * @package    tool_updatecheck
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notification {
    /** @var \core\update\info[] The updates which have become available and which have triggered the notification. */
    protected $newupdates;

    /**
     * Constructor.
     *
     * @param \core\update\info[] $newupdates The updates which have become available.
     */
    public function __construct(array $newupdates) {
        $this->newupdates = $newupdates;
    }

    /**
     * Get the URL of the report page.
     *
     * @return string
     */
    public static function get_report_url(): string {
        global $CFG;

        return (new \moodle_url('/' . $CFG->admin . '/tool/updatecheck/index.php'))->out(false);
    }

    /**
     * Compose the text of the notification.
     *
     * @return string
     */
    public function get_text(): string {
        global $CFG;

        $lines = [];
        $lines[] = get_string('updatenotifications', 'core_admin');
        $lines[] = '';
        $lines[] = get_string('notificationintro', 'tool_updatecheck');

        // Add the Moodle core updates.
        $coreupdates = updateinfo::get_core_updates();
        $lines[] = '';
        $lines[] = get_string('checkcoreupdates', 'tool_updatecheck') .
                ' (' . get_string('status' . updateinfo::get_core_status($coreupdates)) . ')';
        $lines[] = get_string('reportinstalledrelease', 'tool_updatecheck', $CFG->release);
        if (empty($coreupdates)) {
            $lines[] = get_string('reportnocoreupdates', 'tool_updatecheck');
        }
        foreach (report::export_core_updates($coreupdates) as $row) {
            $lines[] = $this->get_core_update_line($row);
        }

        // Add the plugin updates.
        $pluginupdates = updateinfo::get_plugin_updates();
        $lines[] = '';
        $lines[] = get_string('checkpluginupdates', 'tool_updatecheck') .
                ' (' . get_string('status' . updateinfo::get_plugin_status($pluginupdates)) . ')';
        if (empty($pluginupdates)) {
            $lines[] = get_string('reportnopluginupdates', 'tool_updatecheck');
        }
        $hasignored = false;
        $hasmissing = false;
        foreach (report::export_plugin_updates($pluginupdates) as $row) {
            $lines[] = $this->get_plugin_update_line($row);
            $hasignored = $hasignored || $row['ignored'];
            $hasmissing = $hasmissing || $row['missing'];
        }

        // Add the link to the report.
        $lines[] = '';
        $lines[] = get_string('notificationdetailslink', 'tool_updatecheck', ['url' => self::get_report_url()]);

        // Add the footer: The legend of the markers, followed by the explanation why this message has been sent.
        $lines[] = '';
        $lines[] = '---';
        $lines[] = get_string('notificationlegendnew', 'tool_updatecheck', get_string('notificationnew', 'tool_updatecheck'));
        if ($hasignored) {
            $lines[] = get_string(
                'notificationlegendignored',
                'tool_updatecheck',
                get_string('reportignored', 'tool_updatecheck')
            );
        }
        if ($hasmissing) {
            $lines[] = get_string(
                'notificationlegendmissing',
                'tool_updatecheck',
                get_string('reportmissing', 'tool_updatecheck')
            );
        }
        $lines[] = '';
        $lines[] = get_string('notificationfooter', 'tool_updatecheck', [
            'siteurl' => $CFG->wwwroot,
            'settingspath' => implode(' / ', [
                get_string('administrationsite'),
                get_string('plugins', 'core_admin'),
                get_string('tools', 'core_admin'),
                get_string('settings', 'tool_updatecheck'),
            ]),
        ]);

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * Compose the line for a Moodle core update.
     *
     * @param array $row The update as returned by report::export_core_updates().
     * @return string
     */
    protected function get_core_update_line(array $row): string {
        // Compose the line from the same pieces of information as the columns of the report.
        $line = '* ' . $row['release'] . ', ' . get_string('updateavailable_version', 'core_admin', $row['version']);
        $line .= ' - ' . get_string($row['ismajor'] ? 'reporttypemajor' : 'reporttypeminor', 'tool_updatecheck');
        if ($row['overdue']) {
            $line .= ' (' . get_string('reportoverdue', 'tool_updatecheck') . ')';
        }
        if ($row['maturity'] !== '') {
            $line .= ' - ' . $row['maturity'];
        }

        // A Moodle core update is new if exactly this version has become available.
        foreach ($this->newupdates as $newupdate) {
            if ($newupdate->component === 'core' && (string) $newupdate->version === (string) $row['version']) {
                $line .= ' [' . get_string('notificationnew', 'tool_updatecheck') . ']';
                break;
            }
        }

        return $line;
    }

    /**
     * Compose the line for a plugin update.
     *
     * @param array $row The update as returned by report::export_plugin_updates().
     * @return string
     */
    protected function get_plugin_update_line(array $row): string {
        $line = '* ' . get_string('checkpluginupdatesitem', 'tool_updatecheck', [
            'plugin' => $row['name'] . ' (' . $row['component'] . ')',
            'installed' => self::format_release($row['installedrelease'], $row['installedversion']),
            'available' => self::format_release($row['release'], $row['version']),
        ]);
        if ($row['maturity'] !== '') {
            $line .= ' - ' . $row['maturity'];
        }

        // A plugin is considered as new as a whole as soon as any update of it has become available,
        // because only the most mature most recent update of a plugin is listed.
        foreach ($this->newupdates as $newupdate) {
            if ($newupdate->component === $row['component']) {
                $line .= ' [' . get_string('notificationnew', 'tool_updatecheck') . ']';
                break;
            }
        }

        if ($row['ignored']) {
            $line .= ' [' . get_string('reportignored', 'tool_updatecheck') . ']';
        }
        if ($row['missing']) {
            $line .= ' [' . get_string('reportmissing', 'tool_updatecheck') . ']';
        }

        return $line;
    }

    /**
     * Format a release together with its version.
     *
     * @param string|null $release The release, which is optional for plugins.
     * @param int|float|string $version The version.
     * @return string
     */
    protected static function format_release(?string $release, $version): string {
        if ($release === null || $release === '') {
            return (string) $version;
        }

        return $release . ' (' . $version . ')';
    }
}
