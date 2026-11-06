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

namespace local_musi;

use advanced_testcase;
use context_system;
use core\hook\output\before_http_headers;
use core\notification;
use local_musi\task\add_sports_division;
use local_wunderbyte_table\local\customfield\wbt_field_controller_info;
use mod_booking\booking_option;
use mod_booking\customfield\booking_handler;
use mod_booking\singleton_service;
use mod_booking_generator;
use stdClass;
use tool_mocktesttime\time_mock;

/**
 * Tests of the sports division (customfield "sportsdivision") which follows the sport of a booking option.
 *
 * It is written when a booking option is saved in the form (observer) and by a scheduled task.
 *
 * @package local_musi
 * @category test
 * @copyright 2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_musi\observer::bookingoption_updated
 * @covers \local_musi\task\add_sports_division
 * @covers \local_musi\sports
 */
final class sportsdivision_test extends advanced_testcase {
    /** @var stdClass the booking instance the options are created in */
    private stdClass $booking;

    /**
     * Tests set up.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        time_mock::init();
        time_mock::set_mock_time(strtotime('now'));
        singleton_service::destroy_instance();
        // The field controllers are kept in a static array, but every test creates its own customfields.
        wbt_field_controller_info::purge_static_caches();
    }

    /**
     * Mandatory clean-up after each test.
     */
    public function tearDown(): void {
        parent::tearDown();
        /** @var mod_booking_generator $plugingenerator */
        $plugingenerator = self::getDataGenerator()->get_plugin_generator('mod_booking');
        $plugingenerator->teardown();
    }

    /**
     * The customer scenario: the sport of a booking option is changed and the sports division is emptied
     * in the form. The sports division of the new sport has to be there right after saving, no matter
     * if the customfield "sport" is a text field or a dropdown menu.
     *
     * @dataProvider sportfield_provider
     *
     * @param string $type type of the customfield "sport"
     * @param array $configdata configdata of the customfield "sport"
     * @param array $values the values the customfield stores for the sports
     */
    public function test_saving_writes_division_of_changed_sport(string $type, array $configdata, array $values): void {
        $this->create_sports_and_customfields($type, $configdata);

        // A new booking option gets its sports division, too.
        $option = $this->create_option('Fitnesstraining & Yoga', $values['Yoga']);
        $this->assertSame('Gesundheit', $this->get_stored_division($option->id));

        $this->save_option_form($option, $values['Basketball'], '');
        $this->assertSame('Ballsport', $this->get_stored_division($option->id));

        // The cached settings of the booking option know the new sports division.
        $settings = singleton_service::get_instance_of_booking_option_settings($option->id);
        $this->assertSame('Ballsport', $settings->customfields['sportsdivision']);

        // Nothing was wrong, the sports division was only missing. So there is no warning.
        $this->assertEmpty($this->fetch_warnings());
    }

    /**
     * The task writes the sports division of booking options which were not saved in the form.
     *
     * @dataProvider sportfield_provider
     *
     * @param string $type type of the customfield "sport"
     * @param array $configdata configdata of the customfield "sport"
     * @param array $values the values the customfield stores for the sports
     */
    public function test_task_writes_division_of_changed_sport(string $type, array $configdata, array $values): void {
        $this->create_sports_and_customfields($type, $configdata);

        $option = $this->create_option('Fitnesstraining & Yoga');
        $this->set_customfields($option->id, $values['Yoga']);
        $ampersandoption = $this->create_option('Sport with ampersand');
        $this->set_customfields($ampersandoption->id, $values['Fitnesstraining & Yoga']);
        $this->assertNull($this->get_stored_division($option->id));

        $task = new add_sports_division();
        $task->execute();
        $this->assertSame('Gesundheit', $this->get_stored_division($option->id));
        $this->assertSame('Gesundheit', $this->get_stored_division($ampersandoption->id));

        $this->set_customfields($option->id, $values['Basketball'], '');
        $task->execute();
        $this->assertSame('Ballsport', $this->get_stored_division($option->id));
        $this->assertSame('Gesundheit', $this->get_stored_division($ampersandoption->id));
    }

    /**
     * Data provider with the types of the customfield "sport".
     *
     * @return array
     */
    public static function sportfield_provider(): array {
        return [
            'Text field stores the name of the sport' => [
                'text',
                [],
                ['Yoga' => 'Yoga', 'Basketball' => 'Basketball', 'Fitnesstraining & Yoga' => 'Fitnesstraining & Yoga'],
            ],
            'Dropdown menu stores the number of the entry' => [
                'select',
                ['options' => "Yoga\r\nBasketball\r\nFitnesstraining & Yoga", 'defaultvalue' => ''],
                ['Yoga' => 1, 'Basketball' => 2, 'Fitnesstraining & Yoga' => 3],
            ],
        ];
    }

