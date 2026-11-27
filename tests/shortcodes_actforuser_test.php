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
use cache_helper;
use context_course;
use context_system;
use local_shopping_cart\shopping_cart;
use local_wunderbyte_table\local\helper\actforuser;
use mod_booking\singleton_service;
use mod_booking_generator;
use stdClass;
use tool_mocktesttime\time_mock;

/**
 * Tests that the course list is only rendered for another user when a cashier asks for it.
 *
 * The course list can be rendered from the perspective of another user (prices, booking state,
 * book button) by putting the id of this user into the url. Only cashiers may do that; for
 * everybody else the id in the url is ignored.
 *
 * @package local_musi
 * @category test
 * @copyright 2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_musi\shortcodes::allcourseslist
 * @covers \local_musi\shortcodes::unifiedview
 *
 * @runInSeparateProcess
 * @runTestsInSeparateProcesses
 */
final class shortcodes_actforuser_test extends advanced_testcase {
    /** @var int Course module id of the booking instance. */
    private int $cmid;

    /** @var stdClass The user whose id is put into the url. */
    private stdClass $otheruser;

    /** @var stdClass The course of the booking instance. */
    private stdClass $course;

    /**
     * Tests set up: a booking instance with options.
     */
    public function setUp(): void {
        global $PAGE;

        parent::setUp();
        $this->resetAfterTest(true);
        time_mock::init();
        time_mock::set_mock_time(strtotime('now'));
        singleton_service::destroy_instance();

        $this->course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $bookingmanager = $this->getDataGenerator()->create_user();
        $booking = $this->getDataGenerator()->create_module('booking', [
            'name' => 'Test Booking',
            'eventtype' => 'Test event',
            'course' => $this->course->id,
            'bookingmanager' => $bookingmanager->username,
        ]);
        $this->cmid = (int)$booking->cmid;

        $this->setAdminUser();
        /** @var mod_booking_generator $plugingenerator */
        $plugingenerator = self::getDataGenerator()->get_plugin_generator('mod_booking');
        foreach (['Option one', 'Option two'] as $text) {
            $plugingenerator->create_option((object)['bookingid' => $booking->id, 'text' => $text]);
        }

        $this->otheruser = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->otheruser->id, $this->course->id, 'student');

        $PAGE->set_context(context_course::instance($this->course->id));
        $PAGE->set_course($this->course);
        $PAGE->set_url(new \moodle_url('/local/musi/tests/shortcodes_actforuser_test.php'));

        unset($_GET['userid']);
        actforuser::$unverifiedforeignids = 0;
    }

    /**
     * Mandatory clean-up after each test.
     */
    public function tearDown(): void {
        unset($_GET['userid'], $_GET['_buyforuser_']);
        cache_helper::purge_by_event('changesinwunderbytetable');
        parent::tearDown();
        /** @var mod_booking_generator $plugingenerator */
        $plugingenerator = self::getDataGenerator()->get_plugin_generator('mod_booking');
        $plugingenerator->teardown();
    }

    /**
     * Render the course list shortcode as the current user, like a page request does.
     *
     * @return string the html of the course list
     */
    private function render_course_list(): string {
        $env = new stdClass();
        $next = function () {
        };
        $html = shortcodes::allcourseslist('allekurseliste', ['all' => 1, 'id' => $this->cmid], null, $env, $next);
        $this->assertStringContainsString('data-encodedtable', $html, 'The course list was not rendered.');
        $this->assertStringContainsString('data-userid="', $html, 'The course list has no book buttons.');
        return $html;
    }

    /**
     * A user without the cashier capability always gets the course list for themselves.
     */
    public function test_user_id_from_url_is_ignored_for_regular_users(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $this->course->id, 'student');
        $this->setUser($user);
        $_GET['userid'] = (string)$this->otheruser->id;

        $html = $this->render_course_list();

        // The book buttons are rendered for the user who is logged in, not for the user in the url.
        $this->assertStringNotContainsString('data-userid="' . $this->otheruser->id . '"', $html);
        $this->assertStringContainsString('data-userid="' . $user->id . '"', $html);
        // The shopping cart still buys for the user who is logged in.
        $this->assertSame((int)$user->id, (int)shopping_cart::return_buy_for_userid());
        $this->assertSame(0, actforuser::$unverifiedforeignids);
    }

    /**
     * Capabilities for booking other users do not allow rendering the course list for another user.
     *
     * @dataProvider booking_capability_provider
     * @param string $capability
     */
    public function test_user_id_from_url_is_ignored_without_cashier_capability(string $capability): void {
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $this->course->id, 'student');
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability($capability, CAP_ALLOW, $roleid, context_system::instance());
        role_assign($roleid, $user->id, context_system::instance());
        $this->setUser($user);
        $_GET['userid'] = (string)$this->otheruser->id;

        $html = $this->render_course_list();

        $this->assertStringNotContainsString('data-userid="' . $this->otheruser->id . '"', $html);
        $this->assertSame((int)$user->id, (int)shopping_cart::return_buy_for_userid());
        $this->assertSame(0, actforuser::$unverifiedforeignids);
    }

    /**
     * Capabilities that allow booking for others but not acting as cashier.
     *
     * @return array
     */
    public static function booking_capability_provider(): array {
        return [
            'bookforothers' => ['mod/booking:bookforothers'],
            'bookmyteam' => ['mod/booking:bookmyteam'],
        ];
    }

    /**
     * A cashier gets the course list for the user named in the url.
     */
    public function test_user_id_from_url_is_used_for_cashiers(): void {
        $cashier = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($cashier->id, $this->course->id, 'student');
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('local/shopping_cart:cashier', CAP_ALLOW, $roleid, context_system::instance());
        role_assign($roleid, $cashier->id, context_system::instance());
        $this->setUser($cashier);
        $_GET['userid'] = (string)$this->otheruser->id;

        $html = $this->render_course_list();

        // The book buttons are rendered for the user in the url.
        $this->assertStringContainsString('data-userid="' . $this->otheruser->id . '"', $html);
        // The shopping cart buys for the user in the url.
        $this->assertSame((int)$this->otheruser->id, (int)shopping_cart::return_buy_for_userid());
        $this->assertSame(0, actforuser::$unverifiedforeignids);
    }
}
