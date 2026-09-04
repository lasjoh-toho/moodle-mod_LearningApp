<?php
// This file is part of Moodle - http://moodle.org/
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Manually (re-)generates the local snapshot for an activity, always
 * fetching fresh (bypassing the shared app-id-tagged cache) and refreshing
 * that shared cache too. Only available when the site administrator has
 * enabled "Lokale Wiederverwendung erlauben" in the plugin settings.
 *
 * @package     mod_learningapp
 * @copyright   2026 lasjoh-toho
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/learningapp/lib.php');
require_once($CFG->libdir . '/filelib.php');

$id = required_param('id', PARAM_INT); // Course_module ID.

$cm = get_coursemodule_from_id('learningapp', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$learningapp = $DB->get_record('learningapp', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

require_sesskey();
require_capability('mod/learningapp:view', $context);
require_capability('mod/learningapp:storelocally', $context);

$returnurl = new moodle_url('/mod/learningapp/view.php', ['id' => $cm->id]);

if (!get_config('mod_learningapp', 'enable_local_storage')) {
    redirect($returnurl, get_string('localstoragedisabled', 'mod_learningapp'), null,
        \core\output\notification::NOTIFY_ERROR);
}

$ok = \mod_learningapp\local\storage_manager::store(
    $learningapp->id, $learningapp->externalurl, $context, true);

if ($ok) {
    $file = \mod_learningapp\local\storage_manager::get_snapshot_file($learningapp->id, $context);
    $size = $file ? display_size($file->get_filesize()) : '?';
    redirect($returnurl, get_string('storelocallysuccess', 'mod_learningapp', $size), null,
        \core\output\notification::NOTIFY_SUCCESS);
} else {
    redirect($returnurl, get_string('storelocallyfailed', 'mod_learningapp'), null,
        \core\output\notification::NOTIFY_ERROR);
}
