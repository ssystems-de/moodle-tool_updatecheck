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
use tool_updatecheck\local\updateinfo;

/**
 * Admin tool "Update check" - Tests for the update information helper
 *
 * @package    tool_updatecheck
 * @category   test
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_updatecheck\local\updateinfo
 */
final class updateinfo_test extends \advanced_testcase {
    /**
     * Get the plugin's data generator.
     *
     * @return \tool_updatecheck_generator
     */
    protected function get_generator(): \tool_updatecheck_generator {
        return $this->getDataGenerator()->get_plugin_generator('tool_updatecheck');
    }

    /**
     * Test the freshness detection if there is not any update information at all.
     */
    public function test_freshness_without_update_information(): void {
        $this->resetAfterTest();

        $this->assertNull(updateinfo::get_last_fetch());
        $this->assertNull(updateinfo::get_age());
        $this->assertFalse(updateinfo::is_fresh());
        $this->assertSame([], updateinfo::get_core_updates());
        $this->assertSame([], updateinfo::get_plugin_updates());
    }

    /**
     * Test the freshness detection with the default and a configured maximum age.
     */
    public function test_freshness_with_update_information(): void {
        $this->resetAfterTest();
        $now = $this->mock_clock_with_frozen()->time();

        // The update information is younger than the default maximum age.
        $youngerage = (updateinfo::DEFAULT_MAXAGE - 1) * HOURSECS;
        $this->get_generator()->set_update_response([], $now - $youngerage);
        $this->assertSame($now - $youngerage, updateinfo::get_last_fetch());
        $this->assertSame($youngerage, updateinfo::get_age());
        $this->assertTrue(updateinfo::is_fresh());

        // The update information is older than the default maximum age.
        $this->get_generator()->set_update_response([], $now - (updateinfo::DEFAULT_MAXAGE + 1) * HOURSECS);
        $this->assertFalse(updateinfo::is_fresh());

        // The update information is younger than the configured maximum age.
        set_config('generalmaxage', 72, 'tool_updatecheck');
        $this->assertTrue(updateinfo::is_fresh());

        // An invalid maximum age falls back to the default maximum age.
        set_config('generalmaxage', 0, 'tool_updatecheck');
        $this->assertSame(updateinfo::DEFAULT_MAXAGE, updateinfo::get_maxage());
        $this->assertFalse(updateinfo::is_fresh());
    }

    /**
     * Test that a plugin which is missing from disk is listed with its updates, but does not affect the status.
     */
    public function test_missing_plugin(): void {
        $this->resetAfterTest();

        // The fake plugin is only known in the database, so it is missing from disk.
        $missingplugin = $this->get_generator()->create_fake_plugin(['version' => '2020010100']);
        $this->get_generator()->create_plugin_update(['component' => $missingplugin, 'version' => '2021010100']);

        // The update is listed and compared with the database version.
        $updates = updateinfo::get_plugin_updates();
        $this->assertArrayHasKey($missingplugin, $updates);
        $this->assertTrue($updates[$missingplugin]->missing);
        $this->assertFalse($updates[$missingplugin]->ignored);
        $this->assertEquals('2020010100', $updates[$missingplugin]->installedversion);
        $this->assertNull($updates[$missingplugin]->installedrelease);
        $this->assertEquals('2021010100', $updates[$missingplugin]->version);
        $this->assertSame(result::OK, updateinfo::get_plugin_status($updates));

        // An update which is not newer than the database version is not listed.
        $this->get_generator()->set_update_response([$missingplugin => [['version' => '2019010100']]]);
        $this->assertArrayNotHasKey($missingplugin, updateinfo::get_plugin_updates());
    }

    /**
     * Test that the lists of available updates are cached for the current request and that the cache is purged properly.
     */
    public function test_updates_are_cached_per_request(): void {
        $this->resetAfterTest();

        $this->get_generator()->create_plugin_update(['component' => 'tool_updatecheck', 'version' => '2099010100']);
        $this->assertArrayHasKey('tool_updatecheck', updateinfo::get_plugin_updates());

        // Changing a setting without purging the cache does not affect the list within the same request.
        set_config('pluginsignored', 'tool_updatecheck', 'tool_updatecheck');
        $this->assertFalse(updateinfo::get_plugin_updates()['tool_updatecheck']->ignored);
        updateinfo::purge_cache();
        $this->assertTrue(updateinfo::get_plugin_updates()['tool_updatecheck']->ignored);

        // A fetch purges the cache by itself.
        $this->get_generator()->create_remote_plugin_update(['component' => 'tool_updatecheck', 'version' => '2099010200']);
        updateinfo::fetch();
        $this->assertEquals('2099010200', updateinfo::get_plugin_updates()['tool_updatecheck']->version);
    }

