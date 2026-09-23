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
 * Admin tool "Update check" - Plugin updates check
 *
 * @package    tool_updatecheck
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_updatecheck\check;

use core\check\result;
use tool_updatecheck\local\updateinfo;

/**
 * Check if there are plugin updates available.
 *
 * @package    tool_updatecheck
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pluginupdates extends base {
    /**
     * Return the result based on the (fresh) update information.
     *
     * @return result
     */
    protected function get_update_result(): result {
        $updates = updateinfo::get_plugin_updates();

        // Compose the list of updates and count the ignored ones and the ones of plugins which are missing from disk.
        $items = [];
        $ignoredcount = 0;
        $missingcount = 0;
        foreach ($updates as $update) {
            if ($update->ignored) {
                $ignoredcount++;
                continue;
            }
            if ($update->missing) {
                $missingcount++;
                continue;
            }
            $items[] = get_string('checkpluginupdatesitem', 'tool_updatecheck', [
                'plugin' => s(updateinfo::format_plugin_name($update->component, $update->name)),
                'installed' => s($update->installedrelease ?? $update->installedversion),
                'available' => s($update->release ?? $update->version),
            ]);
        }

        // Compose the details.
        // Unless disabled in the settings, the number of plugins with available updates is added as its own line after
        // the list of updates (even if it is 0) to allow monitoring systems to get all information from the details alone.
        $available = get_string('checkpluginupdatesavailable', 'tool_updatecheck', count($items));
        $lines = $items;
        if (updateinfo::show_summary_lines()) {
            $lines[] = $available;
            if ($ignoredcount > 0) {
                $lines[] = get_string('checkpluginupdatesignored', 'tool_updatecheck', $ignoredcount);
            }
            if ($missingcount > 0) {
                $lines[] = get_string('checkpluginupdatesmissing', 'tool_updatecheck', $missingcount);
            }
            $lines[] = updateinfo::get_last_fetch_info();
        }
        $details = $this->format_details($lines);

        // If there is not any update which counts, we are good.
        if (empty($items)) {
            return new result(result::OK, get_string('checkpluginupdatesok', 'tool_updatecheck'), $details);
        }

        // Return the result.
        return new result(updateinfo::get_plugin_status($updates), $available, $details);
    }
}
