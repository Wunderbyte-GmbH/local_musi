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
 * This file contains the definition for the renderable classes for the booking instance
 *
 * @package   local_musi
 * @copyright 2021 Georg Maißer {@link http://www.wunderbyte.at}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_musi\output;

use renderer_base;
use renderable;
use stdClass;
use templatable;

defined('MOODLE_INTERNAL') || die;
require_once(__DIR__ . '/../../lib.php');

/**
 * This class prepares data for displaying a booking option instance
 *
 * @package local_musi
 * @copyright 2021 Georg Maißer {@link http://www.wunderbyte.at}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class card_content_stats1 implements renderable, templatable {

    /** @var stdClass $title */
    public $data = null;

    /**
     * In the Constructor, we gather all the data we need ans store it in the data property.
     */
    public function __construct() {

        $this->data = self::return_booking_stats();
    }

    /**
     * Generate the stats for this website
     *
     * @return stdClass
     */
    private static function return_booking_stats() {
        global $DB;

        if ($activeinstance = get_config('local_musi', 'shortcodessetinstance')) {
            $sql = "SELECT COUNT(*)
                FROM {booking_options} bo
                JOIN {course_modules} cm ON bo.bookingid=cm.instance
                JOIN {modules} m ON cm.module=m.id
                WHERE m.name='booking'
                AND cm.id=:cmid";
            $coursesavailable = $DB->count_records_sql($sql, ['cmid' => $activeinstance]);
        } else {
            $coursesavailable = 0;
        }

        $coursesbooked = $DB->count_records('booking_answers', ['waitinglist' => LOCAL_MUSI_STATUSPARAM_BOOKED]);
        $coursesincart = $DB->count_records('booking_answers', ['waitinglist' => LOCAL_MUSI_STATUSPARAM_RESERVED]);
        // M:USI does not use the normal waiting list but observer list instead.
        $bookinganswersdeleted = $DB->count_records('booking_answers', ['waitinglist' => LOCAL_MUSI_STATUSPARAM_DELETED]);

        $coursesboughtcard = $DB->count_records('local_shopping_cart_history', ['payment' => LOCAL_SHOPPING_CART_PAYMENT_SUCCESS]);
        $coursespending = $DB->count_records('local_shopping_cart_history', ['payment' => LOCAL_SHOPPING_CART_PAYMENT_PENDING]);
        $paymentsaborted = $DB->count_records('local_shopping_cart_history', ['payment' => LOCAL_SHOPPING_CART_PAYMENT_ABORTED]);

        /* We have a couple of payment methods for cashier, they are all bigger (or equal) than 3
        (LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER_CASH). */
        $sql = "SELECT COUNT(*)
            FROM {local_shopping_cart_history}
            WHERE payment >= :cashpayment";
        $params = ['cashpayment' => LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER_CASH];

        $coursesboughtcashier = $DB->count_records_sql($sql, $params);

        $data = new stdClass();
        $data->coursesavailable = ['value' => $coursesavailable];
        $data->coursesbooked = ['value' => $coursesbooked];
        $data->coursesincart = ['value' => $coursesincart];
        $data->bookinganswersdeleted = ['value' => $bookinganswersdeleted];

        $data->coursesboughtcard = ['value' => $coursesboughtcard];
        $data->coursespending = ['value' => $coursespending];
        $data->paymentsaborted = ['value' => $paymentsaborted];
        $data->coursesboughtcashier = ['value' => $coursesboughtcashier];

        return $data;
    }

    /**
     * Export for template.
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {

        // We transform the data object to an array where we can read key & value.
        foreach ($this->data as $key => $value) {

            $item = [
                'key' => get_string($key, 'local_musi'),
            ];

            // We only have value & link at the time as types, but might have more at one point.
            foreach ($value as $type => $name) {
                $item[$type] = $name;
            }

            $returnarray['item'][] = $item;
        }

        return $returnarray;
    }
}
