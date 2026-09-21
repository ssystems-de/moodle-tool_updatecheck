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
 * Admin tool "Update check" - Update information helper
 *
 * @package    tool_updatecheck
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_updatecheck\local;

use core\check\result;

/**
 * Helper class which gathers and rates the information about available updates.
 *
 * This class reads the update information which is cached by Moodle core's update checker, but it deliberately ignores
 * the fact if update notifications are enabled in Moodle core or not. It never fetches data from the remote site
 * by itself as a side effect of reading the update information.
 *
 * @package    tool_updatecheck
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class updateinfo {
    /** @var string Core update within the currently installed major release. */
    const TYPE_MINOR = 'minor';

    /** @var string Core update to a newer major release. */
    const TYPE_MAJOR = 'major';

    /** @var int The default maximum age of the update information (in hours). */
    const DEFAULT_MAXAGE = 25;

    /** @var int The default number of months after which a major update is escalated. */
    const DEFAULT_MAJORMONTHS = 12;

    /** @var int The delay in seconds before the fetch of the update information is retried after a failed attempt. */
    const FETCH_RETRY_DELAY = 10 * MINSECS;

    /** @var string The capability which a user needs to be notified about updates which have become available. */
    const NOTIFICATION_CAPABILITY = 'moodle/site:config';

    /** @var string Plugin name format: Component name, followed by the plugin name in brackets. */
    const NAMEFORMAT_COMPONENTNAME = 'componentname';

    /** @var string Plugin name format: Plugin name, followed by the component name in brackets. */
    const NAMEFORMAT_NAMECOMPONENT = 'namecomponent';

    /** @var string Plugin name format: Plugin name only. */
    const NAMEFORMAT_NAME = 'name';

    /** @var string Plugin name format: Component name only. */
    const NAMEFORMAT_COMPONENT = 'component';

    /** @var string[] The available plugin name formats, the first one is the default. */
    const NAMEFORMATS = [
        self::NAMEFORMAT_COMPONENTNAME,
        self::NAMEFORMAT_NAMECOMPONENT,
        self::NAMEFORMAT_NAME,
        self::NAMEFORMAT_COMPONENT,
    ];

    /**
     * @var string[] The available separators between the lines of the check details, indexed by their setting value.
     *               The first one is the default.
     */
    const SEPARATORS = [
        'semicolon' => '; ',
        'slash' => ' / ',
        'doublecolon' => ' :: ',
        'hash' => ' # ',
        'hyphen' => ' - ',
    ];

    /** @var string[] The check result statuses which can be configured, ordered by ascending severity. */
    const CONFIGURABLE_STATUSES = [result::INFO, result::WARNING, result::ERROR, result::CRITICAL];

    /** @var string[] The check result statuses which can be configured for stale update information. */
    const STALE_STATUSES = [result::UNKNOWN, result::INFO, result::WARNING, result::ERROR, result::CRITICAL];

    /**
     * Get the request cache for the lists of available updates.
     *
     * @return \cache
     */
    protected static function get_cache(): \cache {
        return \cache::make('tool_updatecheck', 'updates');
    }

    /**
     * Purge the cached lists of available updates.
     *
     * This has to be called if the update information or the installed plugins change within the current request
     * (i.e. after a fetch), otherwise the lists would be stale. Tests which change the plugin settings have to call it
     * as well.
     */
    public static function purge_cache(): void {
        self::get_cache()->purge();
    }

    /**
     * Get the current timestamp.
     *
     * @return int
     */
    protected static function now(): int {
        return \core\di::get(\core\clock::class)->time();
    }

    /**
     * Get the timestamp when the update information has been fetched successfully for the last time.
     *
     * @return int|null The timestamp or null if there is not any (valid) update information available.
     */
    public static function get_last_fetch(): ?int {
        // Get the timestamp from Moodle core's update checker.
        // This timestamp does not only cover the fetches which are done by Moodle core's own scheduled task, but every
        // fetch regardless of who triggered it: Each successful run of \core\update\checker::fetch() stores the response
        // together with the current time in the 'recentresponse' and 'recentfetch' settings of the 'core_plugin' component,
        // and get_last_timefetched() simply returns this 'recentfetch' setting. As the scheduled task and the adhoc task
        // of this plugin as well as the button on the report page fetch the update information with the very same
        // fetch() method (see self::fetch() and the checker class of this plugin, which just extends Moodle core's
        // update checker), their fetches are covered as well.
        // Please note that the update checker only returns the timestamp if the stored response is still valid. If it is
        // not (for example after a Moodle major upgrade, as the response is bound to the Moodle branch), it behaves
        // as if the update information has never been fetched and we return null.
        $lastfetch = \core\update\checker::instance()->get_last_timefetched();

        return !empty($lastfetch) ? (int) $lastfetch : null;
    }

    /**
     * Get the human readable information when the update information has been fetched successfully for the last time.
     *
     * @return string
     */
    public static function get_last_fetch_info(): string {
        $lastfetch = self::get_last_fetch();

        return get_string(
            'lastfetch',
            'tool_updatecheck',
            ($lastfetch !== null) ? userdate($lastfetch) : get_string('never')
        );
    }

    /**
     * Get the age of the update information.
     *
     * @return int|null The age in seconds or null if there is not any (valid) update information available.
     */
    public static function get_age(): ?int {
        $lastfetch = self::get_last_fetch();

        return ($lastfetch !== null) ? self::now() - $lastfetch : null;
    }

    /**
     * Check if the update information is available and fresh enough to be rated.
     *
     * @return bool
     */
    public static function is_fresh(): bool {
        $age = self::get_age();

        return $age !== null && $age <= self::get_maxage() * HOURSECS;
    }

    /**
     * Get the installed plugins which are checked for available updates, i.e. all plugins which are not shipped with
     * Moodle core. This includes plugins which are missing from disk (i.e. which are known in the database only), as
     * Moodle core asks the Moodle update server about them as well.
     *
     * The way how these plugins are picked is copied and modified from \core\update\checker::load_current_environment().
     *
     * @return \core\plugininfo\base[] Array of plugin infos, indexed by frankenstyle component name.
     */
    public static function get_checkable_plugins(): array {
        $checkableplugins = [];
        foreach (\core_plugin_manager::instance()->get_plugins() as $plugins) {
            foreach ($plugins as $plugin) {
                if ($plugin->component === '' || $plugin->is_standard()) {
                    continue;
                }
                $checkableplugins[$plugin->component] = $plugin;
            }
        }

        return $checkableplugins;
    }

    /**
     * Get the configured status which the checks return if the update information is missing or outdated.
     *
     * @return string One of the \core\check\result status constants.
     */
    public static function get_stale_status(): string {
        $status = get_config('tool_updatecheck', 'generalstalestatus');

        return in_array($status, self::STALE_STATUSES) ? $status : result::UNKNOWN;
    }

    /**
     * Get the configured maximum age of the update information.
     *
     * @return int The maximum age in hours.
     */
    public static function get_maxage(): int {
        $maxage = (int) get_config('tool_updatecheck', 'generalmaxage');

        return ($maxage > 0) ? $maxage : self::DEFAULT_MAXAGE;
    }

    /**
     * Fetch the update information from the remote site right now.
     *
     * @param bool $notify If the configured recipients should be notified about updates which have become available
     *                     with this fetch. If there are not any recipients configured, nobody is notified.
     * @throws \core\update\checker_exception
     */
    public static function fetch(bool $notify = false): void {
        $checker = checker::create();

        // Remember a failed attempt, so that the checks and the report are able to tell about it.
        try {
            if ($notify && !empty(self::get_notification_recipients())) {
                $checker->fetch_and_notify();
            } else {
                $checker->fetch();
            }
        } catch (\core\update\checker_exception $e) {
            set_config('lastfetchfailuretime', self::now(), 'tool_updatecheck');
            set_config('lastfetchfailuremessage', self::get_fetch_error_message($e), 'tool_updatecheck');
            throw $e;
        }

        // The fetch has succeeded, so a previous failure is not of interest anymore.
        unset_config('lastfetchfailuretime', 'tool_updatecheck');
        unset_config('lastfetchfailuremessage', 'tool_updatecheck');
    }

    /**
     * Get the last failed attempt to fetch the update information, if it has failed after the last successful fetch.
     *
     * @return \stdClass|null Object with the properties time and message, or null if there is not any failure to report.
     */
    public static function get_last_fetch_failure(): ?\stdClass {
        $time = get_config('tool_updatecheck', 'lastfetchfailuretime');
        if (empty($time)) {
            return null;
        }

        $failure = new \stdClass();
        $failure->time = (int) $time;
        $failure->message = (string) get_config('tool_updatecheck', 'lastfetchfailuremessage');

        return $failure;
    }

    /**
     * Get the human readable information about the last failed attempt to fetch the update information.
     *
     * @return string|null The information or null if there is not any failure to report.
     */
    public static function get_last_fetch_failure_info(): ?string {
        $failure = self::get_last_fetch_failure();
        if ($failure === null) {
            return null;
        }

        // The message is embedded into a sentence, so a trailing full stop is stripped to avoid a double one.
        return get_string('lastfetchfailure', 'tool_updatecheck', [
            'time' => userdate($failure->time),
            'message' => rtrim($failure->message, '.'),
        ]);
    }

    /**
     * Get a meaningful error message for a failed fetch of the update information.
     *
     * Moodle core just provides language strings for some of the error codes of its update checker, for the other ones
     * the exception message would be the bare string identifier. And the real cause of the error (like the cURL error
     * or the HTTP response code) is only part of the debug info of the exception, which is not shown to the admin normally.
     *
     * @param \core\update\checker_exception $exception The exception which has been thrown by the update checker.
     * @return string The error message as plain text, it has to be escaped before it is output.
     */
    public static function get_fetch_error_message(\core\update\checker_exception $exception): string {
        $stringmanager = get_string_manager();

        if ($stringmanager->string_exists($exception->errorcode, 'core_plugin')) {
            $message = get_string($exception->errorcode, 'core_plugin');
        } else if ($stringmanager->string_exists('reportfetcherror_' . $exception->errorcode, 'tool_updatecheck')) {
            $message = get_string('reportfetcherror_' . $exception->errorcode, 'tool_updatecheck');
        } else {
            $message = $exception->errorcode;
        }

        $debuginfo = trim((string) $exception->debuginfo);
        if ($debuginfo !== '') {
            $message .= ' (' . $debuginfo . ')';
        }

        return $message;
    }

    /**
     * Get the users who should be notified about updates which have become available.
     *
     * The recipients are picked from the users who are allowed to change the site configuration, as this is the
     * capability which is required by the 'Available update notifications' message provider of Moodle core.
     * Users who have lost this capability since they have been selected are not returned anymore.
     *
     * @return \stdClass[] Array of user objects, indexed by user ID. Empty if the notification mails are disabled.
     */
    public static function get_notification_recipients(): array {
        return get_users_from_config(
            get_config('tool_updatecheck', 'notificationsrecipients'),
            self::NOTIFICATION_CAPABILITY
        );
    }

    /**
     * Request that the update information is fetched from the remote site by cron as soon as possible.
     */
    public static function request_fetch(): void {
        // If the last attempt has failed a moment ago, do not retry it right away. The checks might be called by a
        // monitoring system every minute, and the Moodle update server should not be bothered that often.
        $failure = self::get_last_fetch_failure();
        if ($failure !== null && $failure->time > self::now() - self::FETCH_RETRY_DELAY) {
            return;
        }

        // Queue the adhoc task, but only if it is not queued yet.
        // Please note that the adhoc task never fails (see its execute() method), so there is never a failed task record
        // which would be considered as queued task by Moodle 4.5 and which would prevent queueing a new task.
        \core\task\manager::queue_adhoc_task(new \tool_updatecheck\task\fetch_updates_adhoc(), true);
    }

    /**
     * Get the list of available Moodle core updates.
     *
     * The way how the update information is requested from the update checker is copied and modified from
     * /admin/index.php and \core\update\checker::cron_notifications(), but it uses the settings of this plugin instead of
     * the $CFG->updateminmaturity and $CFG->updatenotifybuilds settings of Moodle core.
     * The rating of the updates as minor, major and overdue is not taken from Moodle core.
     *
     * @return \stdClass[] Array of objects with the properties version, release, maturity, url, type, branch,
     *                     releasedate and overdue, ordered by version.
     */
    public static function get_core_updates(): array {
        // The list is cached for the current request as the report page and the checks gather it within one request.
        $cache = self::get_cache();
        $updates = $cache->get('core');
        if ($updates !== false) {
            return $updates;
        }

        // Get the update information from the cache of Moodle core's update checker.
        $infos = \core\update\checker::instance()->get_update_info('core', self::get_core_update_options());
        if (empty($infos)) {
            $cache->set('core', []);
            return [];
        }

        $localbranch = moodle_major_version(true);
        $majormonths = self::get_major_months();
        $majorthreshold = strtotime('-' . $majormonths . ' months', self::now());

        $updates = [];
        foreach ($infos as $info) {
            $update = new \stdClass();
            $update->version = $info->version;
            $update->release = $info->release;
            $update->maturity = $info->maturity;
            $update->url = $info->url;
            $update->branch = self::extract_branch($info->release);
            $update->releasedate = null;
            $update->overdue = false;

            // If the update is for the installed major release (or if we could not detect the major release at all).
            if ($update->branch === null || $update->branch === $localbranch) {
                $update->type = self::TYPE_MINOR;

                // Otherwise, this is a newer major release.
            } else {
                $update->type = self::TYPE_MAJOR;
                $update->releasedate = self::extract_branch_date($info->version);

                // A major update is overdue if major updates should be escalated immediately
                // or if the major release has been released before the threshold.
                $update->overdue = $majormonths === 0 ||
                        ($update->releasedate !== null && $update->releasedate <= $majorthreshold);
            }

            $updates[] = $update;
        }

        // Order the updates by version.
        usort($updates, function ($a, $b) {
            return $a->version <=> $b->version;
        });

        $cache->set('core', $updates);

        return $updates;
    }

    /**
     * Rate the given list of available Moodle core updates.
     *
     * A minor update is rated with the configured minor update status. A major update is rated as info as long as the
     * oldest available newer major release is younger than the configured number of months. After that, it is rated with
     * the configured major update status. If there are multiple updates, the most severe status wins.
     *
     * @param \stdClass[] $updates The updates as returned by get_core_updates().
     * @return string One of the \core\check\result status constants.
     */
    public static function get_core_status(array $updates): string {
        if (empty($updates)) {
            return result::OK;
        }

        $statuses = [];
        foreach ($updates as $update) {
            if ($update->type === self::TYPE_MINOR) {
                $statuses[] = self::get_configured_status('corestatusminor');
            } else if ($update->overdue) {
                $statuses[] = self::get_configured_status('corestatusmajor');
            } else {
                $statuses[] = result::INFO;
            }
        }

        // Pick the most severe status.
        $severities = array_map(function ($status) {
            return array_search($status, self::CONFIGURABLE_STATUSES);
        }, $statuses);

        return self::CONFIGURABLE_STATUSES[max($severities)];
    }

    /**
     * Get the list of available plugin updates.
     *
     * For each plugin, only the most mature and most recent update is returned.
     *
     * This function is copied and modified from these Moodle core functions, as they do not return anything if update
     * notifications are disabled in Moodle core and as they use the $CFG->updateminmaturity setting of Moodle core:
     * - \core\update\checker::load_current_environment() (picking the plugins which are not shipped with Moodle core)
     * - \core_plugin_manager::load_available_updates_for_plugin() (requesting the update information)
     * - \core\plugininfo\base::available_updates() (keeping only the updates which are newer than the installed version)
     * - \core_plugin_manager::available_updates() (picking the most mature most recent update, but without fetching
     *   the remote plugin info afterwards)
     *
     * @return \stdClass[] Array of objects with the properties component, name, installedversion, installedrelease,
     *                     version, release, maturity, url, ignored and missing (true if the plugin is missing from
     *                     disk), indexed and ordered by component.
     */
    public static function get_plugin_updates(): array {
        // The list is cached for the current request as the report page and the checks gather it within one request.
        $cache = self::get_cache();
        $updates = $cache->get('plugins');
        if ($updates !== false) {
            return $updates;
        }

        $checker = \core\update\checker::instance();
        $options = self::get_plugin_update_options();
        $ignoredplugins = self::get_ignored_plugins();

        $updates = [];
        foreach (self::get_checkable_plugins() as $plugin) {
            // Get the update information from the cache.
            // We do not use $plugin->available_updates() here as this would not return anything if the
            // update notifications are disabled in Moodle core.
            $infos = $checker->get_update_info($plugin->component, $options);
            if (empty($infos)) {
                continue;
            }

            // If the plugin is missing from disk, there is not any disk version and we compare with the database version.
            $installedversion = !empty($plugin->versiondisk) ? $plugin->versiondisk : $plugin->versiondb;

            // Pick the most mature most recent update.
            $best = null;
            foreach ($infos as $info) {
                if ($info->version <= $installedversion) {
                    continue;
                }
                if ($best === null || self::compare_plugin_updates($info, $best) > 0) {
                    $best = $info;
                }
            }
            if ($best === null) {
                continue;
            }

            $update = new \stdClass();
            $update->component = $plugin->component;
            $update->name = $plugin->displayname;
            $update->installedversion = $installedversion;
            $update->installedrelease = $plugin->release;
            $update->version = $best->version;
            $update->release = $best->release;
            $update->maturity = $best->maturity;
            $update->url = $best->url;
            $update->ignored = in_array($plugin->component, $ignoredplugins);
            $update->missing = empty($plugin->versiondisk);
            $updates[$plugin->component] = $update;
        }

        ksort($updates);
        $cache->set('plugins', $updates);

        return $updates;
    }

    /**
     * Rate the given list of available plugin updates.
     *
     * Updates of ignored plugins and of plugins which are missing from disk do not affect the status: The former ones
     * on purpose, the latter ones because they are not actionable, the plugin code would have to be restored first.
     *
     * @param \stdClass[] $updates The updates as returned by get_plugin_updates().
     * @return string One of the \core\check\result status constants.
     */
    public static function get_plugin_status(array $updates): string {
        foreach ($updates as $update) {
            if (!$update->ignored && !$update->missing) {
                return self::get_configured_status('pluginsstatus');
            }
        }

        return result::OK;
    }

    /**
     * Get the list of plugins for which available updates should be ignored.
     *
     * @return string[] Array of frankenstyle component names.
     */
    public static function get_ignored_plugins(): array {
        $ignoredplugins = get_config('tool_updatecheck', 'pluginsignored');
        if (empty($ignoredplugins)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $ignoredplugins))));
    }

    /**
     * Get the list of installed plugins which can be ignored, i.e. all plugins which are not shipped with Moodle core.
     *
     * The way how these plugins are picked is copied and modified from \core\update\checker::load_current_environment().
     *
     * @return string[] Array of plugin names in the format "component name (plugin name)", indexed and ordered
     *                  by frankenstyle component name.
     */
    public static function get_ignorable_plugins(): array {
        $ignorableplugins = [];
        foreach (\core_plugin_manager::instance()->get_plugins() as $plugins) {
            foreach ($plugins as $plugin) {
                if ($plugin->component === '' || $plugin->is_standard()) {
                    continue;
                }
                $ignorableplugins[$plugin->component] = self::format_plugin_name(
                    $plugin->component,
                    $plugin->displayname,
                    self::NAMEFORMAT_COMPONENTNAME
                );
            }
        }

        ksort($ignorableplugins);

        return $ignorableplugins;
    }

    /**
     * Format the name of a plugin for the list of plugin updates in the plugin updates check.
     *
     * @param string $component The frankenstyle component name.
     * @param string $name The human readable plugin name.
     * @param string|null $format One of the NAMEFORMAT_* constants (defaults to the configured format).
     * @return string
     */
    public static function format_plugin_name(string $component, string $name, ?string $format = null): string {
        if ($format === null) {
            $format = get_config('tool_updatecheck', 'checksapipluginnameformat');
        }
        if (!in_array($format, self::NAMEFORMATS)) {
            $format = self::NAMEFORMATS[0];
        }

        switch ($format) {
            case self::NAMEFORMAT_NAMECOMPONENT:
                return $name . ' (' . $component . ')';
            case self::NAMEFORMAT_NAME:
                return $name;
            case self::NAMEFORMAT_COMPONENT:
                return $component;
            default:
                return $component . ' (' . $name . ')';
        }
    }

    /**
     * Get the options for the plugin name format setting.
     *
     * The options are illustrated with this plugin itself as example.
     *
     * @return string[] Array of examples, indexed by NAMEFORMAT_* constants.
     */
    public static function get_nameformat_options(): array {
        $options = [];
        foreach (self::NAMEFORMATS as $format) {
            $options[$format] = self::format_plugin_name(
                'tool_updatecheck',
                get_string('pluginname', 'tool_updatecheck'),
                $format
            );
        }

        return $options;
    }

    /**
     * Get the configured separator between the lines of the check details.
     *
     * @return string
     */
    public static function get_separator(): string {
        $separator = get_config('tool_updatecheck', 'checksapiseparator');

        return self::SEPARATORS[$separator] ?? self::SEPARATORS[array_key_first(self::SEPARATORS)];
    }

    /**
     * Get the options for the separator setting.
     *
     * @return string[] Array of separator names, indexed by the keys of the SEPARATORS constant.
     */
    public static function get_separator_options(): array {
        $options = [];
        foreach (array_keys(self::SEPARATORS) as $separator) {
            $options[$separator] = get_string('setting_checksapiseparator_' . $separator, 'tool_updatecheck');
        }

        return $options;
    }

    /**
     * Get the options for the status settings.
     *
     * @param string[] $statuses The statuses to offer, defaults to the CONFIGURABLE_STATUSES.
     * @return string[] Array of status names, indexed by \core\check\result status constants.
     */
    public static function get_status_options(array $statuses = self::CONFIGURABLE_STATUSES): array {
        $options = [];
        foreach ($statuses as $status) {
            $options[$status] = get_string('status' . $status);
        }

        return $options;
    }

    /**
     * Get the options for the minimum maturity setting.
     *
     * @return string[] Array of maturity names, indexed by MATURITY_* constants.
     */
    public static function get_maturity_options(): array {
        return [
            MATURITY_STABLE => get_string('maturity' . MATURITY_STABLE, 'core_admin'),
            MATURITY_RC => get_string('maturity' . MATURITY_RC, 'core_admin'),
            MATURITY_BETA => get_string('maturity' . MATURITY_BETA, 'core_admin'),
            MATURITY_ALPHA => get_string('maturity' . MATURITY_ALPHA, 'core_admin'),
        ];
    }

    /**
     * Get the human readable name of the given maturity.
     *
     * @param int|null $maturity One of the MATURITY_* constants.
     * @return string The name or an empty string if the maturity is unknown.
     */
    public static function get_maturity_name(?int $maturity): string {
        if (empty($maturity) || !get_string_manager()->string_exists('maturity' . $maturity, 'core_admin')) {
            return '';
        }

        return get_string('maturity' . $maturity, 'core_admin');
    }

    /**
     * Get the options for requesting the Moodle core updates from the update checker according to the plugin settings.
     *
     * @return array The options for \core\update\checker::get_update_info().
     */
    public static function get_core_update_options(): array {
        return [
            'minmaturity' => self::get_minmaturity('coreminmaturity'),
            'notifybuilds' => (bool) get_config('tool_updatecheck', 'corenotifybuilds'),
        ];
    }

    /**
     * Get the options for requesting the plugin updates from the update checker according to the plugin settings.
     *
     * @return array The options for \core\update\checker::get_update_info().
     */
    public static function get_plugin_update_options(): array {
        return [
            'minmaturity' => self::get_minmaturity('pluginsminmaturity'),
        ];
    }

    /**
     * Get the configured minimum maturity of updates for the given minimum maturity setting.
     *
     * @param string $setting The name of the minimum maturity setting.
     * @return int One of the MATURITY_* constants.
     */
    protected static function get_minmaturity(string $setting): int {
        $minmaturity = get_config('tool_updatecheck', $setting);

        return ($minmaturity !== false && $minmaturity !== '') ? (int) $minmaturity : MATURITY_STABLE;
    }

    /**
     * Get the configured status for the given status setting.
     *
     * @param string $setting The name of the status setting.
     * @return string One of the \core\check\result status constants.
     */
    protected static function get_configured_status(string $setting): string {
        $status = get_config('tool_updatecheck', $setting);

        return in_array($status, self::CONFIGURABLE_STATUSES) ? $status : result::WARNING;
    }

    /**
     * Get the configured number of months after which a major update is escalated.
     *
     * @return int The number of months, 0 means that major updates are escalated immediately.
     */
    protected static function get_major_months(): int {
        $months = get_config('tool_updatecheck', 'coremajormonths');
        $months = ($months !== false && $months !== '') ? (int) $months : self::DEFAULT_MAJORMONTHS;

        return max(0, $months);
    }

    /**
     * Extract the major release from a Moodle release string.
     *
     * @param string|null $release The release string, e.g. "5.2.3+ (Build: 20260916)".
     * @return string|null The major release, e.g. "5.2", or null if it could not be detected.
     */
    protected static function extract_branch(?string $release): ?string {
        if ($release !== null && preg_match('/^\s*(\d+)\.(\d+)/', $release, $matches)) {
            return $matches[1] . '.' . $matches[2];
        }

        return null;
    }

    /**
     * Extract the release date of a major release from a Moodle version number.
     *
     * The first eight digits of a Moodle version number are the branching date of the major release
     * and do not change within the major release anymore.
     *
     * @param int|float|string $version The version number, e.g. 2026042003.01.
     * @return int|null The timestamp or null if it could not be detected.
     */
    protected static function extract_branch_date($version): ?int {
        if (!preg_match('/^(\d{4})(\d{2})(\d{2})\d{2}/', (string) $version, $matches)) {
            return null;
        }
        if (!checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
            return null;
        }

        return gmmktime(0, 0, 0, (int) $matches[2], (int) $matches[3], (int) $matches[1]);
    }

    /**
     * Compare two plugin updates to find the most mature most recent one.
     *
     * Updates without explicit maturity are considered more mature than release candidates but less mature than
     * explicit stable, just like Moodle core does it.
     *
     * This function is copied and modified from the comparison within \core_plugin_manager::available_updates().
     *
     * @param \core\update\info $a
     * @param \core\update\info $b
     * @return int
     */
    protected static function compare_plugin_updates(\core\update\info $a, \core\update\info $b): int {
        $maturitya = !empty($a->maturity) ? $a->maturity : MATURITY_STABLE - 25;
        $maturityb = !empty($b->maturity) ? $b->maturity : MATURITY_STABLE - 25;

        if ($maturitya != $maturityb) {
            return $maturitya <=> $maturityb;
        }

        return $a->version <=> $b->version;
    }
}
