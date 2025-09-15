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

namespace local_musi\table;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/../../lib.php');
require_once($CFG->libdir . '/tablelib.php');

use cache_helper;
use local_shopping_cart\interfaces\interface_transaction_complete;
use local_wunderbyte_table\output\table;
use local_wunderbyte_table\wunderbyte_table;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

/**
 * Definitions for transactionstable iteration of wb_table
 * @package local_musi
 */
class musi_transactions_table extends wunderbyte_table {
    /**
     * Changes integer values from status to meaningful strings
     *
     * @param  mixed $values contains all data for that row
     * @return string
     */
    public function col_status($values) {
        // TODO: Comment cases.
        switch ($values->status) {
            case 0:
                return get_string('openorder', 'local_musi');
            case 1:
                return '1';
            case 2:
                return '2';
            case 3:
                return get_string('bookedorder', 'local_musi');
        }
    }

    /**
     * Manipulates price column and and adds Currency to amount.
     *
     * @param  mixed $values contains all data for that row
     * @return string
     */
    public function col_price($values) {
        return $values->price . ' €';
    }

    /**
     * Returns full name for userid.
     *
     * @param  mixed $values contains all data for that row
     * @return string
     */
    public function col_userid($values) {

        return "$values->firstname $values->lastname";
    }

    /**
     * Prepares action button and passes data to update_status function.
     *
     * @param  mixed $values contains all data for that row
     * @return string
     */
    public function col_action($values) {

        global $OUTPUT;

        // We have to use the id as integer - the update_status function will use the right gateway anyway.
        $rowid = (int) explode(' ', $values->id)[1];

        $data[] = [
            'label' => 'überprüfe Status', // Name of your action button.
            'class' => 'btn btn-warning',
            'href' => '#', // You can either use the link, or JS, or both.
            'iclass' => 'fa fa-edit', // Add an icon before the label.
            'id' => $rowid,
            'methodname' => 'update_status', // The method needs to be added to your child of wunderbyte_table class.
            'nomodal' => true,
            'data' => [
                'itemid' => $values->itemid,
                'orderid' => $values->tid,
                'userid' => $values->userid,
                'gateway' => $values->gateway,
            ],
        ];
        table::transform_actionbuttons_array($data);

        if ($values->status == 0) {
            return $OUTPUT->render_from_template('local_wunderbyte_table/component_actionbutton', ['showactionbuttons' => $data]);
        }
    }

    /**
     * This function is called for each data row to allow processing of the
     * 'timecreated' value.
     *
     * @param object $values Contains object with all the values of record.
     * @return string Rendered date.
     * @throws dml_exception
     */
    public function col_timecreated(object $values): string {
        $rendereddate = '';

        if ($this->is_downloading()) {
            $rendereddate = date('Y-m-d H:i:s', $values->timecreated);
        } else if (current_language() === 'de') {
            $rendereddate = date('d.m.Y H:i:s', $values->timecreated);
        } else {
            $rendereddate = date('Y-m-d H:i:s', $values->timecreated);
        }

        return $rendereddate;
    }

    /**
     * This function is called for each data row to allow processing of the
     * 'timemodified' value.
     *
     * @param object $values Contains object with all the values of record.
     * @return string Rendered date.
     * @throws dml_exception
     */
    public function col_timemodified(object $values): string {
        $rendereddate = '';

        if (empty($values->timemodified)) {
            $values->timemodified = $values->timecreated;
        }

        if ($this->is_downloading()) {
            $rendereddate = date('Y-m-d H:i:s', $values->timemodified);
        } else if (current_language() === 'de') {
            $rendereddate = date('d.m.Y H:i:s', $values->timemodified);
        } else {
            $rendereddate = date('Y-m-d H:i:s', $values->timemodified);
        }

        return $rendereddate;
    }

    /**
     * Tries to verify a transaction and delivers order if successful
     *
     * @param integer $id
     * @param string $data
     * @return array
     */
    public function action_update_status(int $id, string $data): array {

        $data = json_decode($data);

        try {
            $transactioncompletestring = 'paygw_' . $data->gateway . '\external\transaction_complete';
            if (class_exists($transactioncompletestring)) {
                try {
                    $transactioncomplete = new $transactioncompletestring();
                    if ($transactioncomplete instanceof interface_transaction_complete) {
                        $result = $transactioncomplete::execute(
                            'local_shopping_cart', // Component.
                            '', // Paymentarea.
                            (int) $data->itemid, // Itemid.
                            $data->orderid, // Tid.
                            '', // Token.
                            '', // Customer.
                            true, // Ischeckstatus.
                            '', // Resourcepath.
                            $data->userid ?? 0, // Userid.
                        );
                    } else {
                        throw new moodle_exception(
                            'ERROR: transaction_complete does not implement transaction_complete interface!'
                        );
                    }
                } catch (\Throwable $e) {
                    echo($e);
                }
            }
        } catch (\Exception $e) {
            // Transaction could not be verified.
            return [
                'success' => 0,
                'message' => get_string('statusnotchanged', 'local_musi') . " : " . $e->getMessage(),
            ];
        }
        // Delete cache if successfull -> data has been changed.
        if ($result['success'] == true) {
            cache_helper::purge_by_event('setbackcachedpaymenttable');
            return [
                'success' => 1,
                'message' => get_string('statuschanged', 'local_musi'),
            ];
        } else {
            return [
                'success' => 0,
                'message' => get_string('statusnotchanged', 'local_musi') . " : " . $result['message'],
            ];
        }
    }
}
