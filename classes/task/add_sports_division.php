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

namespace local_musi\task;

use cache_helper;
use core\task\scheduled_task;
use local_musi\sports;

/**
 * Scheduled task which writes the sports division (customfield "sportsdivision") of the booking options.
 *
 * The sports division of a sport is the section its page is in, see the shortcode [sparten].
 *
 * @package   local_musi
 * @copyright 2022 Wunderbyte GmbH <info@wunderbyte.at>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_sports_division extends scheduled_task {
    /**
     * Get name of the task.
     * @return string
     */
    public function get_name() {
        return get_string('add_sports_division', 'local_musi');
    }

    /**
     * Looks up the sports division of the sports of every booking option and saves it, where it differs.
     *
     * Booking options saved in the form get their sports division right away, see
     * {@see \local_musi\observer::bookingoption_updated()}. The task takes care of everything else:
     * imported booking options, renamed or moved sport pages and new sports divisions.
     */
    public function execute() {
        global $DB;

        // First we check if there is a field sportsdivision at all.
        $fieldid = sports::return_sportsdivision_fieldid();
        if (empty($fieldid)) {
            return;
        }

        // Are there sport divisions in use?
        $sportsofdivisions = sports::return_sports_of_divisions();
        if (empty($sportsofdivisions)) {
            return;
        }

        // The sports divisions as they are stored right now. Only options with another value are saved.
        $storeddivisions = $DB->get_records_menu('customfield_data', ['fieldid' => $fieldid], '', 'instanceid, value');

        $changed = false;
        foreach (sports::return_sportnames_of_options() as $optionid => $sportnames) {
            $storeddivision = $storeddivisions[$optionid] ?? null;
            $division = sports::return_division_for_sports($sportnames, $sportsofdivisions, (string)$storeddivision);

            // A sport without a page has no sports division. We do not touch these booking options.
            if ($division === null || $division === $storeddivision) {
                continue;
            }

            sports::save_sportsdivision($optionid, $division);
            $changed = true;
        }

        if ($changed) {
            // Important: Purge caches here!
            cache_helper::purge_by_event('setbackoptionstable');
            cache_helper::purge_by_event('setbackoptionsettings');
        }
    }
}
