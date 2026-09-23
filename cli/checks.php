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
 * Admin tool "Update check" - CLI script which runs the checks of this plugin
 *
 * This script is copied and modified from admin/cli/checks.php of Moodle core 5.2 (see UPGRADE.md).
 * It exists because the CLI script of Moodle core outputs the details of a check only since Moodle 5.2 (MDL-87648)
 * and because it is not able to output multiple lines of details. This script differs from the script of Moodle core
 * in these aspects:
 * - It runs the checks of this plugin only and thus does not offer the --type option.
 * - The checks are run in CLI mode, which uses the settings of the "Checks CLI" section instead of the "Checks API"
 *   section, and the details of the checks may consist of multiple lines.
 * - The first line always names this plugin instead of the check which has the most severe status.
 * - The script can be included by PHPUnit tests, see the comments at the affected lines.
 *
 * @package    tool_updatecheck
 * @copyright  2020 Brendan Heywood (brendan@catalyst-au.net)
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// PHPUnit defines this constant already when it includes this script.
// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState
defined('CLI_SCRIPT') || define('CLI_SCRIPT', true);

// PHPUnit has loaded the configuration already when it includes this script.
require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use core\check\result;

[$options, $unrecognized] = cli_get_params([
    'help'    => false,
    'filter'  => '',
    'verbose' => false,
], [
    'h' => 'help',
    'f' => 'filter',
    'v' => 'verbose',
]);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

$checks = [
    new \tool_updatecheck\check\coreupdates(true),
    new \tool_updatecheck\check\pluginupdates(true),
];

$help = "Run the checks of the update check admin tool

This script works like admin/cli/checks.php of Moodle core, but it runs the checks of this plugin only
and it outputs the details of the checks on all Moodle versions (Moodle core does this since Moodle 5.2 only).

Options:
 -h, --help      Print out this help
 -f, --filter    Filter to a subset of checks by their reference (substring match)
                 One of tool_updatecheck_coreupdates, tool_updatecheck_pluginupdates
 -v, --verbose   Show details of all checks, not just failed checks

Example:

  sudo -u www-data php admin/tool/updatecheck/cli/checks.php
  sudo -u www-data php admin/tool/updatecheck/cli/checks.php -v
  sudo -u www-data php admin/tool/updatecheck/cli/checks.php -v --filter=pluginupdates

";

if ($options['help']) {
    echo $help;
    // PHPUnit must not be terminated when it includes this script.
    if (PHPUNIT_TEST) {
        return 0;
    }
    die();
}

$filter = $options['filter'];
if ($filter) {
    $checks = array_filter($checks, function ($check, $key) use ($filter) {
        $ref = $check->get_ref();
        return (strpos($ref, $filter) !== false);
    }, 1);
}

// These shell exit codes and labels align with the NRPE standard.
$exitcodes = [
    result::NA        => 0,
    result::OK        => 0,
    result::INFO      => 0,
    result::UNKNOWN   => 3,
    result::WARNING   => 1,
    result::ERROR     => 2,
    result::CRITICAL  => 2,
];
$exitlabel = [
    result::NA        => 'OK',
    result::OK        => 'OK',
    result::INFO      => 'OK',
    result::UNKNOWN   => 'UNKNOWN',
    result::WARNING   => 'WARNING',
    result::ERROR     => 'CRITICAL',
    result::CRITICAL  => 'CRITICAL',
];

$format = "%      10s| % -60s\n";
$spacer = "----------+--------------------------------------------------------------------\n";
$prefix = '          |';

$output = '';
// The first line always names this plugin, regardless of which check has the most severe status.
$title = get_string('checkscli', 'tool_updatecheck') . ' (tool_updatecheck)';
$header = $exitlabel[result::OK] . ': ' . $title . "\n";
$exitcode = $exitcodes[result::OK];

foreach ($checks as $check) {
    $ref = $check->get_ref();

    // Moodle core iterates over get_results() here, which does not exist before Moodle 5.2.
    foreach ([$check->get_result()] as $result) {
        $status = $result->get_status();
        $checkexitcode = $exitcodes[$status];

        // Summary is treated as html.
        $summary = $result->get_summary();
        $summary = html_to_text($summary, 60, false);

        if ($checkexitcode > $exitcode) {
            $exitcode = $checkexitcode;
            $header = $exitlabel[$status] . ': ' . $title . "\n";
        }

        if (empty($messages[$status])) {
            $messages[$status] = $result;
        }

        $len = strlen(get_string('status' . $status));

        if (
            $options['verbose']
            || $status == result::WARNING
            || $status == result::CRITICAL
            || $status == result::ERROR
        ) {
            $output .= sprintf(
                $format,
                $OUTPUT->check_result($result),
                sprintf('%s (%s)', $check->get_name(), $ref),
            );

            $summary = str_replace("\n", "\n" . $prefix . '     ', $summary);
            $output .= sprintf($format, '', '    ' . $summary);
            // The details may consist of multiple lines, which are indented like the lines of the summary.
            $details = html_to_text($result->get_details(), 0, false);
            $details = str_replace("\n", "\n" . $prefix . '     ', $details);
            $output .= sprintf($format, '', '    ' . $details);

            if ($options['verbose']) {
                $actionlink = $check->get_action_link();
                if ($actionlink) {
                    $output .= sprintf($format, '', '    ' . $actionlink->url);
                }
                $output .= sprintf($format, '', '');
            }
        }
    }
}

// Print NRPE header.
print $header;

// Only show the table header if there is anything to show.
if ($output) {
    print sprintf(
        $format,
        get_string('status') . ' ',
        get_string('check')
    ) . $spacer;
    print $output;
}

// NRPE shell exit code. PHPUnit must not be terminated when it includes this script, it gets the exit code instead.
if (PHPUNIT_TEST) {
    return $exitcode;
}
exit($exitcode);
