moodle-tool_updatecheck
=======================

[![Moodle Plugin CI](https://github.com/ssystems-de/moodle-tool_updatecheck/actions/workflows/moodle-plugin-ci.yml/badge.svg?branch=main)](https://github.com/ssystems-de/moodle-tool_updatecheck/actions?query=workflow%3A%22Moodle+Plugin+CI%22+branch%3Amain)

Moodle admin tool which exposes available Moodle core updates and plugin updates as checks in Moodle's Checks API, so that they can be picked up by monitoring systems.


Requirements
------------

This plugin requires Moodle 5.2+


Motivation for this plugin
--------------------------

Moodle core checks for available updates of Moodle core and of the installed plugins regularly and notifies the admins about them by mail and on the admin notifications page. However, if you are operating a larger number of Moodle sites, you do not want to read mails or visit admin pages to find out which of your sites need an update. You want to see this information in your monitoring system, right next to all the other health information of your sites, and you want to be able to sum up the need for action on a dashboard.

Moodle provides the Checks API for exactly this purpose. It allows monitoring systems to query the status of a Moodle site in a standardized way. Unfortunately, Moodle core does not expose available updates in the Checks API at all. The existing "Upgrade check" of Moodle core only tells you if the code which is already deployed on the server still has to be installed into the database, but it does not tell you if there is a newer Moodle release or a newer plugin release available.

This plugin fills this gap. It adds two checks to the Checks API which report available Moodle core updates and available plugin updates.


Installation
------------

Install the plugin like any other plugin to folder
/admin/tool/updatecheck

See http://docs.moodle.org/en/Installing_plugins for details on installing Moodle plugins


Usage & Settings
----------------

After installing the plugin, it is ready to use without the need for any configuration.

The plugin adds two status checks to Moodle's Checks API. You can see them on the page:
Site administration -> Reports -> System status

Additionally, the plugin provides a report which lists all available updates in detail. You find it on the page:
Site administration -> Reports -> Update check

To configure the plugin and its behaviour, please visit:
Site administration -> Plugins -> Admin tools -> Update check settings

There, you find eight sections:

### 1. General

Please note: This plugin works independently from the update notification settings in Moodle core. It provides its checks even if update notifications are disabled in Moodle core and it does not respect the update notification settings of Moodle core, but uses its own settings instead.

### 2. General settings

#### Maximum age of the update information

With this setting, you control the maximum age of the update information in hours. If the update information is older or if it is not available at all, the checks will trigger a fetch of the update information by cron and will return the status "Unknown" until the update information has been fetched. Please note that the update information is fetched once a day by a scheduled task anyway. The default value of 25 hours gives this scheduled task a tolerance of one hour, for example if cron is delayed. Thus, you should not set this setting to a lower value unless you let the scheduled task run more frequently as well.

#### Status for missing or outdated update information

With this setting, you control the status which both checks return as long as the update information is missing or older than the configured maximum age, for example because this Moodle site is not able to connect to the Moodle update server or because cron is not running. By default, the checks return the status "Unknown" in this case, which the Checks API / CLI reports with the exit code 3. If your monitoring system should treat this case like a real problem, you can pick a more severe status here.

### 3. Common Checks settings

The checks of this plugin can be queried in two ways: With the Checks API of Moodle core and with the CLI script of this plugin, see the next two sections and the section "Querying the checks with a monitoring system" below. The settings in this section control how the details of the checks are composed and apply to both ways alike.

#### Plugin name format

With this setting, you control how the plugins are named in the list of available plugin updates which is part of the details of the plugin updates check. You can choose between "tool_updatecheck (Update check)", "Update check (tool_updatecheck)", "Update check" and "tool_updatecheck". This setting does not have any effect on the report page, where the plugin name and the component name are always shown.

#### Include summary lines in the details

With this setting, you control if the details of the checks contain summary lines in addition to the list of available updates: The number of available updates (which repeats the number from the summary, even if it is 0), the number of ignored plugins and of plugins missing from disk with available updates, and the time of the last successful fetch of the update information. These lines allow a monitoring system to get all information from the details alone. If your monitoring system just needs the list of available updates, you can disable the summary lines here. Please note that the CLI script of this plugin does not output the summary lines with the `--verbose` parameter either if they are disabled. This setting does not affect the details which are shown if the update information is missing or outdated.

### 4. Checks API settings

The Checks API is the standard way of Moodle core to expose the health of a Moodle site. The two checks of this plugin are shown on the System status page and can be queried with the CLI script admin/cli/checks.php of Moodle core. However, this CLI script is not able to output multiple lines of details properly and outputs the details of a check only since Moodle 5.2. The setting in this section controls how the lines of the details are separated for the Checks API. It does not affect the CLI script of this plugin, see the next section.

#### Checks API separator

With this setting, you control the separator which is put between the individual pieces of information in the Checks API details, for example between the plugins with available updates. You can choose between a semicolon, a slash, a double colon, a hash and a hyphen.

Background: In the Moodle GUI, each piece of information is shown on its own line. However, the CLI script admin/cli/checks.php, which is normally used by monitoring systems to query the checks, converts the Checks API details to plain text and is not able to output details which consist of multiple lines properly. Thus, the Checks API details are output as one single line in plain text and the individual pieces of information are separated with this separator. In the Moodle GUI, the separator is not visible.

If you are parsing the Checks API details in your monitoring system, you can pick the separator which fits best to your parsing. Please note that the separator characters might also be part of the information itself, for example a slash within a plugin name. This is especially true for the hyphen, which is a common part of release names like "v4.5-r1". The hyphen is always surrounded by one space on each side when it is used as separator. Thus, if you pick the hyphen, your parsing has to include these spaces and has to split the details at " - " instead of "-". The same applies to the slash, the double colon and the hash, which are surrounded by spaces as well, whereas the semicolon is just followed by a space.

### 5. Checks CLI settings

In addition to the Checks API, this plugin ships its own CLI script admin/tool/updatecheck/cli/checks.php which runs the two checks of this plugin only. It works and looks like the CLI script of Moodle core, but it outputs the details of the checks on all Moodle versions and it is able to output multiple lines of details, for example one line per plugin with an available update. The setting in this section controls how the lines of the details are separated for this CLI script. It does not affect the Checks API, see the previous section.

#### Checks CLI separator

With this setting, you control the separator which is put between the individual pieces of information in the details of the checks when they are run by the CLI script of this plugin. By default, each piece of information is output on its own line. If your monitoring system prefers to get the details in one single line, you can pick one of the other separators (a semicolon, a slash, a double colon, a hash or a hyphen), which work in the same way as the Checks API separator.

### 6. Moodle core updates

#### Required maturity of Moodle core updates

With this setting, you control the minimum maturity of available Moodle core updates. Available Moodle core updates which are less mature will not be considered by the Moodle core updates check.

#### Consider new builds

With this setting, you control if a new weekly build of the installed Moodle release should be considered as available update by the Moodle core updates check.

#### Status for minor updates

With this setting, you control the status which is returned by the Moodle core updates check if there is a Moodle minor update available (for example an update from Moodle 4.5.1 to Moodle 4.5.2).

#### Status for major updates

With this setting, you control the status which is returned by the Moodle core updates check if there is a Moodle major update available which is overdue (for example an update from Moodle 4.5 to Moodle 5.0).

#### Escalate major updates after

With this setting, you control the number of months after which an available Moodle major update is escalated. As long as the oldest major release which is newer than the installed major release is younger than this number of months, the Moodle core updates check will just return the status "Info". As soon as this major release is older, the check will return the status which is configured for major updates. If you set this setting to 0, a major update is escalated immediately as soon as it is available. If you are running a LTS release and want to stay on it for a longer time, you should set this setting to a higher value like 24 or 36 months instead. And if you do not want major updates to be escalated at all, simply set the status for major updates to "Info".

### 7. Plugin updates

#### Required maturity of plugin updates

With this setting, you control the minimum maturity of available plugin updates. Available plugin updates which are less mature will not be considered by the plugin updates check.

#### Status for plugin updates

With this setting, you control the status which is returned by the plugin updates check if there is at least one plugin update available.

#### Ignored plugins

With this setting, you can select plugins which should be ignored by the plugin updates check. Available updates for these plugins will not affect the status of the check anymore. This is useful if you want or have to stay with the installed version of a particular plugin or if the plugin has been forked locally. On the report page, the available updates for ignored plugins are still listed, but are marked as ignored.

### 8. Notification mails

#### Notification recipients

With this setting, you control who is notified by this plugin about updates which have become available. You can select particular users, everyone who is allowed to change the site configuration, or nobody. By default, nobody is selected and the plugin does not send any notifications at all. Please note that Moodle core does not send its own update notifications anymore as soon as this plugin is installed, regardless of this setting. See the section "Notification mails" below for details.


Capabilities
------------

This plugin does not add any additional capabilities.


Scheduled Tasks
---------------

This plugin also introduces these additional scheduled tasks:

### \tool_updatecheck\task\fetch_updates

This task fetches the update information from the Moodle update server, regardless of the fact if update notifications are enabled in Moodle core or not. If there are notification recipients selected in the plugin settings, it also notifies them about updates which have become available.\
By default, the task is enabled and runs once a day at a random time.


Checks API
----------

This plugin also introduces these additional checks to the System status page:

### \tool_updatecheck\check\coreupdates

This check reports available Moodle core updates. If there is not any Moodle core update available, the check reports the status OK. If there is a Moodle minor update available (for example an update from Moodle 4.5.1 to Moodle 4.5.2), the check reports the status which is configured for minor updates. If there is a Moodle major update available (for example an update from Moodle 4.5 to Moodle 5.0), the check just reports an info result at first and reports the status which is configured for major updates as soon as the major update is overdue (see the section about Moodle major updates below). If there are multiple Moodle core updates available, the most severe status wins.

The summary of the check contains the number of available updates, for example "Moodle core updates available: 2". The details of the check list the particular releases together with their type, followed by the number of available updates once more and by the time of the last successful fetch of the update information.

In the CLI script admin/cli/checks.php, this check can be filtered with its reference `tool_updatecheck_coreupdates`.

### \tool_updatecheck\check\pluginupdates

This check reports available updates of the installed plugins which are not shipped with Moodle core. If there is not any plugin update available, the check reports the status OK. If there is at least one plugin update available, the check reports the status which is configured for plugin updates. If you have decided to stay with the installed version of a particular plugin on purpose, please add this plugin to the ignored plugins in the plugin settings. An ignored plugin does not trigger the check anymore, so you will not end up with a permanently failing check on the System status page. Updates of plugins which are missing from disk (i.e. which are only known in the database, but whose code has been removed) do not trigger the check either, as they are not actionable before the plugin code has been restored. On the report page and in the notification mails, such plugins are still listed, but are marked as missing from disk.

The summary of the check contains the number of plugins with available updates, for example "Plugins with available updates: 3". The details of the check list the particular plugins together with their installed and available releases, followed by the number of plugins with available updates once more, by the number of ignored plugins and of plugins missing from disk with available updates and by the time of the last successful fetch of the update information.

In the CLI script admin/cli/checks.php, this check can be filtered with its reference `tool_updatecheck_pluginupdates`.

### Details of the checks

The details of both checks repeat the number from the summary on purpose (even if it is 0), so that a monitoring system is able to get all information from the details alone. If your monitoring system does not need these summary lines, you can disable them in the settings section "Common Checks settings". In plain text, for example in the CLI script admin/cli/checks.php, the details are output as one single line and the individual pieces of information are separated with the configured Checks API separator.

### Unknown status

Both checks report the status Unknown (or the status which is configured for this case) if the update information is missing or older than the configured maximum age. In this case, they trigger a fetch of the update information by cron and will report their real status as soon as the update information has been fetched. If the fetch fails, for example because the Moodle update server is not reachable, the details of the checks tell when the last attempt has failed and why, and the checks trigger a new attempt after 10 minutes. If the status Unknown persists, please check if cron is running and if your Moodle site is able to connect to the Moodle update server.


How this plugin works
---------------------

### The update information

The plugin does not implement its own communication with the Moodle update server. It uses the update checker of Moodle core and it shares the cached update information with Moodle core. This way, the Moodle update server is not queried more often than necessary.

However, the plugin works independently from the update notification settings of Moodle core:

* The checks are provided even if update notifications are disabled with `$CFG->disableupdatenotifications` in config.php or if the setting "Automatically check for available updates" is disabled.
* The plugin uses its own settings for the required maturity and for considering new builds instead of the settings of Moodle core.
* The plugin fetches the update information with its own scheduled task once a day.

The checks never fetch the update information from the Moodle update server by themselves as they are expected to return quickly and as they might be called by your monitoring system every minute. If a check detects that the update information is missing or older than the configured maximum age, it queues an adhoc task which will fetch the update information during the next cron run and returns the status "Unknown" in the meantime. If the status "Unknown" persists, please check if cron is running and if your Moodle site is able to connect to the Moodle update server.

### Moodle major updates

A new Moodle major release is normally not a reason to take action immediately, but you should not fall behind too far either. Thus, the Moodle core updates check rates an available major update just as "Info" at first. The update is escalated to the configured status as soon as the oldest major release which is newer than your installed major release has been released more than the configured number of months ago (12 months by default). In other words: The plugin measures for how long your site is behind.

The release date of a major release is derived from the Moodle version number, whose first eight digits are the date when the major release has been branched.


Querying the checks with a monitoring system
--------------------------------------------

There are two ways to query the checks of this plugin from a monitoring system: The Checks API script of Moodle core and the CLI script of this plugin. Both return their result in the way which is expected by Nagios compatible monitoring systems: The first line of the output contains the overall status and the exit code of the script is 0 (OK), 1 (WARNING), 2 (CRITICAL) or 3 (UNKNOWN).

### Using the CLI script of Moodle core (Checks API)

Moodle core ships with the CLI script admin/cli/checks.php which runs all checks of the Checks API. To query both checks of this plugin at once, run:

```
sudo -u www-data php admin/cli/checks.php --filter=tool_updatecheck
```

To query a single check, run:

```
sudo -u www-data php admin/cli/checks.php --filter=tool_updatecheck_pluginupdates
```

Please note that the CLI script only outputs the summary and the details of a check if the check reports a warning, an error or a critical status. If a check reports the status OK, Info or Unknown, you will just get the first line with the overall status. This especially means that a Moodle major update which is not overdue yet and which is thus just reported as Info is not visible in the output. If you want to get the summary and the details of the checks regardless of their status (for example to extract the number of available updates, which is also output if it is 0), add the `--verbose` parameter. With this parameter, the CLI script additionally outputs the URL where the check can be actioned:

```
sudo -u www-data php admin/cli/checks.php --filter=tool_updatecheck --verbose
```

The CLI script of Moodle core has two limitations:

* It outputs the details of a check only since Moodle 5.2. Up to Moodle 5.1, the list of available updates is not visible there at all, only the number from the summary.
* It is not able to output multiple lines of details properly. Thus, the details of the checks are output as one single line and the individual pieces of information are separated with the configured Checks API separator (see the settings section "Checks API settings").

### Using the CLI script of this plugin (Checks CLI)

To overcome these limitations, this plugin ships its own CLI script which runs the two checks of this plugin only:

```
sudo -u www-data php admin/tool/updatecheck/cli/checks.php
```

It is a copy of the CLI script of Moodle core 5.2 onwards which is reduced to the checks of this plugin. Thus, it works and looks like the CLI script of Moodle core, including the `--filter` and `--verbose` parameters and the rule that the summary and the details are only output if the check needs attention or if `--verbose` is given. However, it has these advantages:

* It outputs the details of the checks on all supported Moodle versions.
* It is able to output multiple lines of details. By default, each piece of information is output on its own line, for example one line per plugin with an available update. If your monitoring system prefers one single line, you can pick another separator in the settings section "Checks CLI settings".
* The first line always names this plugin ("Moodle updates (tool_updatecheck)") together with the most severe status of both checks, instead of naming the check which has the most severe status. This makes the first line easier to match in a monitoring system.

If you are running Moodle up to 5.1 or if you want to get the details on multiple lines, please use this script instead of the CLI script of Moodle core.


Notification mails
------------------

### Moodle core does not send its update notifications anymore

Please be aware that Moodle core does not send its own update notifications to the admins anymore as soon as this plugin is installed.

Background: Moodle core only sends an update notification if its own scheduled task "Check for updates" fetches the update information and finds a difference to the previously fetched update information. However, this scheduled task skips its run if the update information has been fetched within the last 24 hours by anybody else. As this plugin fetches the update information once a day by itself to keep the checks up to date, the scheduled task of Moodle core always considers the update information as fresh enough and never gets the chance to detect new updates. By the way, the same happens in a plain Moodle without this plugin if an admin clicks the "Check for available updates" button regularly.

This is a technical limitation in Moodle core which this plugin is not able to influence.

### Letting this plugin send the update notifications

To close this gap, the plugin is able to send the update notifications by itself. To enable this, select the recipients in the setting "Notification recipients" in the plugin settings. By default, nobody is selected and nobody receives any update notifications at all, which is fine if the checks of this plugin are watched by your monitoring system anyway.

In contrast to Moodle core, which always notifies all site administrators, you can pick the recipients:

* You can select particular users, for example only the admins who are really in charge of updating the site.
* You can select "Everyone who can 'Change site configuration'" to notify all possible recipients, including the ones who will be added in the future.
* The users who can be selected are the site administrators and all other users who have the capability `moodle/site:config` in the system context, for example by a custom role. This capability is required by Moodle core to receive this kind of notification. It is not possible to notify users without this capability or arbitrary mail addresses.
* A selected user who loses this capability later is not notified anymore.

If there is at least one recipient selected, the plugin works like this:

* Each time when the scheduled task or the adhoc task of this plugin has fetched the update information, the plugin compares it with the previously fetched update information and notifies the recipients about the updates which have become available since then. Thus, each update is announced only once.
* The notification is a plain text message which is built in the same way as the report of this plugin: It lists all available Moodle core updates and plugin updates together with their status, their type (minor or major release, overdue), their maturity and, for plugins, the installed version. The updates which are new and which have triggered the notification are marked with "[New]", updates of ignored plugins are marked with "[Ignored]".
* The notification is sent with the "Available update notifications" message provider and with the subject of the well-known update notification of Moodle core, so each recipient receives it according to the own notification preferences, by default as mail and as popup notification.
* For more details, the notification links to the report of this plugin (Site administration -> Reports -> Update check). The footer of the notification explains the markers which are used in the list, that the notification has been sent by this plugin and where the recipients can be changed.
* The notifications are sent regardless of the update notification settings of Moodle core, i.e. even if update notifications are disabled with `$CFG->disableupdatenotifications` in config.php or if the setting "Automatically check for available updates" is disabled.
* The notifications respect the settings of this plugin instead of the settings of Moodle core: Updates which do not have the required maturity, new builds which should not be considered and updates of ignored plugins are not announced.
* If an admin fetches the update information manually with the "Check for available updates now" button on the report page, no notification is sent. Moodle core handles its own button in the same way.


Setting the ignored plugins in config.php
-----------------------------------------

If you are managing your Moodle sites with a configuration management tool, you might want to set the list of ignored plugins in config.php instead of the Moodle GUI. This is possible with Moodle's forced plugin settings. The value of the setting is a comma-separated list of the frankenstyle component names of the plugins which should be ignored:

```
$CFG->forced_plugin_settings['tool_updatecheck']['pluginsignored'] = 'mod_example,block_example';
```

All other settings of this plugin can be forced in the same way. The possible values for the status settings are `info`, `warning`, `error` and `critical`:

```
$CFG->forced_plugin_settings['tool_updatecheck']['corestatusminor'] = 'error';
$CFG->forced_plugin_settings['tool_updatecheck']['coremajormonths'] = 36;
```


Theme support
-------------
This plugin is developed and tested on Moodle Core's Boost theme.
It should also work with Boost child themes, including Moodle Core's Classic theme. However, we can't support any other theme than Boost.


Plugin repositories
-------------------

This plugin is published and regularly updated in the Moodle plugins repository:
http://moodle.org/plugins/view/tool_updatecheck

The latest development version can be found on Github:
https://github.com/ssystems-de/moodle-tool_updatecheck


Bug and problem reports
-----------------------

This plugin is carefully developed and thoroughly tested, but bugs and problems can always appear.

Please report bugs and problems on Github:
https://github.com/ssystems-de/moodle-tool_updatecheck/issues


Community feature proposals
---------------------------

The functionality of this plugin is primarily implemented for the needs of our clients and published as-is to the community. We are aware that members of the community will have other needs and would love to see them solved by this plugin.

Please issue feature proposals on Github:
https://github.com/ssystems-de/moodle-tool_updatecheck/issues

Please create pull requests on Github:
https://github.com/ssystems-de/moodle-tool_updatecheck/pulls


Paid support
------------

We are always interested to read about your issues and feature proposals or even get a pull request from you on Github. However, please note that our time for working on community Github issues is limited.

As solution provider, we also offer paid support for this plugin. If you are interested, please have a look at our services on [ssystems.de](https://www.ssystems.de/) or get in touch with us directly via vertrieb@ssystems.de.


Moodle release support
----------------------

This plugin is only maintained for the most recent major release of Moodle as well as the most recent LTS release of Moodle. Bugfixes are backported to the LTS release. However, new features and improvements are not necessarily backported to the LTS release.

Apart from these maintained releases, previous versions of this plugin which work in legacy major releases of Moodle are still available as-is without any further updates in the Moodle Plugins repository.

There may be several weeks after a new major release of Moodle has been published until we can do a compatibility check and fix problems if necessary. If you encounter problems with a new major release of Moodle - or can confirm that this plugin still works with a new major release - please let us know on Github.

If you are running a legacy version of Moodle, but want or need to run the latest version of this plugin, you can get the latest version of the plugin, remove the line starting with $plugin->requires from version.php and use this latest plugin version then on your legacy Moodle. However, please note that you will run this setup completely at your own risk. We can't support this approach in any way and there is an undeniable risk for erratic behavior.


Translating this plugin
-----------------------

This Moodle plugin is shipped with an english language pack only. All translations into other languages must be managed through AMOS (https://lang.moodle.org) by what they will become part of Moodle's official language pack.

As the plugin creator, we manage the translation into german for our own local needs on AMOS. Please contribute your translation into all other languages in AMOS where they will be reviewed by the official language pack maintainers for Moodle.


Right-to-left support
---------------------

This plugin has not been tested with Moodle's support for right-to-left (RTL) languages.
If you want to use this plugin with a RTL language and it doesn't work as-is, you are free to send us a pull request on Github with modifications.


Maintainers
-----------

The plugin is maintained by\
ssystems GmbH


Copyright
---------

The copyright of this plugin is held by\
ssystems GmbH

Individual copyrights of individual developers are tracked in PHPDoc comments and Git commits.
