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

use mod_booking\booking;
use mod_booking\singleton_service;

/**
 * Decides which sections of the page "My courses" (meinekurse.php) are shown.
 *
 * The sections of the current semester only list booking options of the booking instance set in
 * "shortcodessetinstance". So they are only shown when the user teaches or is responsible contact there.
 * The archive sections list every booking instance, so they are shown as soon as the user
 * taught or was responsible contact anywhere.
 *
 * @package     local_musi
 * @copyright   2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mycourses {
    /**
     * Returns the id of the booking instance of the current semester, as used by the shortcodes.
     *
     * @return int 0 if no valid booking instance is set
     */
    public static function get_current_bookingid(): int {

        $cmid = (int) get_config('local_musi', 'shortcodessetinstance');

        if (empty($cmid) || !booking::is_valid_booking_cmid($cmid)) {
            return 0;
        }

        $booking = singleton_service::get_instance_of_booking_by_cmid($cmid);

        return (int) ($booking->id ?? 0);
    }

    /**
     * Is the user teacher of at least one booking option?
     *
     * @param int $userid
     * @param int|null $bookingid only look at this booking instance, null for all booking instances
     * @return bool
     */
    public static function is_teacher(int $userid, ?int $bookingid = null): bool {
        global $DB;

        if ($bookingid === 0) {
            // There is no valid booking instance, so there can not be any booking option.
            return false;
        }

        $params = ['userid' => $userid];
        if ($bookingid !== null) {
            $params['bookingid'] = $bookingid;
        }

        return $DB->record_exists('booking_teachers', $params);
    }

    /**
     * Is the user responsible contact of at least one booking option?
     *
     * @param int $userid
     * @param int|null $bookingid only look at this booking instance, null for all booking instances
     * @return bool
     */
    public static function is_responsible_contact(int $userid, ?int $bookingid = null): bool {
        global $DB;

        if ($bookingid === 0) {
            // There is no valid booking instance, so there can not be any booking option.
            return false;
        }

        // The responsiblecontact column holds a comma separated list of user ids,
        // so we wrap it in commas to avoid matching e.g. 15 when looking for 5.
        $like = $DB->sql_like($DB->sql_concat("','", "responsiblecontact", "','"), ':responsiblecontact');
        $sql = "SELECT 1 FROM {booking_options} WHERE $like";
        $params = ['responsiblecontact' => '%,' . $userid . ',%'];

        if ($bookingid !== null) {
            $sql .= " AND bookingid = :bookingid";
            $params['bookingid'] = $bookingid;
        }

        return $DB->record_exists_sql($sql, $params);
    }
}
