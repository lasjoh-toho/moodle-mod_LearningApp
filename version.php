<?php
// This file is part of Moodle - http://moodle.org/
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Version details for mod_learningapp.
 *
 * @package     mod_learningapp
 * @copyright   2026 lasjoh-toho
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'mod_learningapp';
$plugin->version   = 2026090401;
$plugin->requires  = 2022041900; // Moodle 4.0 (build 20220419) and later.
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.2.0';
$plugin->cron      = 0;
