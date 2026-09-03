<?php
// This file is part of Moodle - http://moodle.org/
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * AJAX endpoint: records a "completed / passed" submission, writes the
 * configured grade to the Moodle gradebook and marks activity completion.
 *
 * @package     mod_learningapp
 * @copyright   2026 lasjoh-toho
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/learningapp/lib.php');

header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => ''];

try {
    require_login(null, false);
    require_sesskey();

    $cmid = required_param('cmid', PARAM_INT);
    $cm = get_coursemodule_from_id('learningapp', $cmid, 0, false, MUST_EXIST);
    $course = get_course($cm->course);
    $learningapp = $DB->get_record('learningapp', ['id' => $cm->instance], '*', MUST_EXIST);

    require_login($course, true, $cm);
    $context = context_module::instance($cm->id);
    require_capability('mod/learningapp:submit', $context);

    if (isguestuser()) {
        throw new moodle_exception('noguest');
    }

    $alreadysubmitted = $DB->record_exists('learningapp_submissions', [
        'learningappid' => $learningapp->id,
        'userid' => $USER->id,
    ]);

    $grade = learningapp_process_submission($learningapp, $cm, $USER->id);

    $response['success'] = true;
    $response['alreadysubmitted'] = $alreadysubmitted;
    $response['grade'] = $grade;
    $response['message'] = get_string('submitsuccess', 'mod_learningapp');
} catch (\Throwable $e) {
    http_response_code(400);
    $response['success'] = false;
    $response['message'] = get_string('submiterror', 'mod_learningapp');
    if (debugging()) {
        $response['debug'] = $e->getMessage();
    }
}

echo json_encode($response);
die;
