<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_musi;

use advanced_testcase;
use context_system;
use local_musi\table\musi_table;
use mod_booking\local\slotbooking\slot_answer;
use mod_booking\singleton_service;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/booking/lib.php');

/**
 * Tests that the booked slots of the current user are shown in the MUSI card and list views
 * ("Meine Kurse" - [meinekursekarten] / [meinekurseliste]).
 *
 * @package local_musi
 * @category test
 * @copyright 2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_musi\table\musi_table::col_bookedslots
 * @covers \local_musi\shortcodes::mycoursescards
 * @covers \local_musi\shortcodes::mycourseslist
 */
final class musi_table_bookedslots_test extends advanced_testcase {
    /**
     * Setup.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        singleton_service::destroy_instance();
        \cache_helper::purge_all();
    }

    /**
     * Tear down.
     */
    public function tearDown(): void {
        parent::tearDown();
        singleton_service::destroy_instance();
    }

    /**
     * The column lists every booked slot of the current user, aggregated over all of their
     * active answers (a user can buy slots of the same option more than once).
     *
     * @return void
     */
    public function test_col_bookedslots_lists_all_booked_slots_of_current_user(): void {
        [, $cmid, $slotoptionid, , $student] = $this->seed();

        $slots = $this->book_default_slots($slotoptionid, (int) $student->id);

        $this->setUser($student);
        $table = new musi_table('musibookedslotscol');
        $html = $table->col_bookedslots((object) ['id' => $slotoptionid]);

        $this->assertNotEmpty($html, 'booked slots column rendered empty for a user with booked slots');
        foreach ($slots as [$start, $end]) {
            $this->assertStringContainsString($this->format_slot($start, $end), $html);
        }
        $this->assertStringContainsString(get_string('slot_report_numslots', 'mod_booking'), $html);
    }

    /**
     * Slots of another user must never be shown, and a user without slots gets an empty column.
     *
     * @return void
     */
    public function test_col_bookedslots_is_empty_for_other_users(): void {
        [, , $slotoptionid, , $student] = $this->seed();
        $this->book_default_slots($slotoptionid, (int) $student->id);

        $otheruser = $this->getDataGenerator()->create_user();
        $this->setUser($otheruser);

        $table = new musi_table('musibookedslotsother');
        $this->assertSame('', $table->col_bookedslots((object) ['id' => $slotoptionid]));
    }

    /**
     * At the cashier (a buyer is selected), the column shows the slots of the buyer, not the ones of the
     * cashier who is logged in.
     *
     * @return void
     */
    public function test_col_bookedslots_shows_slots_of_selected_buyer_at_cashier(): void {
        [, , $slotoptionid, , $student] = $this->seed();
        $studentslots = $this->book_default_slots($slotoptionid, (int) $student->id);

        $adminslot = [strtotime('2050-01-10 15:00:00 UTC'), strtotime('2050-01-10 16:00:00 UTC')];
        $this->insert_slot_answer($slotoptionid, (int) get_admin()->id, [$adminslot]);

        $this->setAdminUser();
        \local_shopping_cart\shopping_cart::buy_for_user((int) $student->id);
        try {
            $table = new musi_table('musibookedslotscashier');
            $html = $table->col_bookedslots((object) ['id' => $slotoptionid]);
        } finally {
            unset($_GET['_buyforuser_']);
        }

        foreach ($studentslots as [$start, $end]) {
            $this->assertStringContainsString($this->format_slot($start, $end), $html);
        }
        $this->assertStringNotContainsString($this->format_slot($adminslot[0], $adminslot[1]), $html);
    }

    /**
     * A normal (non-slot) option has no booked slots column content.
     *
     * @return void
     */
    public function test_col_bookedslots_is_empty_for_normal_option(): void {
        [, , , $normaloptionid, $student] = $this->seed();
        $this->book_normal_option($normaloptionid, (int) $student->id);

        $this->setUser($student);
        $table = new musi_table('musibookedslotsnormal');
        $this->assertSame('', $table->col_bookedslots((object) ['id' => $normaloptionid]));
    }

