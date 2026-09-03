<?php
// This file is part of Moodle - http://moodle.org/
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * English language strings for mod_learningapp.
 *
 * @package     mod_learningapp
 * @copyright   2026 lasjoh-toho
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'LearningApp';
$string['modulename'] = 'LearningApp';
$string['modulenameplural'] = 'LearningApps';
$string['modulename_help'] = 'The LearningApp activity lets you embed an interactive exercise from learningapps.org directly into your Moodle course. Any URL you enter is automatically converted into the correct display format, and learners can mark their attempt as complete to receive a grade in the gradebook.';
$string['pluginadministration'] = 'LearningApp administration';

$string['learningappname'] = 'Activity name';
$string['learningappname_help'] = 'The name shown for this activity in the course.';

$string['externalurl'] = 'LearningApps URL';
$string['externalurl_help'] = 'Paste the link to an app from learningapps.org. The following link formats are automatically detected and converted to the display format (watch?v=…):

* https://learningapps.org/display?v=XXXXX
* https://learningapps.org/show?v=XXXXX
* https://learningapps.org/viewXXXXX
* https://learningapps.org/watch?v=XXXXX (already in the target format)';

$string['invalidurl'] = 'This URL could not be recognised as a valid LearningApps link. Please check the link and try again.';

$string['grademax'] = 'Maximum grade';
$string['grademax_help'] = 'The number of points recorded in the gradebook once a learner submits the activity as complete/passed.';
$string['grademaxpositive'] = 'The maximum grade must be greater than 0.';

$string['storelocally'] = 'Store app data locally in Moodle';
$string['storelocally_help'] = 'When enabled, Moodle downloads the app content once and stores a local copy in the course file storage. This keeps the app usable in the course even if it changes, or if learningapps.org is unavailable. Note: highly dynamic apps may not be fully renderable offline; in that case Moodle automatically falls back to the external source.';
$string['usinglocalcopy'] = 'This activity is being displayed from a locally stored copy.';

$string['enablelocalstorage'] = 'Allow local reuse';
$string['enablelocalstorage_desc'] = 'Allows teachers to use the "Store app data locally in Moodle" option when creating a LearningApp activity.';

$string['playercontrols'] = 'Player controls';
$string['fullscreen'] = 'Fullscreen';
$string['zoomin'] = 'Zoom in';
$string['zoomout'] = 'Zoom out';
$string['zoomreset'] = 'Reset zoom';

$string['markascomplete'] = 'Submit as complete / passed';
$string['alreadysubmitted'] = 'Already submitted';
$string['submitsuccess'] = 'Submission saved successfully.';
$string['submiterror'] = 'The submission could not be saved. Please try again.';

$string['eventcoursemoduleviewed'] = 'LearningApp activity viewed';

$string['learningapp:addinstance'] = 'Add a new LearningApp activity';
$string['learningapp:view'] = 'View LearningApp activity';
$string['learningapp:submit'] = 'Submit LearningApp activity as complete';
$string['learningapp:managesubmissions'] = 'Manage LearningApp activity submissions';

$string['privacy:metadata:learningapp_submissions'] = 'Information about a user\'s submissions for a LearningApp activity.';
$string['privacy:metadata:learningapp_submissions:userid'] = 'The ID of the user who submitted the activity.';
$string['privacy:metadata:learningapp_submissions:grade'] = 'The grade recorded for the submission.';
$string['privacy:metadata:learningapp_submissions:timesubmitted'] = 'The time the submission was made.';
$string['privacy:metadata:learningappserver'] = 'To display the exercise, the LearningApp module exchanges data with the external learningapps.org platform.';
$string['privacy:metadata:learningappserver:externalurl'] = 'The URL of the embedded app is sent to learningapps.org in order to display its content.';

$string['missingidandcmid'] = 'Either the course module ID or the instance ID must be specified.';
