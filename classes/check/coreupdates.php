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
 * Admin tool "Update check" - Moodle core updates check
 *
 * @package    tool_updatecheck
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_updatecheck\check;

use core\check\result;
use tool_updatecheck\local\updateinfo;

/**
 * Check if there are Moodle core updates available.
 *
 * @package    tool_updatecheck
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coreupdates extends base {
    /**
     * Return the result based on the (fresh) update information.
     *
     * @return result
     */
    protected function get_update_result(): result {
        $updates = updateinfo::get_core_updates();

        // Compose the list of updates.
        $items = [];
        foreach ($updates as $update) {
            if ($update->type === updateinfo::TYPE_MINOR) {
                $items[] = get_string('checkcoreupdatesminor', 'tool_updatecheck', ['release' => s($update->release)]);
            } else {
                // The release date of the major release is not shown here on purpose.
                // It is just used internally to find out if the major update is overdue.
                $items[] = get_string(
                    $update->overdue ? 'checkcoreupdatesmajoroverdue' : 'checkcoreupdatesmajor',
                    'tool_updatecheck',
                    ['release' => s($update->release)]
                );
            }
        }

        // Compose the details.
        // The number of available updates is always added as its own line after the list of updates (even if it is 0)
        // to allow monitoring systems to get all information from the details alone.
        $available = get_string('checkcoreupdatesavailable', 'tool_updatecheck', count($items));
        $lines = $items;
        $lines[] = $available;
        $lines[] = updateinfo::get_last_fetch_info();
        $details = $this->format_details($lines);

        // If there is not any update, we are good.
        if (empty($items)) {
            return new result(result::OK, get_string('checkcoreupdatesok', 'tool_updatecheck'), $details);
        }

        // Return the result.
        return new result(updateinfo::get_core_status($updates), $available, $details);
    }
}
