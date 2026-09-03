<?php
// This file is part of Moodle - http://moodle.org/
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Lists all LearningApp instances in a course.
 *
 * @package     mod_learningapp
 * @copyright   2026 lasjoh-toho
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/learningapp/lib.php');

$id = required_param('id', PARAM_INT); // Course id.

$course = get_course($id);
require_login($course);

$context = context_course::instance($course->id);
$PAGE->set_url('/mod/learningapp/index.php', ['id' => $id]);
$PAGE->set_title(format_string($course->shortname) . ': ' . get_string('modulenameplural', 'mod_learningapp'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_learningapp'));

$instances = get_all_instances_in_course('learningapp', $course);

if (empty($instances)) {
    echo $OUTPUT->notification(get_string('noinstances', 'core'), 'info');
} else {
    $table = new html_table();
    $table->head = [get_string('name'), get_string('grademax', 'mod_learningapp')];
    $table->attributes['class'] = 'generaltable mod_index';

    foreach ($instances as $instance) {
        $link = html_writer::link(
            new moodle_url('/mod/learningapp/view.php', ['id' => $instance->coursemodule]),
            format_string($instance->name)
        );
        $table->data[] = [$link, $instance->grademax];
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
