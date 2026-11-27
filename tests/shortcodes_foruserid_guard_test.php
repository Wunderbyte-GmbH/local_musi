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
use local_shopping_cart\shopping_cart;
use mod_booking\singleton_service;
use moodle_url;
use ReflectionMethod;

/**
 * Tests the security gate for rendering musi tables on behalf of another user.
 *
 * A user id taken from the request (eg. ?userid=123) must only be honoured when the
 * current user is a cashier, is allowed to book for others or is a team booker.
 *
 * @package local_musi
 * @category test
 * @copyright 2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class shortcodes_foruserid_guard_test extends advanced_testcase {
    /**
     * Tests set up.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        singleton_service::destroy_instance();
        unset($_GET['userid'], $_GET['_buyforuser_']);
    }

    /**
     * Mandatory clean-up after each test.
     */
    public function tearDown(): void {
        unset($_GET['userid'], $_GET['_buyforuser_']);
        parent::tearDown();
    }

    /**
     * Assign a single capability to a user in the system context.
     *
     * @param int $userid
     * @param string $capability
     * @return void
     */
    private function grant_capability(int $userid, string $capability): void {
        $syscontext = context_system::instance();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability($capability, CAP_ALLOW, $roleid, $syscontext->id);
        role_assign($roleid, $userid, $syscontext->id);
    }

    /**
     * An unauthorised caller must not be able to act for another user, neither via the url param
     * nor when the 'foruserid' argument points to somebody else.
     *
     * @covers \local_musi\shortcodes::get_authorised_foruserid
     * @return void
     */
    public function test_foreign_userid_rejected_without_capability(): void {
        $caller = $this->getDataGenerator()->create_user();
        $victim = $this->getDataGenerator()->create_user();
        $this->setUser($caller);

        $_GET['userid'] = (string) $victim->id;

        $this->assertSame(0, shortcodes::get_authorised_foruserid(['urlparamforuserid' => 'userid']));
        $this->assertSame(0, shortcodes::get_authorised_foruserid(['foruserid' => $victim->id]));
        // Without opt-in, the request parameter is not read at all.
        $this->assertSame(0, shortcodes::get_authorised_foruserid([]));
    }

    /**
     * The own user id needs no capability.
     *
     * @covers \local_musi\shortcodes::get_authorised_foruserid
     * @return void
     */
    public function test_own_userid_allowed_without_capability(): void {
        $caller = $this->getDataGenerator()->create_user();
        $this->setUser($caller);

        $_GET['userid'] = (string) $caller->id;

        $this->assertSame(
            (int) $caller->id,
            shortcodes::get_authorised_foruserid(['urlparamforuserid' => 'userid'])
        );
    }

    /**
     * Cashiers, users who may book for others and team bookers still get the foreign user id.
     *
     * @dataProvider capability_provider
     * @covers \local_musi\shortcodes::get_authorised_foruserid
     * @param string $capability
     * @return void
     */
    public function test_foreign_userid_resolved_with_capability(string $capability): void {
        $caller = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->grant_capability((int) $caller->id, $capability);
        $this->setUser($caller);

        $_GET['userid'] = (string) $other->id;

        $this->assertSame(
            (int) $other->id,
            shortcodes::get_authorised_foruserid(['urlparamforuserid' => 'userid'])
        );
        $this->assertSame((int) $other->id, shortcodes::get_authorised_foruserid(['foruserid' => $other->id]));
    }

    /**
     * Capabilities which allow to act for another user.
     *
     * @return array
     */
    public static function capability_provider(): array {
        return [
            'cashier' => ['local/shopping_cart:cashier'],
            'bookforothers' => ['mod/booking:bookforothers'],
            'bookmyteam' => ['mod/booking:bookmyteam'],
        ];
    }

    /**
     * The table initialisation must not switch the "buy for user" of shopping cart
     * to a foreign user for an unauthorised caller.
     *
     * @covers \local_musi\shortcodes::inittableforcourses
     * @return void
     */
    public function test_inittable_does_not_buy_for_foreign_user_without_capability(): void {
        global $PAGE;

        $caller = $this->getDataGenerator()->create_user();
        $victim = $this->getDataGenerator()->create_user();
        $this->setUser($caller);
        $PAGE->set_url(new moodle_url('/local/musi/dashboard.php'));

        $_GET['userid'] = (string) $victim->id;

        $method = new ReflectionMethod(shortcodes::class, 'inittableforcourses');
        $method->invoke(null, ['urlparamforuserid' => 'userid']);

        $this->assertSame((int) $caller->id, (int) shopping_cart::return_buy_for_userid());
    }

    /**
     * For a cashier, the table initialisation still switches the "buy for user".
     *
     * @covers \local_musi\shortcodes::inittableforcourses
     * @return void
     */
    public function test_inittable_buys_for_foreign_user_as_cashier(): void {
        global $PAGE;

        $cashier = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->grant_capability((int) $cashier->id, 'local/shopping_cart:cashier');
        $this->setUser($cashier);
        $PAGE->set_url(new moodle_url('/local/musi/dashboard.php'));

        $_GET['userid'] = (string) $other->id;

        $method = new ReflectionMethod(shortcodes::class, 'inittableforcourses');
        $method->invoke(null, ['urlparamforuserid' => 'userid']);

        $this->assertSame((int) $other->id, (int) shopping_cart::return_buy_for_userid());
    }
}