    /**
     * A booking option has exactly one sports division, also when it has more than one sport.
     * Customfields with multiple choice store the chosen sports in one value.
     *
     * @dataProvider multiplesports_provider
     *
     * @param string $storedsports the value of the customfield "sport"
     * @param string $storeddivision the value of the customfield "sportsdivision"
     * @param string $expecteddivision
     * @param bool $expectwarning if saving the form has to warn about a replaced sports division
     */
    public function test_option_with_multiple_sports(
        string $storedsports,
        string $storeddivision,
        string $expecteddivision,
        bool $expectwarning
    ): void {
        $this->create_sports_and_customfields('text', []);

        // The task.
        $option = $this->create_option('Fitnesstraining & Yoga');
        $this->set_customfields($option->id, $storedsports, $storeddivision);
        $task = new add_sports_division();
        $task->execute();
        $this->assertSame($expecteddivision, $this->get_stored_division($option->id));

        $this->assertArrayHasKey($option->id, sports::return_list_of_boids_with_sport('Yoga'));
        $this->assertArrayNotHasKey($option->id, sports::return_list_of_boids_with_sport('Schach'));

        // Saving the form.
        $formoption = $this->create_option('Saved in the form');
        $this->fetch_warnings();
        $this->save_option_form($formoption, $storedsports, $storeddivision);
        $this->assertSame($expecteddivision, $this->get_stored_division($formoption->id));
        $this->assertCount($expectwarning ? 1 : 0, $this->fetch_warnings());
    }

    /**
     * Data provider for test_option_with_multiple_sports.
     *
     * @return array
     */
    public static function multiplesports_provider(): array {
        return [
            'Same sports division' => ['Fitnesstraining,Yoga', '', 'Gesundheit', false],
            'Different sports divisions: first section wins' => ['Basketball,Yoga', '', 'Gesundheit', false],
            'Different sports divisions: a stored matching one is kept' => ['Basketball,Yoga', 'Ballsport', 'Ballsport', false],
            'Different sports divisions: a stored other one is replaced' => ['Basketball,Yoga', 'Denksport', 'Gesundheit', true],
            'Json array' => ['["Basketball","Yoga"]', '', 'Gesundheit', false],
            'Different case and whitespace' => [' yoga , BASKETBALL', ' ballsport', 'Ballsport', false],
        ];
    }

    /**
     * A sports division entered by hand which does not match the sport is replaced when the form is
     * saved and the user gets a warning.
     */
    public function test_saving_warns_about_division_not_matching_sport(): void {
        $this->create_sports_and_customfields('text', []);

        $option = $this->create_option('Fitnesstraining & Yoga', 'Yoga');
        $this->fetch_warnings();

        $this->save_option_form($option, 'Basketball', 'Denk & Sport');
        $this->assertSame('Ballsport', $this->get_stored_division($option->id));

        // Saving the form is an AJAX request. A notification of Moodle would be lost there, so there is none yet.
        $this->assertEmpty(notification::fetch());
        $notifications = $this->fetch_warnings();
        $this->assertCount(1, $notifications);
        $notification = reset($notifications);
        $this->assertSame(notification::WARNING, $notification->get_message_type());
        $this->assertSame(
            get_string('sportsdivisionreplaced', 'local_musi', (object)[
                'option' => 'Fitnesstraining &amp; Yoga',
                'olddivision' => 'Denk &amp; Sport',
                'newdivision' => 'Ballsport',
            ]),
            $notification->get_message()
        );

        // There is no page for chess. We can not tell what is right, so the entered sports division stays.
        $this->save_option_form($option, 'Schach', 'Denksport');
        $this->assertSame('Denksport', $this->get_stored_division($option->id));
        $this->assertEmpty($this->fetch_warnings());
    }

    /**
     * The task only touches the booking options it has a sports division for and only writes, when
     * the sports division is not stored yet.
     */
    public function test_task_leaves_other_options_untouched(): void {
        global $DB;

        $this->create_sports_and_customfields('text', []);

        // There is no page for chess, so the manually entered sports division has to stay.
        $chessoption = $this->create_option('Chess');
        $this->set_customfields($chessoption->id, 'Schach', 'Denksport');
        $nosportoption = $this->create_option('No sport');
        $yogaoption = $this->create_option('Yoga');
        $this->set_customfields($yogaoption->id, 'Yoga');

        // A customfield "sport" of another component must not be mixed up with the one of mod_booking.
        $category = $this->getDataGenerator()->create_custom_field_category([
            'name' => 'Course fields', 'component' => 'core_course', 'area' => 'course',
        ]);
        $coursefield = $this->getDataGenerator()->create_custom_field([
            'categoryid' => $category->get('id'), 'name' => 'Sport', 'shortname' => 'sport', 'type' => 'text',
        ]);
        $DB->insert_record('customfield_data', [
            'fieldid' => $coursefield->get('id'),
            'instanceid' => $nosportoption->id,
            'value' => 'Basketball',
            'charvalue' => 'Basketball',
            'valueformat' => FORMAT_MOODLE,
            'valuetrust' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
            'contextid' => context_system::instance()->id,
        ]);

        $task = new add_sports_division();
        $task->execute();
        $this->assertSame('Denksport', $this->get_stored_division($chessoption->id));
        $this->assertEmpty($this->get_stored_division($nosportoption->id));
        $this->assertSame('Gesundheit', $this->get_stored_division($yogaoption->id));

        // Everything is up to date now, so a second run has nothing to write.
        $writes = $DB->perf_get_writes();
        $task->execute();
        $this->assertSame($writes, $DB->perf_get_writes());
    }

