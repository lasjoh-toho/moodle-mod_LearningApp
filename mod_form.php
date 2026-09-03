<?php
// This file is part of Moodle - http://moodle.org/
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/learningapp/lib.php');

/**
 * The mod_learningapp activity settings form.
 *
 * @package     mod_learningapp
 * @copyright   2026 lasjoh-toho
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_learningapp_mod_form extends moodleform_mod {

    /**
     * Defines the form fields.
     */
    public function definition() {
        global $CFG;

        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('learningappname', 'mod_learningapp'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        $mform->addElement('text', 'externalurl', get_string('externalurl', 'mod_learningapp'), ['size' => '64']);
        $mform->setType('externalurl', PARAM_URL);
        $mform->addRule('externalurl', null, 'required', null, 'client');
        $mform->addHelpButton('externalurl', 'externalurl', 'mod_learningapp');

        $mform->addElement('float', 'grademax', get_string('grademax', 'mod_learningapp'));
        $mform->setType('grademax', PARAM_FLOAT);
        $mform->setDefault('grademax', 100);
        $mform->addHelpButton('grademax', 'grademax', 'mod_learningapp');
        $mform->addRule('grademax', null, 'required', null, 'client');

        if (get_config('mod_learningapp', 'enable_local_storage')) {
            $mform->addElement('advcheckbox', 'storelocally', get_string('storelocally', 'mod_learningapp'));
            $mform->addHelpButton('storelocally', 'storelocally', 'mod_learningapp');
            $mform->setDefault('storelocally', 0);
        } else {
            $mform->addElement('hidden', 'storelocally', 0);
            $mform->setType('storelocally', PARAM_INT);
        }

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    /**
     * Server-side validation: the external URL must be convertible to the
     * canonical watch?v= format.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $converted = learningapp_transform_url($data['externalurl'] ?? '');
        if ($converted === false) {
            $errors['externalurl'] = get_string('invalidurl', 'mod_learningapp');
        }

        if (isset($data['grademax']) && (float)$data['grademax'] <= 0) {
            $errors['grademax'] = get_string('grademaxpositive', 'mod_learningapp');
        }

        return $errors;
    }

    /**
     * Preprocess data before displaying the form (converts URL for display too).
     *
     * @param array $defaultvalues
     */
    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);
    }
}
