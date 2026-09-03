<?php
// This file is part of Moodle - http://moodle.org/
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configcheckbox(
        'mod_learningapp/enable_local_storage',
        get_string('enablelocalstorage', 'mod_learningapp'),
        get_string('enablelocalstorage_desc', 'mod_learningapp'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'mod_learningapp/local_storage_max_size_mb',
        get_string('localstoragemaxsize', 'mod_learningapp'),
        get_string('localstoragemaxsize_desc', 'mod_learningapp'),
        15,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'mod_learningapp/enable_html_download',
        get_string('enablehtmldownload', 'mod_learningapp'),
        get_string('enablehtmldownload_desc', 'mod_learningapp'),
        0
    ));
}
