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
 * Functions for SAP text files (daily SAP sums for M:USI).
 *
 * @package local_musi
 * @since Moodle 4.0
 * @copyright 2022 Wunderbyte GmbH <info@wunderbyte.at>
 * @author Bernhard Fischer
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_musi;

use context_system;
use core_payment\account;
use moodle_exception;
use stdClass;
use stored_file;

/**
 * Deals with local_shortcodes regarding booking.
 */
class sap_daily_sums {
    /**
     * Helper function to create SAP text files for M:USI.
     *
     * @param int $daytimestamp timestamp of day to generate the SAP text file for
     * @return array [$content, $errorcontent] file content and content of the error file
     */
    private static function generate_sap_text_file_for_date(int $daytimestamp): array {
        global $DB;

        $day = date('Y-m-d', $daytimestamp);
        $filename = 'SAP_USI_' . date('Ymd', $daytimestamp);

        $startofday = strtotime($day . ' 00:00');
        $endofday = strtotime($day . ' 24:00');

        // Get payment account from settings.
        $accountid = get_config('local_shopping_cart', 'accountid');
        $account = null;
        if (!empty($accountid)) {
            $account = new account($accountid);
        }

        // Create selects for each payment gateway.
        $colselects = [];
        // Create an array of table names for the payment gateways.
        if (!empty($account)) {
            foreach ($account->get_gateways() as $gateway) {
                $gwname = $gateway->get('gateway');
                if ($gateway->get('enabled')) {
                    $tablename = "paygw_" . $gwname;

                    $cols = $DB->get_columns($tablename);
                    // Only use tables with exactly 5 columns: id, paymentid, <pgw>_orderid, paymentbrand, pboriginal.
                    if (count($cols) <> 5) {
                        continue;
                    }

                    // Generate a select for each table.
                    // Only do this, if an orderid exists.
                    foreach ($cols as $key => $value) {
                        if (strpos($key, 'orderid') !== false) {
                            $colselects[] =
                                "SELECT
                                    $gwname.paymentid,
                                    $gwname.$key AS orderid,
                                    $gwname.paymentbrand
                                FROM {paygw_$gwname} $gwname";
                        }
                    }
                }
            }
        }

        $selectorderidpart = "";
        $gatewayspart = "";
        if (!empty($colselects)) {
            $selectorderidpart = ", pgw.orderid, pgw.paymentbrand";
            $colselectsstring = implode(' UNION ', $colselects);
            $gatewayspart = "JOIN ($colselectsstring) pgw ON p.id = pgw.paymentid";
        }

        $sql = "SELECT
                    p.id, p.itemid AS identifier, p.amount AS price, p.currency,
                    p.userid, u.lastname, u.firstname, u.email,
                    p.timecreated, p.timemodified,
                    p.gateway$selectorderidpart
                FROM {payments} p
                LEFT JOIN {user} u
                ON u.id = p.userid
                $gatewayspart
                WHERE p.timecreated BETWEEN :startofday AND :endofday
                AND p.component = 'local_shopping_cart'
                AND p.itemid <> 0";

        $params = [
            'startofday' => $startofday,
            'endofday' => $endofday,
        ];

        $content = '';
        $errorcontent = '';
        $datafordb = [];
        if ($records = $DB->get_records_sql($sql, $params)) {
            foreach ($records as $record) {

                // If the SAP line has errors, we need to add it to a separate file.
                $linehaserrors = false;
                $errorinfo = null;
                $info = null;

                // Kostenstelle ermitteln und zum $record hinzufügen.
                $record->costcenter = self::get_costcenter_by_identifier($record->identifier);

                // Wenn die Kostenstelle fehlt, wird die Zeile zum Error-File hinzugefügt.
                if (empty($record->costcenter)) {
                    $linehaserrors = true;
                    $errorinfo = 'keine_kostenstelle';
                }
                // Wenn der Nachname fehlt, wird die Zeile zum Error-File hinzugefügt.
                if (empty($record->lastname)) {
                    $linehaserrors = true;
                    $errorinfo = 'kein_nachname';
                }

                // Generate the SAP line (without line break).
                $currentline = self::generate_sap_line_for_record($record);

                // Gather data needed to write content to local_musi_sap table.
                $dbman = $DB->get_manager();
                $openorderid = 0;
                // Currently only payunity is supported for openorderid here...
                // ...as this is needed by USI Wien only by now.
                if ($dbman->table_exists('paygw_payunity_openorders')) {
                    $openorderid = $DB->get_field('paygw_payunity_openorders', 'id', [
                        'itemid' => $record->identifier,
                        'status' => 3,
                    ]);
                    if (empty($openorderid)) {
                        $openorderid = 0;
                    }
                }
                // We only insert if there is no existing (error-free) record for this identifier!
                if ($DB->record_exists('local_musi_sap', ['identifier' => $record->identifier, 'error' => null])) {
                    $linehaserrors = true;
                    $errorinfo = 'eintrag_fuer_identifier_existiert_bereits';
                }
                $currentrecorddata = new stdClass();
                $currentrecorddata->identifier = $record->identifier;
                $currentrecorddata->userid = $record->userid;
                $currentrecorddata->paymentid = $record->id;
                $currentrecorddata->pu_openorderid = $openorderid;
                $currentrecorddata->sap_line = $currentline; // Without line break.
                $currentrecorddata->error = $errorinfo;
                $currentrecorddata->info = $info;
                $currentrecorddata->filename = $filename;
                if ($linehaserrors) {
                    // If we have errors we show that it was written to errors file.
                    $currentrecorddata->filename .= '_errors';
                }
                $datafordb[] = $currentrecorddata;

                // ENDE: Zeilenumbruch.
                $currentline .= "\n"; // Needs to be LF, not CRLF, so we use \n.

                if (!$linehaserrors) {
                    $content .= $currentline;
                } else {
                    $errorcontent .= $currentline;
                }
            }
        }

        // Daten aus manuellen Nachbuchungen hinzufügen.
        self::add_sap_data_for_manual_rebookings($content, $errorcontent, $datafordb, $startofday, $endofday, $filename);

        return [$content, $errorcontent, $datafordb];
    }

