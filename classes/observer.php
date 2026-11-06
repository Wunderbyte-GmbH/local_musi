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
use local_musi\local\pending_warnings;
use mod_booking\booking_option;
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
     * Observer for the payment_successful event
     */
    public static function payment_successful() {
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

        self::update_sportsdivision($optionid);

        $bookingoption = singleton_service::get_instance_of_booking_option($cmid, $optionid);
        $settings = $bookingoption->settings;

        if (!empty($settings->teachers) && get_config('local_musi', 'autoaddtosubstitutionspool')) {
            $teacherids = array_keys($settings->teachers);
            if (
                isset($settings->customfieldsfortemplates['sport'])
                && isset($settings->customfieldsfortemplates['sport']['value'])
            ) {
                $value = $settings->customfieldsfortemplates['sport']['value'];
                // Depending on the type of customfield handle differently.
                switch (gettype($value)) {
                    case "string":
                        substitutionspool_form::add_or_update_substitution_for_sport($value, $teacherids, false);
                        break;
                    case "array":
                        foreach ($value as $v) {
                            substitutionspool_form::add_or_update_substitution_for_sport($v, $teacherids, false);
                        };
                        break;
                }
            }
        }
    }

    /**
     * Writes the sports division which belongs to the sport of a booking option right after it was saved.
     *
     * An emptied sports division is filled in again. A sports division which does not match the sport
     * is replaced and the user who saved the booking option is told so with a warning on the next page.
     *
     * @param int $optionid
     * @return void
     */
    private static function update_sportsdivision(int $optionid): void {
        global $DB;

        // The cheapest checks come first, as most updates have nothing to do with sports.
        $sportnames = sports::return_sportnames_of_options($optionid)[$optionid] ?? [];
        if (empty($sportnames)) {
            return;
        }

        $fieldid = sports::return_sportsdivision_fieldid();
        if (empty($fieldid)) {
            return;
        }

        $storeddivision = $DB->get_field('customfield_data', 'value', ['fieldid' => $fieldid, 'instanceid' => $optionid]);
        $storeddivision = $storeddivision === false ? null : $storeddivision;

        $division = sports::return_division_for_sports(
            $sportnames,
            sports::return_sports_of_divisions(),
            (string)$storeddivision
        );

        // A sport without a page has no sports division, so we can not tell if the stored one is right.
        if ($division === null || $division === $storeddivision) {
            return;
        }

        sports::save_sportsdivision($optionid, $division);
        // The settings of the booking option were cached before this observer was called.
        booking_option::purge_cache_for_option($optionid);

        // Only a different sports division is worth a warning, not an empty one or another spelling.
        $oldname = sports::normalise_sportname((string)$storeddivision);
        if ($oldname !== '' && $oldname !== sports::normalise_sportname($division)) {
            $settings = singleton_service::get_instance_of_booking_option_settings($optionid);
            pending_warnings::add(get_string('sportsdivisionreplaced', 'local_musi', (object)[
                'option' => format_string($settings->get_title_with_prefix()),
                'olddivision' => s($storeddivision),
                'newdivision' => s($division),
            ]));
        }
    }
}
