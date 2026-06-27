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

/**
 * Static guard: per-row table column formatters must not run their own DB queries.
 *
 * wunderbyte_table calls col_<name>()/other_cols() once per rendered row, so a
 * direct $DB->get_*()/execute() inside such a method is an N+1 by construction.
 * This test tokenises the table classes and fails if a new column formatter
 * introduces an unguarded query, before it can reach a high-performance page.
 *
 * It needs no database and is therefore cheap to run on every CI push.
 *
 * @package local_musi
 * @category test
 * @copyright 2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class musi_column_no_per_row_db_test extends advanced_testcase {

    /**
     * Files whose col_ and other_cols methods must stay query-free.
     *
     * @return array<string, array{0:string}>
     */
    public static function table_class_provider(): array {
        global $CFG;
        return [
            'musi_table' => [$CFG->dirroot . '/local/musi/classes/table/musi_table.php'],
        ];
    }

    /**
     * Methods allowed to keep a direct query, with the reason. Keep this list short
     * and justified; the default expectation is zero per-row queries. Currently empty:
     * col_receipt batch-loads both its receipt and installment lookups per page
     * (musi_table::get_latest_receipt_ledger / get_installment_identifiers).
     *
     * @var array<string, string>
     */
    private const ALLOWLIST = [];

    /**
     * @dataProvider table_class_provider
     * @param string $file
     */
    public function test_column_formatters_do_not_query_db(string $file): void {
        $this->assertFileExists($file);
        $offenders = [];

        foreach ($this->extract_methods($file) as $method => $body) {
            if (!preg_match('/^(col_|other_cols)/', $method)) {
                continue;
            }
            if (isset(self::ALLOWLIST[$method])) {
                continue;
            }
            // $DB->get_record / get_records / get_field / get_fieldset / get_recordset /
            // record_exists / count_records / execute, with or without the _sql suffix.
            if (preg_match('/\$DB\s*->\s*(get_record|get_records|get_field|get_fieldset|get_recordset|record_exists|count_records|execute)/', $body)) {
                $offenders[] = $method;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Per-row column formatter(s) run a direct DB query (N+1 risk): " . implode(', ', $offenders)
                . ". Batch-load before rendering (see musi_table::get_latest_receipt_ledger), "
                . "or add a justified entry to ALLOWLIST."
        );
    }

    /**
     * Return [methodname => methodbodysource] for every method in a PHP class file,
     * using the tokenizer so braces in strings/comments do not confuse brace matching.
     *
     * @param string $file
     * @return array<string, string>
     */
    private function extract_methods(string $file): array {
        $tokens = token_get_all(file_get_contents($file));
        $methods = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
                continue;
            }
            // Find the method name.
            $name = null;
            for ($j = $i + 1; $j < $count; $j++) {
                if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                    $name = $tokens[$j][1];
                    break;
                }
                if ($tokens[$j] === '(') {
                    break; // Closure, no name.
                }
            }
            if ($name === null) {
                continue;
            }
            // Find the opening brace of the body.
            $k = $j;
            while ($k < $count && $tokens[$k] !== '{') {
                // An abstract/interface method ends in ';' before any '{'.
                if ($tokens[$k] === ';') {
                    break;
                }
                $k++;
            }
            if ($k >= $count || $tokens[$k] !== '{') {
                continue;
            }
            // Collect the body until the matching closing brace.
            $depth = 0;
            $body = '';
            for ($m = $k; $m < $count; $m++) {
                $text = is_array($tokens[$m]) ? $tokens[$m][1] : $tokens[$m];
                if ($tokens[$m] === '{') {
                    $depth++;
                } else if ($tokens[$m] === '}') {
                    $depth--;
                }
                $body .= $text;
                if ($depth === 0) {
                    break;
                }
            }
            $methods[$name] = $body;
        }

        return $methods;
    }
}
