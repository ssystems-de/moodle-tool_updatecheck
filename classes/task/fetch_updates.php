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
 * Admin tool "Update check" - Scheduled task to fetch the update information
 *
 * @package    tool_updatecheck
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_updatecheck\task;

use tool_updatecheck\local\updateinfo;

/**
 * Scheduled task which fetches the update information regularly.
 *
 * In contrast to Moodle core's \core\task\check_for_updates_task, this task fetches the update information
 * regardless of the fact if update notifications are enabled in Moodle core or not.
 *
 * @package    tool_updatecheck
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fetch_updates extends \core\task\scheduled_task {
    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskfetchupdates', 'tool_updatecheck');
    }

    /**
     * Do the job.
     * Throw exceptions on errors (the job will be retried).
     */
    public function execute() {
        // Fetch the update information.
        // We deliberately fetch on every run, even if the update information has been fetched recently (for example
        // by Moodle core itself). This guarantees that the update information never gets older than 24 hours plus
        // the delay of cron, which is the precondition for the default maximum age of 25 hours. If we would skip a run
        // because of a recent fetch, the update information would get older than the maximum age before the next run and
        // the checks would return the unknown status for no good reason.
        // As a side effect, Moodle core does not send its update notifications anymore. Thus, we notify the admins
        // about new updates ourselves (if enabled).
        mtrace('Fetching info about available updates ... ', '');
        updateinfo::fetch(true);
        mtrace('SUCCESS');
    }
}
