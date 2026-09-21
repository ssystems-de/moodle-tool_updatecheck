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
 * Admin tool "Update check" - Behat data generator
 *
 * @package    tool_updatecheck
 * @category   test
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Behat data generator which fakes the update information which is normally fetched from the Moodle update server.
 *
 * @package    tool_updatecheck
 * @category   test
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_tool_updatecheck_generator extends behat_generator_base {
    /**
     * Get a list of the entities that can be created.
     *
     * @return array entity name => information about how to generate.
     */
    protected function get_creatable_entities(): array {
        return [
            'core updates' => [
                'singular' => 'core update',
                'datagenerator' => 'core_update',
                'required' => ['type'],
            ],
            'plugin updates' => [
                'singular' => 'plugin update',
                'datagenerator' => 'plugin_update',
                'required' => ['component'],
            ],
            // The remote updates are not stored as already fetched update information. They will be returned
            // as soon as the plugin really fetches the update information within the same scenario.
            'remote core updates' => [
                'singular' => 'remote core update',
                'datagenerator' => 'remote_core_update',
                'required' => ['type'],
            ],
            'remote plugin updates' => [
                'singular' => 'remote plugin update',
                'datagenerator' => 'remote_plugin_update',
                'required' => ['component'],
            ],
        ];
    }
}
