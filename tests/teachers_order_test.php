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

/**
 * Tests for the order of the teachers in the course shortcodes of local_musi.
 *
 * @package local_musi
 * @category test
 * @copyright 2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_musi;

use advanced_testcase;
use cache_helper;
use context_system;
use mod_booking\booking_option;
use mod_booking\singleton_service;
use mod_booking\teachers_handler;
use mod_booking_generator;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/booking/lib.php');

/**
 * The course shortcodes show the teachers of a booking option in the order that was set in the option form
 * of mod_booking (PRO), and sorted by name if no order was set.
 *
 * @package local_musi
 * @category test
 * @copyright 2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @covers \local_musi\table\musi_table::col_teacher
 * @covers \local_musi\shortcodes::allcourseslist
 * @covers \local_musi\shortcodes::allcoursescards
 */
final class teachers_order_test extends advanced_testcase {
    /**
     * Setup.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        singleton_service::destroy_instance();
        cache_helper::purge_all();
    }

    /**
     * Tear down.
     */
    public function tearDown(): void {
        parent::tearDown();
        singleton_service::destroy_instance();
    }

    /**
     * [allekurseliste] and [allekursekarten] list the teachers in the order of the booking option.
     *
     * @dataProvider shortcode_provider
     *
     * @param string $shortcode name of the shortcode
     * @param string $callback method of the shortcodes class
     */
    public function test_shortcode_shows_teachers_in_their_order(string $shortcode, string $callback): void {
        global $PAGE;

        $course = $this->getDataGenerator()->create_course();
        $booking = $this->getDataGenerator()->create_module('booking', ['course' => $course->id, 'name' => 'Musi teachers']);
        $maier = $this->getDataGenerator()->create_user(['firstname' => 'Bea', 'lastname' => 'Maier']);
        $zimmer = $this->getDataGenerator()->create_user(['firstname' => 'Anna', 'lastname' => 'Zimmer']);
        $adler = $this->getDataGenerator()->create_user(['firstname' => 'Carl', 'lastname' => 'Adler']);

        /** @var mod_booking_generator $plugingenerator */
        $plugingenerator = $this->getDataGenerator()->get_plugin_generator('mod_booking');
        $record = new stdClass();
        $record->bookingid = $booking->id;
        $record->text = 'Option with teachers';
        $record->teachersforoption = implode(',', [$maier->username, $zimmer->username, $adler->username]);
        $option = $plugingenerator->create_option($record);

        $PAGE->set_context(context_system::instance());
        $PAGE->set_url('/local/musi/tests/teachers_order_test.php');
        $env = new stdClass();
        $next = fn($x) => $x;
        // The option has no dates: without "all" the shortcodes only list options that are not over yet.
        $args = ['id' => $booking->cmid, 'all' => 1];

        // Without an order: sorted by name.
        $html = (string) shortcodes::$callback($shortcode, $args, null, $env, $next);
        $this->assert_text_order(['Carl Adler', 'Bea Maier', 'Anna Zimmer'], $html);

        // The order of the option form.
        $record->id = $option->id;
        $record->cmid = $booking->cmid;
        $record->teachersforoption = [(int)$maier->id, (int)$zimmer->id, (int)$adler->id];
        $record->{teachers_handler::FORM_TEACHERSORDER} = [$zimmer->id, $adler->id, $maier->id];
        booking_option::update($record);

        $html = (string) shortcodes::$callback($shortcode, $args, null, $env, $next);
        $this->assert_text_order(['Anna Zimmer', 'Carl Adler', 'Bea Maier'], $html);
    }

    /**
     * Data provider for test_shortcode_shows_teachers_in_their_order.
     *
     * @return array
     */
    public static function shortcode_provider(): array {
        return [
            '[allekurseliste]' => ['allekurseliste', 'allcourseslist'],
            '[allekursekarten]' => ['allekursekarten', 'allcoursescards'],
        ];
    }

    /**
     * Asserts that the texts appear in the haystack in the given order.
     *
     * @param string[] $texts
     * @param string $haystack
     */
    private function assert_text_order(array $texts, string $haystack): void {
        $offset = 0;
        foreach ($texts as $text) {
            $position = strpos($haystack, $text, $offset);
            $this->assertNotFalse($position, "'$text' is missing or out of order (expected: " . implode(', ', $texts) . ')');
            $offset = $position + strlen($text);
        }
    }
}
