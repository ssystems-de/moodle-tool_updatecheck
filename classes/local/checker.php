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
 * Admin tool "Update check" - Update checker
 *
 * @package    tool_updatecheck
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_updatecheck\local;

/**
 * Update checker which fetches the update information and which is able to send the update notifications to the admins.
 *
 * Background: Moodle core sends its update notifications only from within \core\update\checker::cron_execute(), which
 * compares the previously stored response with the freshly fetched response. As soon as the update information is
 * fetched by anybody else (like by this plugin), Moodle core's scheduled task considers the update information as
 * fresh enough and skips its run, or it does not detect any changes anymore. Thus, Moodle core does not send any update
 * notifications anymore as soon as this plugin is installed. This cannot be solved from outside of Moodle core,
 * but this class is able to take over the job: It extends Moodle core's update checker to get access to its protected
 * notification methods and sends the update notifications as part of its own fetch.
 *
 * @package    tool_updatecheck
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class checker extends \core\update\checker {
    /** @var string The name of the plugin setting which holds the fake response of the Moodle update server in tests. */
    const TESTFAKERESPONSE = 'testfakeresponse';

    /**
     * Factory method for this class.
     *
     * Moodle core's update checker is a singleton whose instance() method always returns an instance of the core class
     * itself. Thus, this class has its own factory method. It does not need to be a singleton as it does not hold
     * any state which has to survive, the fetched update information is stored in the database.
     *
     * @return self
     */
    public static function create(): self {
        return new self();
    }

    /**
     * Make the request to get the update information from the remote site.
     *
     * Automated tests must not connect to the Moodle update server. Thus, if (and only if) this site is a PHPUnit or
     * Behat test site, the plugin data generator is able to provide a fake response in a plugin setting. In contrast to
     * a mocked object, this works across processes: The fake response is picked up by the tasks which are run within the
     * test process as well as by the report page which is run by the web server, and it is reset together with the
     * database after each test.
     * On a production site, the plugin setting is never evaluated.
     *
     * @return string The raw response.
     * @throws \core\update\checker_exception
     */
    protected function get_response() {
        if (PHPUNIT_TEST || defined('BEHAT_SITE_RUNNING')) {
            $fakeresponse = get_config('tool_updatecheck', self::TESTFAKERESPONSE);
            if ($fakeresponse !== false) {
                return $fakeresponse;
            }
        }

        return parent::get_response();
    }

    /**
     * Fetch the update information from the remote site.
     *
     * @throws \core\update\checker_exception
     */
    public function fetch() {
        parent::fetch();

        // The lists of available updates which have been gathered before within this request are stale now.
        updateinfo::purge_cache();

        // Moodle core's update checker singleton might have loaded the previous response in this process already
        // and would not notice the response which we have just stored. Thus, we have to reset it.
        \core\update\checker::reset_caches(true);
    }

    /**
     * Fetch the update information from the remote site and notify the admins about updates which have become available.
     *
     * This function is copied and modified from \core\update\checker::cron_execute(), but it does not swallow exceptions
     * as the calling tasks have to fail (and to be retried) if the fetch fails.
     *
     * @throws \core\update\checker_exception
     */
    public function fetch_and_notify(): void {
        // Load the previous response from the database for sure as it might have been changed in the meantime
        // by Moodle core's update checker singleton.
        $this->restore_response(true);
        $previous = $this->recentresponse;

        $this->fetch();

        $this->restore_response(true);
        $current = $this->recentresponse;

        $changes = $this->compare_responses($previous, $current);
        $notifications = $this->cron_notifications($changes);
        $this->cron_notify($notifications);
    }

    /**
     * Given the list of changes in available updates, pick those to send to the admins.
     *
     * This function is copied and modified from \core\update\checker::cron_notifications(), but it uses the settings of
     * this plugin instead of the $CFG->updateminmaturity and $CFG->updatenotifybuilds settings of Moodle core, it skips
     * the ignored plugins and it does not use \core\plugininfo\base::available_updates() which would not return anything
     * if update notifications are disabled in Moodle core.
     *
     * @param array $changes The changes as returned by compare_responses().
     * @return \core\update\info[] The updates to send to the admins.
     */
    protected function cron_notifications(array $changes) {
        if (empty($changes)) {
            return [];
        }

        $notifications = [];
        $ignoredplugins = updateinfo::get_ignored_plugins();

        foreach ($changes as $component => $componentchanges) {
            if (empty($componentchanges)) {
                continue;
            }

            // Get the updates which should be considered according to the settings of this plugin.
            if ($component === 'core') {
                $plugin = null;
                $options = updateinfo::get_core_update_options();
            } else {
                if (in_array($component, $ignoredplugins)) {
                    continue;
                }
                $plugin = \core_plugin_manager::instance()->get_plugin_info($component);
                if ($plugin === null || empty($plugin->versiondisk)) {
                    continue;
                }
                $options = updateinfo::get_plugin_update_options();
            }
            $componentupdates = $this->get_update_info($component, $options);
            if (empty($componentupdates)) {
                continue;
            }

            // Notify only about those changes which are present in the considered updates.
            foreach ($componentchanges as $componentchange) {
                foreach ($componentupdates as $componentupdate) {
                    if ($componentupdate->version != $componentchange['version']) {
                        continue;
                    }
                    if ($component === 'core') {
                        // We already know that this is a real update with a higher version, but there can be two
                        // Moodle releases which have the same version. Thus, we compare the release as well.
                        if ((string) $componentupdate->release === (string) ($componentchange['release'] ?? '')) {
                            $notifications[] = $componentupdate;
                        }
                    } else if ($componentupdate->version > $plugin->versiondisk) {
                        $notifications[] = $componentupdate;
                    }
                }
            }
        }

        return $notifications;
    }

    /**
     * Send the given notifications to the configured recipients via the messaging API.
     *
     * This function replaces \core\update\checker::cron_notify(), which sends the notifications to all admins without
     * exception. It sends the message with the same message provider and the same subject as Moodle core, but it differs
     * in these aspects:
     * - The message is sent to the recipients which are configured in the plugin settings.
     * - The message is a plain text message which is built in the same way as the report page of this plugin
     *   (see the notification class), instead of the message of Moodle core which just lists the new updates.
     * - The message links to the report of this plugin instead of the admin notifications page and the plugins overview
     *   of Moodle core, as these pages do not show any updates if update notifications are disabled in Moodle core.
     * - The footer explains that the message has been sent by this plugin and where the recipients can be changed,
     *   instead of pointing to the update notification settings of Moodle core which do not have any effect here.
     *
     * @param \core\update\info[] $notifications The updates which have become available.
     */
    protected function cron_notify(array $notifications) {
        global $CFG;

        if (empty($notifications)) {
            $this->cron_mtrace('nothing to notify about. ', '');
            return;
        }

        $recipients = updateinfo::get_notification_recipients();

        if (empty($recipients)) {
            return;
        }

        $this->cron_mtrace('sending notifications ... ', '');

        $text = (new notification($notifications))->get_text();

        foreach ($recipients as $recipient) {
            $message = new \core\message\message();
            $message->courseid = SITEID;
            $message->component = 'moodle';
            $message->name = 'availableupdate';
            $message->userfrom = get_admin();
            $message->userto = $recipient;
            $message->subject = get_string('updatenotificationsubject', 'core_admin', ['siteurl' => $CFG->wwwroot]);
            $message->fullmessage = $text;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = '';
            $message->smallmessage = get_string('updatenotifications', 'core_admin');
            $message->notification = 1;
            $message->contexturl = notification::get_report_url();
            $message->contexturlname = get_string('pluginname', 'tool_updatecheck');
            message_send($message);
        }
    }
}
