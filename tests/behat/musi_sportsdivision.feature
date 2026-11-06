@local @local_musi @local_musi_sportsdivision
Feature: As admin - the sports division of a booking option follows its sport when I save the booking option.

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | numsections |
      | Course 1 | C1        | 0        | 2           |
    And I clean booking cache
    And the following "activities" exist:
      | activity | course | name       | intro               | bookingmanager | eventtype | Default view for booking options |
      | booking  | C1     | Sportkurse | Booking description | admin          | Webinar   | All bookings                     |
    ## The sections are the sports divisions, the pages in them are the sports.
    And the following "activities" exist:
      | activity | course | section | name       | intro      | content                              | contentformat |
      | page     | C1     | 1       | Yoga       | Yoga       | [allekurseliste category=Yoga]       | 0             |
      | page     | C1     | 2       | Basketball | Basketball | [allekurseliste category=Basketball] | 0             |
    And the following "custom field categories" exist:
      | name     | component   | area    | itemid |
      | SportArt | mod_booking | booking | 0      |
    And the following "custom fields" exist:
      | name   | category | type | shortname      |
      | Sport  | SportArt | text | sport          |
      | Sparte | SportArt | text | sportsdivision |
    And the following "mod_booking > options" exist:
      | booking    | text            | course | description | sport |
      | Sportkurse | Fitnesstraining | C1     | Fitness     | Yoga  |
    And the section "1" of course "C1" for musi has the name "Gesundheit"
    And the section "2" of course "C1" for musi has the name "Ballsport"
    And I change viewport size to "1366x6000"

  @javascript
  Scenario: Musi sports division: an emptied sports division is filled in when the sport is changed
    Given I am on the "Sportkurse" Activity page logged in as admin
    And I click on "Settings" "icon" in the ".allbookingoptionstable_r1" "css_element"
    And I click on "Edit booking option" "link" in the ".allbookingoptionstable_r1" "css_element"
    When I set the following fields to these values:
      | Sport  | Basketball |
      | Sparte |            |
    And I press "Save"
    And I wait until the page is ready
    Then I should not see "does not match the sport of the course"
    And I click on "Settings" "icon" in the ".allbookingoptionstable_r1" "css_element"
    And I click on "Edit booking option" "link" in the ".allbookingoptionstable_r1" "css_element"
    And the field "Sparte" matches value "Ballsport"

  @javascript
  Scenario: Musi sports division: a sports division which does not match the sport is replaced with a warning
    Given I am on the "Sportkurse" Activity page logged in as admin
    And I click on "Settings" "icon" in the ".allbookingoptionstable_r1" "css_element"
    And I click on "Edit booking option" "link" in the ".allbookingoptionstable_r1" "css_element"
    When I set the following fields to these values:
      | Sport  | Basketball |
      | Sparte | Denksport  |
    And I press "Save"
    And I wait until the page is ready
    Then I should see "The sports division \"Denksport\" does not match the sport of the course \"Fitnesstraining\" and has been replaced by \"Ballsport\"."
    And I click on "Settings" "icon" in the ".allbookingoptionstable_r1" "css_element"
    And I click on "Edit booking option" "link" in the ".allbookingoptionstable_r1" "css_element"
    And the field "Sparte" matches value "Ballsport"
