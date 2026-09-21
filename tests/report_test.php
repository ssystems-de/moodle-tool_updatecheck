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
use tool_updatecheck\output\report;
use tool_updatecheck\task\fetch_updates_adhoc;

/**
 * Admin tool "Update check" - Tests for the report renderable
 *
 * @package    tool_updatecheck
 * @category   test
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_updatecheck\output\report
 */
final class report_test extends \advanced_testcase {
    /**
     * Export the report data.
     *
     * Please note: In PHPUnit, the statuses are rendered by the CLI renderer, i.e. in uppercase and with ANSI colors.
     *
     * @return array
     */
    protected function export_report(): array {
        global $PAGE;

        return (new report())->export_for_template($PAGE->get_renderer('core'));
    }

    /**
     * Test that the report shows the unknown status and requests a fetch if the update information is missing.
     */
    public function test_missing_update_information(): void {
        $this->resetAfterTest();

        $data = $this->export_report();

        $this->assertFalse($data['fresh']);
        $this->assertSame(result::UNKNOWN, $data['corestatus']);
        $this->assertStringContainsStringIgnoringCase(get_string('status' . result::UNKNOWN), $data['corestatusbadge']);
        $this->assertSame(result::UNKNOWN, $data['pluginstatus']);
        $this->assertStringContainsStringIgnoringCase(get_string('status' . result::UNKNOWN), $data['pluginstatusbadge']);
        $this->assertFalse($data['hascoreupdates']);
        $this->assertFalse($data['haspluginupdates']);

        // The fetch is requested only once.
        $this->assertCount(1, \core\task\manager::get_adhoc_tasks(fetch_updates_adhoc::class));
    }

    /**
     * Test that the report lists the updates and shows the same status as the checks.
     */
    public function test_available_updates(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator()->get_plugin_generator('tool_updatecheck');
        $generator->create_core_update(['type' => 'minor']);
        $generator->create_plugin_update(['component' => 'tool_updatecheck']);
        set_config('corestatusminor', result::CRITICAL, 'tool_updatecheck');
        set_config('pluginsignored', 'tool_updatecheck', 'tool_updatecheck');

        $data = $this->export_report();

        $this->assertTrue($data['fresh']);
        $this->assertCount(1, $data['coreupdates']);
        $this->assertFalse($data['coreupdates'][0]['ismajor']);
        $this->assertCount(1, $data['pluginupdates']);
        $this->assertSame('tool_updatecheck', $data['pluginupdates'][0]['component']);
        $this->assertTrue($data['pluginupdates'][0]['ignored']);

        // The Moodle core updates have the configured status, the plugin updates are ok as the only update is ignored.
        $this->assertSame(result::CRITICAL, $data['corestatus']);
        $this->assertStringContainsStringIgnoringCase(get_string('status' . result::CRITICAL), $data['corestatusbadge']);
        $this->assertSame(result::OK, $data['pluginstatus']);
        $this->assertStringContainsStringIgnoringCase(get_string('status' . result::OK), $data['pluginstatusbadge']);

        // A fetch is not requested.
        $this->assertCount(0, \core\task\manager::get_adhoc_tasks(fetch_updates_adhoc::class));
    }
}