    /**
     * Test that the error message of a failed fetch is meaningful.
     */
    public function test_fetch_error_message(): void {
        // Moodle core provides a string for this error code, and the real cause is part of the debug info.
        $exception = new \core\update\checker_exception('err_response_curl', 'cURL error 28: Connection timed out');
        $this->assertSame(
            get_string('err_response_curl', 'core_plugin') . ' (cURL error 28: Connection timed out)',
            updateinfo::get_fetch_error_message($exception)
        );

        // Moodle core does not provide a string for this error code.
        $exception = new \core\update\checker_exception('err_response_status', 'ERROR');
        $this->assertSame(
            get_string('reportfetcherror_err_response_status', 'tool_updatecheck') . ' (ERROR)',
            updateinfo::get_fetch_error_message($exception)
        );

        // There is not any debug info.
        $exception = new \core\update\checker_exception('err_response_empty');
        $this->assertSame(
            get_string('reportfetcherror_err_response_empty', 'tool_updatecheck'),
            updateinfo::get_fetch_error_message($exception)
        );

        // An unknown error code is returned as it is.
        $exception = new \core\update\checker_exception('err_whatever');
        $this->assertSame('err_whatever', updateinfo::get_fetch_error_message($exception));
    }

    /**
     * Test that a minor update is detected and rated with the configured status.
     */
    public function test_core_minor_update(): void {
        $this->resetAfterTest();

        $this->get_generator()->create_core_update(['type' => 'minor']);

        $updates = updateinfo::get_core_updates();
        $this->assertCount(1, $updates);
        $this->assertSame(updateinfo::TYPE_MINOR, $updates[0]->type);
        $this->assertSame(moodle_major_version(true), $updates[0]->branch);
        $this->assertNull($updates[0]->releasedate);
        $this->assertFalse($updates[0]->overdue);

        // The default status is warning.
        $this->assertSame(result::WARNING, updateinfo::get_core_status($updates));

        // The status can be configured.
        set_config('corestatusminor', result::CRITICAL, 'tool_updatecheck');
        $this->assertSame(result::CRITICAL, updateinfo::get_core_status($updates));
    }

    /**
     * Test that a major update is rated as info first and is escalated after the configured number of months.
     */
    public function test_core_major_update(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(gmmktime(12, 0, 0, 6, 1, 2090));

        // The major release has been released on 1 January 2090, now it is 1 June 2090.
        $this->get_generator()->create_core_update([
            'type' => 'major',
            'version' => 2090010100.00,
            'release' => '99.0 (Build: 20900101)',
        ]);

        $updates = updateinfo::get_core_updates();
        $this->assertCount(1, $updates);
        $this->assertSame(updateinfo::TYPE_MAJOR, $updates[0]->type);
        $this->assertSame('99.0', $updates[0]->branch);
        $this->assertSame(gmmktime(0, 0, 0, 1, 1, 2090), $updates[0]->releasedate);
        $this->assertFalse($updates[0]->overdue);
        $this->assertSame(result::INFO, updateinfo::get_core_status($updates));

        // With a shorter period, the major update is escalated with the configured status.
        set_config('coremajormonths', 3, 'tool_updatecheck');
        updateinfo::purge_cache();
        set_config('corestatusmajor', result::ERROR, 'tool_updatecheck');
        $updates = updateinfo::get_core_updates();
        $this->assertTrue($updates[0]->overdue);
        $this->assertSame(result::ERROR, updateinfo::get_core_status($updates));

        // With the default period, the major update is escalated after 12 months.
        unset_config('coremajormonths', 'tool_updatecheck');
        updateinfo::purge_cache();
        $clock->set_to(gmmktime(12, 0, 0, 12, 31, 2090));
        $this->assertFalse(updateinfo::get_core_updates()[0]->overdue);
        // The overdue flag depends on the current time, so the cache has to be purged after the clock has changed.
        $clock->set_to(gmmktime(12, 0, 0, 1, 2, 2091));
        updateinfo::purge_cache();
        $updates = updateinfo::get_core_updates();
        $this->assertTrue($updates[0]->overdue);
        $this->assertSame(result::ERROR, updateinfo::get_core_status($updates));

        // With a very long period, the major update is not escalated (yet).
        set_config('coremajormonths', 36, 'tool_updatecheck');
        updateinfo::purge_cache();
        $updates = updateinfo::get_core_updates();
        $this->assertFalse($updates[0]->overdue);
        $this->assertSame(result::INFO, updateinfo::get_core_status($updates));

        // With a period of 0 months, the major update is escalated immediately, even on the day of its release.
        $clock->set_to(gmmktime(0, 0, 0, 1, 1, 2090));
        $this->assertFalse(updateinfo::get_core_updates()[0]->overdue);
        set_config('coremajormonths', 0, 'tool_updatecheck');
        updateinfo::purge_cache();
        $updates = updateinfo::get_core_updates();
        $this->assertTrue($updates[0]->overdue);
        $this->assertSame(result::ERROR, updateinfo::get_core_status($updates));

        // If major updates should never be escalated, the status for major updates can be set to info.
        set_config('corestatusmajor', result::INFO, 'tool_updatecheck');
        $this->assertSame(result::INFO, updateinfo::get_core_status(updateinfo::get_core_updates()));
    }

