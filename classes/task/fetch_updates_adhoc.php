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
 * Admin tool "Update check" - Adhoc task to fetch the update information
 *
 * @package    tool_updatecheck
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_updatecheck\task;

use tool_updatecheck\local\updateinfo;

/**
 * Adhoc task which fetches the update information as soon as possible.
 *
 * This task is queued by the checks if they detect that the update information is missing or too old.
 *
 * @package    tool_updatecheck
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fetch_updates_adhoc extends \core\task\adhoc_task {
    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskfetchupdatesadhoc', 'tool_updatecheck');
    }

    /**
     * Do the job.
     *
     * This task deliberately does not fail if the fetch fails: A failed adhoc task would be retried by Moodle core with
     * a growing delay of up to one day, and its failed record would remain in the database for weeks. In Moodle 4.5, such
     * a record is considered as queued task, which would prevent the checks from queueing a new task. Instead, the
     * failure is remembered by updateinfo::fetch() and reported by the checks, and the checks request a new fetch after
     * a short delay (see updateinfo::request_fetch()).
     */
    public function execute() {
        // If the update information has been fetched in the meantime, there is nothing to do.
        if (updateinfo::is_fresh()) {
            mtrace('Info about available updates has been fetched in the meantime, skipping.');
            return;
        }

        // Fetch the update information and notify the recipients about new updates (if enabled).
        mtrace('Fetching info about available updates ... ', '');
        try {
            updateinfo::fetch(true);
            mtrace('SUCCESS');
        } catch (\core\update\checker_exception $e) {
            mtrace('FAILED: ' . updateinfo::get_fetch_error_message($e));
        }
    }
}
