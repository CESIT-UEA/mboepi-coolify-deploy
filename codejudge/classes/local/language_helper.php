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
 * Helper for supported programming languages.
 *
 * @package     qtype_codejudge
 * @copyright   2026 IA Judge Contributors
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_codejudge\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Centralises the supported languages for codejudge questions.
 */
class language_helper {

    /**
     * Default language for new questions.
     */
    public const DEFAULT_LANGUAGE = 'python';

    /**
     * Returns the supported language options.
     *
     * @return array<string, string>
     */
    public static function get_options(): array {
        return [
            'python' => get_string('lang_python', 'qtype_codejudge'),
            'c' => get_string('lang_c', 'qtype_codejudge'),
            'java' => get_string('lang_java', 'qtype_codejudge'),
            'javascript' => get_string('lang_javascript', 'qtype_codejudge'),
        ];
    }

    /**
     * Returns the default language.
     *
     * @return string
     */
    public static function get_default(): string {
        return self::DEFAULT_LANGUAGE;
    }

    /**
     * Checks whether the given language is supported.
     *
     * @param string $language Language key.
     * @return bool
     */
    public static function is_supported(string $language): bool {
        return array_key_exists($language, self::get_options());
    }

    /**
     * Normalises an input language to one of the supported values.
     *
     * @param string|null $language Language key from form or stored data.
     * @return string
     */
    public static function normalise(?string $language): string {
        $language = trim((string)$language);
        if ($language === '' || !self::is_supported($language)) {
            return self::DEFAULT_LANGUAGE;
        }

        return $language;
    }
}
