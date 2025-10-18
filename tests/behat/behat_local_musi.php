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
 * Defines message providers (types of messages being sent)
 *
 * @package local_musi
 * @copyright 2025 Wunderbyte GmbH <info@wunderbyte.at>
 * @author Andrii Semenets
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\Mink\Exception\DriverException;
use mod_booking\booking;
use mod_booking\singleton_service;
use mod_booking\booking_rules\booking_rules;
use mod_booking\booking_rules\rules_info;
use mod_booking\bo_availability\conditions\maxoptionsfromcategory;
use Behat\Gherkin\Node\TableNode;

/**
 * To create booking specific behat scearios.
 */
class behat_local_musi extends behat_base {
    /**
     * Create booking option in booking instance
     * @Given /^I set shortcodessetinstance for musi to "(?P<instancename_string>(?:[^"]|\\")*)"$/
     * @param string $optionname
     * @param string $instancename
     * @return void
     */
    public function i_set_shortcodessetinstance_for_musi_to($instancename) {

        $booking = $this->get_booking_by_name($instancename);
        set_config('shortcodessetinstance', $booking->id, 'local_musi');
    }

    /**
     * Get a booking by booking instance name.
     *
     * @param string $name booking instance name.
     * @return stdClass the corresponding DB row.
     */
    protected function get_booking_by_name(string $name): stdClass {
        global $DB;
        return $DB->get_record('booking', ['name' => $name], '*', MUST_EXIST);
    }

    /**
     * Get a booking coursemodule object from the name.
     *
     * @param string $name name.
     * @return stdClass cm from get_coursemodule_from_instance.
     */
    protected function get_cm_by_booking_name(string $name): stdClass {
        $booking = $this->get_booking_by_name($name);
        return get_coursemodule_from_instance('booking', $booking->id, $booking->course);
    }
}
