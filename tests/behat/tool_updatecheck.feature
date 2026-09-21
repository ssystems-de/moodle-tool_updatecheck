@tool @tool_updatecheck
Feature: Using the update check admin tool
  In order to monitor if my Moodle site needs to be updated
  As admin
  I need to be able to see available Moodle core and plugin updates in the system status checks

  Background:
    Given I log in as "admin"

  Scenario: The checks are unknown as long as the update information has not been fetched yet
    When I navigate to "Reports > System status" in site administration
    Then I should see "Unknown" in the "Moodle core updates" "table_row"
    And I should see "The update information is missing or outdated" in the "Moodle core updates" "table_row"
    And I should see "Unknown" in the "Plugin updates" "table_row"
    And I should see "The update information is missing or outdated" in the "Plugin updates" "table_row"

  Scenario: The status for missing or outdated update information can be configured
    Given the following config values are set as admin:
      | config             | value   | plugin           |
      | generalstalestatus | warning | tool_updatecheck |
    When I navigate to "Reports > System status" in site administration
    Then I should see "Warning" in the "Moodle core updates" "table_row"
    And I should see "The update information is missing or outdated" in the "Moodle core updates" "table_row"
    And I should see "Warning" in the "Plugin updates" "table_row"

  Scenario: The checks are ok if there are not any updates available
    Given the following "tool_updatecheck > plugin updates" exist:
      | component        | version    |
      | tool_updatecheck | 2000010100 |
    When I navigate to "Reports > System status" in site administration
    Then I should see "OK" in the "Moodle core updates" "table_row"
    And I should see "There are no Moodle core updates available." in the "Moodle core updates" "table_row"
    And I should see "OK" in the "Plugin updates" "table_row"
    And I should see "There are no plugin updates available." in the "Plugin updates" "table_row"

  Scenario: The checks show a warning if there are updates available
    Given the following "tool_updatecheck > core updates" exist:
      | type  |
      | minor |
    And the following "tool_updatecheck > plugin updates" exist:
      | component        | release  |
      | tool_updatecheck | v99.0-r1 |
    When I navigate to "Reports > System status" in site administration
    Then I should see "Warning" in the "Moodle core updates" "table_row"
    And I should see "Moodle core updates available: 1" in the "Moodle core updates" "table_row"
    And I should see "Warning" in the "Plugin updates" "table_row"
    And I should see "Plugins with available updates: 1" in the "Plugin updates" "table_row"

  Scenario: A major update which has just been released is just an info
    Given the following "tool_updatecheck > core updates" exist:
      | type  |
      | major |
    When I navigate to "Reports > System status" in site administration
    Then I should see "Info" in the "Moodle core updates" "table_row"
    And I should see "Moodle core updates available: 1" in the "Moodle core updates" "table_row"

  Scenario: The status of the checks can be configured
    Given the following "tool_updatecheck > core updates" exist:
      | type  |
      | minor |
    And the following "tool_updatecheck > plugin updates" exist:
      | component        |
      | tool_updatecheck |
    And the following config values are set as admin:
      | config          | value    | plugin           |
      | corestatusminor | critical | tool_updatecheck |
      | pluginsstatus   | error    | tool_updatecheck |
    When I navigate to "Reports > System status" in site administration
    Then I should see "Critical" in the "Moodle core updates" "table_row"
    And I should see "Error" in the "Plugin updates" "table_row"

  Scenario: The checks work even if update notifications are disabled in Moodle core
    Given the following config values are set as admin:
      | updateautocheck | 0 |
    And the following "tool_updatecheck > core updates" exist:
      | type  |
      | minor |
    When I navigate to "Reports > System status" in site administration
    Then I should see "Warning" in the "Moodle core updates" "table_row"

  Scenario: Available updates of ignored plugins do not affect the plugin updates check
    Given the following "tool_updatecheck > plugin updates" exist:
      | component        | release  |
      | tool_updatecheck | v99.0-r1 |
    And the following config values are set as admin:
      | config         | value            | plugin           |
      | pluginsignored | tool_updatecheck | tool_updatecheck |
    When I navigate to "Reports > System status" in site administration
    Then I should see "OK" in the "Plugin updates" "table_row"
    And I should see "There are no plugin updates available." in the "Plugin updates" "table_row"
    And I navigate to "Reports > Update check" in site administration
    And I should see "Ignored" in the "tool_updatecheck" "table_row"

  Scenario: Available updates of plugins which are missing from disk do not affect the plugin updates check
    # A plugin which is only known in the database is considered as missing from disk by Moodle.
    Given the following config values are set as admin:
      | config  | value      | plugin                |
      | version | 2020010100 | local_updatecheckfake |
    And the following "tool_updatecheck > plugin updates" exist:
      | component             | version    |
      | local_updatecheckfake | 2021010100 |
    When I navigate to "Reports > System status" in site administration
    Then I should see "OK" in the "Plugin updates" "table_row"
    And I navigate to "Reports > Update check" in site administration
    And I should see "Missing from disk" in the "local_updatecheckfake" "table_row"
    And I should see "2021010100" in the "local_updatecheckfake" "table_row"

  Scenario: The report lists the available updates
    Given the following "tool_updatecheck > core updates" exist:
      | type  |
      | minor |
      | major |
    And the following "tool_updatecheck > plugin updates" exist:
      | component        | release  |
      | tool_updatecheck | v99.0-r1 |
    When I navigate to "Reports > Update check" in site administration
    Then I should see "Last successful fetch of the update information:"
    And I should not see "Last successful fetch of the update information: Never"
    And I should see "Minor release" in the ".tool_updatecheck-coreupdates" "css_element"
    And I should see "Major release" in the "99.0 (Build" "table_row"
    And I should not see "Overdue" in the "99.0 (Build" "table_row"
    And I should see "v99.0-r1" in the "tool_updatecheck" "table_row"
    And I should not see "Ignored" in the "tool_updatecheck" "table_row"

  Scenario: The report tells the admin if the update information has not been fetched yet
    When I navigate to "Reports > Update check" in site administration
    Then I should see "Last successful fetch of the update information: Never"
    And I should see "The update information is missing or older than 25 hours."
    And I should see "There are no Moodle core updates available."
    And I should see "There are no plugin updates available."

  Scenario: The action link of the checks leads to the report
    When I navigate to "Reports > System status" in site administration
    And I click on "Update check" "link" in the "Moodle core updates" "table_row"
    Then I should see "Check for available updates now"

  Scenario: The admin can fetch the update information manually on the report
    Given the following "tool_updatecheck > remote plugin updates" exist:
      | component        | release  |
      | tool_updatecheck | v99.0-r1 |
    When I navigate to "Reports > Update check" in site administration
    And I should see "Last successful fetch of the update information: Never"
    And I press "Check for available updates now"
    Then I should see "The update information has been fetched successfully."
    And I should not see "Last successful fetch of the update information: Never"
    And I should see "v99.0-r1" in the "tool_updatecheck" "table_row"

  Scenario: The report tells the admin if the update information could not be fetched
    # An invalid fake response lets the fetch fail like a broken response of the Moodle update server would do.
    Given the following config values are set as admin:
      | config           | value                        | plugin           |
      | testfakeresponse | This is not a valid response | tool_updatecheck |
    When I navigate to "Reports > Update check" in site administration
    And I press "Check for available updates now"
    Then I should not see "The update information has been fetched successfully."
    And I should see "The update information is missing or older than 25 hours." in the ".alert-warning" "css_element"
    And I should see "The last attempt to fetch the update information" in the ".alert-danger" "css_element"
    And I should see ". Cron will retry it." in the ".alert-danger" "css_element"
    And I navigate to "Reports > System status" in site administration
    And I should see "Unknown" in the "Moodle core updates" "table_row"
