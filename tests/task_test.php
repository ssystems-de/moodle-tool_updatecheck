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

use tool_updatecheck\check\coreupdates;
use tool_updatecheck\local\updateinfo;
use tool_updatecheck\task\fetch_updates;
use tool_updatecheck\task\fetch_updates_adhoc;

/**
 * Admin tool "Update check" - Tests for the tasks
 *
 * Please note: These tests do not cover the real fetch as they must not connect to the Moodle update server.
 *
 * @package    tool_updatecheck
 * @category   test
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_updatecheck\task\fetch_updates
 * @covers     \tool_updatecheck\task\fetch_updates_adhoc
 * @covers     \tool_updatecheck\local\updateinfo
 * @covers     \tool_updatecheck\check\base
 */
final class task_test extends \advanced_testcase {
    /**
     * Test that the adhoc task does not fetch again if the update information has been fetched in the meantime.
     */
    public function test_adhoc_task_skips_fresh_update_information(): void {
        $this->resetAfterTest();
        $now = $this->mock_clock_with_frozen()->time();

        $fetchtime = $now - HOURSECS;
        $this->getDataGenerator()->get_plugin_generator('tool_updatecheck')->set_update_response([], $fetchtime);

        $this->expectOutputRegex('/fetched in the meantime, skipping/');
        (new fetch_updates_adhoc())->execute();

        $this->assertSame($fetchtime, updateinfo::get_last_fetch());
    }

    /**
     * Test that the adhoc task does not fail if the fetch fails, but that the failure is remembered and reported.
     */
    public function test_adhoc_task_handles_fetch_failure(): void {
        $this->resetAfterTest();
        $now = $this->mock_clock_with_frozen()->time();
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_updatecheck');
        $generator->set_remote_update_failure();

        // The task completes without an exception, so Moodle core does not retry it and does not keep a failed record.
        $this->expectOutputRegex('/FAILED: /');
        (new fetch_updates_adhoc())->execute();
        $this->assertNull(updateinfo::get_last_fetch());

        // The failure is remembered.
        $failure = updateinfo::get_last_fetch_failure();
        $this->assertSame($now, $failure->time);
        $this->assertStringContainsString(
            get_string('reportfetcherror_err_response_empty', 'tool_updatecheck'),
            $failure->message
        );

        // And the checks report it.
        $details = html_to_text((new coreupdates())->get_result()->get_details(), 0, false);
        $this->assertStringContainsString(userdate($now), $details);
        $this->assertStringContainsString($failure->message, $details);

        // As soon as a fetch succeeds, the failure is forgotten.
        $generator->set_remote_update_response([]);
        (new fetch_updates_adhoc())->execute();
        $this->assertSame($now, updateinfo::get_last_fetch());
        $this->assertNull(updateinfo::get_last_fetch_failure());
        $this->assertStringNotContainsString(
            get_string('reportfetcherror_err_response_empty', 'tool_updatecheck'),
            (new coreupdates())->get_result()->get_details()
        );
    }

    /**
     * Test that a new fetch is not requested right after a failed attempt, but after the retry delay.
     */
    public function test_fetch_is_not_requested_right_after_a_failure(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen();
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_updatecheck');
        $generator->set_remote_update_failure();

        // The check requests a fetch and the task fails.
        (new coreupdates())->get_result();
        $this->assertCount(1, \core\task\manager::get_adhoc_tasks(fetch_updates_adhoc::class));
        $this->expectOutputRegex('/FAILED: /');
        $this->run_adhoc_tasks();
        $this->assertCount(0, \core\task\manager::get_adhoc_tasks(fetch_updates_adhoc::class));

        // Right after the failure, the check does not request a new fetch.
        (new coreupdates())->get_result();
        $this->assertCount(0, \core\task\manager::get_adhoc_tasks(fetch_updates_adhoc::class));

        // After the retry delay, it does.
        $clock->bump(updateinfo::FETCH_RETRY_DELAY + 1);
        (new coreupdates())->get_result();
        $this->assertCount(1, \core\task\manager::get_adhoc_tasks(fetch_updates_adhoc::class));
    }

    /**
     * Run the queued adhoc tasks of this plugin.
     */
    protected function run_adhoc_tasks(): void {
        while ($task = \core\task\manager::get_next_adhoc_task(time())) {
            $this->assertInstanceOf(fetch_updates_adhoc::class, $task);
            $task->execute();
            \core\task\manager::adhoc_task_complete($task);
        }
    }

    /**
     * Test that the scheduled task is registered and enabled.
     */
    public function test_scheduled_task_is_registered(): void {
        $task = \core\task\manager::get_scheduled_task(fetch_updates::class);

        $this->assertInstanceOf(fetch_updates::class, $task);
        $this->assertFalse((bool) $task->get_disabled());
    }
}
