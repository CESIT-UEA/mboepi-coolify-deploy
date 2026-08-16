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
 * lib.php
 *
 * @package   local_kopere_pay
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_kopere_dashboard\output\layout;

/**
 * Call-back method to extend the navigation
 *
 * @param global_navigation $nav
 * @return void
 * @throws \core\exception\moodle_exception
 * @throws coding_exception
 * @throws dml_exception
 */
function local_kopere_pay_extend_navigation(global_navigation $nav) {
    global $CFG;

    $context = context_system::instance();
    if (isloggedin() && has_capability('local/kopere_pay:manage', $context)) {
        $node = $nav->add(
            get_string("pluginname", "local_kopere_pay"),
            new moodle_url($CFG->wwwroot . '/local/kopere_pay/open.php?classname=dashboard&method=start'),
            navigation_node::TYPE_CUSTOM,
            null,
            null,
            new pix_icon("icon", get_string("pluginname", "local_kopere_pay"), "local_kopere_pay")
        );

        $node->showinflatnavigation = true;
    }
}

/**
 * Function local_kopere_pay_extend_navigation_course
 *
 * @param $navigation
 * @param $course
 * @param $context
 * @return void
 * @throws coding_exception
 */
function local_kopere_pay_extend_navigation_course($navigation, $course, $context) {
    global $CFG;

    if (!has_capability("moodle/course:update", $context)) {
        return;
    }

    $urlbase = "{$CFG->wwwroot}/local/kopere_pay/open.php";

    $url = "{$urlbase}?classname=course_detail&method=details&course={$course->id}";
    $navigation->add(
        get_string("pluginname", "local_kopere_pay"), $url, navigation_node::TYPE_SETTING, null, null,
        new pix_icon("icon", "", "local_kopere_pay")
    );

    $admin = $navigation->add(get_string("pluginname", "local_kopere_pay"));

    $url = "{$urlbase}?classname=course_detail&method=details&course={$course->id}";
    $admin->add(
        get_string("tab_details", "local_kopere_pay"),
        $url, navigation_node::TYPE_SETTING, null, null, new pix_icon('i/report', '')
    );

    $url = "{$urlbase}?classname=course_detail&method=edit&course={$course->id}";
    $admin->add(
        get_string("tab_edit", "local_kopere_pay"),
        $url, navigation_node::TYPE_SETTING, null, null, new pix_icon('i/report', '')
    );

    $url = "{$urlbase}?classname=transaction&method=dashboard&course={$course->id}";
    $admin->add(
        get_string("transactions_title", "local_kopere_pay"),
        $url, navigation_node::TYPE_SETTING, null, null, new pix_icon('i/report', '')
    );

    $url = "{$urlbase}?classname=coupons&method=dashboard&course={$course->id}";
    $admin->add(
        get_string("tab_coupons", "local_kopere_pay"),
        $url, navigation_node::TYPE_SETTING, null, null, new pix_icon('i/report', '')
    );

    $url = "{$urlbase}?classname=builder&method=creator&course={$course->id}";
    $admin->add(
        get_string("tab_pagebuilder", "local_kopere_pay"),
        $url, navigation_node::TYPE_SETTING, null, null, new pix_icon('i/report', '')
    );
}

/**
 * Dispatches the current request.
 *
 * @param string $rawclassname
 * @param string $method
 * @param context_system $context
 * @return string
 * @throws moodle_exception
 * @throws \required_capability_exception
 */
function local_kopere_pay_dispatch($rawclassname, $method, context_system $context) {
    [$classname, $fqcn] = local_kopere_pay_resolve_classname($rawclassname);
    local_kopere_pay_validate_method($method);
    local_kopere_pay_require_route_capability($classname, $method, $context);

    $instance = new $fqcn();
    if (!is_callable([$instance, $method])) {
        throw new moodle_exception("invalidrequest", "error", "", null, "Method not found");
    }

    return $instance->{$method}();
}

/**
 * Dispatches the current request and renders it inside Kopere Dashboard layout.
 *
 * @param string $rawclassname
 * @param string $method
 * @param context_system $context
 * @return void
 * @throws moodle_exception
 * @throws required_capability_exception
 */
function local_kopere_pay_dispatch_and_render($rawclassname, $method, context_system $context) {
    $content = local_kopere_pay_dispatch($rawclassname, $method, $context);
    layout::page_render($context, $content, true, "kopere-pay-page");
}

/**
 * Resolves the class name received from the URL into a local_kopere_pay class.
 *
 * @param string $rawclassname
 * @return array
 * @throws moodle_exception
 */
function local_kopere_pay_resolve_classname($rawclassname) {
    $classname = str_replace("-", "_", trim($rawclassname));

    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $classname)) {
        throw new moodle_exception("invalidrequest", "error", "", null, "Invalid class name");
    }

    $fqcn = "\\local_kopere_pay\\{$classname}";
    if (!class_exists($fqcn)) {
        throw new moodle_exception("class_not_found", "local_kopere_pay");
    }

    return [$classname, $fqcn];
}

/**
 * Validates the method name received from the URL.
 *
 * @param string $method
 * @return void
 * @throws moodle_exception
 */
function local_kopere_pay_validate_method($method) {
    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $method) || strpos($method, "__") === 0) {
        throw new moodle_exception("invalidrequest", "error", "", null, "Invalid method name");
    }
}

/**
 * Requires the correct capability for the requested route.
 *
 * @param string $classname
 * @param string $method
 * @param context_system $context
 * @return void
 * @throws required_capability_exception
 */
function local_kopere_pay_require_route_capability($classname, $method, context_system $context) {
    $viewroutes = [
        "dashboard" => ["start", "preview", "type_block_preview"],
        "chart_data" => ["load_data"],
    ];

    if (isset($viewroutes[$classname]) && in_array($method, $viewroutes[$classname], true)) {
        require_capability("local/kopere_pay:view", $context);
        return;
    }

    require_capability("local/kopere_pay:manage", $context);
}

/**
 * Serves files for local_kopere_pay.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 * @throws \coding_exception
 */
function local_kopere_pay_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_SYSTEM) {
        return false;
    }

    if ($filearea !== "catalog_banner") {
        return false;
    }

    $itemid = array_shift($args);
    $filename = array_pop($args);
    if (!$args) {
        $filepath = "/";
    } else {
        $filepath = "/" . implode("/", $args) . "/";
    }

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, "local_kopere_pay", $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return false;
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}