    /**
     * Helper function to generate SAP line for record.
     * @param string &$content reference to $content
     * @param string &$errorcontent reference to $errorcontent
     * @param array &$datafordb reference to $datafordb array
     * @param int $startofday unix timestamp
     * @param int $endofday unix timestamp
     * @param string $filename the filename (without _errors suffix)
     * @return void
     */
    private static function add_sap_data_for_manual_rebookings(&$content, &$errorcontent, &$datafordb,
        int $startofday, int $endofday, string $filename): void {
        global $DB;

        // 1. Get one record for each manual rebooking.
        $sql = 'SELECT s1.*
                FROM (
                    SELECT *, ROW_NUMBER() OVER (PARTITION BY identifier ORDER BY itemid DESC) AS n
                    FROM {local_shopping_cart_ledger}
                ) AS s1
                WHERE n = 1 -- Only one row per identifier.
                AND paymentstatus = 2
                AND timecreated BETWEEN :startofday AND :endofday AND ' .
                $DB->sql_like('s1.payment', ':manualrebooking');
        $params = [
            'startofday' => $startofday,
            'endofday' => $endofday,
            'manualrebooking' => '7',
        ];
        $records = $DB->get_records_sql($sql, $params);

        // If we have no manual rebookings, we return.
        if (empty($records)) {
            return;
        }

        foreach ($records as $record) {

            // If the SAP line has errors, we need to add it to a separate file.
            $linehaserrors = false;
            $errorinfo = null;
            $info = 'manuelle_nachbuchung';

            unset($record->id); // We'll need the paymentid instead.
            unset($record->price); // Get it from openorders table instead!

            // Wenn die annotation einen identifier enthält, dann diesen verwenden!
            $annotationarr = explode(' ', $record->annotation);
            if (is_number($annotationarr[0])) {
                $record->identifier = (int) $annotationarr[0];
            }
            $allowedpaymentbrands = ['VC', 'MC', 'EP', 'DC', 'AX'];
            if (in_array(end($annotationarr), $allowedpaymentbrands)) {
                $record->paymentbrand = end($annotationarr);
            } else {
                $record->paymentbrand = 'UK';
                $linehaserrors = true;
                $errorinfo = 'zahlungsart_ungueltig';
            }

            // Nachname.
            $record->lastname = $DB->get_field('user', 'lastname', ['id' => $record->userid]);

            // Wenn die Kostenstelle fehlt, wird die Zeile zum Error-File hinzugefügt.
            if (empty($record->costcenter)) {
                $linehaserrors = true;
                $errorinfo = 'keine_kostenstelle';
            }
            // Wenn der Nachname fehlt, wird die Zeile zum Error-File hinzugefügt.
            if (empty($record->lastname)) {
                $linehaserrors = true;
                $errorinfo = 'kein_nachname';
            }

            // Eintrag in 'paygw_payunity_openorders'-Tabelle vorhanden?
            // Dann Preis von dort nehmen...
            $dbman = $DB->get_manager();
            $openorderid = 0;
            // Currently only payunity is supported for openorderid here...
            // ...as this is needed by USI Wien only by now.
            if ($dbman->table_exists('paygw_payunity_openorders')) {
                $openordersrecord = $DB->get_record('paygw_payunity_openorders', [
                    'itemid' => $record->identifier,
                ]);
                if (!empty($openordersrecord)) {
                    $openorderid = $openordersrecord->id;
                    $record->price = $openordersrecord->price;
                }
            }
            if ($openorderid == 0) {
                // In this case we have to calculate the price from ledger.
                $record->price = $DB->get_field_sql(
                    'SELECT SUM(price) AS price
                    FROM {local_shopping_cart_ledger}
                    WHERE identifier = :identifier
                    AND ' . $DB->sql_like('payment', ':manualrebooking'),
                    [
                        'identifier' => $record->identifier,
                        'manualrebooking' => '7',
                    ]
                );
            }
            // Round price to full number.
            $record->price = (int) round($record->price);

            // Nun kann die SAP-Zeile erstellt werden.
            $currentline = self::generate_sap_line_for_record($record);

            // Eintrag in der Payments-Tabelle vorhanden? Dann paymentid mitspeichern.
            $paymentid = $DB->get_field('payments', 'id', ['itemid' => $record->identifier]);
            if (empty($paymentid)) {
                $paymentid = 0;
            }

            // Es darf pro Identifier nur einen (fehlerfreien) Eintrag geben!
            if ($DB->record_exists('local_musi_sap', ['identifier' => $record->identifier, 'error' => null])) {
                $linehaserrors = true;
                $errorinfo = 'eintrag_fuer_identifier_existiert_bereits';
            }

            // Zusätzliche Infos für Eintrag in local_musi_sap-Tabelle.
            $currentrecorddata = new stdClass();
            $currentrecorddata->identifier = $record->identifier;
            $currentrecorddata->userid = $record->userid;
            $currentrecorddata->paymentid = $paymentid;
            $currentrecorddata->pu_openorderid = $openorderid;
            $currentrecorddata->sap_line = $currentline; // Without line break.
            $currentrecorddata->error = $errorinfo;
            $currentrecorddata->info = $info;
            $currentrecorddata->filename = $filename;
            if ($linehaserrors) {
                // If we have errors we show that it was written to errors file.
                $currentrecorddata->filename .= '_errors';
            }
            $datafordb[] = $currentrecorddata;

            // ENDE: Zeilenumbruch.
            $currentline .= "\n"; // Needs to be LF, not CRLF, so we use \n.

            if (!$linehaserrors) {
                $content .= $currentline;
            } else {
                $errorcontent .= $currentline;
            }
        }
    }

