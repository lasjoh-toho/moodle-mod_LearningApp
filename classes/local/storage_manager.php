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
 * manager stores a best-effort, self-contained local snapshot: the watch
 * page HTML is fetched, and any stylesheets, scripts, images and other
 * media it references are fetched too and embedded directly into the
 * snapshot (stylesheets/scripts inlined as <style>/<script> content, images
 * and other media as base64 data: URIs). Apps that load additional
 * resources dynamically via JavaScript at runtime (e.g. fetching exercise
 * data from an API after the page has loaded) cannot be captured this way;
 * in that case Moodle transparently falls back to embedding the live
 * external URL. This limitation is documented in the plugin README.
 *
 * @package     mod_learningapp
 * @copyright   2026 lasjoh-toho
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class storage_manager {

    /** @var string file area used to store cached snapshots */
    const FILEAREA = 'localstorage';

    /** @var int hard per-asset size cap in bytes, regardless of admin setting */
    const MAX_ASSET_BYTES = 6 * 1024 * 1024;

    /** @var int hard cap on the number of embedded assets, to bound request time */
    const MAX_ASSETS = 80;

    /** @var int fallback total embed budget in MB if no admin setting is stored */
    const DEFAULT_MAX_TOTAL_MB = 15;

    /**
     * Downloads the given LearningApps watch URL and stores a self-contained
     * local snapshot (images/audio/video/fonts embedded as data URIs,
     * stylesheets/scripts inlined) in the Moodle file system.
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
            'CURLOPT_TIMEOUT' => 25,
            'CURLOPT_FOLLOWLOCATION' => true,
            'CURLOPT_MAXREDIRS' => 5,
        ]);
        $html = $curl->get($watchurl);
        $info = $curl->get_info();

        if ($curl->get_errno() || empty($html) || (int)($info['http_code'] ?? 0) >= 400) {
            debugging('mod_learningapp: could not fetch local snapshot for ' . $watchurl, DEBUG_DEVELOPER);
            return false;
        }

        $maxtotalmb = (int)get_config('mod_learningapp', 'local_storage_max_size_mb');
        if ($maxtotalmb <= 0) {
            $maxtotalmb = self::DEFAULT_MAX_TOTAL_MB;
        }
        $maxtotalbytes = $maxtotalmb * 1024 * 1024;

        $budget = new \stdClass();
        $budget->totalbytes = 0;
        $budget->maxtotalbytes = $maxtotalbytes;
        $budget->seen = [];
        $budget->curl = $curl;

        $html = self::embed_assets($html, $budget);

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

        return true;
    }

    /**
     * Rewrites external stylesheets/scripts into inline content and
     * external images/media into base64 data: URIs, so the resulting HTML
     * is as self-contained as possible within the configured size budget.
     *
     * @param string $html
     * @param \stdClass $budget shared fetch budget/cache (totalbytes, maxtotalbytes, seen, curl)
     * @return string
     */
    protected static function embed_assets($html, \stdClass $budget) {
        // 1) Inline external stylesheets, embedding any url(...) references inside them too.
        $html = preg_replace_callback(
            '#<link\b[^>]*rel=["\']stylesheet["\'][^>]*href=["\']([^"\']+)["\'][^>]*/?>#i',
            function($m) use ($budget) {
                $css = self::fetch_text($m[1], $budget);
                if ($css === null) {
                    return $m[0];
                }
                $css = self::embed_css_urls($css, $budget);
                return '<style>' . $css . '</style>';
            },
            $html
        );

        // 2) Inline external <script src="...">...</script> tags (skip ones with inline content already).
        $html = preg_replace_callback(
            '#<script\b((?:(?!src=)[^>])*)\bsrc=["\']([^"\']+)["\']([^>]*)>\s*</script>#i',
            function($m) use ($budget) {
                $js = self::fetch_text($m[2], $budget);
                if ($js === null) {
                    return $m[0];
                }
                return '<script' . $m[1] . $m[3] . '>' . $js . '</script>';
            },
            $html
        );

        // 3) Embed images/media referenced via src=/poster= attributes as data URIs.
        $html = preg_replace_callback(
            '#\b(src|poster)=["\']((?:https?:)?//[^"\']+)["\']#i',
            function($m) use ($budget) {
                $datauri = self::fetch_data_uri($m[2], $budget);
                if ($datauri === null) {
                    return $m[0];
                }
                return $m[1] . '="' . $datauri . '"';
            },
            $html
        );

        // 4) Embed url(...) references inside any remaining inline <style> blocks.
        $html = preg_replace_callback(
            '#<style\b[^>]*>(.*?)</style>#is',
            function($m) use ($budget) {
                return '<style>' . self::embed_css_urls($m[1], $budget) . '</style>';
            },
            $html
        );

        return $html;
    }

    /**
     * Embeds url(...) references inside a block of CSS as data: URIs.
     *
     * @param string $css
     * @param \stdClass $budget
     * @return string
     */
    protected static function embed_css_urls($css, \stdClass $budget) {
        return preg_replace_callback(
            '#url\(\s*[\'"]?((?:https?:)?//[^\'")]+)[\'"]?\s*\)#i',
            function($m) use ($budget) {
                $datauri = self::fetch_data_uri($m[1], $budget);
                if ($datauri === null) {
                    return $m[0];
                }
                return 'url("' . $datauri . '")';
            },
            $css
        );
    }

    /**
     * Fetches a URL's raw text content (for inlining scripts/stylesheets),
     * honouring the shared size budget and a per-request cache.
     *
     * @param string $url
     * @param \stdClass $budget
     * @return string|null null if the URL could not/should not be fetched
     */
    protected static function fetch_text($url, \stdClass $budget) {
        $url = self::normalise_url($url);
        if ($url === null) {
            return null;
        }
        if (array_key_exists($url, $budget->seen)) {
            return $budget->seen[$url]['text'] ?? null;
        }
        if (count($budget->seen) >= self::MAX_ASSETS) {
            return null;
        }

        $content = $budget->curl->get($url);
        if ($budget->curl->get_errno() || $content === '' || $content === false) {
            $budget->seen[$url] = ['text' => null, 'datauri' => null];
            return null;
        }

        $bytes = strlen($content);
        if ($bytes > self::MAX_ASSET_BYTES || ($budget->totalbytes + $bytes) > $budget->maxtotalbytes) {
            $budget->seen[$url] = ['text' => null, 'datauri' => null];
            return null;
        }

        $budget->totalbytes += $bytes;
        $budget->seen[$url] = ['text' => $content, 'datauri' => null];
        return $content;
    }

    /**
     * Fetches a URL and returns it as a base64 data: URI, honouring the
     * shared size budget and a per-request cache.
     *
     * @param string $url
     * @param \stdClass $budget
     * @return string|null null if the URL could not/should not be embedded
     */
    protected static function fetch_data_uri($url, \stdClass $budget) {
        $url = self::normalise_url($url);
        if ($url === null) {
            return null;
        }
        if (array_key_exists($url, $budget->seen)) {
            return $budget->seen[$url]['datauri'] ?? null;
        }
        if (count($budget->seen) >= self::MAX_ASSETS) {
            return null;
        }

        $content = $budget->curl->get($url);
        $info = $budget->curl->get_info();
        if ($budget->curl->get_errno() || $content === '' || $content === false) {
            $budget->seen[$url] = ['text' => null, 'datauri' => null];
            return null;
        }

        $bytes = strlen($content);
        if ($bytes > self::MAX_ASSET_BYTES || ($budget->totalbytes + $bytes) > $budget->maxtotalbytes) {
            $budget->seen[$url] = ['text' => null, 'datauri' => null];
            return null;
        }

        $mime = $info['content_type'] ?? '';
        $mime = trim(explode(';', $mime)[0] ?? '');
        if (empty($mime) || $mime === 'application/octet-stream') {
            $mime = self::guess_mime_from_extension($url);
        }

        $budget->totalbytes += $bytes;
        $datauri = 'data:' . $mime . ';base64,' . base64_encode($content);
        $budget->seen[$url] = ['text' => null, 'datauri' => $datauri];
        return $datauri;
    }

    /**
     * Normalises a URL found in the markup and filters out anything we
     * should not (or safely cannot) fetch.
     *
     * @param string $url
     * @return string|null
     */
    protected static function normalise_url($url) {
        $url = trim($url);
        if ($url === ''
                || strpos($url, 'data:') === 0
                || strpos($url, '#') === 0
                || strpos($url, 'mailto:') === 0
                || strpos($url, 'javascript:') === 0) {
            return null;
        }
        if (strpos($url, '//') === 0) {
            $url = 'https:' . $url;
        }
        if (!preg_match('#^https?://#i', $url)) {
            // Relative paths cannot be safely resolved without knowing the
            // original page's base URL context; leave them untouched.
            return null;
        }
        return $url;
    }

    /**
     * Best-effort MIME type guess from a URL's file extension, used when
     * the server did not send a usable Content-Type header.
     *
     * @param string $url
     * @return string
     */
    protected static function guess_mime_from_extension($url) {
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
        $map = [
            'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'webp' => 'image/webp',
            'ico' => 'image/x-icon', 'bmp' => 'image/bmp',
            'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'ogg' => 'audio/ogg', 'oga' => 'audio/ogg',
            'mp4' => 'video/mp4', 'webm' => 'video/webm', 'ogv' => 'video/ogg',
            'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf', 'otf' => 'font/otf',
            'css' => 'text/css', 'js' => 'application/javascript', 'json' => 'application/json',
        ];
        return $map[$ext] ?? 'application/octet-stream';
    }

    /**
     * Whether a local snapshot exists for the given instance.
     *
     * @param int $instanceid
     * @param \context_module $context
     * @return bool
     */
    public static function has_local_copy($instanceid, \context_module $context) {
        return self::get_snapshot_file($instanceid, $context) !== null;
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
     * Returns the stored snapshot file object, if any.
     *
     * @param int $instanceid
     * @param \context_module $context
     * @return \stored_file|null
     */
    public static function get_snapshot_file($instanceid, \context_module $context) {
        $fs = get_file_storage();
        $file = $fs->get_file($context->id, 'mod_learningapp', self::FILEAREA, $instanceid, '/', 'snapshot.html');
        if (!$file || $file->is_directory()) {
            return null;
        }
        return $file;
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
