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
use tool_updatecheck\task\fetch_updates;
use tool_updatecheck\task\fetch_updates_adhoc;

/**
 * Admin tool "Update check" - Tests for the notification mails
 *
 * Please note: These tests do not connect to the Moodle update server, the remote response is faked by the
 * plugin data generator.
 *
 * @package    tool_updatecheck
 * @category   test
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_updatecheck\local\checker
 * @covers     \tool_updatecheck\local\updateinfo
 * @covers     \tool_updatecheck\task\fetch_updates
 * @covers     \tool_updatecheck\task\fetch_updates_adhoc
 */
final class notification_test extends \advanced_testcase {
    /** @var string The version of the plugin update which has been fetched before already. */
    const KNOWNVERSION = '2098010100';

    /** @var string The version of the plugin update which becomes available with the next fetch. */
    const NEWVERSION = '2099010100';

    /**
     * Fake the update information: A Moodle core update and a plugin update have been fetched before already,
     * and one more plugin update will become available with the next fetch.
     *
     * @param int $newmaturity The maturity of the plugin update which becomes available with the next fetch.
     */
    protected function fake_update_information(int $newmaturity = MATURITY_STABLE): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_updatecheck');

        // The update information which has been fetched before already.
        $generator->create_core_update(['type' => 'minor']);
        $generator->create_plugin_update(['component' => 'tool_updatecheck', 'version' => self::KNOWNVERSION]);

