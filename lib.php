<?php
// This file is part of Moodle - http://moodle.org/
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Library of interface functions and constants for mod_learningapp.
 *
 * @package     mod_learningapp
 * @copyright   2026 lasjoh-toho
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/gradelib.php');

/** Regex for a valid LearningApps app id (alphanumeric). */
define('LEARNINGAPP_ID_REGEX', '/^[A-Za-z0-9]+$/');

/**
 * Returns whether mod_learningapp supports a given feature.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function learningapp_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_MOD_PURPOSE:
            return defined('MOD_PURPOSE_CONTENT') ? MOD_PURPOSE_CONTENT : null;
        default:
            return null;
    }
}

/**
 * Normalises and converts a LearningApps URL into the canonical
 * https://learningapps.org/watch?v=XXXXX format.
 *
 * Accepted input formats:
 *  - https://learningapps.org/watch?v=XXXXX      (already canonical)
 *  - https://learningapps.org/display?v=XXXXX
 *  - https://learningapps.org/show?v=XXXXX
 *  - https://learningapps.org/viewXXXXX
 *
 * @param string $url raw URL as entered by the teacher
 * @return string|false the canonical watch URL, or false if the URL could not be converted
 */
function learningapp_transform_url($url) {
    $url = trim((string)$url);
    if ($url === '') {
        return false;
    }

    // Be lenient: allow URLs without scheme by prefixing https://.
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }

    $parts = @parse_url($url);
    if ($parts === false || empty($parts['host'])) {
        return false;
    }

    $host = strtolower($parts['host']);
    if ($host !== 'learningapps.org' && $host !== 'www.learningapps.org') {
        return false;
    }

    $path = isset($parts['path']) ? $parts['path'] : '';
    $query = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }

    // Case 1: /watch?v=XXXXX (already canonical) or /display?v=XXXXX or /show?v=XXXXX.
    if (preg_match('#^/(watch|display|show)/?$#i', $path)) {
        if (!empty($query['v']) && preg_match(LEARNINGAPP_ID_REGEX, $query['v'])) {
            return 'https://learningapps.org/watch?v=' . $query['v'];
        }
        return false;
    }

    // Case 2: /viewXXXXX - the id is embedded directly in the path.
    if (preg_match('#^/view([A-Za-z0-9]+)/?$#i', $path, $matches)) {
        return 'https://learningapps.org/watch?v=' . $matches[1];
    }

    return false;
}

/**
 * Adds a new learningapp instance.
 *
 * @param stdClass $data submitted form data
 * @param mod_learningapp_mod_form|null $mform
 * @return int id of the newly inserted record
 */
function learningapp_add_instance(stdClass $data, $mform = null) {
    global $DB;

    $converted = learningapp_transform_url($data->externalurl);
    if ($converted === false) {
        throw new moodle_exception('invalidurl', 'mod_learningapp');
    }
    $data->externalurl = $converted;
    $data->storelocally = (!empty($data->storelocally) && get_config('mod_learningapp', 'enable_local_storage')) ? 1 : 0;
    $data->timecreated = time();
    $data->timemodified = $data->timecreated;

    $data->id = $DB->insert_record('learningapp', $data);

    learningapp_grade_item_update($data);

    if ($data->storelocally) {
        $cm = get_coursemodule_from_instance('learningapp', $data->id, $data->course, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        \mod_learningapp\local\storage_manager::store($data->id, $data->externalurl, $context);
    }

    return $data->id;
}

/**
 * Updates an existing learningapp instance.
 *
 * @param stdClass $data submitted form data
 * @param mod_learningapp_mod_form|null $mform
 * @return bool true on success
 */
function learningapp_update_instance(stdClass $data, $mform = null) {
    global $DB;

    $converted = learningapp_transform_url($data->externalurl);
    if ($converted === false) {
        throw new moodle_exception('invalidurl', 'mod_learningapp');
    }
    $data->externalurl = $converted;
    $data->storelocally = (!empty($data->storelocally) && get_config('mod_learningapp', 'enable_local_storage')) ? 1 : 0;
    $data->timemodified = time();
    $data->id = $data->instance;

    $DB->update_record('learningapp', $data);

    learningapp_grade_item_update($data);

    $context = context_module::instance($data->coursemodule);
    if ($data->storelocally) {
        \mod_learningapp\local\storage_manager::store($data->id, $data->externalurl, $context);
    } else {
        \mod_learningapp\local\storage_manager::purge($data->id, $context);
    }

    return true;
}

/**
 * Deletes a learningapp instance.
 *
 * @param int $id instance id
 * @return bool true on success
 */
function learningapp_delete_instance($id) {
    global $DB;

    if (!$learningapp = $DB->get_record('learningapp', ['id' => $id])) {
        return false;
    }

    $cm = get_coursemodule_from_instance('learningapp', $id);
    if ($cm) {
        $context = context_module::instance($cm->id);
        \mod_learningapp\local\storage_manager::purge($id, $context);
    }

    $DB->delete_records('learningapp_submissions', ['learningappid' => $id]);
    $DB->delete_records('learningapp', ['id' => $id]);

    learningapp_grade_item_delete($learningapp);

    return true;
}

/**
 * Creates or updates the gradebook grade item for a learningapp instance.
 *
 * @param stdClass $learningapp instance record (must contain course, id/instance, name, grademax)
 * @param mixed $grades optional grade(s) to push, or null/'reset'/'delete'
 * @return int GRADE_UPDATE_OK or an error code
 */
function learningapp_grade_item_update($learningapp, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $params = [
        'itemname' => $learningapp->name,
        'gradetype' => GRADE_TYPE_VALUE,
        'grademax'  => !empty($learningapp->grademax) ? (float)$learningapp->grademax : 100.0,
        'grademin'  => 0,
    ];

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    $instanceid = !empty($learningapp->id) ? $learningapp->id : $learningapp->instance;

    return grade_update('mod/learningapp', $learningapp->course, 'mod', 'learningapp',
        $instanceid, 0, $grades, $params);
}

/**
 * Deletes the gradebook grade item for a learningapp instance.
 *
 * @param stdClass $learningapp
 * @return int
 */
function learningapp_grade_item_delete($learningapp) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update('mod/learningapp', $learningapp->course, 'mod', 'learningapp',
        $learningapp->id, 0, null, ['deleted' => 1]);
}

