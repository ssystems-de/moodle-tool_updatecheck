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

namespace tool_updatecheck;

use tool_updatecheck\local\updateinfo;

/**
 * Admin tool "Update check" - Tests for the CLI script of the plugin
 *
 * @package    tool_updatecheck
 * @category   test
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_updatecheck\check\base
 * @covers     \tool_updatecheck\check\coreupdates
 * @covers     \tool_updatecheck\check\pluginupdates
 * @covers     \tool_updatecheck\local\updateinfo
 */
final class cli_test extends \advanced_testcase {
    /**
     * Run the CLI script of the plugin with the given arguments.
     *
     * @param string[] $args The command line arguments.
     * @return array The exit code and the output.
     */
    protected function run_script(array $args = []): array {
        global $CFG, $OUTPUT, $PAGE;

        // The script reads its options from the command line and renders the status with the CLI renderer.
        $_SERVER['argv'] = array_merge(['checks.php'], $args);
        $OUTPUT = $PAGE->get_renderer('core', null, RENDERER_TARGET_CLI);

        ob_start();
        $exitcode = require($CFG->dirroot . '/' . $CFG->admin . '/tool/updatecheck/cli/checks.php');
        $output = ob_get_clean();

        return [$exitcode, $output];
    }

    /**
     * Get the lines of the output of the script without the table decoration.
     *
     * @param string $output The output of the script.
     * @return string[]
     */
    protected function get_lines(string $output): array {
        // The status column is rendered with ANSI colour codes, so its width is not fixed.
        return array_map(function ($line) {
            return trim(preg_replace('/^[^|]*\|/', '', $line));
        }, explode("\n", trim($output)));
    }

    /**
     * Test that the script reports unknown update information with the exit code 3 and always names the plugin.
     */
    public function test_unknown(): void {
        $this->resetAfterTest();

        [$exitcode, $output] = $this->run_script();
        $this->assertSame(3, $exitcode);
        $this->assertSame("UNKNOWN: Moodle updates (tool_updatecheck)\n", $output);

        // With the verbose option, the checks are listed with their details.
        [$exitcode, $output] = $this->run_script(['--verbose']);
        $this->assertSame(3, $exitcode);
        $lines = $this->get_lines($output);
        $this->assertContains('Moodle core updates (tool_updatecheck_coreupdates)', $lines);
        $this->assertContains('Plugin updates (tool_updatecheck_pluginupdates)', $lines);
        $this->assertContains(get_string('checkunknown', 'tool_updatecheck'), $lines);
    }

    /**
     * Test that the script reports available updates with the exit code 1 and the details on separate lines.
     */
    public function test_updates_available(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_updatecheck');
        $missingplugin = $generator->create_fake_plugin();
        $generator->create_plugin_update(['component' => 'tool_updatecheck', 'release' => 'v99.0-r1']);
        $generator->create_plugin_update(['component' => $missingplugin, 'version' => '2099010100']);

        [$exitcode, $output] = $this->run_script();
        $this->assertSame(1, $exitcode);
        $lines = $this->get_lines($output);

        // The first line always names the plugin, not the check which has the most severe status.
        $this->assertSame('WARNING: Moodle updates (tool_updatecheck)', $lines[0]);

        // The core updates check is ok and thus not listed, the plugin updates check is listed with its details,
        // each on its own line, as the newline is the default separator of the CLI script.
        $this->assertNotContains('Moodle core updates (tool_updatecheck_coreupdates)', $lines);
        $this->assertContains('Plugin updates (tool_updatecheck_pluginupdates)', $lines);
        $this->assertContains(get_string('checkpluginupdatesavailable', 'tool_updatecheck', 1), $lines);
        $this->assertContains(get_string('checkpluginupdatesmissing', 'tool_updatecheck', 1), $lines);
        $installedrelease = \core_plugin_manager::instance()->get_plugin_info('tool_updatecheck')->release;
        $this->assertContains(
            'tool_updatecheck (' . get_string('pluginname', 'tool_updatecheck') . '): ' . $installedrelease . ' to v99.0-r1',
            $lines
        );
        $this->assertStringContainsString('Last successful fetch of the update information:', $output);

        // The Checks API separator does not affect the script.
        set_config('checksapiseparator', 'hash', 'tool_updatecheck');
        updateinfo::purge_cache();
        [$exitcode, $output] = $this->run_script();
        $this->assertStringNotContainsString(' # ', $output);

        // With another CLI separator, the details are output as one single line.
        set_config('checkscliseparator', 'semicolon', 'tool_updatecheck');
        updateinfo::purge_cache();
        [$exitcode, $output] = $this->run_script();
        $this->assertStringContainsString(
            ' to v99.0-r1; ' . get_string('checkpluginupdatesavailable', 'tool_updatecheck', 1) . '; ',
            $output
        );
    }

