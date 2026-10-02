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
use mod_booking\singleton_service;
use mod_booking_generator;
use moodle_url;

/**
 * Tests the visibility of the sections of the current semester on meinekurse.php.
 *
 * The page shows one section for the courses the user teaches and one for the courses the user is
 * responsible contact of. Both only list booking options of the configured semester instance, so the
 * headings must stay hidden for users who only have courses in an archive instance (MUSI-880).
 *
 * @package local_musi
 * @category test
 * @copyright 2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @runInSeparateProcess
 * @runTestsInSeparateProcesses
 */
final class mycourses_sections_test extends advanced_testcase {
    /**
     * Tests set up.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        singleton_service::destroy_instance();
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
     * A section of the current semester is only visible if that instance really has matching options.
     *
     * @covers \local_musi\shortcodes::has_taught_options
     * @covers \local_musi\shortcodes::has_responsiblecontact_options
     *
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function test_sections_of_current_semester_hidden_for_archive_only_users(): void {
        global $DB, $PAGE;

        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['shortname' => 'musi880']);

        $PAGE->set_url(new moodle_url('/local/musi/meinekurse.php'));
        $PAGE->set_context(context_system::instance());

        // Two booking instances: the current semester and an older one that lives in the archive.
        $current = $this->create_booking_instance($course, 'Current semester');
        $archive = $this->create_booking_instance($course, 'Old semester');

        // Meinekurse.php takes the instance of the current semester from this setting.
        set_config('shortcodessetinstance', $current->cmid, 'local_musi');

        $teachernow = $this->getDataGenerator()->create_user();
        $teacherarchive = $this->getDataGenerator()->create_user();
        $contactnow = $this->getDataGenerator()->create_user();
        $contactarchive = $this->getDataGenerator()->create_user();
        $nobody = $this->getDataGenerator()->create_user();

        /** @var mod_booking_generator $plugingenerator */
        $plugingenerator = self::getDataGenerator()->get_plugin_generator('mod_booking');

        $plugingenerator->create_option((object) [
            'bookingid' => $current->id,
            'text' => 'Option of the current semester',
            'teachersforoption' => $teachernow->username,
            'responsiblecontact' => $contactnow->username,
        ]);
        $plugingenerator->create_option((object) [
            'bookingid' => $archive->id,
            'text' => 'Option of the old semester',
            'teachersforoption' => $teacherarchive->username,
            'responsiblecontact' => $contactarchive->username,
        ]);

        // Users with options in the current instance see their section.
        $this->setUser($teachernow);
        $this->assertTrue(
            shortcodes::has_taught_options((int) $teachernow->id),
            'The teacher section must be visible for a teacher of the current semester.'
        );
        $this->setUser($contactnow);
        $this->assertTrue(
            shortcodes::has_responsiblecontact_options((int) $contactnow->id),
            'The contact section must be visible for a responsible contact of the current semester.'
        );

        // Users with options in the archive only must not get an empty section.
        $this->setUser($teacherarchive);
        $this->assertFalse(
            shortcodes::has_taught_options((int) $teacherarchive->id),
            'The teacher section must be hidden for a teacher of an archive instance only.'
        );
        $this->setUser($contactarchive);
        $this->assertFalse(
            shortcodes::has_responsiblecontact_options((int) $contactarchive->id),
            'The contact section must be hidden for a responsible contact of an archive instance only.'
        );

        // A user without any of both roles never sees one of the sections.
        $this->setUser($nobody);
        $this->assertFalse(shortcodes::has_taught_options((int) $nobody->id));
        $this->assertFalse(shortcodes::has_responsiblecontact_options((int) $nobody->id));

        // The archive sections cover all instances, so they have to stay visible for the archive users.
        $this->assertTrue(
            $DB->record_exists('booking_teachers', ['userid' => $teacherarchive->id]),
            'The teacher archive section must stay visible for a teacher of an archive instance.'
        );
        $like = $DB->sql_like($DB->sql_concat("','", "responsiblecontact", "','"), ':responsiblecontact');
        $this->assertTrue(
            $DB->record_exists_sql(
                "SELECT 1 FROM {booking_options} WHERE $like",
                ['responsiblecontact' => '%,' . $contactarchive->id . ',%']
            ),
            'The contact archive section must stay visible for a responsible contact of an archive instance.'
        );
    }

    /**
     * Without a configured semester instance no section of the current semester can be shown.
     *
     * @covers \local_musi\shortcodes::has_taught_options
     * @covers \local_musi\shortcodes::has_responsiblecontact_options
     *
     * @throws \dml_exception
     */
    public function test_sections_of_current_semester_hidden_without_configured_instance(): void {
        global $PAGE;

        $this->setAdminUser();

        $PAGE->set_url(new moodle_url('/local/musi/meinekurse.php'));
        $PAGE->set_context(context_system::instance());

        set_config('shortcodessetinstance', '', 'local_musi');

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->assertFalse(shortcodes::has_taught_options((int) $user->id));
        $this->assertFalse(shortcodes::has_responsiblecontact_options((int) $user->id));
    }

    /**
     * Creates a booking instance in the given course.
     *
     * @param \stdClass $course
     * @param string $name
     * @return \stdClass the booking instance, including its cmid
     */
    private function create_booking_instance($course, string $name) {
        $bdata = [
            'name' => $name,
            'eventtype' => 'Test event',
            'bookedtext' => ['text' => 'text'],
            'waitingtext' => ['text' => 'text'],
            'notifyemail' => ['text' => 'text'],
            'statuschangetext' => ['text' => 'text'],
            'deletedtext' => ['text' => 'text'],
            'pollurltext' => ['text' => 'text'],
            'pollurlteacherstext' => ['text' => 'text'],
            'notificationtext' => ['text' => 'text'],
            'userleave' => ['text' => 'text'],
            'tags' => '',
            'course' => $course->id,
            'showviews' => ['mybooking,myoptions,showall,showactive,myinstitution'],
        ];

        return $this->getDataGenerator()->create_module('booking', $bdata);
    }
}
