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

/**
 * Interface that all AI provider classes must implement.
 *
 * @package     qtype_codejudge
 * @copyright   2026 IA Judge Contributors
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_codejudge\ai;

defined('MOODLE_INTERNAL') || die();

/**
 * Contract for AI provider implementations.
 *
 * Each provider receives a fully-assembled prompt string and must return
 * a normalised result array. All HTTP communication, authentication and
 * response parsing is the responsibility of the concrete provider class.
 */
interface provider_interface {

    /**
     * Sends a prompt to the AI provider and returns the parsed evaluation.
     *
     * @param  string $prompt The complete prompt to send (rubric + code + instructions).
     * @return array{score: float, feedback: string, raw: string}
     *         Associative array with:
     *         - 'score'    (float)  Numeric grade 0–100.
     *         - 'feedback' (string) Pedagogical feedback text (plain text or Markdown).
     *         - 'raw'      (string) Raw JSON string returned by the API (for debugging).
     * @throws \moodle_exception If the API call fails or the response cannot be parsed.
     */
    public function evaluate(string $prompt): array;

    /**
     * Performs a lightweight connectivity/authentication check against the provider.
     *
     * Used by the "Test Connection" button in the admin settings page.
     *
     * @return array{success: bool, message: string}
     *         - 'success' (bool)   True if the provider responded correctly.
     *         - 'message' (string) Human-readable result or error message.
     */
    public function test_connection(): array;
}
