@tool @tool_updatecheck @javascript
Feature: Getting notified about available updates by the update check admin tool
  In order to learn about new Moodle core and plugin updates without a monitoring system
  As admin
  I need to be able to let the update check admin tool send the update notifications

  Background:
    # Moodle core sends the update notifications from the main admin to all admins, but the popup notification
    # processor does not show notifications which users have sent to themselves. Thus, we need a second admin.
    Given the following "users" exist:
      | username | firstname | lastname | email              |
      | admin2   | Second    | Admin    | admin2@example.com |
    And the user "admin2" is a site administrator in tool_updatecheck

  Scenario: Nobody is notified about new updates by default
    Given the following "tool_updatecheck > remote plugin updates" exist:
      | component        | version    |
      | tool_updatecheck | 2099010100 |
    When I run the scheduled task "\tool_updatecheck\task\fetch_updates"
    And I log in as "admin2"
    And I open the notification popover
    Then I should not see "Moodle updates are available" in the "#nav-notification-popover-container" "css_element"
    And I navigate to "Reports > Update check" in site administration
    And I should see "2099010100" in the "tool_updatecheck" "table_row"

  Scenario: The admins are notified about new updates if they are selected as notification recipients
    Given the following config values are set as admin:
      | config                  | value   | plugin           |
      | notificationsrecipients | $@ALL@$ | tool_updatecheck |
    And the following "tool_updatecheck > remote plugin updates" exist:
      | component        | version    |
      | tool_updatecheck | 2099010100 |
    When I run the scheduled task "\tool_updatecheck\task\fetch_updates"
    And I log in as "admin2"
    And I open the notification popover
    Then I should see "Moodle updates are available" in the "#nav-notification-popover-container" "css_element"

  Scenario: The admins are notified about new updates even if update notifications are disabled in Moodle core
    Given the following config values are set as admin:
      | config                  | value   | plugin           |
      | updateautocheck         | 0       |                  |
      | notificationsrecipients | $@ALL@$ | tool_updatecheck |
    And the following "tool_updatecheck > remote core updates" exist:
      | type  |
      | minor |
    When I run the scheduled task "\tool_updatecheck\task\fetch_updates"
    And I log in as "admin2"
    And I open the notification popover
    Then I should see "Moodle updates are available" in the "#nav-notification-popover-container" "css_element"

  Scenario: The admins are only notified about updates which they have not been notified about yet
    Given the following config values are set as admin:
      | config                  | value   | plugin           |
      | notificationsrecipients | $@ALL@$ | tool_updatecheck |
    And the following "tool_updatecheck > plugin updates" exist:
      | component        | version    |
      | tool_updatecheck | 2099010100 |
    And the following "tool_updatecheck > remote plugin updates" exist:
      | component        | version    |
      | tool_updatecheck | 2099010100 |
    When I run the scheduled task "\tool_updatecheck\task\fetch_updates"
    And I log in as "admin2"
    And I open the notification popover
    Then I should not see "Moodle updates are available" in the "#nav-notification-popover-container" "css_element"
