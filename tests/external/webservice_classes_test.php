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
 * The web service classes of local_musi load without deprecation notices.
 *
 * @package    local_musi
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_musi\external;

use advanced_testcase;
use core_external\external_api;
use core_external\external_description;
use core_external\external_function_parameters;

/**
 * The web service classes of local_musi load without deprecation notices.
 *
 * Pages like Site administration > Server > Web services > API documentation describe every
 * registered web service function: they load its class and call execute_parameters() and
 * execute_returns(). A class that still names the global external_* classes from before Moodle 4.2
 * makes the autoloader emit a debugging notice there since Moodle 5.3 (MDL-81225).
 *
 * The test runs in a separate process: the autoloader creates the alias for an old class name only
 * once per process, so a test that ran earlier could hide the notice.
 *
 * @covers \local_musi\external\get_all_itemids
 * @runTestsInSeparateProcesses
 */
final class webservice_classes_test extends advanced_testcase {
    /**
     * Every function of db/services.php can be described without a debugging notice.
     */
    public function test_functions_are_described_without_debugging(): void {
        global $CFG;
        $this->resetAfterTest();

        $functions = [];
        require($CFG->dirroot . '/local/musi/db/services.php');
        $this->assertNotEmpty($functions);

        foreach (array_keys($functions) as $name) {
            $info = external_api::external_function_info($name);
            $this->assertInstanceOf(external_function_parameters::class, $info->parameters_desc, $name);
            $this->assertInstanceOf(external_description::class, $info->returns_desc, $name);
        }

        $this->assertDebuggingNotCalled();
    }
}