    /**
     * Test that the summary lines of the details can be disabled, even with the verbose option.
     */
    public function test_summary_lines_can_be_disabled(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_updatecheck');
        $generator->create_plugin_update(['component' => 'tool_updatecheck', 'release' => 'v99.0-r1']);
        set_config('checkssummarylines', 0, 'tool_updatecheck');

        [$exitcode, $output] = $this->run_script(['--verbose']);
        $this->assertSame(1, $exitcode);
        $lines = $this->get_lines($output);
        $this->assertContains(get_string('checkpluginupdatesavailable', 'tool_updatecheck', 1), $lines);
        $this->assertStringContainsString(' to v99.0-r1', $output);
        $this->assertCount(1, preg_grep('/Plugins with available updates: 1/', $lines));
        // The core updates check is listed with its summary only, its count line is not part of the details.
        $this->assertContains(get_string('checkcoreupdatesok', 'tool_updatecheck'), $lines);
        $this->assertNotContains(get_string('checkcoreupdatesavailable', 'tool_updatecheck', 0), $lines);
        $this->assertStringNotContainsString('Last successful fetch', $output);
    }

    /**
     * Test that the CLI script uses the configured plugin name format.
     */
    public function test_plugin_name_format(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_updatecheck');
        $generator->create_plugin_update(['component' => 'tool_updatecheck', 'release' => 'v99.0-r1']);
        set_config('checkspluginnameformat', updateinfo::NAMEFORMAT_COMPONENT, 'tool_updatecheck');

        [$exitcode, $output] = $this->run_script();
        $this->assertContains(
            'tool_updatecheck: ' . $this->get_installed_release() . ' to v99.0-r1',
            $this->get_lines($output)
        );
    }

    /**
     * Test that the checks can be filtered by their reference.
     */
    public function test_filter(): void {
        $this->resetAfterTest();
        $this->getDataGenerator()->get_plugin_generator('tool_updatecheck')->create_core_update(['type' => 'minor']);

        // Without a filter, the core updates check is listed and the plugin updates check is ok.
        [$exitcode, $output] = $this->run_script();
        $this->assertSame(1, $exitcode);
        $this->assertStringContainsString('(tool_updatecheck_coreupdates)', $output);

        // With a filter for the plugin updates check, everything is ok.
        [$exitcode, $output] = $this->run_script(['--filter=pluginupdates']);
        $this->assertSame(0, $exitcode);
        $this->assertSame("OK: Moodle updates (tool_updatecheck)\n", $output);
    }

    /**
     * Test that the help is output.
     */
    public function test_help(): void {
        [$exitcode, $output] = $this->run_script(['--help']);
        $this->assertSame(0, $exitcode);
        $this->assertStringContainsString('--verbose', $output);
    }

    /**
     * Get the installed release of this plugin.
     *
     * @return string
     */
    protected function get_installed_release(): string {
        return \core_plugin_manager::instance()->get_plugin_info('tool_updatecheck')->release;
    }
}
