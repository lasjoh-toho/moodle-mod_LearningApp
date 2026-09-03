<?php
// This file is part of Moodle - http://moodle.org/
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace mod_learningapp\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\deletion_criteria;
use core_privacy\local\request\helper as request_helper;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider for mod_learningapp.
 *
 * @package     mod_learningapp
 * @copyright   2026 lasjoh-toho
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    core_userlist_provider {

    /**
     * Returns metadata about this plugin's personal data stores.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('learningapp_submissions', [
            'userid' => 'privacy:metadata:learningapp_submissions:userid',
            'grade' => 'privacy:metadata:learningapp_submissions:grade',
            'timesubmitted' => 'privacy:metadata:learningapp_submissions:timesubmitted',
        ], 'privacy:metadata:learningapp_submissions');

        $collection->add_external_location_link('learningappserver', [
            'externalurl' => 'privacy:metadata:learningappserver:externalurl',
        ], 'privacy:metadata:learningappserver');

        return $collection;
    }

    /**
     * Returns all contexts containing personal data for the given user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT ctx.id
                  FROM {learningapp_submissions} s
                  JOIN {learningapp} la ON la.id = s.learningappid
                  JOIN {course_modules} cm ON cm.instance = la.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'learningapp'
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel
                 WHERE s.userid = :userid";

        $params = [
            'contextlevel' => CONTEXT_MODULE,
            'userid' => $userid,
        ];

        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, $params);
        return $contextlist;
    }

    /**
     * Returns all userids with data in the given context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('learningapp', $context->instanceid);
        if (!$cm) {
            return;
        }

        $sql = "SELECT userid FROM {learningapp_submissions} WHERE learningappid = :instanceid";
        $userlist->add_from_sql('userid', $sql, ['instanceid' => $cm->instance]);
    }

    /**
     * Exports personal data for the given approved contextlist.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('learningapp', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $submission = $DB->get_record('learningapp_submissions', [
                'learningappid' => $cm->instance,
                'userid' => $user->id,
            ]);

            if ($submission) {
                $data = (object)[
                    'grade' => $submission->grade,
                    'timesubmitted' => \core_privacy\local\request\transform::datetime($submission->timesubmitted),
                ];
                writer::with_context($context)->export_data([], $data);
            }
        }
    }

    /**
     * Deletes all personal data for all users in the given context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('learningapp', $context->instanceid);
        if (!$cm) {
            return;
        }

        $DB->delete_records('learningapp_submissions', ['learningappid' => $cm->instance]);
    }

    /**
     * Deletes personal data for the given user in the given contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('learningapp', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $DB->delete_records('learningapp_submissions', [
                'learningappid' => $cm->instance,
                'userid' => $userid,
            ]);
        }
    }

    /**
     * Deletes personal data for an approved userlist within a context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('learningapp', $context->instanceid);
        if (!$cm) {
            return;
        }

        foreach ($userlist->get_userids() as $userid) {
            $DB->delete_records('learningapp_submissions', [
                'learningappid' => $cm->instance,
                'userid' => $userid,
            ]);
        }
    }
}
