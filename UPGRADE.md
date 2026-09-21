Upgrading this plugin
=====================

This is an internal documentation for plugin developers with some notes what has to be considered when updating this plugin to a new Moodle major version.

General
-------

* Generally, this is a quite simple plugin with just one purpose.
* It relies on Moodle core's Checks API and on Moodle core's update checker (\core\update\checker), which both have been quite stable between Moodle major versions in the past.
* Thus, the upgrading effort is low.


Upstream changes
----------------

* This plugin does not ship any third-party libraries, but some functions in \tool_updatecheck\local\updateinfo are copied and modified from Moodle core, because the core functions do not return anything if update notifications are disabled in Moodle core and because they use the update notification settings of Moodle core instead of the settings of this plugin. These functions are marked with a "copied and modified from" note in their PHPDoc comments and should be reviewed if Moodle core changes the way how available updates are picked.
* The plugin relies on the fact that the first eight digits of the Moodle version number are the branching date of the major release and that the Moodle release string starts with the major release number. If Moodle HQ changes the versioning scheme, \tool_updatecheck\local\updateinfo::get_core_updates() has to be adapted.


Automated tests
---------------

* The plugin has a good coverage with Behat and PHPUnit tests which test all of the plugin's user stories.
* The automated tests do not connect to the Moodle update server. They work with a faked response of the Moodle update server which is created by the plugin's data generator.


Manual tests
------------

* As the automated tests do not connect to the Moodle update server, you should verify manually that the update information can still be fetched. To do this, visit the report on Site administration -> Reports -> Update check, press the "Check for available updates now" button and verify that the update information has been fetched successfully.
* Additionally, you should verify that the checks can still be queried with `php admin/cli/checks.php --filter=tool_updatecheck`.


Visual checks
-------------

* It might be advisable to have a look at the report page of the plugin in the Moodle GUI as Moodle themes can always change small details in this area.
