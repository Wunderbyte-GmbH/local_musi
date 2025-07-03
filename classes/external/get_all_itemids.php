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
 * This class contains a list of webservice functions related to the Shopping Cart Module by Wunderbyte.
 *
 * @package    local_musi
 * @copyright  2022 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_musi\external;

use context_system;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_value;
use external_single_structure;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * External Service for shopping cart.
 *
 * @package   local_musi
 * @copyright 2022 Wunderbyte GmbH {@link http://www.wunderbyte.at}
 * @author    Georg Maißer
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_all_itemids extends external_api {
    /**
     * Describes the paramters for add_item_to_cart.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'component'  => new external_value(PARAM_RAW, 'component', VALUE_DEFAULT),
            'area'  => new external_value(PARAM_RAW, 'area', VALUE_DEFAULT),
            ]);
    }

    /**
     * Webservice for shopping_cart class to add a new item to the cart.
     *
     * @param string $component
     * @param string $area
     *
     * @return array
     */
    public static function execute(string $component, string $area): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'component' => $component,
            'area' => $area,
        ]);

        require_login();

        $returnarray = [];

        // This webservice should only be usable by admin users.
        $context = context_system::instance();
        if (!has_capability('local/shopping_cart:cashier', $context)) {
            throw new moodle_exception('norighttoaccess', 'local_musi');
        }
        if (!$instanceid = get_config('local_musi', 'shortcodessetinstance')) {
            return $returnarray;
        }

        $sql = "SELECT bo.id, bo.text
                FROM {booking_options} bo
                JOIN {course_modules} cm ON bo.bookingid=cm.instance
                JOIN {modules} m ON cm.module=m.id
                WHERE m.name='booking'
                AND cm.id=:cmid";
        $params = ['cmid' => $instanceid];

        $records = $DB->get_records_sql($sql, $params);

        foreach ($records as $record) {
            $returnarray[] = [
                'id' => $record->id,
                'name' => $record->text,
            ];
        }
        return $returnarray;
    }

    /**
     * Returns array of items.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure(
                [
                    'id' => new external_value(PARAM_INT, 'Item id'),
                    'name' => new external_value(PARAM_TEXT, 'Item name'),
                ]
            )
        );
    }
}