/**
 * Pushes grades for one or all users to the gradebook (called by core grade refresh).
 *
 * @param int $courseid
 * @param string $type
 * @param string $dir
 */
function learningapp_update_grades($learningapp, $userid = 0, $nullifnone = true) {
    global $DB;

    $grades = [];
    $sql = "SELECT userid, MAX(grade) AS rawgrade
              FROM {learningapp_submissions}
             WHERE learningappid = :id";
    $params = ['id' => $learningapp->id];
    if ($userid) {
        $sql .= ' AND userid = :userid';
        $params['userid'] = $userid;
    }
    $sql .= ' GROUP BY userid';

    $records = $DB->get_records_sql($sql, $params);
    foreach ($records as $record) {
        $grades[$record->userid] = (object)[
            'userid' => $record->userid,
            'rawgrade' => $record->rawgrade,
        ];
    }

    if ($grades) {
        learningapp_grade_item_update($learningapp, $grades);
    } else if ($nullifnone) {
        learningapp_grade_item_update($learningapp);
    }
}

/**
 * Records a completed submission, updates the gradebook and marks activity completion.
 *
 * @param stdClass $learningapp instance record
 * @param stdClass $cm course module record
 * @param int $userid
 * @return float the grade that was recorded
 */
function learningapp_process_submission(stdClass $learningapp, stdClass $cm, $userid) {
    global $DB;

    $grademax = !empty($learningapp->grademax) ? (float)$learningapp->grademax : 100.0;

    $existing = $DB->get_record('learningapp_submissions', [
        'learningappid' => $learningapp->id,
        'userid' => $userid,
    ]);

    if ($existing) {
        $existing->grade = $grademax;
        $existing->timesubmitted = time();
        $DB->update_record('learningapp_submissions', $existing);
    } else {
        $record = (object)[
            'learningappid' => $learningapp->id,
            'userid' => $userid,
            'grade' => $grademax,
            'timesubmitted' => time(),
        ];
        $DB->insert_record('learningapp_submissions', $record);
    }

    learningapp_update_grades($learningapp, $userid);

    $course = get_course($learningapp->course);
    $completion = new completion_info($course);
    if ($completion->is_enabled($cm) && $cm->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $completion->update_state($cm, COMPLETION_COMPLETE, $userid);
    }

    return $grademax;
}

/**
 * Serves locally cached snapshot files through pluginfile.php.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool false if the file was not found, does not return if the file is served
 */
function learningapp_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_login($course, true, $cm);

    if ($filearea !== \mod_learningapp\local\storage_manager::FILEAREA) {
        return false;
    }

    if (!has_capability('mod/learningapp:view', $context)) {
        return false;
    }

    $itemid = (int)array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_learningapp', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, null, 0, $forcedownload, $options);
}