    /**
     * Helper function to generate SAP line for record.
     * @param stdClass $record
     * @return string
     */
    private static function generate_sap_line_for_record(stdClass $record): string {
        $currentline = '';
        /*
            * Mandant - 3 Stellen alphanumerisch - immer "101"
            * Buchungskreis - 4 Stellen alphanumerisch - immer "VIE1"
            * Währung - 3 Stellen alphanumerisch - immer "EUR"
            * Belegart - 2 Stellen alphanumerisch - Immer "DR"
            */
        $currentline .= "101#VIE1#EUR#DR#";
        // Referenzbelegnummer - 16 Stellen alphanumerisch.
        $currentline .= str_pad($record->identifier, 16, " ", STR_PAD_LEFT) . '#';
        // Buchungsdatum - 10 Stellen.
        $currentline .= date('d.m.Y', $record->timemodified) . '#';
        // Belegdatum - 10 Stellen.
        $currentline .= date('d.m.Y', $record->timecreated) . '#';
        // Belegkopftext - 25 Stellen alphanumerisch - in unserem Fall immer "US".
        $currentline .= str_pad('US', 25, " ", STR_PAD_LEFT) . '#';
        // Betrag - 14 Stellen alphanumerisch - Netto-Betrag.
        $renderedprice = (string) $record->price;
        $renderedprice = str_replace('.', ',', $renderedprice);
        $currentline .= str_pad($renderedprice, 14, " ", STR_PAD_LEFT) . '#';
        // Steuer rechnen - 1 Stelle alphanumerisch - immer "X".
        $currentline .= 'X#';
        /* Steuerbetrag - 14 Stellen alphanumerisch - derzeit sind keine Geschäftsfälle mit Umsatzsteuer vorgesehen,
        daher bleibt das Feld immer leer. */
        $currentline .= str_pad('', 14, " ") . '#';
        /* Werbeabgabe - 14 Stellen alphanumerisch - kein bekannter Geschäftsfall im Moment,
        daher bleibt das Feld immer leer. */
        $currentline .= str_pad('', 14, " ") . '#';
        // Buchungsschlüssel - 2 Stellen alphanumerisch - 50 - bei Rechnungen, 40 - bei Gutschriften
        // Derzeit können nur Rechnungen geloggt werden, daher immer 50.
        $currentline .= '50#';
        // Geschäftsfall-Code - 3 Stellen alphanumerisch - immer "US0".
        $currentline .= 'US0#';
        // Zahlungscode - 3 Stellen alphanumerisch.
        if (!empty($record->paymentbrand)) {
            $currentline .= str_pad($record->paymentbrand, 3, " ", STR_PAD_LEFT) . '#';
        } else {
            $currentline .= str_pad('', 3, " ", STR_PAD_LEFT) . '#';
        }
        // Buchungstext - 50 Stellen alphanumerisch.
        $lastname = 'UNKNOWN';
        if (!empty($record->lastname)) {
            $lastname = self::clean_string_for_sap($record->lastname);
        }
        $buchungstext = " US $record->userid " . $lastname;
        if (strlen($buchungstext) > 50) {
            $buchungstext = substr($buchungstext, 0, 50);
        }
        $currentline .= str_pad($buchungstext, 50, " ", STR_PAD_LEFT) . '#';
        // Zuordnung - analog "Referenzbelegnummer" - 18 Stellen alphanumerisch.
        $currentline .= str_pad($record->identifier, 18, " ", STR_PAD_LEFT) . '#';

        // Kostenstelle - 10 Stellen - leer.
        $currentline .= str_pad('', 10, " ", STR_PAD_LEFT) . '#';

        // Innenauftrag - 12 Stellen - USI Wien immer "ET592002".
        /* MUSI-350: Dort, wo jetzt immer "ET592002" steht muss der Wert
        des optionalen Feldes Statistik-Kostenstelle geschrieben werden. */
        // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
        /* $currentline .= str_pad('ET592002', 12, " ", STR_PAD_LEFT) . '#'; */
        $currentline .= str_pad($record->costcenter ?? '', 12, " ", STR_PAD_LEFT) . '#';

        // Anrede - 15 Stellen.
        $currentline .= str_pad('', 15, " ", STR_PAD_LEFT) . '#';
        // Name 1 - 35 Stellen.
        $currentline .= str_pad('', 35, " ", STR_PAD_LEFT) . '#';
        // Name 2 - 35 Stellen.
        $currentline .= str_pad('', 35, " ", STR_PAD_LEFT) . '#';
        // Name 3 - 35 Stellen.
        $currentline .= str_pad('', 35, " ", STR_PAD_LEFT) . '#';
        // Name 4 - 35 Stellen.
        $currentline .= str_pad('', 35, " ", STR_PAD_LEFT) . '#';
        // Straße / Hausnummer - 35 Stellen.
        $currentline .= str_pad('', 35, " ", STR_PAD_LEFT) . '#';
        // Ort - 35 Stellen.
        $currentline .= str_pad('', 35, " ", STR_PAD_LEFT) . '#';
        // Postleitzahl - 10 Stellen.
        $currentline .= str_pad('', 10, " ", STR_PAD_LEFT) . '#';
        // Land - 2 Stellen.
        $currentline .= str_pad('', 2, " ", STR_PAD_LEFT) . '#';
        // Zahlungsbedingung - 4 Stellen.
        $currentline .= str_pad('', 4, " ", STR_PAD_LEFT) . '#';

        return $currentline;
    }