    /**
     * [meinekursekarten] shows the booked slot times on the card of the slot option - independent
     * of the setting musishortcodesshowoptiondates.
     *
     * @return void
     */
    public function test_mycoursescards_shows_booked_slots(): void {
        [, $cmid, $slotoptionid, $normaloptionid, $student] = $this->seed();
        $slots = $this->book_default_slots($slotoptionid, (int) $student->id);
        $this->book_normal_option($normaloptionid, (int) $student->id);
        set_config('musishortcodesshowoptiondates', 0, 'local_musi');

        $html = $this->render_shortcode_as($student, 'mycoursescards', $cmid);

        $this->assertStringContainsString('Slot option', $html, 'slot option card is missing');
        $this->assertStringContainsString('Normal option', $html, 'normal option card is missing');
        foreach ($slots as [$start, $end]) {
            $this->assertStringContainsString($this->format_slot($start, $end), $html);
        }
        $this->assertSame(
            1,
            substr_count($html, get_string('slot_report_numslots', 'mod_booking') . ':'),
            'booked slots block must appear exactly once (only on the slot option card)'
        );
    }

    /**
     * [meinekurseliste] shows the booked slot times in the list row of the slot option.
     *
     * @return void
     */
    public function test_mycourseslist_shows_booked_slots(): void {
        [, $cmid, $slotoptionid, , $student] = $this->seed();
        $slots = $this->book_default_slots($slotoptionid, (int) $student->id);
        set_config('musishortcodesshowoptiondates', 0, 'local_musi');

        $html = $this->render_shortcode_as($student, 'mycourseslist', $cmid);

        $this->assertStringContainsString('Slot option', $html, 'slot option row is missing');
        foreach ($slots as [$start, $end]) {
            $this->assertStringContainsString($this->format_slot($start, $end), $html);
        }
    }

    /**
     * Create a booking instance with one fixed slot option and one normal option, plus a student.
     *
     * @return array{0:int,1:int,2:int,3:int,4:\stdClass} [bookingid, cmid, slotoptionid, normaloptionid, student]
     */
    private function seed(): array {
        $course = $this->getDataGenerator()->create_course();
        $booking = $this->getDataGenerator()->create_module('booking', ['course' => $course->id, 'name' => 'Musi slots']);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        /** @var \mod_booking_generator $plugingenerator */
        $plugingenerator = $this->getDataGenerator()->get_plugin_generator('mod_booking');

        $record = [
            'bookingid' => $booking->id,
            'text' => 'Slot option',
            'course' => $course->id,
            'optiontype' => MOD_BOOKING_OPTIONTYPE_SLOTBOOKING,
            'maxanswers' => 20,
            'slot_enabled' => 1,
            'slot_type' => 'fixed',
            'slot_duration_minutes' => 60,
            'slot_interval_minutes' => 60,
            'slot_custom_max_duration' => 60 * MINSECS,
            'slot_custom_min_duration' => 60 * MINSECS,
            'slot_custom_max_days' => DAYSECS,
            'slot_custom_start_interval_minutes' => 60,
            'slot_opening_time' => '08:00',
            'slot_closing_time' => '18:00',
            'slot_valid_from' => strtotime('2050-01-07 00:00:00 UTC'),
            'slot_valid_until' => strtotime('2050-01-10 23:59:59 UTC'),
            'slot_max_participants_per_slot' => 5,
            'slot_max_slots_per_user' => 5,
            'slot_booking_view_mode' => 'list',
            'slot_add_examiners' => 0,
            'slot_teachers_required' => 0,
            'slot_allow_self_rebooking' => 0,
            'slot_change_deadline_minutes' => '',
        ];
        for ($day = 1; $day <= 7; $day++) {
            $record['slot_day_' . $day] = 1;
        }
        $slotoption = $plugingenerator->create_option((object) $record);

        $normaloption = $plugingenerator->create_option((object) [
            'bookingid' => $booking->id,
            'text' => 'Normal option',
            'course' => $course->id,
            'maxanswers' => 20,
            'coursestarttime_0' => strtotime('2050-02-01 10:00:00 UTC'),
            'courseendtime_0' => strtotime('2050-02-01 12:00:00 UTC'),
        ]);

        singleton_service::destroy_instance();

        return [(int) $booking->id, (int) $booking->cmid, (int) $slotoption->id, (int) $normaloption->id, $student];
    }

