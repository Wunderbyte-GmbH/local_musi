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
 *
 * @package    local_musi
 * @author     Stephan Lorbek
 * @copyright  2024 Stephan Lorbek
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_musi\task;

defined('MOODLE_INTERNAL') || die();

use coding_exception;
use core\task\scheduled_task;
use DateTime;
use dml_exception;
use local_musi\event\parsing_failed;
use local_musi\event\task_executed;

class taskrunner extends scheduled_task {
    /**
     * get_name function
     * @return string
     * @throws coding_exception
     */
    public function get_name(): string {
        return get_string('taskrunner', 'local_musi');
    }

    /**
     * execute function
     * @return void
     * @throws dml_exception|coding_exception
     */
    public function execute(): void {
        global $USER, $PAGE;

        if (!get_config("local_musi", "schedulerenable")) {
            return;
        }
        $json = json_decode(get_config("local_musi", "schedulertasks"), false);

        if (!empty(get_config("local_musi", "schedulertasks")) && $json == null) {
            $parseerrorevent = parsing_failed::create([
                'relateduserid' => $USER->id,
                'context' => $PAGE->context,
                'objectid' => $USER->id,
            ]);
            $parseerrorevent->trigger();
            return;
        }
        foreach ($json as $key => $task) {
            $configname = $task->config;
            $configscope = $task->scope;
            $configtime = $task->time;
            $configval = $task->value;
            $configtext = $task->text;

            $currenttime = new DateTime();
            $configtime = DateTime::createFromFormat('d.m.Y H:i', $configtime);

            if (
                $currenttime->format('d.m.Y H:i') == $configtime->format('d.m.Y H:i') ||
                $currenttime > $configtime
            ) {
                set_config($configname, $configval, $configscope);
                $te = task_executed::create([
                    'relateduserid' => $USER->id,
                    'context' => $PAGE->context,
                    'objectid' => $USER->id,
                    'other' => [
                        'identifier' => $configscope . '/' . $configname,
                        'message' => $configtext,
                    ],
                ]);
                $te->trigger();
                unset($json[$key]);
                set_config("schedulertasks", json_encode($json), "local_musi");
            }
        }
    }
}
