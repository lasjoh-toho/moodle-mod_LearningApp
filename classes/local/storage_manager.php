<?php
// This file is part of Moodle - http://moodle.org/
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace mod_learningapp\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Handles the optional local caching of a LearningApps app so that it can
 * be reused if the app changes or the external platform is unavailable.
 *
 * Note: LearningApps does not publish an official export/download API. This
 * manager stores a best-effort local snapshot of the watch page HTML plus
 * any same-origin static assets it can discover in that markup. Apps that
 * load additional resources dynamically via JavaScript at runtime cannot be
 * fully mirrored offline; in that case Moodle transparently falls back to
 * embedding the live external URL. This limitation is documented in the
 * plugin README.
 *
 * @package     mod_learningapp
 * @copyright   2026 lasjoh-toho
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class storage_manager {

    /** @var string file area used to store cached snapshots */
    const FILEAREA = 'localstorage';

    /**
     * Downloads and stores a local snapshot of the given LearningApps watch URL.
     *
     * @param int $instanceid learningapp instance id
     * @param string $watchurl canonical https://learningapps.org/watch?v=XXXXX URL
     * @param \context_module $context
     * @return bool true on success, false if the download failed
     */
    public static function store($instanceid, $watchurl, \context_module $context) {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        // Clear any previous snapshot first so stale files never linger.
        self::purge($instanceid, $context);

        $curl = new \curl();
        $curl->setopt([
            'CURLOPT_TIMEOUT' => 20,
            'CURLOPT_FOLLOWLOCATION' => true,
            'CURLOPT_MAXREDIRS' => 5,
        ]);
        $html = $curl->get($watchurl);
        $info = $curl->get_info();

        if ($curl->get_errno() || empty($html) || (int)($info['http_code'] ?? 0) >= 400) {
            debugging('mod_learningapp: could not fetch local snapshot for ' . $watchurl, DEBUG_DEVELOPER);
            return false;
        }

        $fs = get_file_storage();
        $filerecord = [
            'contextid' => $context->id,
            'component' => 'mod_learningapp',
            'filearea'  => self::FILEAREA,
            'itemid'    => $instanceid,
            'filepath'  => '/',
            'filename'  => 'snapshot.html',
        ];
        $fs->create_file_from_string($filerecord, $html);

        // Best-effort: also try to cache the same-origin JS/CSS assets referenced
        // in the markup so the snapshot has a chance of rendering standalone.
        if (preg_match_all('#(?:src|href)=["\'](https://learningapps\.org/[^"\']+\.(?:js|css))["\']#i',
                $html, $matches)) {
            $seen = [];
            foreach (array_unique($matches[1]) as $assturl) {
                if (isset($seen[$assturl]) || count($seen) >= 25) {
                    continue;
                }
                $seen[$assturl] = true;
                $assetcontent = $curl->get($assturl);
                if ($curl->get_errno() || empty($assetcontent)) {
                    continue;
                }
                $fs->create_file_from_string([
                    'contextid' => $context->id,
                    'component' => 'mod_learningapp',
                    'filearea'  => self::FILEAREA,
                    'itemid'    => $instanceid,
                    'filepath'  => '/assets/',
                    'filename'  => basename(parse_url($assturl, PHP_URL_PATH)),
                ], $assetcontent);
            }
        }

        return true;
    }

    /**
     * Whether a local snapshot exists for the given instance.
     *
     * @param int $instanceid
     * @param \context_module $context
     * @return bool
     */
    public static function has_local_copy($instanceid, \context_module $context) {
        $fs = get_file_storage();
        $file = $fs->get_file($context->id, 'mod_learningapp', self::FILEAREA, $instanceid, '/', 'snapshot.html');
        return $file && !$file->is_directory();
    }

    /**
     * Returns the pluginfile.php URL to the locally stored snapshot, if any.
     *
     * @param int $instanceid
     * @param \context_module $context
     * @return \moodle_url|null
     */
    public static function get_local_url($instanceid, \context_module $context) {
        if (!self::has_local_copy($instanceid, $context)) {
            return null;
        }
        return \moodle_url::make_pluginfile_url($context->id, 'mod_learningapp', self::FILEAREA,
            $instanceid, '/', 'snapshot.html');
    }

    /**
     * Deletes all locally cached files for an instance.
     *
     * @param int $instanceid
     * @param \context_module $context
     */
    public static function purge($instanceid, \context_module $context) {
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_learningapp', self::FILEAREA, $instanceid);
    }
}
