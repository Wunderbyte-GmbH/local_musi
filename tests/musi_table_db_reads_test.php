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
use local_musi\table\musi_table;
use local_wunderbyte_table\wunderbyte_table;
use mod_booking\singleton_service;
use moodle_url;

/**
 * Performance-regression tests pinning the number of DB reads a musi table render
 * costs, so an uncached per-row query in a column formatter is caught in CI.
 *
 * In a high-performance booking environment a per-row DB call turns one page view
 * into N queries (N = rows shown). These tests use Moodle's built-in query counter
 * ($DB->perf_get_reads()), the same pattern Moodle core uses (e.g.
 * calendar/tests/coursecat_proxy_test.php, admin/tool/usertours/tests/cache_test.php).
 *
 * Focus: musi_table::col_receipt(), which used to run one get_record_sql() per row
 * and now batch-loads the ledger once per page (see musi_table::get_latest_receipt_ledger()).
 *
 * @package local_musi
 * @category test
 * @copyright 2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_musi\table\musi_table::col_receipt
 * @covers \local_musi\table\musi_table::get_latest_receipt_ledger
 */
final class musi_table_db_reads_test extends advanced_testcase {

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        singleton_service::destroy_instance();
        \cache_helper::purge_all();
    }

    /**
     * Seed one booking instance with $n options.
     *
     * @param int $n
     * @return array{0:int,1:int[]} [bookingid, optionids]
     */
    private function seed_options(int $n): array {
        $course = $this->getDataGenerator()->create_course();
        $booking = $this->getDataGenerator()->create_module('booking', [
            'course' => $course->id,
            'name' => 'Perf booking',
        ]);

        /** @var \mod_booking_generator $gen */
        $gen = self::getDataGenerator()->get_plugin_generator('mod_booking');
        $optionids = [];
        for ($i = 0; $i < $n; $i++) {
            $option = $gen->create_option((object) [
                'bookingid' => $booking->id,
                'courseid' => $course->id,
                'text' => 'Option ' . $i,
                'description' => 'Option ' . $i,
                'chooseorcreatecourse' => 0,
                'coursestarttime_0' => strtotime('now + 1 day'),
                'courseendtime_0' => strtotime('now + 2 day'),
                'maxanswers' => 5,
                'maxoverbooking' => 0,
            ]);
            $optionids[] = (int) $option->id;
        }
        return [(int) $booking->id, $optionids];
    }

    /**
     * Render a musi table over a booking instance with the given columns, the way
     * the AJAX load_data webservice does (encode, re-instantiate, printtable()).
     *
     * @param string $uniqueid stable id so the rawdata cache is reused across renders
     * @param int $bookingid
     * @param string[] $columns
     */
    private function render_musi_table(string $uniqueid, int $bookingid, array $columns): void {
        global $PAGE;

        $table = new musi_table($uniqueid);
        $table->define_headers($columns);
        $table->define_columns($columns);
        $table->set_filter_sql(
            's1.id, s1.text, s1.bookingid',
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

    /**
     * Warm the caches for a column set, then measure the DB reads a second,
     * fully-warm render costs.
     *
     * @param string $uniqueid
     * @param int $bookingid
     * @param string[] $columns
     * @return int
     */
    private function warm_render_reads(string $uniqueid, int $bookingid, array $columns): int {
        global $DB;
        $this->render_musi_table($uniqueid, $bookingid, $columns);
        $before = $DB->perf_get_reads();
        $this->render_musi_table($uniqueid, $bookingid, $columns);
        return $DB->perf_get_reads() - $before;
    }

    /**
     * The marginal DB cost of adding the receipt column must be bounded (a per-page
     * batch load), not proportional to the number of rows.
     */
    public function test_receipt_column_marginal_cost_is_bounded(): void {
        global $PAGE;

        // Seed as admin (option field handlers need edit capabilities, otherwise
        // prepare_save_fields swallows their exceptions and writes a broken row).
        $this->setAdminUser();
        $student = $this->getDataGenerator()->create_user();
        [$bookingid] = $this->seed_options(40);

        // Measure as a plain (non-cashier) user so col_receipt reaches its lookup
        // (return_user_to_buy_for() resolves to $USER).
        $this->setUser($student);
        $PAGE->set_context(\context_system::instance());

        $base = $this->warm_render_reads('musibase', $bookingid, ['id', 'text']);
        $withreceipt = $this->warm_render_reads('musireceipt', $bookingid, ['id', 'text', 'receipt']);

        $marginal = $withreceipt - $base;
        $this->assertLessThan(
            5,
            $marginal,
            "Adding the receipt column cost {$marginal} extra DB reads over 40 rows "
                . "(base={$base}, with receipt={$withreceipt}). Expected a bounded per-page batch load, "
                . "not ~1 read/row (N+1 in col_receipt)."
        );
    }

    /**
     * A fully warm musi table render that includes the receipt column must cost a
     * constant number of DB reads, independent of the number of rows shown.
     */
    public function test_receipt_render_db_reads_do_not_scale_with_rows(): void {
        global $DB, $PAGE;

        $this->setAdminUser();
        $student = $this->getDataGenerator()->create_user();

        [$smallid] = $this->seed_options(10);
        [$largeid] = $this->seed_options(40);

        $this->setUser($student);
        $PAGE->set_context(\context_system::instance());

        $small = $this->warm_render_reads('musismall', $smallid, ['id', 'text', 'receipt']);
        $large = $this->warm_render_reads('musilarge', $largeid, ['id', 'text', 'receipt']);

        $growth = $large - $small;
        $this->assertLessThan(
            5,
            $growth,
            "Receipt-bearing render grew by {$growth} DB reads for 10 -> 40 rows "
                . "(small={$small}, large={$large}). A per-row query in col_receipt would make this scale."
        );
    }

    /**
     * Correctness guard for the batched installment lookup: a receipt whose purchase
     * history has further successful identifiers must still render all of them.
     */
    public function test_col_receipt_renders_batched_installment_identifiers(): void {
        global $DB;

        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();
        [, $optionids] = $this->seed_options(1);
        $optionid = reset($optionids);

        // One purchase history (schistoryid) with a main receipt + two installments.
        $schistoryid = 987654;
        $this->insert_ledger_row($user->id, $optionid, 1001, $schistoryid, strtotime('now'));
        $this->insert_ledger_row($user->id, $optionid, 1002, $schistoryid, strtotime('now - 10 minutes'));
        $this->insert_ledger_row($user->id, $optionid, 1003, $schistoryid, strtotime('now - 20 minutes'));

        $this->setUser($user);

        $table = new musi_table('musiinstallments');
        $html = $table->col_receipt((object) ['id' => $optionid]);

        $this->assertNotEmpty($html, 'receipt with installments rendered empty');
        // The two other identifiers of the same purchase history must appear.
        $this->assertStringContainsString('1002', $html, 'missing installment identifier 1002');
        $this->assertStringContainsString('1003', $html, 'missing installment identifier 1003');
    }

    /**
     * Insert a successful 'option' ledger row.
     *
     * @param int $userid
     * @param int $optionid ledger itemid
     * @param int $identifier
     * @param int $schistoryid
     * @param int $timecreated
     */
    private function insert_ledger_row(int $userid, int $optionid, int $identifier, int $schistoryid, int $timecreated): void {
        global $DB;
        $DB->insert_record('local_shopping_cart_ledger', (object) [
            'userid' => $userid,
            'itemid' => $optionid,
            'area' => 'option',
            'identifier' => $identifier,
            'schistoryid' => $schistoryid,
            'paymentstatus' => 2, // LOCAL_SHOPPING_CART_PAYMENT_SUCCESS.
            'usermodified' => $userid,
            'timecreated' => $timecreated,
            'timemodified' => $timecreated,
        ]);
    }
}
