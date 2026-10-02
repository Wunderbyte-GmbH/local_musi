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

namespace local_musi\table;

use advanced_testcase;
use mod_booking\singleton_service;

/**
 * Tests the user specific cache of the rendered columns of the musi table.
 *
 * @package local_musi
 * @category test
 * @copyright 2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_musi\table\musi_table
 */
final class musi_table_cache_test extends advanced_testcase {
    /**
     * Clean up the user we buy for.
     */
    public function tearDown(): void {
        unset($_GET['_buyforuser_']);
        parent::tearDown();
    }

    /**
     * A cashier buying for a student renders the column of available places with the link to manage the bookings.
     * The student must not get this html out of the cache afterwards.
     */
    public function test_col_bookings_cache_does_not_leak_between_viewers(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        singleton_service::destroy_instance();

        if (!class_exists('local_shopping_cart\shopping_cart')) {
            $this->markTestSkipped('Buying for another user requires local_shopping_cart.');
        }

        set_config('musicachebookingoptionsanswers', 1, 'local_musi');

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $booking = $this->getDataGenerator()->create_module('booking', ['course' => $course->id]);

        /** @var \mod_booking_generator $plugingenerator */
        $plugingenerator = self::getDataGenerator()->get_plugin_generator('mod_booking');
        $option = $plugingenerator->create_option((object) [
            'bookingid' => $booking->id,
            'text' => 'Cached option',
            'description' => 'Test',
            'maxanswers' => 5,
            'chooseorcreatecourse' => 1,
            'courseid' => $course->id,
        ]);
        $values = $DB->get_record('booking_options', ['id' => $option->id]);

        // On a real page, the booking answers of the option are cached already when the column is rendered.
        $settings = singleton_service::get_instance_of_booking_option_settings($option->id);
        singleton_service::get_instance_of_booking_answers($settings);
        $this->assertNotEmpty(\cache::make('mod_booking', 'bookingoptionsanswers')->get($option->id));

        // The admin is cashier and buys for the student.
        $this->setAdminUser();
        singleton_service::destroy_instance();
        \local_shopping_cart\shopping_cart::buy_for_user((int) $student->id);
        $table = new musi_table('musi_table_cache_test_admin');
        $adminhtml = $table->col_bookings($values);
        $this->assertStringContainsString('report.php', $adminhtml, 'The cashier gets the link to manage the bookings.');

        // Now the student looks at the very same booking option.
        unset($_GET['_buyforuser_']);
        $this->setUser($student);
        singleton_service::destroy_instance();
        $table = new musi_table('musi_table_cache_test_student');
        $studenthtml = $table->col_bookings($values);

        $this->assertStringNotContainsString(
            'report.php',
            $studenthtml,
            'The student must not get the cached html of the cashier.'
        );
    }
}
