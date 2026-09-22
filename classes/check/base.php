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
 * Admin tool "Update check" - Base class for the checks
 *
 * @package    tool_updatecheck
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_updatecheck\check;

use core\check\check;
use core\check\result;
use tool_updatecheck\local\updateinfo;

/**
 * Base class for the checks which handles everything that both checks have in common.
 *
 * @package    tool_updatecheck
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base extends check {
    /**
     * A link to a place to action this.
     *
     * @return \action_link|null
     */
    public function get_action_link(): ?\action_link {
        global $CFG;

        // Compose and return a link to the report page.
        return new \action_link(
            new \moodle_url('/' . $CFG->admin . '/tool/updatecheck/index.php'),
            get_string('pluginname', 'tool_updatecheck')
        );
    }

    /**
     * Return result.
     *
     * @return result
     */
    public function get_result(): result {
        // If the update information is missing or too old.
        if (!updateinfo::is_fresh()) {
            // Request that the update information is fetched by cron as soon as possible.
            updateinfo::request_fetch();

            // And return the configured status for stale update information (unknown by default) in the meantime.
            // If the last attempt to fetch the update information has failed, tell about it, as this is most likely
            // the reason why the update information is not fresh.
            $lines = [get_string('checkunknowndetails', 'tool_updatecheck')];
            $failureinfo = updateinfo::get_last_fetch_failure_info();
            if ($failureinfo !== null) {
                $lines[] = s($failureinfo);
            }
            $lines[] = updateinfo::get_last_fetch_info();

            return new result(
                updateinfo::get_stale_status(),
                get_string('checkunknown', 'tool_updatecheck'),
                $this->format_details($lines)
            );
        }

        // Otherwise, rate the update information.
        return $this->get_update_result();
    }

    /**
     * Return the result based on the (fresh) update information.
     *
     * @return result
     */
    abstract protected function get_update_result(): result;

    /**
     * Format the given lines as details of a check result.
     *
     * The details of a check result are shown as HTML in the Moodle GUI, but they are converted to plain text by
     * admin/cli/checks.php. This CLI script is not able to indent multiple lines of details and would output a broken table.
     * Thus, we must not use any HTML block elements or line breaks within the details. Instead, each line is wrapped into
     * an inline element which is displayed as block in the Moodle GUI. The lines are separated by the configured separator
     * which is visually hidden in the Moodle GUI, but which is kept in the plain text.
     *
     * @param string[] $lines The lines as HTML strings.
     * @return string
     */
    protected function format_details(array $lines): string {
        // Compose the separator.
        // The details are one single HTML string which is used by the Moodle GUI and by admin/cli/checks.php (which
        // converts it to plain text) alike, and we do not know the consumer here. Thus, the separator has to be part of
        // the HTML, otherwise the lines would be glued together in the plain text. However, in the Moodle GUI, the lines
        // are broken by the block elements already and the separator would just be clutter. That's why it is hidden
        // there: Visually with the sr-only class and additionally
        // from screen readers with the aria-hidden attribute, as they would read out loud separators like "slash" otherwise.
        // The conversion to plain text ignores the classes and the attribute, so the separator is kept there.
        // The separator does not need to be escaped as it is one of the hardcoded values from updateinfo::SEPARATORS.
        $separator = \html_writer::span(updateinfo::get_separator(), 'sr-only', ['aria-hidden' => 'true']);

        // Wrap each line into an inline element which is displayed as block in the Moodle GUI.
        $lines = array_map(function ($line) {
            return \html_writer::span($line, 'd-block');
        }, $lines);

        return implode($separator, $lines);
    }
}
