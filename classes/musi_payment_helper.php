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

namespace local_musi;

use context_system;

/**
 * Helper functions for payment stuff.
 *
 * @package local_musi
 * @copyright 2023 Wunderbyte GmbH <info@wunderbyte.at>
 * @author Bernhard Fischer
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class musi_payment_helper {

    /**
     * Helper function to get name of the orderid column for each gateway table.
     * We currently do not need it here, but we'll need it later, so keep it!
     *
     * @param string $gwname the name of the gateway
     * @return string the name of the orderid column
     */
    public static function get_name_of_orderid_column(string $gwname): string {
        switch ($gwname) {
            case 'paypal':
                return 'pp_orderid';
            case 'mpay24':
                return 'mpay24_orderid';
            case 'payunity':
                return 'pu_orderid';
        }
    }

    /**
     * Get enabled payment gateways having an openorders tables.
     * This means they are fully supported by M:USI.
     *
     * @return array    an array of strings containing the gateway names
     *                  of payment accounts which are supported
     */
    public static function get_supported_payment_gateways(): array {

        global $DB;
        $dbman = $DB->get_manager();

        $supportedgateways = [];

        // We need the accounts to run through all the gateways.
        $accounts = \core_payment\helper::get_payment_accounts_to_manage(context_system::instance());
        foreach ($accounts as $account) {

            foreach ($account->get_gateways() as $gateway) {

                if (empty($gateway->get('enabled'))) {
                    continue;
                }

                $name = $gateway->get('gateway');

                // Check if there is an openorders table. If not, the gateway is not supported by transactions list.
                $table = "paygw_" . $name . "_openorders";
                if ($dbman->table_exists($table)) {
                    $supportedgateways[] = $name;
                }
            }
        }

        return $supportedgateways;
    }

    /**
     * Get payment accounts without openorders tables.
     * This means they are not yet fully supported by M:USI.
     *
     * @return array    an array of strings containing the gateway names
     *                  of payment accounts which are not fully supported
     */
    public static function get_unsupported_payment_gateways(): array {

        global $DB;
        $dbman = $DB->get_manager();

        $unsupportedgateways = [];

        // We need the accounts to run through all the gateways.
        $accounts = \core_payment\helper::get_payment_accounts_to_manage(context_system::instance());
        foreach ($accounts as $account) {

            foreach ($account->get_gateways() as $gateway) {

                $name = $gateway->get('gateway');

                // Check if there is an openorders table. If not, the gateway is not supported by transactions list.
                $table = "paygw_" . $name . "_openorders";
                if (!$dbman->table_exists($table)) {
                    $unsupportedgateways[] = $name;
                }
            }
        }

        return $unsupportedgateways;
    }

}
