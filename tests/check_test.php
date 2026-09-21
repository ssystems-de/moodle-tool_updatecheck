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

use core\check\result;
use tool_updatecheck\check\coreupdates;
use tool_updatecheck\check\pluginupdates;
use tool_updatecheck\local\updateinfo;
use tool_updatecheck\task\fetch_updates_adhoc;

/**
 * Admin tool "Update check" - Tests for the checks
 *
 * @package    tool_updatecheck
 * @category   test
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_updatecheck\check\base
 * @covers     \tool_updatecheck\check\coreupdates
 * @covers     \tool_updatecheck\check\pluginupdates
 */
final class check_test extends \advanced_testcase {
    /**
     * Get the plugin's data generator.
     *
     * @return \tool_updatecheck_generator
     */
    protected function get_generator(): \tool_updatecheck_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_updatecheck');
    }

    /**
     * Test that the checks are registered as status checks.
     */
    public function test_checks_are_registered(): void {
        $refs = array_map(function ($check) {
            return $check->get_ref();
        }, \core\check\manager::get_checks('status'));

        $this->assertContains('tool_updatecheck_coreupdates', $refs);
        $this->assertContains('tool_updatecheck_pluginupdates', $refs);
    }

    /**
     * Test that the checks return unknown and request a fetch if the update information is missing.
     */
    public function test_missing_update_information(): void {
        $this->resetAfterTest();

        $this->assertSame(result::UNKNOWN, (new coreupdates())->get_result()->get_status());
        $this->assertSame(result::UNKNOWN, (new pluginupdates())->get_result()->get_status());

        // The fetch is requested only once.
        $this->assertCount(1, \core\task\manager::get_adhoc_tasks(fetch_updates_adhoc::class));
    }

    /**
     * Test that the status for missing or outdated update information can be configured.
     */
    public function test_stale_status_is_configurable(): void {
        $this->resetAfterTest();

        foreach (updateinfo::STALE_STATUSES as $status) {
            set_config('generalstalestatus', $status, 'tool_updatecheck');
            $this->assertSame($status, (new coreupdates())->get_result()->get_status());
            $this->assertSame($status, (new pluginupdates())->get_result()->get_status());
        }

        // An invalid value falls back to unknown.
        set_config('generalstalestatus', 'whatever', 'tool_updatecheck');
        $this->assertSame(result::UNKNOWN, (new coreupdates())->get_result()->get_status());
    }

    /**
     * Test that the checks return unknown and request a fetch if the update information is outdated.
     */
    public function test_outdated_update_information(): void {
        $this->resetAfterTest();
        $now = $this->mock_clock_with_frozen()->time();

        // Even if there are updates in the outdated update information.
        $this->get_generator()->create_core_update(['type' => 'minor']);
        $this->get_generator()->create_plugin_update(['component' => 'tool_updatecheck']);
        set_config('recentfetch', $now - 49 * HOURSECS, 'core_plugin');
        \core\update\checker::reset_caches(true);

        $this->assertSame(result::UNKNOWN, (new coreupdates())->get_result()->get_status());
        $this->assertSame(result::UNKNOWN, (new pluginupdates())->get_result()->get_status());
        $this->assertCount(1, \core\task\manager::get_adhoc_tasks(fetch_updates_adhoc::class));
    }

    /**
     * Test that the checks are ok and do not request a fetch if there are not any updates.
     */
    public function test_no_updates(): void {
        $this->resetAfterTest();

        $this->get_generator()->set_update_response([]);

        $result = (new coreupdates())->get_result();
        $this->assertSame(result::OK, $result->get_status());
        $this->assertSame(get_string('checkcoreupdatesok', 'tool_updatecheck'), $result->get_summary());
        $this->assertStringContainsString(
            get_string('checkcoreupdatesavailable', 'tool_updatecheck', 0),
            $result->get_details()
        );

        $result = (new pluginupdates())->get_result();
        $this->assertSame(result::OK, $result->get_status());
        $this->assertSame(get_string('checkpluginupdatesok', 'tool_updatecheck'), $result->get_summary());
        $this->assertStringContainsString(
            get_string('checkpluginupdatesavailable', 'tool_updatecheck', 0),
            $result->get_details()
        );

        $this->assertCount(0, \core\task\manager::get_adhoc_tasks(fetch_updates_adhoc::class));
    }

    /**
     * Test the result of the Moodle core updates check.
     */
    public function test_coreupdates(): void {
        $this->resetAfterTest();

        $this->get_generator()->create_core_update(['type' => 'minor']);
        $this->get_generator()->create_core_update(['type' => 'major']);
        set_config('corestatusminor', result::ERROR, 'tool_updatecheck');

        $result = (new coreupdates())->get_result();
        $this->assertSame(result::ERROR, $result->get_status());

        // The summary only contains the number of available updates.
        $available = get_string('checkcoreupdatesavailable', 'tool_updatecheck', 2);
        $this->assertSame($available, $result->get_summary());

        // The details contain the list of updates, followed by the number of available updates and the last fetch.
        $details = html_to_text($result->get_details(), 0, false);
        $this->assertMatchesRegularExpression(
            '/' . preg_quote(moodle_major_version(true) . '.99 (Build: 20990101)', '/') . '.*; 99\\.0 \\(Build: .*; ' .
                    preg_quote($available . '; ' . updateinfo::get_last_fetch_info(), '/') . '/',
            $details
        );

        // If there is only the young major update, the check is just an info.
        $this->get_generator()->set_update_response([]);
        $this->get_generator()->create_core_update(['type' => 'major']);
        $this->assertSame(result::INFO, (new coreupdates())->get_result()->get_status());
    }

    /**
     * Test the result of the plugin updates check.
     */
    public function test_pluginupdates(): void {
        $this->resetAfterTest();

        $this->get_generator()->create_plugin_update(['component' => 'tool_updatecheck', 'release' => 'v99.0-r1']);

        $result = (new pluginupdates())->get_result();
        $this->assertSame(result::WARNING, $result->get_status());
        $this->assertSame(get_string('checkpluginupdatesavailable', 'tool_updatecheck', 1), $result->get_summary());
        $installedrelease = \core_plugin_manager::instance()->get_plugin_info('tool_updatecheck')->release;
        $pluginname = get_string('pluginname', 'tool_updatecheck');

        // By default, the plugin is listed with its component name, followed by its plugin name.
        $this->assertStringContainsString(
            'tool_updatecheck (' . $pluginname . '): ' . $installedrelease . ' to v99.0-r1',
            $result->get_details()
        );

        // But the plugin name format can be configured.
        $formats = [
            updateinfo::NAMEFORMAT_NAMECOMPONENT => $pluginname . ' (tool_updatecheck): ',
            updateinfo::NAMEFORMAT_NAME => '<span class="d-block">' . $pluginname . ': ',
            updateinfo::NAMEFORMAT_COMPONENT => '<span class="d-block">tool_updatecheck: ',
        ];
        foreach ($formats as $format => $expected) {
            set_config('checksapipluginnameformat', $format, 'tool_updatecheck');
            $this->assertStringContainsString(
                $expected . $installedrelease . ' to v99.0-r1',
                (new pluginupdates())->get_result()->get_details()
            );
        }

        // If the plugin is ignored, the check is ok, but mentions the ignored update.
        set_config('pluginsignored', 'tool_updatecheck', 'tool_updatecheck');
        updateinfo::purge_cache();
        $result = (new pluginupdates())->get_result();
        $this->assertSame(result::OK, $result->get_status());
        $this->assertStringContainsString(
            get_string('checkpluginupdatesignored', 'tool_updatecheck', 1),
            $result->get_details()
        );
    }

    /**
     * Test that the details of the checks are a single line as soon as they are converted to plain text.
     *
     * This is important as admin/cli/checks.php is not able to indent multiple lines of details
     * and would output a broken table otherwise.
     */
    public function test_details_are_single_line_in_plain_text(): void {
        $this->resetAfterTest();

        // First, check the details if the update information is missing.
        foreach ([new coreupdates(), new pluginupdates()] as $check) {
            $details = html_to_text($check->get_result()->get_details(), 0, false);
            $this->assertStringNotContainsString("\n", trim($details));
        }

        // Then, check the details if there are multiple updates, ignored plugins and plugins which are missing from disk.
        // This plugin itself is the only additional plugin which is guaranteed to be installed, so we need fake ones.
        $ignoredplugin = $this->get_generator()->create_fake_plugin(['component' => 'local_updatecheckignored']);
        $missingplugin = $this->get_generator()->create_fake_plugin(['component' => 'local_updatecheckmissing']);
        $this->get_generator()->create_core_update(['type' => 'minor']);
        $this->get_generator()->create_core_update(['type' => 'major']);
        $this->get_generator()->create_plugin_update(['component' => 'tool_updatecheck']);
        $this->get_generator()->create_plugin_update(['component' => $ignoredplugin]);
        $this->get_generator()->create_plugin_update(['component' => $missingplugin]);
        set_config('pluginsignored', $ignoredplugin, 'tool_updatecheck');
        updateinfo::purge_cache();

        foreach ([new coreupdates(), new pluginupdates()] as $check) {
            $details = html_to_text($check->get_result()->get_details(), 0, false);
            $this->assertStringNotContainsString("\n", trim($details));
        }

        // Make sure that the details really consist of all kinds of lines, otherwise this test would be pointless.
        $details = (new pluginupdates())->get_result()->get_details();
        $this->assertStringContainsString('tool_updatecheck', $details);
        $this->assertStringContainsString(get_string('checkpluginupdatesavailable', 'tool_updatecheck', 1), $details);
        $this->assertStringContainsString(get_string('checkpluginupdatesignored', 'tool_updatecheck', 1), $details);
        $this->assertStringContainsString(get_string('checkpluginupdatesmissing', 'tool_updatecheck', 1), $details);
    }

    /**
     * Test that the lines of the details are separated by the configured separator in plain text.
     */
    public function test_details_separator(): void {
        $this->resetAfterTest();

        $this->get_generator()->create_plugin_update(['component' => 'tool_updatecheck', 'release' => 'v99.0-r1']);
        $available = get_string('checkpluginupdatesavailable', 'tool_updatecheck', 1);
        $lastfetch = updateinfo::get_last_fetch_info();

        // By default, the separator is a semicolon.
        // The list of updates is followed by the number of plugins with available updates and the last fetch.
        $details = html_to_text((new pluginupdates())->get_result()->get_details(), 0, false);
        $this->assertStringContainsString('v99.0-r1; ' . $available . '; ' . $lastfetch, $details);

        // But the separator can be configured.
        $separators = [
            'semicolon' => '; ',
            'slash' => ' / ',
            'doublecolon' => ' :: ',
            'hash' => ' # ',
            'hyphen' => ' - ',
            'invalid' => '; ',
        ];
        foreach ($separators as $separator => $expected) {
            set_config('checksapiseparator', $separator, 'tool_updatecheck');
            $details = html_to_text((new pluginupdates())->get_result()->get_details(), 0, false);
            $this->assertStringContainsString('v99.0-r1' . $expected . $available . $expected . $lastfetch, $details);
        }

        // The setting offers all separators.
        $this->assertSame(array_keys(updateinfo::SEPARATORS), array_keys(updateinfo::get_separator_options()));
    }

    /**
     * Test that the action link of the checks points to the report page.
     */
    public function test_action_link(): void {
        $link = (new coreupdates())->get_action_link();
        $this->assertStringContainsString('/tool/updatecheck/index.php', $link->url->out(false));
    }
}
