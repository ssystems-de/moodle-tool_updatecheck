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
    /** @var bool True if the check is run by the CLI script of this plugin instead of the Checks API. */
    protected $cli;

    /**
     * Constructor.
     *
     * @param bool $cli True if the check is run by the CLI script of this plugin, which is able to output multiple lines
     *                  of details and which has its own settings for the separator and the plugin name format.
     *                  The Checks API of Moodle core instantiates the checks without any arguments.
     */
    public function __construct(bool $cli = false) {
        $this->cli = $cli;
    }

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
     * The details of a check result are one single HTML string. Depending on the consumer, it is either shown as HTML
     * in the Moodle GUI or converted to plain text by a CLI script. Each line is wrapped into an inline element which is
     * displayed as block in the Moodle GUI, and the lines are joined with a separator which depends on the consumer:
     *
     * Checks API (the check is instantiated by Moodle core): The consumer is not known, it might be the Moodle GUI or
     * admin/cli/checks.php of Moodle core. This CLI script is not able to indent multiple lines of details and would
     * output a broken table. Thus, the lines must not be separated by HTML block elements or line breaks, but by the
     * configured Checks API separator, which is visually hidden in the Moodle GUI and kept in the plain text.
     *
     * Checks CLI (the check is instantiated by the CLI script of this plugin): The consumer is the CLI script of this
     * plugin only, which is able to output multiple lines of details. Thus, the lines may be separated by the newline,
     * which is the default of the configured Checks CLI separator. The other Checks CLI separators work like the
     * Checks API separators.
     *
     * @param string[] $lines The lines as HTML strings.
     * @return string
     */
    protected function format_details(array $lines): string {
        // Wrap each line into an inline element which is displayed as block in the Moodle GUI.
        $lines = array_map(function ($line) {
            return \html_writer::span($line, 'd-block');
        }, $lines);

        $separator = updateinfo::get_separator($this->cli);

        if ($this->cli) {
            // Checks CLI.
            // The details are only converted to plain text by the CLI script of this plugin, so the separator can be put
            // into the HTML as it is. Just the newline has to be put into the HTML as line break element, as the
            // conversion to plain text collapses literal newlines to spaces, but converts a line break element to a newline.
            // The separator does not need to be escaped as it is one of the hardcoded values from updateinfo::CLI_SEPARATORS.
            if ($separator === "\n") {
                $separator = \html_writer::empty_tag('br');
            }
        } else {
            // Checks API.
            // The separator has to be part of the HTML, otherwise the lines would be glued together in the plain text.
            // However, in the Moodle GUI, the lines are broken by the block elements already and the separator would
            // just be clutter. That's why it is hidden there: Visually with the sr-only class and additionally
            // from screen readers with the aria-hidden attribute, as they would read out loud separators like "slash"
            // otherwise. The conversion to plain text ignores the class and the attribute, so the separator is kept there.
            // The separator does not need to be escaped as it is one of the hardcoded values from updateinfo::API_SEPARATORS.
            $separator = \html_writer::span($separator, 'sr-only', ['aria-hidden' => 'true']);
        }

        return implode($separator, $lines);
    }
}
