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
use mod_booking\customfield\booking_handler;

/**
 * Scheduled task creates SAP files in Moodle data directory.
 * @package local_musi
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
     * Scheduled task that creates the SAP files needed for reporting.
     *
     */
    public function execute() {

        // First we check if there is a field sportsdivision at all.
        $handler = booking_handler::create();
        $categories = $handler->get_fields();

        $return = true;
        foreach ($categories as $category) {
            $name = $category->get('shortname');
            if ($name == 'sportsdivision') {
                $return = false;
            }
        }

        if ($return) {
            return;
        }

        // Are there sport divisions in use?
        $courseid = sports::return_courseid();

        if (empty($courseid)) {
            return;
        }

        $data = sports::get_all_sportsdivisions_data($courseid, false);

        // Get List of all booking options which use a particular sport.
        foreach ($data["categories"] as $category) {
            foreach ($category["sports"] as $sport) {
                $boids = sports::return_list_of_boids_with_sport($sport['name']);

                foreach ($boids as $boid) {
                    $instance = (object)[
                        'id' => $boid->id,
                        'customfield_sportsdivision' => $category['name'],
                    ];
                    $handler->instance_form_save($instance);
                }
            }
        }
        // Important: Purge caches here!
        cache_helper::purge_by_event('setbackoptionstable');
        cache_helper::purge_by_event('setbackoptionsettings');
    }
}