    /**
     * Creates the pages of the sports in the sections of the sports divisions and the customfields
     * "sport" and "sportsdivision" of mod_booking.
     *
     * @param string $sporttype type of the customfield "sport"
     * @param array $sportconfigdata configdata of the customfield "sport"
     */
    private function create_sports_and_customfields(string $sporttype, array $sportconfigdata): void {
        global $DB;

        $this->setAdminUser();
        $generator = $this->getDataGenerator();

        // The sections of the course are the sports divisions, the pages in them are the sports.
        $course = $generator->create_course(['numsections' => 2]);
        $divisions = [
            1 => ['name' => 'Gesundheit', 'sports' => ['Yoga', 'Fitnesstraining', 'Fitnesstraining & Yoga']],
            2 => ['name' => 'Ballsport', 'sports' => ['Basketball']],
        ];
        foreach ($divisions as $sectionnumber => $division) {
            $DB->set_field('course_sections', 'name', $division['name'], ['course' => $course->id, 'section' => $sectionnumber]);
            foreach ($division['sports'] as $sport) {
                $generator->create_module('page', [
                    'course' => $course->id,
                    'section' => $sectionnumber,
                    'name' => $sport,
                    'content' => '[allekurseliste category="' . $sport . '"]',
                ]);
            }
        }

        $category = $generator->create_custom_field_category([
            'name' => 'Sport', 'component' => 'mod_booking', 'area' => 'booking',
        ]);
        $generator->create_custom_field([
            'categoryid' => $category->get('id'), 'name' => 'Sport', 'shortname' => 'sport',
            'type' => $sporttype, 'configdata' => $sportconfigdata,
        ]);
        $generator->create_custom_field([
            'categoryid' => $category->get('id'), 'name' => 'Sports division', 'shortname' => 'sportsdivision',
            'type' => 'text',
        ]);

        $this->booking = $generator->create_module('booking', [
            'course' => $course->id,
            'name' => 'Sports courses',
            'bookingmanager' => 'admin',
        ]);
    }

    /**
     * Creates a booking option the way the form of the booking option does.
     *
     * @param string $name name of the booking option
     * @param string|int|null $sport value of the customfield "sport"
     * @return stdClass the booking option
     */
    private function create_option(string $name, $sport = null): stdClass {
        $record = (object)['bookingid' => $this->booking->id, 'text' => $name];
        if ($sport !== null) {
            $record->customfield_sport = $sport;
        }
        /** @var mod_booking_generator $plugingenerator */
        $plugingenerator = self::getDataGenerator()->get_plugin_generator('mod_booking');
        return $plugingenerator->create_option($record);
    }

    /**
     * Saves sport and sports division of a booking option the way the form of the booking option does.
     *
     * @param stdClass $option the booking option
     * @param string|int $sport value of the customfield "sport"
     * @param string $division value of the customfield "sportsdivision"
     */
    private function save_option_form(stdClass $option, $sport, string $division): void {
        $settings = singleton_service::get_instance_of_booking_option_settings($option->id);
        booking_option::update((object)[
            'id' => $option->id,
            'cmid' => $settings->cmid,
            'text' => $settings->text,
            'customfield_sport' => $sport,
            'customfield_sportsdivision' => $division,
        ]);
    }

    /**
     * Writes the customfields without the form of the booking option, so no event is triggered.
     * This is what the task is for.
     *
     * @param int $optionid
     * @param string|int $sport value of the customfield "sport"
     * @param string|null $division value of the customfield "sportsdivision", null to leave it alone
     */
    private function set_customfields(int $optionid, $sport, ?string $division = null): void {
        $instance = (object)['id' => $optionid, 'customfield_sport' => $sport];
        if ($division !== null) {
            $instance->customfield_sportsdivision = $division;
        }
        booking_handler::create()->instance_form_save($instance);
    }

    /**
     * Opens the next page for the user: the header of the page triggers the hook which hands the kept
     * warnings over to Moodle.
     *
     * @return array the notifications the page displays
     */
    private function fetch_warnings(): array {
        global $PAGE;

        $hook = new before_http_headers($PAGE->get_renderer('core'));
        \core\di::get(\core\hook\manager::class)->dispatch($hook);
        return notification::fetch();
    }

    /**
     * Returns the value of the customfield "sportsdivision" as it is stored for a booking option.
     *
     * @param int $optionid
     * @return string|null null if nothing is stored
     */
    private function get_stored_division(int $optionid): ?string {
        global $DB;

        $value = $DB->get_field_sql(
            "SELECT cfd.value
               FROM {customfield_data} cfd
               JOIN {customfield_field} cff ON cff.id = cfd.fieldid
               JOIN {customfield_category} cfc ON cfc.id = cff.categoryid
              WHERE cff.shortname = 'sportsdivision' AND cfc.component = 'mod_booking' AND cfd.instanceid = :optionid",
            ['optionid' => $optionid]
        );
        return $value === false ? null : $value;
    }
}
