@local @local_musi @local_musi_navigation
Feature: Baisc functionality of local_urise works as expected

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | student1 | Student   | 1        |
      | student2 | Student   | 2        |
      | teacher1 | Teacher   | 1        |
      | teacher2 | Teacher   | 2        |
    And the following "categories" exist:
      | name  | category | idnumber |
      | Cat 1 | 0        | CAT1     |
      | Cat 2 | 0        | CAT2     |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | CAT1     |
      | Course 2 | C2        | CAT2     |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | teacher1 | C1     | editingteacher |
      | student2 | C2     | student        |
      | teacher2 | C2     | editingteacher |
    And the following "activities" exist:
      | activity | course | name       | intro               | bookingmanager | eventtype | autoenrol |
      | booking  | C1     | C1Booking1 | Booking description | teacher1       | Webinar   | 1         |
      | booking  | C2     | C2Booking1 | Booking description | teacher2       | Webinar   | 1         |
    And I set shortcodessetinstance for musi to "C1Booking1"
    And I change viewport size to "1366x10000"

  @javascript
  Scenario: Musi navigation: display main menu and goto dashboard
    Given I log in as "admin"
    ## Validate dashboard page
    And I click on "dropdownMenuButton" "button" in the "#usernavigation" "css_element"
    And I click on "Dashboard" "text" in the "nav div[aria-labelledby=\"dropdownMenuButton\"]" "css_element"
    And I wait to be redirected
    And I should see "M:USI Plugin" in the ".page-header-headings" "css_element"
    ## Validate my courses page
    And I click on "dropdownMenuButton" "button" in the "#usernavigation" "css_element"
    And I click on "My courses" "text" in the "nav div[aria-labelledby=\"dropdownMenuButton\"]" "css_element"
    And I wait to be redirected
    And I should see "My courses" in the "#region-main" "css_element"