    /**
     * Test that the oldest newer major release decides about the escalation and that the most severe status wins.
     */
    public function test_core_multiple_updates(): void {
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(gmmktime(12, 0, 0, 3, 1, 2090));

        // The first major release is 14 months old, the second major release is 2 months old.
        $this->get_generator()->create_core_update([
            'type' => 'major',
            'version' => 2090010100.00,
            'release' => '99.0 (Build: 20900101)',
        ]);
        $this->get_generator()->create_core_update([
            'type' => 'major',
            'version' => 2089010105.00,
            'release' => '98.5 (Build: 20900201)',
        ]);
        $this->get_generator()->create_core_update(['type' => 'minor']);
        set_config('corestatusminor', result::INFO, 'tool_updatecheck');
        set_config('corestatusmajor', result::ERROR, 'tool_updatecheck');

        // The updates are ordered by version.
        $updates = updateinfo::get_core_updates();
        $this->assertCount(3, $updates);
        $this->assertSame(updateinfo::TYPE_MINOR, $updates[0]->type);
        $this->assertSame('98.5', $updates[1]->branch);
        $this->assertTrue($updates[1]->overdue);
        $this->assertSame('99.0', $updates[2]->branch);
        $this->assertFalse($updates[2]->overdue);

        // The overdue major update is the most severe one.
        $this->assertSame(result::ERROR, updateinfo::get_core_status($updates));

        // As soon as the minor update is more severe, it wins.
        set_config('corestatusminor', result::CRITICAL, 'tool_updatecheck');
        $this->assertSame(result::CRITICAL, updateinfo::get_core_status($updates));
    }

    /**
     * Test that new builds of the installed release are only considered if this is configured.
     */
    public function test_core_notifybuilds(): void {
        global $CFG;
        $this->resetAfterTest();

        $version = null;
        $release = null;
        require($CFG->dirroot . '/version.php');

        // There is a new build of the installed release.
        $this->get_generator()->create_core_update([
            'type' => 'minor',
            'version' => $version + 1,
            'release' => preg_replace('/\(Build: \d+\)/', '(Build: 20990101)', $release),
        ]);

        // By default, new builds are not considered.
        $this->assertSame([], updateinfo::get_core_updates());

        // But they can be considered.
        set_config('corenotifybuilds', 1, 'tool_updatecheck');
        updateinfo::purge_cache();
        $this->assertCount(1, updateinfo::get_core_updates());
    }

