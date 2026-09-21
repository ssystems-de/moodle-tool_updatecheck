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
 * Admin tool "Update check" - Report page
 *
 * @package    tool_updatecheck
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Get parameters.
$fetch = optional_param('fetch', false, PARAM_BOOL);

// Set up the admin page (this includes the login and capability check).
admin_externalpage_setup('tool_updatecheck_report');

// If the admin wants to fetch the update information right now.
if ($fetch) {
    require_sesskey();

    // Fetch the update information and redirect back to the report.
    try {
        \tool_updatecheck\local\updateinfo::fetch();
        redirect(
            $PAGE->url,
            get_string('reportfetchsuccess', 'tool_updatecheck'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (\core\update\checker_exception $e) {
        // The failure has been remembered by the fetch and is shown on the report, so there is no need for a notification.
        redirect($PAGE->url);
    }
}

// Output the report.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'tool_updatecheck'));
echo $OUTPUT->render_from_template(
    'tool_updatecheck/report',
    (new \tool_updatecheck\output\report())->export_for_template($OUTPUT)
);
echo $OUTPUT->footer();
