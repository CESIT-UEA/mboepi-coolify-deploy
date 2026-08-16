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
 * email_event.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_kopere_pay\util;

use coding_exception;
use moodle_url;
use stdClass;

/**
 * Class email_event
 */
class email_event {

    /**
     * Send new user access data to the student.
     *
     * @param stdClass $user
     * @param string $password
     * @return bool
     * @throws coding_exception
     */
    public static function user_created(stdClass $user, string $password): bool {
        global $SITE;

        $data = self::base_data($user);
        $data->password = $password;

        $subject = get_string("email_user_created_subject", "local_kopere_pay", $SITE->fullname);
        $html = get_string("email_user_created_html", "local_kopere_pay", $data);

        return self::send_to_user($user, $subject, $html);
    }

    /**
     * Send paid enrollment confirmation to the student.
     *
     * @param stdClass $course
     * @param stdClass $user
     * @return bool
     * @throws coding_exception
     */
    public static function paid(stdClass $course, stdClass $user): bool {
        $data = self::base_data($user);
        $data->coursefullname = self::course_fullname($course);
        $data->courselink = self::course_link($course);

        $subject = get_string("email_paid_subject", "local_kopere_pay", $data->coursefullname);
        $html = get_string("email_paid_html", "local_kopere_pay", $data);

        return self::send_to_user($user, $subject, $html);
    }

    /**
     * Send refused/refunded payment notification to site admins.
     *
     * @param stdClass $course
     * @param stdClass $user
     * @return bool
     * @throws coding_exception
     */
    public static function refunded(stdClass $course, stdClass $user): bool {
        $data = self::base_data($user);
        $data->coursefullname = self::course_fullname($course);
        $data->profilelink = self::profile_link($user);

        $subject = get_string("email_refunded_subject", "local_kopere_pay");
        $html = get_string("email_refunded_html", "local_kopere_pay", $data);

        return self::send_to_admins($subject, $html);
    }

    /**
     * Build common placeholders for notification messages.
     *
     * @param stdClass $user
     * @return stdClass
     */
    private static function base_data(stdClass $user): stdClass {
        global $SITE;

        $data = new stdClass();
        $data->fullname = fullname($user);
        $data->username = $user->username;
        $data->moodlefullname = format_string($SITE->fullname);
        $data->moodlelink = (new moodle_url('/'))->out(false);

        return $data;
    }

    /**
     * Return the course or cohort display name.
     *
     * @param stdClass $course
     * @return string
     */
    private static function course_fullname(stdClass $course): string {
        if (!empty($course->fullname)) {
            return format_string($course->fullname);
        }
        if (!empty($course->name)) {
            return format_string($course->name);
        }

        return get_string("not_defined", "local_kopere_pay");
    }

    /**
     * Return the best student link for the purchased item.
     *
     * @param stdClass $course
     * @return string
     */
    private static function course_link(stdClass $course): string {
        if (empty($course->isCoorte) && !empty($course->id)) {
            return (new moodle_url('/course/view.php', ["id" => $course->id]))->out(false);
        }

        return (new moodle_url('/my'))->out(false);
    }

    /**
     * Return the user profile link.
     *
     * @param stdClass $user
     * @return string
     */
    private static function profile_link(stdClass $user): string {
        return (new moodle_url('/user/profile.php', ["id" => $user->id]))->out(false);
    }

    /**
     * Send an email to one user.
     *
     * @param stdClass $touser
     * @param string $subject
     * @param string $html
     * @return bool
     */
    private static function send_to_user(stdClass $touser, string $subject, string $html): bool {
        $fromuser = self::admin_user();
        $text = self::html_to_text($html);

        return email_to_user($touser, $fromuser, $subject, $text, $html);
    }

    /**
     * Send an email to all site admins.
     *
     * @param string $subject
     * @param string $html
     * @return bool
     */
    private static function send_to_admins(string $subject, string $html): bool {
        $fromuser = self::admin_user();
        $text = self::html_to_text($html);
        $sent = false;

        foreach (get_admins() as $admin) {
            if (email_to_user($admin, $fromuser, $subject, $text, $html)) {
                $sent = true;
            }
        }

        return $sent;
    }

    /**
     * Return the first site admin to be used as sender.
     *
     * @return stdClass
     */
    private static function admin_user(): stdClass {
        foreach (get_admins() as $admin) {
            return $admin;
        }

        return \core_user::get_support_user();
    }

    /**
     * Convert HTML to plain text for email clients without HTML support.
     *
     * @param string $html
     * @return string
     */
    private static function html_to_text(string $html): string {
        return trim(html_to_text($html));
    }
}
