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
 * This file contains the definition for the renderable classes for transactions list
 *
 * @package   local_musi
 * @copyright 2023 Christian Badusch {@link http://www.wunderbyte.at}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_musi\output;

use local_musi\table\musi_transactions_table;
use renderable;
use renderer_base;
use templatable;
use stdClass;
use local_musi\musi_payment_helper;
use local_wunderbyte_table\filters\types\standardfilter;

/**
 * This class prepares to data to render transactionstable in mustache template
 */
class transactionslist implements renderable, templatable {

    /**
     * @var array $tabledata Holds the data for rendering the transactions table.
     */
    private $tabledata = [];

    /**
     * Constructs a new instance of the transactionslist class.
     *
     * This constructor initializes the transactionslist object and can set any initial
     * state that is needed for preparing the data to render in a mustache template.
     */
    public function __construct() {
        global $DB;

        // Create instance of transactions wb_table and specify columns and headers.
        $table = new musi_transactions_table('musi_transactions_table');

        // Headers.
        $table->define_headers([
            get_string('id', 'local_musi'),
            get_string('timecreated', 'local_shopping_cart'),
            get_string('timemodified', 'local_shopping_cart'),
            get_string('transactionid', 'local_musi'),
            get_string('itemid', 'local_musi'),
            get_string('merchantref', 'local_musi'),
            get_string('customorderid', 'local_musi'),
            get_string('username', 'local_musi'),
            get_string('price', 'local_musi'),
            get_string('gateway', 'local_musi'),
            get_string('status', 'local_musi'),
            get_string('names', 'local_musi'),
            get_string('action', 'local_musi'),
        ]);

        // Columns.
        $table->define_columns([
            'id',
            'timecreated',
            'timemodified',
            'tid',
            'itemid',
            'merchantref',
            'customorderid',
            'username',
            'price',
            'gateway',
            'status',
            'names',
            'action',
        ]);

        // Pass SQL to table.
        // phpcs:ignore moodle.Commenting.TodoComment.MissingInfoInline
        // TODO: Add functionality for other providers.
        list($fields, $from, $where) = self::return_all_sql_transaction();

        $table->set_filter_sql($fields, $from, $where, '');

        $table->sortable(true, 'timecreated', SORT_DESC);

        // Define Filters.
        $standardfilter = new standardfilter('status', get_string('status', 'local_musi'));
        $standardfilter->add_options([
            '0' => get_string('openorder', 'local_musi'),
            '3' => get_string('bookedorder', 'local_musi'),
        ]);
        $table->add_filter($standardfilter);

        // Full text search columns.
        $table->define_fulltextsearchcolumns(['id', 'timecreated', 'timemodified', 'tid', 'itemid',
            'merchantref', 'customorderid', 'username', 'price', 'gateway', 'status', 'names']);

        // Sortable columns.
        $table->define_sortablecolumns(['id', 'timecreated', 'timemodified', 'tid', 'itemid',
            'merchantref', 'customorderid', 'username', 'price', 'gateway', 'status', 'names']);

        $table->define_cache('local_musi', 'cachedpaymenttable');

        $table->pageable(true);

        // Pass html to render.
        list($idstring, $encodedtable, $html) = $table->lazyouthtml(50, true);
        $this->tabledata = $html;
        // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
        // Not lazy laod : $this->tabledata = $table->outhtml(20, true).

    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        $data = new stdClass();
        $data->table = $this->tabledata;
        return $data;
    }

     /**
      * Return SQL Query in correct format for wb_table
      *
      * @return array
      */
    private static function return_all_sql_transaction(): array {
        global $DB;

        $concatsql = $DB->sql_group_concat("sch.itemname", "<br>", "sch.itemname");
        $concatusername = $DB->sql_fullname("u.lastname", "u.firstname");

        $gatewaynames = musi_payment_helper::get_supported_payment_gateways();
        $gatewayselectstring = "";

        foreach ($gatewaynames as $gwname) {

            // For some gateways, we store a merchantref or a customorderid in the openorders table.
            // So check if columns merchantref or customorderid exist.
            $dbman = $DB->get_manager();
            $openorderstable = "paygw_" . $gwname . "_openorders";
            $merchantrefselector = "NULL AS merchantref";
            $customorderidselector = "NULL AS customorderid";
            if ($dbman->table_exists($openorderstable)) {
                $openorderscols = $DB->get_columns($openorderstable);
                foreach ($openorderscols as $key => $value) {
                    if (strpos($key, 'merchantref') !== false) {
                        $merchantrefselector = "$gwname.merchantref";
                    }
                    if (strpos($key, 'customorderid') !== false) {
                        $customorderidselector = "$gwname.customorderid";
                    }
                }
            }

            $gwselect = "SELECT " .
                $DB->sql_concat("'" . "{$gwname} " . "'", "$gwname.id") . " AS id,
                $gwname.tid,
                $gwname.itemid,
                $gwname.userid,
                $gwname.price,
                $gwname.status,
                $gwname.timecreated,
                $gwname.timemodified,
                $merchantrefselector,
                $customorderidselector,
                $concatusername AS username,
                '{$gwname}' as gateway,
                $concatsql AS names
            FROM
            {paygw_{$gwname}_openorders} $gwname
            LEFT JOIN {local_shopping_cart_history} sch
            ON $gwname.itemid = sch.identifier AND $gwname.userid=sch.userid
            LEFT JOIN {user} u
            ON u.id = $gwname.userid
            GROUP BY $gwname.id, u.firstname, u.lastname";

            if ($gatewayselectstring === '') {
                $gatewayselectstring = '(' . $gwselect;
            } else {
                $gatewayselectstring = $gatewayselectstring . ' UNION ' . $gwselect;
            }
        }

        $fields = '*';
        $from = $gatewayselectstring . ') as s1';
        $where = "1 = 1";

        return [$fields, $from, $where];
    }
}
