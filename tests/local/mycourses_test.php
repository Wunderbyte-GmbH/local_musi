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

namespace local_musi\local;

use advanced_testcase;
use mod_booking\singleton_service;
use stdClass;

/**
 * Tests which sections of the page "My courses" are shown.
 *
 * @package local_musi
 * @category test
 * @copyright 2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_musi\local\mycourses
 */
final class mycourses_test extends advanced_testcase {
    /** @var stdClass booking instance of the current semester */
    private $current;

    /** @var stdClass booking instance of last year */
    private $archive;

    /** @var stdClass course of both booking instances */
    private $course;

    /**
     * Set up two booking instances, the current one is set for the shortcodes.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
        singleton_service::destroy_instance();

        $this->course = $this->getDataGenerator()->create_course();
        $this->archive = $this->getDataGenerator()->create_module('booking', ['course' => $this->course->id]);
        $this->current = $this->getDataGenerator()->create_module('booking', ['course' => $this->course->id]);

        set_config('shortcodessetinstance', $this->current->cmid, 'local_musi');
    }

    /**
     * Create a booking option with a teacher and a responsible contact.
     *
     * @param stdClass $booking
     * @param stdClass $teacher
     * @param stdClass $contact
     * @return void
     */
    private function create_option(stdClass $booking, stdClass $teacher, stdClass $contact): void {
        /** @var \mod_booking_generator $plugingenerator */
        $plugingenerator = self::getDataGenerator()->get_plugin_generator('mod_booking');
        $plugingenerator->create_option((object) [
            'bookingid' => $booking->id,
            'text' => 'Option of ' . $booking->name,
            'description' => 'Test',
            'chooseorcreatecourse' => 1,
            'courseid' => $this->course->id,
            'teachersforoption' => $teacher->username,
            'responsiblecontact' => $contact->username,
        ]);
        singleton_service::destroy_instance();
    }

    /**
     * The booking instance set for the shortcodes is the current one.
     */
    public function test_current_bookingid(): void {
        $this->assertSame((int) $this->current->id, mycourses::get_current_bookingid());

        set_config('shortcodessetinstance', '', 'local_musi');
        $this->assertSame(0, mycourses::get_current_bookingid());

        set_config('shortcodessetinstance', '999999', 'local_musi');
        $this->assertSame(0, mycourses::get_current_bookingid());
    }

    /**
     * Who only taught last year gets the archive section, but no empty section of the current semester.
     */
    public function test_last_years_teacher_sees_archive_only(): void {
        $teacher = $this->getDataGenerator()->create_user();
        $contact = $this->getDataGenerator()->create_user();
        $this->create_option($this->archive, $teacher, $contact);

        $currentbookingid = mycourses::get_current_bookingid();

        // Archive sections.
        $this->assertTrue(mycourses::is_teacher((int) $teacher->id));
        $this->assertTrue(mycourses::is_responsible_contact((int) $contact->id));

        // Sections of the current semester.
        $this->assertFalse(mycourses::is_teacher((int) $teacher->id, $currentbookingid));
        $this->assertFalse(mycourses::is_responsible_contact((int) $contact->id, $currentbookingid));
    }

    /**
     * Who teaches in the current semester gets the section of the current semester.
     */
    public function test_current_teacher_sees_current_section(): void {
        $teacher = $this->getDataGenerator()->create_user();
        $contact = $this->getDataGenerator()->create_user();
        $this->create_option($this->current, $teacher, $contact);

        $currentbookingid = mycourses::get_current_bookingid();

        $this->assertTrue(mycourses::is_teacher((int) $teacher->id, $currentbookingid));
        $this->assertTrue(mycourses::is_responsible_contact((int) $contact->id, $currentbookingid));
        $this->assertTrue(mycourses::is_teacher((int) $teacher->id));
        $this->assertTrue(mycourses::is_responsible_contact((int) $contact->id));
    }

    /**
     * Without any booking option, no section is shown at all.
     */
    public function test_user_without_options_sees_nothing(): void {
        $teacher = $this->getDataGenerator()->create_user();
        $contact = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->create_option($this->current, $teacher, $contact);

        $currentbookingid = mycourses::get_current_bookingid();

        $this->assertFalse(mycourses::is_teacher((int) $other->id));
        $this->assertFalse(mycourses::is_teacher((int) $other->id, $currentbookingid));
        $this->assertFalse(mycourses::is_responsible_contact((int) $other->id));
        $this->assertFalse(mycourses::is_responsible_contact((int) $other->id, $currentbookingid));
    }

    /**
     * Without a valid booking instance for the shortcodes, there is no section of the current semester.
     */
    public function test_no_current_instance_shows_no_current_section(): void {
        $teacher = $this->getDataGenerator()->create_user();
        $contact = $this->getDataGenerator()->create_user();
        $this->create_option($this->current, $teacher, $contact);

        set_config('shortcodessetinstance', '', 'local_musi');
        $currentbookingid = mycourses::get_current_bookingid();

        $this->assertFalse(mycourses::is_teacher((int) $teacher->id, $currentbookingid));
        $this->assertFalse(mycourses::is_responsible_contact((int) $contact->id, $currentbookingid));
        // The archive still knows about the booking option.
        $this->assertTrue(mycourses::is_teacher((int) $teacher->id));
    }
}
