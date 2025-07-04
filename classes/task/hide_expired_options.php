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

/**
 * Scheduled task to hide expired options
 * @package local_musi
 */
class hide_expired_options extends scheduled_task {
    /**
     * Get name of the task.
     * @return string
     */
    public function get_name() {
        return get_string('hide_expired_options', 'local_musi');
    }

    /**
     * Scheduled task that checks for expired options ad hides them.
     *
     */
    public function execute() {
        global $DB;
        $expiredoptions = $DB->get_records_sql(
                    "SELECT id, text, lastdate, mbo_courseendtime, GREATEST(lastdate, mbo_courseendtime) AS latest_end
                    FROM (
                    SELECT
                        mbo.id,
                        mbo.text,
                        MAX(mbo2.courseendtime) AS lastdate,
                        mbo.courseendtime AS mbo_courseendtime
                    FROM m_booking_options mbo
                    LEFT JOIN m_booking_optiondates mbo2 ON (mbo.id = mbo2.bookingid)
                    GROUP BY mbo.id, mbo.text, mbo.courseendtime) t
                    WHERE GREATEST(lastdate, mbo_courseendtime) < EXTRACT(EPOCH FROM NOW())", []);

        foreach ($expiredoptions as $option) {
            $DB->set_field('booking_options', 'invisible', 2, ['id' => $option->id]);
        }
        cache_helper::purge_by_event('setbackoptionstable');
        cache_helper::purge_by_event('setbackoptionsettings');
    }
}
