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

/**
 * Contract manager class.
 *
 * @package local_musi
 * @copyright 2022 Wunderbyte GmbH <info@wunderbyte.at>
 * @author Bernhard Fischer
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class contractmanager {
    /**
     * Calculate the hourly rate of a teacher for a certain booking option
     * using the contract formula.
     * If an error occurs (e.g. profile field not found), this function will always return 0.
     *
     * @param int $userid the user for which we want to get the hourly rate
     * @return float the user's hourly rate
     */
    public static function get_hourrate(int $userid) {

        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/profile/lib.php'); // Needed for profile_load_data.

        $hourrate = 0.0; // Default value.

        if (!$user = $DB->get_record('user', ['id' => $userid])) {
            return (float) 0.0;
        }

        // Load custom user profile fields for user.
        profile_load_data($user);

        $contractformula = get_config('local_musi', 'contractformula');

        if (!$jsonobject = json_decode($contractformula)) {
            // We return an hour rate of 0 if the formula is invalid.
            return (float) 0.0;
        }

        foreach ($jsonobject as $formulacomponent) {
            // For invalid JSON.
            if (is_string($formulacomponent)) {
                // We return an hour rate of 0 if the formula is invalid.
                return (float) 0.0;
            }

            $key = key($formulacomponent);
            $value = $formulacomponent->$key;

            if ($key === 'customfield') {
                foreach ($value as $cfval) {
                    if (
                        empty($cfval->hourrate)
                        || empty($cfval->value)
                        || empty($user->{'profile_field_' . $cfval->name})
                    ) {
                        continue;
                    }
                    if ($user->{'profile_field_' . $cfval->name} === $cfval->value) {
                        return (float) $cfval->hourrate;
                    }
                }
            }
        }
        return $hourrate;
    }
}
