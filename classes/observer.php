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
 * Event observers.
 *
 * @package     local_musi
 * @copyright   2023 Wunderbyte GmbH <info@wunderbyte.at>
 * @author      Bernhard Fischer
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_musi;

use cache_helper;
use local_musi\form\substitutionspool_form;
use mod_booking\singleton_service;

/**
 * Event observer for local_musi.
 */
class observer {

    /**
     * Observer for the payment_added event
     */
    public static function payment_added() {
        cache_helper::purge_by_event('setbackcachedpaymenttable');
    }

    /**
     * Observer for the payment_completed event
     */
    public static function payment_completed() {
        cache_helper::purge_by_event('setbackcachedpaymenttable');
    }

    /**
     * Observer for the bookingoption_updated event
     *
     * @param \mod_booking\event\bookingoption_updated $event
     *
     * @return void
     *
     */
    public static function bookingoption_updated(\mod_booking\event\bookingoption_updated $event) {
        $optionid = $event->objectid;
        $cmid = $event->contextinstanceid;

        $bookingoption = singleton_service::get_instance_of_booking_option($cmid, $optionid);
        $settings = $bookingoption->settings;

        if (!empty($settings->teachers) && get_config('local_musi', 'autoaddtosubstitutionspool')) {
            $teacherids = array_keys($settings->teachers);
            if (isset($settings->customfieldsfortemplates['sport']) && isset ($settings->customfieldsfortemplates['sport']['value'])) {
                $value = $settings->customfieldsfortemplates['sport']['value'];
                substitutionspool_form::add_or_update_substitution_for_sport($value, $teacherids, false);
            }
        }
    }
}