    /**
     * Test that the plugin uses its own minimum maturity settings, separately for Moodle core and for plugins.
     */
    public function test_minmaturity(): void {
        $this->resetAfterTest();

        $this->get_generator()->create_core_update(['type' => 'minor', 'maturity' => MATURITY_BETA]);
        $this->get_generator()->create_plugin_update(['component' => 'tool_updatecheck', 'maturity' => MATURITY_BETA]);

        // By default, only stable updates are considered.
        $this->assertSame([], updateinfo::get_core_updates());
        $this->assertSame([], updateinfo::get_plugin_updates());

        // The setting of Moodle core does not have any effect.
        set_config('updateminmaturity', MATURITY_ALPHA);
        $this->assertSame([], updateinfo::get_core_updates());
        $this->assertSame([], updateinfo::get_plugin_updates());

        // But the Moodle core setting of the plugin has, and it only affects the Moodle core updates.
        set_config('coreminmaturity', MATURITY_BETA, 'tool_updatecheck');
        updateinfo::purge_cache();
        $this->assertCount(1, updateinfo::get_core_updates());
        $this->assertSame([], updateinfo::get_plugin_updates());

        // And the plugins setting of the plugin only affects the plugin updates.
        set_config('coreminmaturity', MATURITY_STABLE, 'tool_updatecheck');
        set_config('pluginsminmaturity', MATURITY_BETA, 'tool_updatecheck');
        updateinfo::purge_cache();
        $this->assertSame([], updateinfo::get_core_updates());
        $this->assertCount(1, updateinfo::get_plugin_updates());
    }

    /**
     * Test that plugin updates are detected and that the most mature most recent update is picked.
     */
    public function test_plugin_updates(): void {
        $this->resetAfterTest();
        set_config('pluginsminmaturity', MATURITY_RC, 'tool_updatecheck');
        updateinfo::purge_cache();

        $installedversion = \core_plugin_manager::instance()->get_plugin_info('tool_updatecheck')->versiondisk;

        // There is an old version, the installed version, two newer stable versions and an even newer release candidate.
        $this->get_generator()->create_plugin_update(['component' => 'tool_updatecheck',
                'version' => $installedversion - 1, 'release' => 'old']);
        $this->get_generator()->create_plugin_update(['component' => 'tool_updatecheck',
                'version' => $installedversion, 'release' => 'installed']);
        $this->get_generator()->create_plugin_update(['component' => 'tool_updatecheck',
                'version' => 2099010100, 'release' => 'stable1']);
        $this->get_generator()->create_plugin_update(['component' => 'tool_updatecheck',
                'version' => 2099010101, 'release' => 'stable2']);
        $this->get_generator()->create_plugin_update(['component' => 'tool_updatecheck',
                'version' => 2099010102, 'release' => 'rc', 'maturity' => MATURITY_RC]);

        // There is also an update for a plugin which is not installed.
        $this->get_generator()->create_plugin_update(['component' => 'tool_doesnotexist']);

        $updates = updateinfo::get_plugin_updates();
        $this->assertSame(['tool_updatecheck'], array_keys($updates));
        $update = $updates['tool_updatecheck'];
        $this->assertSame('tool_updatecheck', $update->component);
        $this->assertSame(get_string('pluginname', 'tool_updatecheck'), $update->name);
        $this->assertEquals($installedversion, $update->installedversion);
        $this->assertEquals(2099010101, $update->version);
        $this->assertSame('stable2', $update->release);
        $this->assertFalse($update->ignored);

        // The default status is warning, but it can be configured.
        $this->assertSame(result::WARNING, updateinfo::get_plugin_status($updates));
        set_config('pluginsstatus', result::ERROR, 'tool_updatecheck');
        $this->assertSame(result::ERROR, updateinfo::get_plugin_status($updates));
    }

    /**
     * Test that there is not any plugin update if only older or equal versions are available.
     */
    public function test_plugin_without_newer_version(): void {
        $this->resetAfterTest();

        $installedversion = \core_plugin_manager::instance()->get_plugin_info('tool_updatecheck')->versiondisk;
        $this->get_generator()->create_plugin_update(['component' => 'tool_updatecheck', 'version' => $installedversion]);

        $updates = updateinfo::get_plugin_updates();
        $this->assertSame([], $updates);
        $this->assertSame(result::OK, updateinfo::get_plugin_status($updates));
    }

