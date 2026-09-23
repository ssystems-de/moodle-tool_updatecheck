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
* The details of the checks API output are one single line in plain text on purpose (see \tool_updatecheck\check\base::format_details()), because admin/cli/checks.php of Moodle core is not able to indent multiple lines. If Moodle core changes the way how the details are output in the Checks API script, this should be reviewed.
* The CLI script cli/checks.php is copied and modified from admin/cli/checks.php of Moodle core 5.2 (as of MDL-87648, which added the output of the check details to the script and which was released with Moodle 5.2.0). It is reduced to the checks of this plugin, does not offer the --type option and does not iterate over the results of a check with get_results(), as this method does not exist before Moodle 5.2. Additionally, it runs the checks in CLI mode (see the constructor of \tool_updatecheck\check\base), indents multiple lines of details, always names this plugin in the first line and contains two small hooks which allow PHPUnit to include it. All deviations are listed in the file comment of the script. Apart from that, the code is kept identical on purpose. When a new Moodle major version is released, compare cli/checks.php with admin/cli/checks.php of Moodle core and adopt upstream changes, so that both scripts keep outputting the same.


Automated tests
---------------

* The plugin has a good coverage with Behat and PHPUnit tests which test all of the plugin's user stories.
* The automated tests do not connect to the Moodle update server. They work with a faked response of the Moodle update server which is created by the plugin's data generator.


Manual tests
------------

* As the automated tests do not connect to the Moodle update server, you should verify manually that the update information can still be fetched. To do this, visit the report on Site administration -> Reports -> Update check, press the "Check for available updates now" button and verify that the update information has been fetched successfully.
* Additionally, you should verify that the checks can still be queried with `php admin/cli/checks.php --filter=tool_updatecheck` and with `php admin/tool/updatecheck/cli/checks.php`, and that both scripts output the same.


Visual checks
-------------

* It might be advisable to have a look at the report page of the plugin in the Moodle GUI as Moodle themes can always change small details in this area.
