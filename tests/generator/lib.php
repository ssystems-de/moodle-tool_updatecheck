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
 * Admin tool "Update check" - Data generator
 *
 * @package    tool_updatecheck
 * @category   test
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Data generator which fakes the update information which is normally fetched from the Moodle update server.
 *
 * @package    tool_updatecheck
 * @category   test
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_updatecheck_generator extends component_generator_base {
    /**
     * Fake the installation of an additional plugin which is not shipped with Moodle core.
     *
     * This is needed for tests which need more than one plugin with available updates, as this plugin itself is the
     * only additional plugin which is guaranteed to be installed on a test site.
     *
     * The plugin is just registered in the database like a really installed plugin whose code has been removed from
     * disk afterwards. Moodle considers such a plugin as missing from disk and this plugin handles it accordingly:
     * It is listed with its available updates, but it does not affect the status of the plugin updates check.
     *
     * @param array $data The plugin data with the keys component (defaults to local_updatecheckfake) and version.
     * @return string The frankenstyle component name of the fake plugin.
     */
    public function create_fake_plugin(array $data = []): string {
        $component = $data['component'] ?? 'local_updatecheckfake';

        set_config('version', $data['version'] ?? '2020010100', $component);
        \core_plugin_manager::reset_caches();
        \tool_updatecheck\local\updateinfo::purge_cache();

        return $component;
    }

    /**
     * Store a fake response of the Moodle update server.
     *
     * @param array $updates The available updates as array of arrays of update infos, indexed by component.
     * @param int|null $fetchtime The timestamp when the response has been fetched (defaults to now).
     */
    public function set_update_response(array $updates, ?int $fetchtime = null): void {
        if ($fetchtime === null) {
            $fetchtime = \core\di::get(\core\clock::class)->time();
        }

        // Store the response in the same way as Moodle core's update checker does it.
        set_config('recentfetch', $fetchtime, 'core_plugin');
        set_config('recentresponse', $this->build_update_response($updates, $fetchtime), 'core_plugin');

        // Reset the caches to make sure that the fake response is picked up.
        \core\update\checker::reset_caches(true);
        \core_plugin_manager::reset_caches();
        \tool_updatecheck\local\updateinfo::purge_cache();
    }

    /**
     * Fake the next fetches from the Moodle update server.
     *
     * In contrast to set_update_response(), the fake response is not stored as already fetched update information. It
     * will be returned as soon as the plugin really fetches the update information, for example within its tasks or
     * with the button on the report page. It is provided in a plugin setting which is only evaluated on test sites
     * (see \tool_updatecheck\local\checker::get_response()) and which is reset together with the database.
     *
     * @param array $updates The available updates as array of arrays of update infos, indexed by component.
     */
    public function set_remote_update_response(array $updates): void {
        $time = \core\di::get(\core\clock::class)->time();
        set_config(
            \tool_updatecheck\local\checker::TESTFAKERESPONSE,
            $this->build_update_response($updates, $time),
            'tool_updatecheck'
        );
    }

    /**
     * Let the next fetches from the Moodle update server fail.
     *
     * The fake response is invalid, so the update checker rejects it with an exception like it would do with a real
     * broken response.
     */
    public function set_remote_update_failure(): void {
        set_config(\tool_updatecheck\local\checker::TESTFAKERESPONSE, 'This is not a valid response.', 'tool_updatecheck');
    }

    /**
     * Compose a fake response of the Moodle update server.
     *
     * @param array $updates The available updates as array of arrays of update infos, indexed by component.
     * @param int $time The timestamp when the response has been generated.
     * @return string The raw JSON response.
     */
    protected function build_update_response(array $updates, int $time): string {
        global $CFG;

        $version = null;
        require($CFG->dirroot . '/version.php');

        // Compose the response in the same way as the Moodle update server does it.
        return json_encode([
            'status' => 'OK',
            'provider' => 'https://download.moodle.org/api/1.3/updates.php',
            'apiver' => '1.3',
            'timegenerated' => $time,
            'ticket' => 'faketicket',
            'forbranch' => moodle_major_version(true),
            'forversion' => (string) $version,
            'updates' => $updates,
        ]);
    }

    /**
     * Add an available Moodle core update to the fake response of the Moodle update server.
     *
     * @param array $data The update data with the keys type (minor or major, defaults to minor), version, release,
     *                    maturity and url. If version and release are not given, they are generated based on the type.
     */
    public function create_core_update(array $data): void {
        $this->add_update('core', $this->build_core_update($data));
    }

    /**
     * Add an available plugin update to the fake response of the Moodle update server.
     *
     * @param array $data The update data with the keys component (required), version, release, maturity and url.
     */
    public function create_plugin_update(array $data): void {
        $update = $this->build_plugin_update($data);
        $this->add_update($data['component'], $update);
    }

    /**
     * Add an available Moodle core update to the fake response which will be returned by the next fetches from the
     * Moodle update server (see set_remote_update_response()).
     *
     * @param array $data The update data, see create_core_update().
     */
    public function create_remote_core_update(array $data): void {
        $this->add_remote_update('core', $this->build_core_update($data));
    }

    /**
     * Add an available plugin update to the fake response which will be returned by the next fetches from the
     * Moodle update server (see set_remote_update_response()).
     *
     * @param array $data The update data, see create_plugin_update().
     */
    public function create_remote_plugin_update(array $data): void {
        $update = $this->build_plugin_update($data);
        $this->add_remote_update($data['component'], $update);
    }

    /**
     * Compose a Moodle core update info.
     *
     * @param array $data The update data, see create_core_update().
     * @return array The update info.
     */
    protected function build_core_update(array $data): array {
        global $CFG;

        $version = null;
        require($CFG->dirroot . '/version.php');

        // Generate the version and release if necessary.
        $type = $data['type'] ?? 'minor';
        if ($type === 'major') {
            // The major release has been released today.
            $now = \core\di::get(\core\clock::class)->time();
            $defaultversion = (float) (gmdate('Ymd', $now) . '99');
            $defaultrelease = '99.0 (Build: ' . gmdate('Ymd', $now) . ')';
        } else {
            $defaultversion = $version + 1;
            $defaultrelease = moodle_major_version(true) . '.99 (Build: 20990101)';
        }

        return [
            'version' => $data['version'] ?? $defaultversion,
            'release' => $data['release'] ?? $defaultrelease,
            'maturity' => (int) ($data['maturity'] ?? MATURITY_STABLE),
            'url' => $data['url'] ?? 'https://download.moodle.org',
        ];
    }

    /**
     * Compose a plugin update info.
     *
     * @param array $data The update data, see create_plugin_update().
     * @return array The update info.
     */
    protected function build_plugin_update(array $data): array {
        if (empty($data['component'])) {
            throw new coding_exception('The component of the plugin update must be given.');
        }

        return [
            'version' => $data['version'] ?? '2099010100',
            'release' => $data['release'] ?? 'v99.0-r1',
            'maturity' => (int) ($data['maturity'] ?? MATURITY_STABLE),
            'url' => $data['url'] ?? 'https://moodle.org/plugins/' . $data['component'],
        ];
    }

    /**
     * Add an available update to the fake response which will be returned by the next fetches from the
     * Moodle update server.
     *
     * @param string $component The frankenstyle component name.
     * @param array $update The update info.
     */
    protected function add_remote_update(string $component, array $update): void {
        // Get the updates from the existing fake response, if any.
        $updates = [];
        $response = get_config('tool_updatecheck', \tool_updatecheck\local\checker::TESTFAKERESPONSE);
        if (!empty($response)) {
            $updates = json_decode($response, true)['updates'] ?? [];
        }

        // Add the update and set the response again.
        $updates[$component][] = $update;
        $this->set_remote_update_response($updates);
    }

    /**
     * Add an available update to the fake response of the Moodle update server.
     *
     * @param string $component The frankenstyle component name.
     * @param array $update The update info.
     */
    protected function add_update(string $component, array $update): void {
        // Get the updates from the existing fake response, if any.
        $updates = [];
        $response = get_config('core_plugin', 'recentresponse');
        if (!empty($response)) {
            $updates = json_decode($response, true)['updates'] ?? [];
        }

        // Add the update and store the response again.
        $updates[$component][] = $update;
        $this->set_update_response($updates);
    }
}
