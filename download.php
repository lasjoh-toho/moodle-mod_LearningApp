<?php
// This file is part of Moodle - http://moodle.org/
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Lets an authorised user download a snapshot of the LearningApps activity
 * as a standalone HTML file. Only available when the site administrator has
 * enabled "HTML-Download erlauben" in the plugin settings.
 *
 * @package     mod_learningapp
 * @copyright   2026 lasjoh-toho
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/learningapp/lib.php');

$id = required_param('id', PARAM_INT); // Course_module ID.

$cm = get_coursemodule_from_id('learningapp', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$learningapp = $DB->get_record('learningapp', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

require_capability('mod/learningapp:view', $context);
require_capability('mod/learningapp:downloadhtml', $context);

if (!get_config('mod_learningapp', 'enable_html_download')) {
    throw new moodle_exception('htmldownloaddisabled', 'mod_learningapp');
}

// Serve an existing local snapshot if we already have one; otherwise fetch
// one now on demand (this also leaves a reusable local copy behind, exactly
// like the "store locally" option would).
$file = \mod_learningapp\local\storage_manager::get_snapshot_file($learningapp->id, $context);

if (!$file) {
    $fetched = \mod_learningapp\local\storage_manager::store($learningapp->id, $learningapp->externalurl, $context);
    if (!$fetched) {
        throw new moodle_exception('htmldownloadfailed', 'mod_learningapp');
    }
    $file = \mod_learningapp\local\storage_manager::get_snapshot_file($learningapp->id, $context);
    if (!$file) {
        throw new moodle_exception('htmldownloadfailed', 'mod_learningapp');
    }
}

$event = \mod_learningapp\event\course_module_viewed::create([
    'objectid' => $learningapp->id,
    'context'  => $context,
]);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('learningapp', $learningapp);
$event->trigger();

$downloadname = clean_filename(format_string($learningapp->name, true, ['context' => $context])) . '.html';

send_stored_file($file, 0, 0, true, ['filename' => $downloadname]);