    /**
     * Book three slots for the user, split over two separate answers (two purchases).
     *
     * @param int $optionid
     * @param int $userid
     * @return array list of [start, end]
     */
    private function book_default_slots(int $optionid, int $userid): array {
        $first = [strtotime('2050-01-07 09:00:00 UTC'), strtotime('2050-01-07 10:00:00 UTC')];
        $second = [strtotime('2050-01-07 11:00:00 UTC'), strtotime('2050-01-07 12:00:00 UTC')];
        $third = [strtotime('2050-01-09 09:00:00 UTC'), strtotime('2050-01-09 10:00:00 UTC')];

        $this->insert_slot_answer($optionid, $userid, [$first, $second]);
        $this->insert_slot_answer($optionid, $userid, [$third]);

        return [$first, $second, $third];
    }

    /**
     * Insert a booked slot answer directly (bypassing checkout), like the mod_booking slot tests do.
     *
     * @param int $optionid
     * @param int $userid
     * @param array $ranges list of [start, end]
     * @return int booking answer id
     */
    private function insert_slot_answer(int $optionid, int $userid, array $ranges): int {
        global $DB;

        $settings = singleton_service::get_instance_of_booking_option_settings($optionid);
        $slots = array_map(fn($r) => ['start' => $r[0], 'end' => $r[1]], $ranges);

        $answer = (object) [
            'bookingid' => (int) $settings->bookingid,
            'optionid' => $optionid,
            'userid' => $userid,
            'waitinglist' => MOD_BOOKING_STATUSPARAM_BOOKED,
            'places' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
            'startdate' => $ranges[0][0],
            'enddate' => end($ranges)[1],
            'json' => '',
        ];
        slot_answer::set_slot_data($answer, ['slots' => $slots, 'teachers' => []]);

        $baid = (int) $DB->insert_record('booking_answers', $answer);
        \cache_helper::purge_all();
        singleton_service::destroy_instance();

        return $baid;
    }

    /**
     * Book a normal option for the user.
     *
     * @param int $optionid
     * @param int $userid
     * @return void
     */
    private function book_normal_option(int $optionid, int $userid): void {
        global $DB;

        $settings = singleton_service::get_instance_of_booking_option_settings($optionid);
        $DB->insert_record('booking_answers', (object) [
            'bookingid' => (int) $settings->bookingid,
            'optionid' => $optionid,
            'userid' => $userid,
            'waitinglist' => MOD_BOOKING_STATUSPARAM_BOOKED,
            'places' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
            'json' => '',
        ]);
        \cache_helper::purge_all();
        singleton_service::destroy_instance();
    }

    /**
     * Render a local_musi shortcode callback as the given user.
     *
     * @param \stdClass $user
     * @param string $callback shortcodes method name
     * @param int $cmid
     * @return string
     */
    private function render_shortcode_as(\stdClass $user, string $callback, int $cmid): string {
        global $PAGE;

        $this->setUser($user);
        $PAGE->set_context(context_system::instance());
        $PAGE->set_url('/local/musi/meinekurse.php');

        $env = new \stdClass();
        $next = fn($x) => $x;

        return (string) shortcodes::$callback('meinekursekarten', ['id' => $cmid], null, $env, $next);
    }

    /**
     * Expected display format of a single slot (same as mod_booking's bookingoptions_wbtable).
     *
     * @param int $start
     * @param int $end
     * @return string
     */
    private function format_slot(int $start, int $end): string {
        return s(userdate($start, get_string('strftimedatetime', 'langconfig'))
            . ' - ' . userdate($end, get_string('strftimetime', 'langconfig')));
    }
}
