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

namespace local_musi\local;

use core\hook\output\before_http_headers;
use core\notification;

/**
 * Warnings which are created while a form is saved via AJAX and shown on the next page the user opens.
 *
 * The notifications of Moodle (\core\notification) are kept in the session, too, but they are handed out
 * to the first request which renders any output. The form of a booking option is saved with an AJAX
 * request and reloaded with another one, while the browser already leaves the page. The reload receives
 * the notifications and nobody ever sees them. So we keep our warnings on our own and only pass them on
 * to Moodle, when a real page is about to be displayed.
 *
 * @package    local_musi
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pending_warnings {
    /**
     * Keeps a warning for the next page the user opens.
     *
     * @param string $message html of the warning, the caller has to take care of escaping
     * @return void
     */
    public static function add(string $message): void {
        global $SESSION;

        if (!isset($SESSION->local_musi_pendingwarnings) || !is_array($SESSION->local_musi_pendingwarnings)) {
            $SESSION->local_musi_pendingwarnings = [];
        }
        $SESSION->local_musi_pendingwarnings[] = $message;
    }

    /**
     * Hook callback: a page is about to be displayed, so the kept warnings are handed over to Moodle now.
     * The header of this very page shows them.
     *
     * @param before_http_headers $hook
     * @return void
     */
    public static function before_http_headers(before_http_headers $hook): void {
        global $SESSION;

        if (empty($SESSION->local_musi_pendingwarnings)) {
            return;
        }

        // AJAX requests can print a header, too (the reload of the form of a booking option does).
        // Their output is not a page the user gets to see, so the warnings have to wait.
        if (defined('AJAX_SCRIPT') && AJAX_SCRIPT) {
            return;
        }

        foreach ($SESSION->local_musi_pendingwarnings as $message) {
            notification::warning($message);
        }
        unset($SESSION->local_musi_pendingwarnings);
    }
}