    /**
     * Helper function to get cost center for identifier.
     * @param int $identifier
     * @return string
     */
    private static function get_costcenter_by_identifier(int $identifier): string {
        global $DB;
        // Kostenstelle.
        $sqlkostenstelle = "SELECT s1.kst
        FROM {payments} p
        JOIN {local_shopping_cart_history} h
        ON h.identifier = p.itemid
        JOIN (
            SELECT d.instanceid AS optionid, d.value AS kst
            FROM {customfield_field} f
            JOIN {customfield_category} c
            ON c.id = f.categoryid AND c.component = 'mod_booking' AND f.shortname = 'kst'
            JOIN {customfield_data} d
            ON d.fieldid = f.id
        ) s1
        ON s1.optionid = h.itemid
        WHERE p.itemid = :identifier AND s1.kst <> '' AND s1.kst IS NOT NULL
        LIMIT 1";
        $paramskostenstelle = ['identifier' => $identifier];
        $kostenstelle = $DB->get_field_sql($sqlkostenstelle, $paramskostenstelle);
        return $kostenstelle;
    }

    /**
     * Helper function to replace special characters like "ä" with "ae" etc.
     * And convert to uppercase letters.
     *
     * @param string $stringwithspecialchars
     * @return string the cleaned output string
     */
    public static function clean_string_for_sap(string $stringwithspecialchars): string {

        // At first replace special chars.
        $umlaute = [
            "/ß/" ,
            "/ä/", "/à/", "/á/", "/â/", "/æ/", "/ã/", "/å/", "/ā/",
            "/Ä/", "/À/", "/Á/", "/Â/", "/Æ/", "/Ã/", "/Å/", "/Ā/",
            "/é/", "/è/", "/ê/", "/ë/", "/ė/",
            "/É/", "/È/", "/Ê/", "/Ë/", "/Ė/",
            "/î/", "/ï/", "/í/", "/ī/", "/ì/",
            "/Î/", "/Ï/", "/Í/", "/Ī/", "/Ì/",
            "/ö/", "/ô/", "/ò/", "/ó/", "/õ/", "/œ/", "/ø/", "/ō/",
            "/Ö/", "/Ô/", "/Ò/", "/Ó/", "/Õ/", "/Œ/", "/Ø/", "/Ō/",
            "/ü/", "/û/", "/ù/", "/ú/", "/ū/",
            "/Ü/", "/Û/", "/Ù/", "/Ú/", "/Ū/",
            "/ç/", "/ć/", "/č/",
            "/Ç/", "/Ć/", "/Č/",
        ];

        $replace = [
            "ss" ,
            "ae", "a", "a", "a", "ae", "a", "a", "a",
            "Ae", "A", "A", "A", "Ae", "A", "A", "A",
            "e", "e", "e", "e", "e",
            "E", "E", "E", "E", "E",
            "i", "i", "i", "i", "i",
            "I", "I", "I", "I", "I",
            "oe", "o", "o", "o", "o", "o", "o", "o",
            "Oe", "O", "O", "O", "O", "Oe", "O", "O",
            "ue", "u", "u", "u", "u",
            "Ue", "U", "U", "U", "U",
            "c", "c", "c",
            "C", "C", "C",
        ];

        $string = preg_replace($umlaute, $replace, $stringwithspecialchars);

        // Now remove any remaining special chars.
        $string = preg_replace("/[^A-Za-z0-9 ]/", '', $string);

        // Now make sure, it's encoded as UTF-8.
        // Note: utf8_encode() has been deprecated since php 8.2 .
        $string = mb_convert_encoding($string, 'UTF-8', 'ISO-8859-1');

        // At last, make it UPPERCASE.
        return strtoupper($string);
    }

