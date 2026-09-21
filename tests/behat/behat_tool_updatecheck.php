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
 * Admin tool "Update check" - Custom Behat steps
 *
 * @package    tool_updatecheck
 * @category   test
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');

use Behat\Mink\Exception\ExpectationException;

/**
 * Custom Behat steps for the update check admin tool.
 *
 * @package    tool_updatecheck
 * @category   test
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_tool_updatecheck extends behat_base {
    /**
     * Make the given user a site administrator in addition to the existing site administrators.
     *
     * This is needed to test the update notifications: Moodle core sends them from the main admin to all admins, but
     * the popup notification processor does not show notifications which users have sent to themselves.
     *
     * This cannot be done with the steps of Moodle core: The scheduled task which sends the notifications is run within
     * the Behat process, which does not notice if the site administrators are changed by the web server on the
     * 'Site administrators' page. And setting the 'siteadmins' setting with the config values step would require
     * to know the ID of the user.
     *
     * @Given the user :username is a site administrator in tool_updatecheck
     *
     * @param string $username The username of the user.
     */
    public function the_user_is_a_site_administrator(string $username): void {
        global $CFG;

        $user = $this->get_user_by_identifier($username);
        if (!$user) {
            throw new ExpectationException('No user found with username ' . $username, $this->getSession()->getDriver());
        }

        $admins = array_filter(explode(',', $CFG->siteadmins));
        if (!in_array($user->id, $admins)) {
            $admins[] = $user->id;
            set_config('siteadmins', implode(',', $admins));
        }
    }
}
