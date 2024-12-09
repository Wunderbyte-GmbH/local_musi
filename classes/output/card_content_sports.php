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

use moodle_url;
use renderer_base;
use renderable;
use stdClass;
use templatable;

/**
 * This class prepares data for displaying sports categories data.
 *
 * @package local_musi
 * @copyright 2022 Wunderbyte GmbH {@link http://www.wunderbyte.at}
 * @author Georg Maißer, Bernhard Fischer
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class card_content_sports implements renderable, templatable {
    /** @var stdClass $title */
    public $data = null;

    /**
     * In the Constructor, we gather all the data we need ans store it in the data property.
     */
    public function __construct() {

        $this->data = self::return_sports_stats();
    }

    /**
     * Generate the stats for this website
     *
     * @return stdClass
     */
    private static function return_sports_stats() {
        global $DB;

        $data = new stdClass();

        $url = new moodle_url('/local/musi/sparten.php');
        $sportsdivisions = get_string('sportsdivisions', 'local_musi');
        $data->{$sportsdivisions} = ['link' => $url->out(false)];
        $data->html = ['html' => '<hr>'];

        $sportspages = $DB->get_records_sql(
            "SELECT cm.id, p.name
            FROM {page} p
            JOIN {course_modules} cm
            ON cm.instance = p.id
            JOIN {modules} m
            ON m.id = cm.module
            WHERE m.name = 'page'
            AND (p.content LIKE '%allekurse%category%'
            OR p.intro LIKE '%allekurse%category%')"
        );

        foreach ($sportspages as $sportspage) {
            $url = new moodle_url('/mod/page/view.php', ['id' => $sportspage->id]);
            $data->{$sportspage->name} = ['link' => $url->out(false)];
        }

        return $data;
    }

    /**
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {

        // Initialize.
        $returnarray = [];
        $returnarray['item'] = [];

        // We transform the data object to an array where we can read key & value.
        foreach ($this->data as $key => $value) {
            $item = [
                'key' => $key,
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