    /**
     * Collect all files without errors in a single directory.
     *
     * @param stored_file $file
     * @param string $filename
     * @return void
     */
    public static function copy_file_to_dir(stored_file $file, string $filename) {
        global $CFG;
        // Copy the file to a directory in moodleroot, create dir if it does not.
        try {
            $dataroot = $CFG->dataroot;
            $datadir = $dataroot . '/sapfiles';
            $filepath = $datadir . "/" . $filename;
            if (!is_dir($datadir)) {
                // Create the directory if it doesn't exist.
                if (!make_upload_directory('sapfiles')) {
                    // Handle directory creation error (e.g., display an error message).
                    throw new moodle_exception('errorcreatingdirectory', 'local_musi');
                }
            }
            // Copy the file to the sapfiles directory.
            if (file_exists($filepath)) {
                return;
            }
            if (!$file->copy_content_to($filepath)) {
                throw new moodle_exception('errorcopyingfiles', 'local_musi');
            }
        } catch (moodle_exception $e) {
            // Exception handling.
            $message = $e->getMessage();
            $code = $e->getCode();
            // Get detailed information about $file.
            $fileinfo = [
                    'filename' => $file->get_filename(),
                    'filepath' => $file->get_filepath(),
                    'filesize' => $file->get_filesize(),
                    'filearea' => $file->get_filearea(),
                    'timecreated' => $file->get_timecreated(),

            ];
            debugging("Moodle Exception: $message (Code: $code). File Info: " . var_dump($fileinfo, true));
        }
    }

