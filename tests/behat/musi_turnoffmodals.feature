@local @local_musi @local_musi_turnoffmodals
Feature: Turn off modals - pre booking pages are shown inline in the MUSI shortcode lists
  As an admin I turn off the modals (booking | turnoffmodals)
  So that the pre booking pages of [allekurseliste] are shown inside the booking option row
  instead of opening a modal.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                | idnumber |
      | teacher1 | Teacher   | 1        | teacher1@example.com | T1       |
      | student1 | Student   | 1        | student1@example.com | S1       |
    And the following "courses" exist:
      | fullname | shortname | category | enablecompletion |
      | Course 1 | C1        | 0        | 1                |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | teacher1 | C1     | manager        |
      | student1 | C1     | student        |
    And I clean booking cache
    And the following config values are set as admin:
      | config        | value | plugin  |
      | turnoffmodals | 1     | booking |
    And the following "activities" exist:
      | activity | course | name       | intro               | bookingmanager | eventtype | Default view for booking options | bookingpolicy |
      | booking  | C1     | BookingCMP | Booking description | teacher1       | Webinar   | All bookings                     | Are you sure? |
    ## The option needs real (future) dates: [allekurseliste] filters on courseendtime > today,
    ## so an option without optiondates would never show up there.
    And the following "mod_booking > options" exist:
      | booking    | text       | course | description   | maxanswers | optiondateid_0 | daystonotify_0 | coursestarttime_0 | courseendtime_0 |
      | BookingCMP | Option01-t | C1     | Inline policy | 3          | 0              | 0              | ## tomorrow ##    | ## +2 days ##   |
    And the following "activity" exists:
      | activity      | page                                       |
      | course        | C1                                         |
      | idnumber      | musi_turnoffmodals                         |
      | name          | MusiTurnOffModals                          |
      | intro         | Booking Options List Page                  |
      | content       | [allekurseliste requirelogin=false]        |
      | contentformat | 0                                          |
    And I am logged in as admin
    And I set the following administration settings values:
      | Set the booking instance which should be used by default | BookingCMP |
    And I log out
    And I change viewport size to "1366x10000"

  @javascript
  Scenario: MUSI shortcode: the booking policy of [allekurseliste] is shown inline, no modal opens
    Given I am on the "musi_turnoffmodals" Activity page logged in as student1
    And I wait until the page is ready
    And I should see "Option01-t"
    When I click on "Book now" "text"
    Then I should see "Are you sure?" in the ".prepage-inline" "css_element"
    And ".modal.show" "css_element" should not exist
    ## The whole booking process has to run inline as well.
    And I set the field "bookingpolicy_checkbox" to "checked"
    And I follow "Continue"
    And I should see "You have successfully booked Option01-t" in the ".prepage-inline .condition-confirmation" "css_element"
    And ".modal.show" "css_element" should not exist
