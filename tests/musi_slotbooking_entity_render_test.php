<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_musi;

use advanced_testcase;
use context_system;
use local_musi\table\musi_table;
use local_wunderbyte_table\wunderbyte_table;
use mod_booking\bo_availability\bo_info;
use mod_booking\singleton_service;
use moodle_url;
use stdClass;

/**
 * Performance-regression tests: listing a slot-booking option (with a location/
 * entity shown) must not add DB requests to the plain list render.
 *
 * Slot booking queries availabilities, but by design that work lives in the
 * booking flow (bo_info hard_block / prepages), NOT in list rendering: the
 * slotbooking condition's get_description()/is_available() only read
 * $settings->slotconfig, and bo_info::get_condition_results() defaults
 * $onlyhardblock=false (the value the render path uses), so hard_block() and its
 * slot/entity-conflict queries never run while a list is drawn. The entity/location
 * is baked into the cached booking_option_settings (loaded once on cache miss).
 *
 * These tests pin both guarantees with Moodle's DB read counter so a future change
 * that pulls a slot/entity query into the render path is caught in CI.
 *
 * @package local_musi
 * @category test
 * @copyright 2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_booking\bo_availability\conditions\slotbooking
 * @covers \local_musi\table\musi_table::col_location
 */
final class musi_slotbooking_entity_render_test extends advanced_testcase {

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        singleton_service::destroy_instance();
        \cache_helper::purge_all();
    }

    /**
     * Create a booking instance plus $n options, each attached to a shared entity
     * (location). If $slot is true every option is also made a slot-booking option.
     *
     * @param int $n
     * @param bool $slot
     * @return array{0:int,1:int[]} [bookingid, optionids]
     */
    private function seed(int $n, bool $slot): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $booking = $this->getDataGenerator()->create_module('booking', [
            'course' => $course->id,
            'name' => 'Slot list booking',
        ]);

        $entitygenerator = self::getDataGenerator()->get_plugin_generator('local_entities');
        $entityid = $entitygenerator->create_entities([
            'name' => 'Hall ' . ($slot ? 'slot' : 'normal'),
            'shortname' => 'hall' . ($slot ? 's' : 'n'),
            'description' => 'Location',
        ]);

        /** @var \mod_booking_generator $gen */
        $gen = self::getDataGenerator()->get_plugin_generator('mod_booking');

        $optionids = [];
        for ($i = 0; $i < $n; $i++) {
            $record = (object) [
                'bookingid' => $booking->id,
                'courseid' => $course->id,
                'text' => 'Option ' . $i,
                'description' => 'Option ' . $i,
                'chooseorcreatecourse' => 0,
                'coursestarttime_0' => strtotime('now + 1 day'),
                'courseendtime_0' => strtotime('now + 2 day'),
                'maxanswers' => 5,
                'maxoverbooking' => 0,
                // Attach the location entity at option level (drives col_location).
                'local_entities_entityid_0' => $entityid,
            ];
            $option = $gen->create_option($record);
            $optionids[] = (int) $option->id;

            if ($slot) {
                // Make it a slot-booking option; defaults cover every other column.
                $DB->insert_record('booking_slot_config', (object) ['optionid' => $option->id]);
            }
        }

        // Drop the settings caches so they are rebuilt WITH the slot config / entity.
        singleton_service::destroy_instance();
        \cache_helper::purge_all();

        return [(int) $booking->id, $optionids];
    }

    /**
     * The render-time availability evaluation (bo_info::get_condition_results, the
     * call the bookit button uses) must not cost a slot-booking option any more DB
     * reads than a normal option once warm: hard_block (slot/entity queries) must
     * not run here, and the slot condition's get_description() is settings-only.
     */
    public function test_slotbooking_evaluation_adds_no_reads_over_normal_option(): void {
        global $DB;

        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();
        [, $normalids] = $this->seed(1, false);
        [, $slotids] = $this->seed(1, true);
        $normalid = reset($normalids);
        $slotid = reset($slotids);

        // Confirm the option really is slot enabled (otherwise the test is vacuous).
        $settings = singleton_service::get_instance_of_booking_option_settings($slotid);
        $this->assertNotEmpty($settings->slotconfig, 'fixture did not enable slot booking');

        $this->setUser($user);

        // Warm both evaluations (request caches / singletons / answer caches).
        bo_info::get_condition_results($normalid, (int) $user->id);
        bo_info::get_condition_results($slotid, (int) $user->id);

        $before = $DB->perf_get_reads();
        bo_info::get_condition_results($normalid, (int) $user->id);
        $normalreads = $DB->perf_get_reads() - $before;

        $before = $DB->perf_get_reads();
        bo_info::get_condition_results($slotid, (int) $user->id);
        $slotreads = $DB->perf_get_reads() - $before;

        $extra = $slotreads - $normalreads;
        $this->assertLessThan(
            2,
            $extra,
            "Evaluating a slot-booking option cost {$extra} more DB reads than a normal option "
                . "(normal={$normalreads}, slot={$slotreads}); the slot/entity availability queries "
                . "must stay in the booking flow (hard_block), not the render-time evaluation."
        );
    }

    /**
     * A warm musi list render that shows the location column must cost a constant
     * number of DB reads, whether or not the options are slot-booking options.
     */
    public function test_slot_and_entity_list_render_does_not_add_reads(): void {
        global $DB, $PAGE;

        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();

        [$normalid] = $this->seed(20, false);
        [$slotid] = $this->seed(20, true);

        $this->setUser($user);
        $PAGE->set_context(context_system::instance());

        $normal = $this->warm_location_render_reads('musinormalloc', $normalid);
        $slot = $this->warm_location_render_reads('musislotloc', $slotid);

        // The slot list must not cost materially more reads than the normal list:
        // the location comes from cached settings and the slot condition is DB-free.
        $extra = $slot - $normal;
        $this->assertLessThan(
            5,
            $extra,
            "A list of slot-booking options cost {$extra} more DB reads than the same-sized "
                . "list of normal options (normal={$normal}, slot={$slot}). Listing a slot option "
                . "with its location must not trigger an extra request."
        );
    }

    /**
     * Render a musi table with id + location columns and return the DB reads of a
     * second, fully-warm render.
     *
     * @param string $uniqueid
     * @param int $bookingid
     * @return int
     */
    private function warm_location_render_reads(string $uniqueid, int $bookingid): int {
        global $DB;
        $this->render_location_table($uniqueid, $bookingid);
        $before = $DB->perf_get_reads();
        $this->render_location_table($uniqueid, $bookingid);
        return $DB->perf_get_reads() - $before;
    }

    /**
     * Render the options of a booking instance as a musi table showing the location.
     *
     * @param string $uniqueid
     * @param int $bookingid
     */
    private function render_location_table(string $uniqueid, int $bookingid): void {
        global $PAGE;

        $table = new musi_table($uniqueid);
        $table->define_headers(['id', 'location']);
        $table->define_columns(['id', 'location']);
        // Select the full option row (as the real shortcode does) so settings built
        // from the row do not emit undefined-property notices.
        $table->set_filter_sql(
            's1.*',
            '{booking_options} s1',
            's1.bookingid = :bookingid',
            '',
            ['bookingid' => $bookingid]
        );
        $table->pageable(true);
        $table->pagesize = 200;
        $table->define_baseurl(new moodle_url('/local/wunderbyte_table/download.php'));

        $hash = $table->return_encoded_table();
        $reloaded = wunderbyte_table::instantiate_from_tablecache_hash($hash);
        if (empty($reloaded->baseurl)) {
            $reloaded->baseurl = new moodle_url('/local/wunderbyte_table/download.php');
        }
        $tableobject = $reloaded->printtable(
            $reloaded->pagesize,
            $reloaded->useinitialsbar,
            $reloaded->downloadhelpbutton
        );
        $tableobject->export_for_template($PAGE->get_renderer('local_wunderbyte_table'));
    }
}