        // The update information which will be returned by the next fetch.
        $generator->create_remote_core_update(['type' => 'minor']);
        $generator->create_remote_plugin_update(['component' => 'tool_updatecheck', 'version' => self::KNOWNVERSION]);
        $generator->create_remote_plugin_update([
            'component' => 'tool_updatecheck',
            'version' => self::NEWVERSION,
            'maturity' => $newmaturity,
        ]);
    }

    /**
     * Assert that the update information has really been fetched, i.e. that the plugin update which becomes available
     * with the next fetch is known now.
     */
    protected function assert_update_information_has_been_fetched(): void {
        $this->assertSame(self::NEWVERSION, (string) updateinfo::get_plugin_updates()['tool_updatecheck']->version);
    }

    /**
     * Test that the notification mails are disabled by default, but that the update information is fetched anyway.
     */
    public function test_notifications_are_disabled_by_default(): void {
        $this->resetAfterTest();
        $this->fake_update_information();
        $sink = $this->redirectMessages();

        $this->expectOutputRegex('/Fetching info about available updates/');
        (new fetch_updates())->execute();

        $this->assert_update_information_has_been_fetched();
        $this->assertSame(0, $sink->count());
    }

    /**
     * Test that the scheduled task notifies all admins, and that the message is built like the report: It lists all
     * available updates and marks the new ones.
     */
    public function test_scheduled_task_notifies_about_new_updates(): void {
        global $CFG;

        $this->resetAfterTest();
        set_config('notificationsrecipients', '$@ALL@$', 'tool_updatecheck');
        $this->fake_update_information();
        $sink = $this->redirectMessages();

        $this->expectOutputRegex('/sending notifications/');
        (new fetch_updates())->execute();

        $messages = $sink->get_messages();
        $this->assertCount(count(get_admins()), $messages);
        $message = reset($messages);
        $this->assertSame('moodle', $message->component);
        $this->assertSame('availableupdate', $message->eventtype);

        // The message is a plain text message.
        $this->assertEquals(FORMAT_PLAIN, $message->fullmessageformat);
        $this->assertEmpty($message->fullmessagehtml);
        $this->assertStringNotContainsString(PHP_EOL . PHP_EOL . PHP_EOL, $message->fullmessage);
        $lines = explode(PHP_EOL, $message->fullmessage);

        // The Moodle core update has been known before. It is listed together with the status and the installed
        // release, but it is not marked as new.
        $this->assertContains('Moodle core updates (Warning)', $lines);
        $this->assertContains('Installed Moodle release: ' . $CFG->release, $lines);
        $corelines = preg_grep('/^\* .* - Minor release - Stable version$/', $lines);
        $this->assertCount(1, $corelines);

        // The plugin update is new. Only the most recent update of the plugin is listed, together with the
        // installed version, and it is marked as new.
        $this->assertContains('Plugin updates (Warning)', $lines);
        $pluginlines = preg_grep('/^\* Update check \(tool_updatecheck\): /', $lines);
        $this->assertCount(1, $pluginlines);
        $pluginline = reset($pluginlines);
        $this->assertStringContainsString(' to v99.0-r1 (' . self::NEWVERSION . ') - Stable version [New]', $pluginline);
        $this->assertStringNotContainsString(self::KNOWNVERSION, $message->fullmessage);

        // The message links to the report of this plugin and not to the pages of Moodle core, which do not show
        // any updates if update notifications are disabled in Moodle core.
        $reporturl = (new \moodle_url('/' . $CFG->admin . '/tool/updatecheck/index.php'))->out(false);
        $this->assertStringContainsString($reporturl, $message->fullmessage);
        $this->assertSame($reporturl, $message->contexturl);
        $this->assertStringNotContainsString('/plugins.php', $message->fullmessage);

        // The footer explains the marker for new updates, but not the marker for ignored plugins as there are not any.
        // And it points to the settings of this plugin and not to the update notification settings of Moodle core.
        $footer = substr($message->fullmessage, strpos($message->fullmessage, PHP_EOL . '---' . PHP_EOL));
        $this->assertStringContainsString('[New] marks updates which have become available', $footer);
        $this->assertStringNotContainsString('[Ignored] marks', $footer);
        $this->assertStringContainsString('Site administration / Plugins / Admin tools / Update check settings', $footer);
    }

    /**
     * Test that ignored plugins are listed in the message like on the report, but marked as ignored.
     */
    public function test_notification_marks_ignored_plugins(): void {
        $this->resetAfterTest();
        set_config('notificationsrecipients', '$@ALL@$', 'tool_updatecheck');
        set_config('pluginsignored', 'tool_updatecheck', 'tool_updatecheck');
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_updatecheck');
        $generator->create_remote_core_update(['type' => 'major']);
        $generator->create_remote_plugin_update(['component' => 'tool_updatecheck']);
        $sink = $this->redirectMessages();

        $this->expectOutputRegex('/sending notifications/');
        (new fetch_updates())->execute();

        $messages = $sink->get_messages();
        $lines = explode(PHP_EOL, reset($messages)->fullmessage);

        // The new major update has triggered the message. It has just been released and thus is not overdue.
        $this->assertContains('Moodle core updates (Info)', $lines);
        $this->assertCount(1, preg_grep('/^\* .* - Major release - Stable version \[New\]$/', $lines));

        // The update of the ignored plugin has not triggered the message and does not affect the status.
        $this->assertContains('Plugin updates (OK)', $lines);
        $this->assertCount(1, preg_grep('/^\* Update check \(tool_updatecheck\): .* \[Ignored\]$/', $lines));
        $this->assertCount(0, preg_grep('/tool_updatecheck.*\[New\]/', $lines));

        // The footer explains both markers.
        $this->assertCount(1, preg_grep('/^\[New\] marks updates which have become available/', $lines));
        $this->assertCount(1, preg_grep('/^\[Ignored\] marks plugins which are ignored/', $lines));
    }

    /**
     * Test that plugins which are missing from disk are listed in the message like on the report, but marked as missing.
     */
    public function test_notification_marks_missing_plugins(): void {
        $this->resetAfterTest();
        set_config('notificationsrecipients', '$@ALL@$', 'tool_updatecheck');
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_updatecheck');
        $missingplugin = $generator->create_fake_plugin();
        $generator->create_remote_plugin_update(['component' => 'tool_updatecheck']);
        $generator->create_remote_plugin_update(['component' => $missingplugin]);
        $sink = $this->redirectMessages();

        $this->expectOutputRegex('/sending notifications/');
        (new fetch_updates())->execute();

        $messages = $sink->get_messages();
        $lines = explode(PHP_EOL, reset($messages)->fullmessage);

        // The update of this plugin has triggered the message, the one of the missing plugin has not.
        $this->assertContains('Plugin updates (Warning)', $lines);
        $this->assertCount(1, preg_grep('/^\* Update check \(tool_updatecheck\): .* \[New\]$/', $lines));
        $this->assertCount(1, preg_grep('/^\* .* \(' . $missingplugin . '\): 2020010100 to .* \[Missing from disk\]$/', $lines));
        $this->assertCount(0, preg_grep('/' . $missingplugin . '.*\[New\]/', $lines));
        $this->assertCount(1, preg_grep('/^\[Missing from disk\] marks plugins/', $lines));
    }

    /**
     * Test that an overdue major update is marked as overdue in the message like on the report.
     */
    public function test_notification_marks_overdue_major_updates(): void {
        $this->resetAfterTest();
        set_config('notificationsrecipients', '$@ALL@$', 'tool_updatecheck');
        set_config('coremajormonths', 0, 'tool_updatecheck');
        $generator = $this->getDataGenerator()->get_plugin_generator('tool_updatecheck');
        $generator->create_remote_core_update(['type' => 'major']);
        $sink = $this->redirectMessages();

        $this->expectOutputRegex('/sending notifications/');
        (new fetch_updates())->execute();

        $messages = $sink->get_messages();
        $lines = explode(PHP_EOL, reset($messages)->fullmessage);

        $this->assertContains('Moodle core updates (Warning)', $lines);
        $this->assertCount(
            1,
            preg_grep('/^\* 99\.0 .*, Version \d+ - Major release \(Overdue\) - Stable version \[New\]$/', $lines)
        );
        $this->assertContains('Plugin updates (OK)', $lines);
        $this->assertContains('There are no plugin updates available.', $lines);
    }

    /**
     * Test that an update is announced only once.
     */
    public function test_updates_are_announced_only_once(): void {
        $this->resetAfterTest();
        set_config('notificationsrecipients', '$@ALL@$', 'tool_updatecheck');
        $this->fake_update_information();
        $sink = $this->redirectMessages();

        $this->expectOutputRegex('/nothing to notify about/');
        (new fetch_updates())->execute();
        $sink->clear();
        (new fetch_updates())->execute();

        $this->assert_update_information_has_been_fetched();
        $this->assertSame(0, $sink->count());
    }

    /**
     * Test that the adhoc task notifies the admins as well.
     */
    public function test_adhoc_task_notifies_about_new_updates(): void {
        $this->resetAfterTest();
        set_config('notificationsrecipients', '$@ALL@$', 'tool_updatecheck');
        $clock = $this->mock_clock_with_frozen();
        $this->fake_update_information();
        $sink = $this->redirectMessages();

        // Let the update information get older than the maximum age, otherwise the adhoc task would skip the fetch.
        $clock->bump(2 * DAYSECS);

        $this->expectOutputRegex('/sending notifications/');
        (new fetch_updates_adhoc())->execute();

        $this->assertSame(count(get_admins()), $sink->count());
    }

    /**
     * Test that the notifications are sent even if the update notifications are disabled in Moodle core.
     */
    public function test_notifications_ignore_core_settings(): void {
        global $CFG;

        $this->resetAfterTest();
        $CFG->disableupdatenotifications = true;
        set_config('updateautocheck', 0);
        set_config('notificationsrecipients', '$@ALL@$', 'tool_updatecheck');
        $this->fake_update_information();
        $sink = $this->redirectMessages();

        $this->expectOutputRegex('/sending notifications/');
        (new fetch_updates())->execute();

        $this->assertSame(count(get_admins()), $sink->count());
    }

    /**
     * Test that updates of ignored plugins are not announced.
     */
    public function test_notifications_skip_ignored_plugins(): void {
        $this->resetAfterTest();
        set_config('notificationsrecipients', '$@ALL@$', 'tool_updatecheck');
        set_config('pluginsignored', 'tool_updatecheck', 'tool_updatecheck');
        $this->fake_update_information();
        $sink = $this->redirectMessages();

        $this->expectOutputRegex('/nothing to notify about/');
        (new fetch_updates())->execute();

        $this->assertSame(0, $sink->count());
    }

    /**
     * Test that updates which do not have the required maturity are not announced, regardless of the required maturity
     * which is configured in Moodle core.
     */
    public function test_notifications_respect_required_maturity(): void {
        $this->resetAfterTest();
        set_config('updateminmaturity', MATURITY_ALPHA);
        set_config('notificationsrecipients', '$@ALL@$', 'tool_updatecheck');
        set_config('pluginsminmaturity', MATURITY_STABLE, 'tool_updatecheck');
        $this->fake_update_information(MATURITY_BETA);
        $sink = $this->redirectMessages();

        $this->expectOutputRegex('/nothing to notify about/');
        (new fetch_updates())->execute();

        $this->assertSame(0, $sink->count());
    }

    /**
     * Create a second admin, a user who is allowed to change the site configuration by role and an ordinary user.
     *
     * @return \stdClass[] The users, indexed by admin, configurator and ordinary.
     */
    protected function create_potential_recipients(): array {
        global $CFG;

        $generator = $this->getDataGenerator();
        $systemcontext = \context_system::instance();

        $admin = $generator->create_user();
        set_config('siteadmins', $CFG->siteadmins . ',' . $admin->id);

        $configurator = $generator->create_user();
        $roleid = $generator->create_role();
        assign_capability(updateinfo::NOTIFICATION_CAPABILITY, CAP_ALLOW, $roleid, $systemcontext);
        role_assign($roleid, $configurator->id, $systemcontext);

        $ordinary = $generator->create_user();

        return ['admin' => $admin, 'configurator' => $configurator, 'ordinary' => $ordinary];
    }

    /**
     * Get the IDs of the users who have received a message, in ascending order.
     *
     * @param \phpunit_message_sink $sink The message sink.
     * @return int[]
     */
    protected function get_recipient_ids(\phpunit_message_sink $sink): array {
        $ids = array_map(function ($message) {
            return (int) $message->useridto;
        }, $sink->get_messages());
        sort($ids);

        return $ids;
    }

    /**
     * Test that everyone who is allowed to change the site configuration can be notified, not only the admins.
     */
    public function test_notifications_are_sent_to_everyone_who_can(): void {
        $this->resetAfterTest();
        $users = $this->create_potential_recipients();
        set_config('notificationsrecipients', '$@ALL@$', 'tool_updatecheck');
        $this->fake_update_information();
        $sink = $this->redirectMessages();

        $this->expectOutputRegex('/sending notifications/');
        (new fetch_updates())->execute();

        $expected = array_map('intval', array_keys(get_admins()));
        $expected[] = (int) $users['configurator']->id;
        sort($expected);
        $this->assertContains((int) $users['admin']->id, $expected);
        $this->assertSame($expected, $this->get_recipient_ids($sink));
    }

    /**
     * Test that only the selected users are notified.
     */
    public function test_notifications_are_sent_to_selected_recipients_only(): void {
        $this->resetAfterTest();
        $users = $this->create_potential_recipients();
        set_config('notificationsrecipients', $users['admin']->id . ',' . $users['configurator']->id, 'tool_updatecheck');
        $this->fake_update_information();
        $sink = $this->redirectMessages();

        $this->expectOutputRegex('/sending notifications/');
        (new fetch_updates())->execute();

        $expected = [(int) $users['admin']->id, (int) $users['configurator']->id];
        sort($expected);
        $this->assertSame($expected, $this->get_recipient_ids($sink));
    }

    /**
     * Test that a selected user who is not allowed to change the site configuration (anymore) is not notified.
     */
    public function test_notifications_skip_recipients_without_capability(): void {
        $this->resetAfterTest();
        $users = $this->create_potential_recipients();
        set_config('notificationsrecipients', $users['ordinary']->id, 'tool_updatecheck');
        $this->fake_update_information();
        $sink = $this->redirectMessages();

        $this->expectOutputRegex('/Fetching info about available updates/');
        (new fetch_updates())->execute();

        $this->assert_update_information_has_been_fetched();
        $this->assertSame(0, $sink->count());
    }

    /**
     * Test that a manual fetch does not notify the admins.
     */
    public function test_manual_fetch_does_not_notify(): void {
        $this->resetAfterTest();
        set_config('notificationsrecipients', '$@ALL@$', 'tool_updatecheck');
        $this->fake_update_information();
        $sink = $this->redirectMessages();

        updateinfo::fetch();

        $this->assert_update_information_has_been_fetched();
        $this->assertSame(0, $sink->count());
    }
}
