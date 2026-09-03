<?php
// This file is part of Moodle - http://moodle.org/
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Player view for mod_learningapp.
 *
 * @package     mod_learningapp
 * @copyright   2026 lasjoh-toho
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/learningapp/lib.php');

$id = optional_param('id', 0, PARAM_INT);       // Course_module ID.
$a  = optional_param('a', 0, PARAM_INT);        // Learningapp instance ID.

if ($id) {
    $cm = get_coursemodule_from_id('learningapp', $id, 0, false, MUST_EXIST);
    $course = get_course($cm->course);
    $learningapp = $DB->get_record('learningapp', ['id' => $cm->instance], '*', MUST_EXIST);
} else if ($a) {
    $learningapp = $DB->get_record('learningapp', ['id' => $a], '*', MUST_EXIST);
    $course = get_course($learningapp->course);
    $cm = get_coursemodule_from_instance('learningapp', $learningapp->id, $course->id, false, MUST_EXIST);
} else {
    throw new moodle_exception('missingparameter');
}

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/learningapp:view', $context);

$event = \mod_learningapp\event\course_module_viewed::create([
    'objectid' => $learningapp->id,
    'context'  => $context,
]);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('learningapp', $learningapp);
$event->trigger();

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$PAGE->set_url('/mod/learningapp/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($learningapp->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_cm($cm, $course);
$PAGE->add_body_class('mod-learningapp-page');

$PAGE->requires->css('/mod/learningapp/styles.css');

$embedurl = $learningapp->externalurl;
$uselocal = false;
if (!empty($learningapp->storelocally)) {
    $localurl = \mod_learningapp\local\storage_manager::get_local_url($learningapp->id, $context);
    if ($localurl) {
        $embedurl = $localurl->out(false);
        $uselocal = true;
    }
}

$cansubmit = has_capability('mod/learningapp:submit', $context) && !isguestuser();
$alreadysubmitted = false;
if ($cansubmit) {
    $alreadysubmitted = $DB->record_exists('learningapp_submissions', [
        'learningappid' => $learningapp->id,
        'userid' => $USER->id,
    ]);
}

$jsparams = [
    'cmid'      => $cm->id,
    'sesskey'   => sesskey(),
    'ajaxurl'   => (new moodle_url('/mod/learningapp/ajax_submit.php'))->out(false),
    'strings'   => [
        'submitsuccess' => get_string('submitsuccess', 'mod_learningapp'),
        'submiterror'   => get_string('submiterror', 'mod_learningapp'),
        'alreadysubmitted' => get_string('alreadysubmitted', 'mod_learningapp'),
    ],
];
$PAGE->requires->js_call_amd('mod_learningapp/player', 'init', [$jsparams]);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($learningapp->name));

if ($learningapp->intro) {
    echo $OUTPUT->box(format_module_intro('learningapp', $learningapp, $cm->id), 'generalbox mod_introbox', 'learningappintro');
}

if ($uselocal) {
    echo $OUTPUT->notification(get_string('usinglocalcopy', 'mod_learningapp'), 'info');
}
?>

<div class="learningapp-toolbar" role="toolbar" aria-label="<?php p(get_string('playercontrols', 'mod_learningapp')); ?>">
    <button type="button" class="btn btn-secondary la-zoom-out" title="<?php p(get_string('zoomout', 'mod_learningapp')); ?>">&minus;</button>
    <button type="button" class="btn btn-secondary la-zoom-reset" title="<?php p(get_string('zoomreset', 'mod_learningapp')); ?>">100%</button>
    <button type="button" class="btn btn-secondary la-zoom-in" title="<?php p(get_string('zoomin', 'mod_learningapp')); ?>">+</button>
    <button type="button" class="btn btn-secondary la-fullscreen" title="<?php p(get_string('fullscreen', 'mod_learningapp')); ?>">
        <?php p(get_string('fullscreen', 'mod_learningapp')); ?>
    </button>
</div>

<div class="learningapp-container" id="learningapp-container">
    <div class="learningapp-frame-wrap" id="learningapp-frame-wrap">
        <iframe id="learningapp-frame"
                class="learningapp-frame"
                src="<?php echo s($embedurl); ?>"
                allowfullscreen="true"
                loading="lazy"
                title="<?php p(format_string($learningapp->name)); ?>"></iframe>
    </div>
</div>

<?php if ($cansubmit): ?>
<div class="learningapp-completion mt-3">
    <button type="button"
            id="learningapp-submit"
            class="btn btn-primary"
            data-instanceid="<?php echo (int)$learningapp->id; ?>"
            <?php if ($alreadysubmitted) echo 'disabled'; ?>>
        <?php echo $alreadysubmitted
            ? get_string('alreadysubmitted', 'mod_learningapp')
            : get_string('markascomplete', 'mod_learningapp'); ?>
    </button>
    <span id="learningapp-submit-feedback" class="ms-2" aria-live="polite"></span>
</div>
<?php endif; ?>

<?php
echo $OUTPUT->footer();