    /**
     * Create SAP files for a specific date.
     * @param string $starttimestamp
     * @return void
     * @throws \file_exception
     * @throws \stored_file_creation_exception
     * @throws moodle_exception
     */
    public static function create_sap_files_from_date(int $starttimestamp): void {
        global $DB, $USER;

        $now = time();
        if (!$context = context_system::instance()) {
            throw new moodle_exception('badcontext');
        }
        $yesterday = strtotime('-1 day', $now);
        $fs = get_file_storage();
        $contextid = $context->id;
        $component = 'local_musi';
        $filearea = 'musi_sap_dailysums';
        $itemid = 0;
        $filepath = '/';
        while ($starttimestamp <= $yesterday) {
            $filename = 'SAP_USI_' . date('Ymd', $starttimestamp);
            // Retrieve the file from the Files API.
            $file = $fs->get_file($contextid, $component, $filearea, $itemid, $filepath, $filename);
            if (!$file) {
                $fileinfo = [
                        'contextid' => $contextid,
                        'component' => $component,
                        'filearea' => $filearea,
                        'itemid' => $itemid,
                        'filepath' => $filepath,
                        'filename' => $filename,
                ];

                list($content, $errorcontent, $datafordb) =
                    self::generate_sap_text_file_for_date($starttimestamp);

                foreach ($datafordb as $recordfordb) {
                    $recordfordb->usermodified = $USER->id;
                    $recordfordb->timecreated = $now;
                    $recordfordb->timemodified = $now;
                    $DB->insert_record('local_musi_sap', $recordfordb);
                }

                $file = $fs->create_file_from_string($fileinfo, $content);

                // If we have error content, we create an error file.
                if (!empty($errorcontent)) {
                    $errorfilename = 'SAP_USI_' . date('Ymd', $starttimestamp) . '_errors';
                    $errorfile = $fs->get_file($contextid, $component, $filearea, $itemid, $filepath, $errorfilename);
                    if (!$errorfile) {
                        $errorfileinfo = [
                                'contextid' => $contextid,
                                'component' => $component,
                                'filearea' => $filearea,
                                'itemid' => $itemid,
                                'filepath' => $filepath,
                                'filename' => $errorfilename,
                        ];
                        $fs->create_file_from_string($errorfileinfo, $errorcontent);
                    }
                }
            }
            $starttimestamp = strtotime('+1 day', $starttimestamp);
            // Collect all files in single directory.
            self::copy_file_to_dir($file, $filename);
        }
    }
}