    /**
     * Test that updates of ignored plugins are still listed, but do not affect the status.
     */
    public function test_ignored_plugins(): void {
        $this->resetAfterTest();

        $this->get_generator()->create_plugin_update(['component' => 'tool_updatecheck']);
        set_config('pluginsignored', 'tool_doesnotexist, tool_updatecheck', 'tool_updatecheck');
        updateinfo::purge_cache();

        $this->assertSame(['tool_doesnotexist', 'tool_updatecheck'], updateinfo::get_ignored_plugins());

        $updates = updateinfo::get_plugin_updates();
        $this->assertCount(1, $updates);
        $this->assertTrue($updates['tool_updatecheck']->ignored);
        $this->assertSame(result::OK, updateinfo::get_plugin_status($updates));

        // The plugin can be ignored as it is not shipped with Moodle core, but standard plugins can't.
        $ignorableplugins = updateinfo::get_ignorable_plugins();
        $this->assertArrayHasKey('tool_updatecheck', $ignorableplugins);
        $this->assertSame(
            'tool_updatecheck (' . get_string('pluginname', 'tool_updatecheck') . ')',
            $ignorableplugins['tool_updatecheck']
        );

        // The plugins are ordered by their component name.
        $components = array_keys($ignorableplugins);
        $sortedcomponents = $components;
        sort($sortedcomponents);
        $this->assertSame($sortedcomponents, $components);
        $this->assertArrayNotHasKey('mod_forum', $ignorableplugins);
    }

    /**
     * Test that the update information is rated even if the update notifications are disabled in Moodle core.
     */
    public function test_disabled_core_notifications(): void {
        global $CFG;
        $this->resetAfterTest();

        $CFG->disableupdatenotifications = true;
        set_config('updateautocheck', 0);

        $this->get_generator()->create_core_update(['type' => 'minor']);
        $this->get_generator()->create_plugin_update(['component' => 'tool_updatecheck']);

        // Moodle core does not report the plugin update.
        $this->assertNull(\core_plugin_manager::instance()->get_plugin_info('tool_updatecheck')->available_updates());

        // But the plugin does.
        $this->assertTrue(updateinfo::is_fresh());
        $this->assertCount(1, updateinfo::get_core_updates());
        $this->assertCount(1, updateinfo::get_plugin_updates());
    }

    /**
     * Test the plugin name formats.
     */
    public function test_format_plugin_name(): void {
        $this->resetAfterTest();

        // The default format is the component name, followed by the plugin name.
        $this->assertSame('mod_foo (Foo)', updateinfo::format_plugin_name('mod_foo', 'Foo'));

        // The format can be configured.
        set_config('checksapipluginnameformat', updateinfo::NAMEFORMAT_NAMECOMPONENT, 'tool_updatecheck');
        $this->assertSame('Foo (mod_foo)', updateinfo::format_plugin_name('mod_foo', 'Foo'));
        set_config('checksapipluginnameformat', updateinfo::NAMEFORMAT_NAME, 'tool_updatecheck');
        $this->assertSame('Foo', updateinfo::format_plugin_name('mod_foo', 'Foo'));
        set_config('checksapipluginnameformat', updateinfo::NAMEFORMAT_COMPONENT, 'tool_updatecheck');
        $this->assertSame('mod_foo', updateinfo::format_plugin_name('mod_foo', 'Foo'));

        // An invalid format falls back to the default format.
        set_config('checksapipluginnameformat', 'invalid', 'tool_updatecheck');
        $this->assertSame('mod_foo (Foo)', updateinfo::format_plugin_name('mod_foo', 'Foo'));

        // A given format overrides the configured format.
        $this->assertSame('Foo', updateinfo::format_plugin_name('mod_foo', 'Foo', updateinfo::NAMEFORMAT_NAME));

        // The setting offers all formats and the default format is the first option.
        $this->assertSame(updateinfo::NAMEFORMATS, array_keys(updateinfo::get_nameformat_options()));
        $this->assertSame(updateinfo::NAMEFORMAT_COMPONENTNAME, updateinfo::NAMEFORMATS[0]);
    }

    /**
     * Test that a fetch is only requested once.
     */
    public function test_request_fetch(): void {
        $this->resetAfterTest();

        updateinfo::request_fetch();
        updateinfo::request_fetch();

        $this->assertCount(1, \core\task\manager::get_adhoc_tasks(\tool_updatecheck\task\fetch_updates_adhoc::class));
    }
}
