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
 * manager stores a best-effort, self-contained local snapshot.
 *
 * LearningApps serves its watch/display pages as a two-level structure: the
 * fetched page is only an outer wrapper containing an initially empty
 * <iframe id="frame">, whose real src (.../show.php?id=XXXXX, the actual
 * exercise) is assigned at runtime by an inline bootstrap script. Since we
 * cannot execute JavaScript while fetching, this manager parses that
 * bootstrap script to find the show.php URL, fetches that nested document
 * too, and embeds it (with its own images/scripts/stylesheets) as a
 * data:text/html URI directly on the <iframe>'s src attribute — then
 * removes the bootstrap script and replaces it with a minimal postMessage
 * relay (see amd/src/player.js for the automatic completion detection this
 * enables). Only one level of nesting is followed to keep fetch time and
 * memory bounded.
 *
 * Images, audio, video and fonts referenced anywhere in either document are
 * embedded as base64 data: URIs; external stylesheets/scripts are inlined.
 * Some CDN-hosted assets (fonts in particular) reject direct, referrer-less
 * requests with an HTML error page instead of the real file; this manager
 * sends a Referer/User-Agent header to reduce that, and — critically —
 * verifies the HTTP status and that the response's content-type is
 * plausible for the asset type before embedding it, so a failed fetch is
 * skipped rather than silently embedded as bogus/broken data.
 *
 * Apps that load further content dynamically via JavaScript at runtime
 * (e.g. fetching exercise data from an API after the page has loaded)
 * cannot be captured this way; in that case Moodle falls back to embedding
 * the live external URL. This limitation is documented in the plugin
 * README.
 *
 * To avoid re-fetching (and re-embedding, at real bandwidth/time cost) the
 * same LearningApps app for every course activity that happens to use it,
 * successfully generated snapshots are also cached in a shared, site-level
 * area tagged by the app's LearningApps id (extract_app_id()). Automatic
 * generation (activity save, on-demand HTML download) reuses that shared
 * cache when present; the explicit "store locally now" action always
 * fetches fresh and refreshes the shared cache too.
 *
 * @package     mod_learningapp
 * @copyright   2026 lasjoh-toho
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class storage_manager {

    /** @var string file area used to store each activity's own snapshot */
    const FILEAREA = 'localstorage';

    /** @var string file area used for the shared, app-id-tagged snapshot cache */
    const APPCACHE_FILEAREA = 'appcache';

    /** @var int hard per-asset size cap in bytes, regardless of admin setting */
    const MAX_ASSET_BYTES = 6 * 1024 * 1024;

    /** @var int hard cap on the number of embedded assets, to bound request time */
    const MAX_ASSETS = 80;

    /** @var int fallback total embed budget in MB if no admin setting is stored */
    const DEFAULT_MAX_TOTAL_MB = 15;

    /** @var string realistic user agent, some CDNs reject requests without one */
    const FETCH_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
        . '(KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36 Moodle-mod_learningapp';

    /**
     * Downloads the given LearningApps watch URL and stores a self-contained
     * local snapshot (nested show.php exercise inlined, images/audio/video/
     * fonts embedded as data URIs, stylesheets/scripts inlined) in the
     * Moodle file system.
     *
     * @param int $instanceid learningapp instance id
     * @param string $watchurl canonical https://learningapps.org/watch?v=XXXXX URL
     * @param \context_module $context
     * @param bool $forcerefresh if true, always fetches fresh instead of reusing
     *                           the shared app-id-tagged cache, and refreshes
     *                           that shared cache too
     * @return bool true on success, false if the download failed
     */
    public static function store($instanceid, $watchurl, \context_module $context, $forcerefresh = false) {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        // Clear any previous snapshot for this activity first so stale/broken
        // files never linger, regardless of what happens below.
        self::purge($instanceid, $context);

        $fs = get_file_storage();
        $syscontext = \context_system::instance();
        $appid = self::extract_app_id($watchurl);

        // Dedup: reuse the shared, app-id-tagged snapshot if one already
        // exists and a fresh fetch wasn't explicitly requested.
        if (!$forcerefresh && $appid !== null) {
            $shared = $fs->get_file($syscontext->id, 'mod_learningapp', self::APPCACHE_FILEAREA,
                0, '/' . $appid . '/', 'snapshot.html');
            if ($shared && !$shared->is_directory()) {
                $fs->create_file_from_storedfile([
                    'contextid' => $context->id,
                    'component' => 'mod_learningapp',
                    'filearea'  => self::FILEAREA,
                    'itemid'    => $instanceid,
                    'filepath'  => '/',
                    'filename'  => 'snapshot.html',
                ], $shared);
                return true;
            }
        }

        $curl = new \curl();
        $curl->setopt([
            'CURLOPT_TIMEOUT' => 25,
            'CURLOPT_FOLLOWLOCATION' => true,
            'CURLOPT_MAXREDIRS' => 5,
            'CURLOPT_USERAGENT' => self::FETCH_USER_AGENT,
            'CURLOPT_REFERER' => $watchurl,
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

        $html = self::embed_assets($html, $budget, $watchurl, 0);

        $filerecord = [
            'contextid' => $context->id,
            'component' => 'mod_learningapp',
            'filearea'  => self::FILEAREA,
            'itemid'    => $instanceid,
            'filepath'  => '/',
            'filename'  => 'snapshot.html',
        ];
        $fs->create_file_from_string($filerecord, $html);

        if ($appid !== null) {
            $existingshared = $fs->get_file($syscontext->id, 'mod_learningapp', self::APPCACHE_FILEAREA,
                0, '/' . $appid . '/', 'snapshot.html');
            if ($existingshared) {
                $existingshared->delete();
            }
            $fs->create_file_from_string([
                'contextid' => $syscontext->id,
                'component' => 'mod_learningapp',
                'filearea'  => self::APPCACHE_FILEAREA,
                'itemid'    => 0,
                'filepath'  => '/' . $appid . '/',
                'filename'  => 'snapshot.html',
            ], $html);
        }

        return true;
    }

    /**
     * Extracts the LearningApps app id (the v= parameter) from a canonical
     * watch URL, used to tag the shared dedup cache.
     *
     * @param string $watchurl
     * @return string|null
     */
    protected static function extract_app_id($watchurl) {
        if (preg_match('/[?&]v=([A-Za-z0-9]+)/', $watchurl, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Rewrites external stylesheets/scripts into inline content and
     * external images/media into base64 data: URIs, so the resulting HTML
     * is as self-contained as possible within the configured size budget.
     * At depth 0, also follows LearningApps' nested show.php exercise
     * iframe (see class docblock) one level deep.
     *
     * @param string $html
     * @param \stdClass $budget shared fetch budget/cache (totalbytes, maxtotalbytes, seen, curl)
     * @param string $baseurl URL this HTML was fetched from, used to resolve relative asset paths
     * @param int $depth current nesting depth (0 = outer wrapper, 1 = nested exercise)
     * @return string
     */
    protected static function embed_assets($html, \stdClass $budget, $baseurl, $depth) {
        // Step 0 (outer wrapper only): follow the nested show.php exercise
        // iframe, embed it fully, and neutralise the bootstrap script that
        // would otherwise overwrite our embedded src with a live URL.
        if ($depth === 0 && preg_match('#//learningapps\.org/show\.php\?id=([A-Za-z0-9]+)#i', $html, $idmatch)) {
            $showurl = 'https://learningapps.org/show.php?id=' . $idmatch[1] . '&disableanalytics=1';
            $innerhtml = self::fetch_text($showurl, $budget, $baseurl);
            if ($innerhtml !== null) {
                $innerhtml = self::embed_assets($innerhtml, $budget, $showurl, $depth + 1);
                $datauri = 'data:text/html;charset=utf-8;base64,' . base64_encode($innerhtml);
                $newhtml = preg_replace(
                    '#(<iframe\b[^>]*\bid=["\']frame["\'][^>]*\bsrc=)["\'][^"\']*["\']#i',
                    '$1"' . $datauri . '"',
                    $html,
                    1
                );
                if ($newhtml !== null) {
                    $html = $newhtml;
                    $stripped = preg_replace(
                        '#<script\b[^>]*>(?:(?!</script>).)*setURLs(?:(?!</script>).)*</script>#is',
                        '',
                        $html
                    );
                    if ($stripped !== null) {
                        $html = $stripped;
                    }
                    $relay = '<script>window.addEventListener("message",function(e){'
                        . 'if(window.parent&&window.parent!==window){window.parent.postMessage(e.data,"*");}'
                        . '});</script>';
                    $withrelay = preg_replace('#</body>#i', $relay . '</body>', $html, 1);
                    if ($withrelay !== null) {
                        $html = $withrelay;
                    }
                }
            }
        }

        // Step 1: inline external stylesheets, embedding any url(...) references inside them too.
        $html = preg_replace_callback(
            '#<link\b[^>]*rel=["\']stylesheet["\'][^>]*href=["\']([^"\']+)["\'][^>]*/?>#i',
            function($m) use ($budget, $baseurl) {
                $css = self::fetch_text($m[1], $budget, $baseurl, 'text');
                if ($css === null) {
                    return $m[0];
                }
                $css = self::embed_css_urls($css, $budget, $baseurl);
                return '<style>' . $css . '</style>';
            },
            $html
        );

        // Step 2: inline external <script src="...">...</script> tags (skip ones with inline content already).
        $html = preg_replace_callback(
            '#<script\b((?:(?!src=)[^>])*)\bsrc=["\']([^"\']+)["\']([^>]*)>\s*</script>#i',
            function($m) use ($budget, $baseurl) {
                $js = self::fetch_text($m[2], $budget, $baseurl, 'text');
                if ($js === null) {
                    return $m[0];
                }
                return '<script' . $m[1] . $m[3] . '>' . $js . '</script>';
            },
            $html
        );

        // Step 3: embed images/media referenced via src=/poster= attributes as data URIs.
        $html = preg_replace_callback(
            '#\b(src|poster)=["\']([^"\']+)["\']#i',
            function($m) use ($budget, $baseurl) {
                $datauri = self::fetch_data_uri($m[2], $budget, $baseurl, 'image');
                if ($datauri === null) {
                    return $m[0];
                }
                return $m[1] . '="' . $datauri . '"';
            },
            $html
        );

        // Step 4: embed url(...) references inside any remaining inline <style> blocks.
        $html = preg_replace_callback(
            '#<style\b[^>]*>(.*?)</style>#is',
            function($m) use ($budget, $baseurl) {
                return '<style>' . self::embed_css_urls($m[1], $budget, $baseurl) . '</style>';
            },
            $html
        );

        return $html;
    }

    /**
     * Embeds url(...) references inside a block of CSS as data: URIs.
     * font-face src="url(...)" fallbacks (woff/eot/ttf) are treated as font
     * assets for the content-type plausibility check; anything else as image.
     *
     * @param string $css
     * @param \stdClass $budget
     * @param string $baseurl
     * @return string
     */
    protected static function embed_css_urls($css, \stdClass $budget, $baseurl) {
        return preg_replace_callback(
            '#url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)#i',
            function($m) use ($budget, $baseurl) {
                $expect = preg_match('/\.(woff2?|eot|ttf|otf)(\?|$)/i', $m[1]) ? 'font' : 'image';
                $datauri = self::fetch_data_uri($m[1], $budget, $baseurl, $expect);
                if ($datauri === null) {
                    return $m[0];
                }
                return 'url("' . $datauri . '")';
            },
            $css
        );
    }

    /**
     * Fetches a URL's raw text content (for inlining scripts/stylesheets/the
     * nested exercise document), honouring the shared size budget, a
     * per-request cache, and rejecting failed/implausible responses.
     *
     * @param string $url
     * @param \stdClass $budget
     * @param string $baseurl used to resolve $url if it is relative
     * @param string $expect 'text' (html/js/css) — used for the plausibility check
     * @return string|null null if the URL could not/should not be fetched
     */
    protected static function fetch_text($url, \stdClass $budget, $baseurl, $expect = 'text') {
        $url = self::normalise_url($url, $baseurl);
        if ($url === null) {
            return null;
        }
        if (array_key_exists($url, $budget->seen)) {
            return $budget->seen[$url]['text'] ?? null;
        }
        if (count($budget->seen) >= self::MAX_ASSETS) {
            return null;
        }

        $budget->curl->setopt(['CURLOPT_REFERER' => $baseurl]);
        $content = $budget->curl->get($url);
        $info = $budget->curl->get_info();

        if (!self::response_is_usable($budget->curl, $info, $content, $url, $expect)) {
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
     * shared size budget, a per-request cache, and rejecting failed/
     * implausible responses (e.g. an HTML error page returned instead of
     * the requested image/font, which some CDNs do with a 200 status).
     *
     * @param string $url
     * @param \stdClass $budget
     * @param string $baseurl used to resolve $url if it is relative
     * @param string $expect 'image' or 'font' — used for the plausibility check
     * @return string|null null if the URL could not/should not be embedded
     */
    protected static function fetch_data_uri($url, \stdClass $budget, $baseurl, $expect = 'image') {
        $url = self::normalise_url($url, $baseurl);
        if ($url === null) {
            return null;
        }
        if (array_key_exists($url, $budget->seen)) {
            return $budget->seen[$url]['datauri'] ?? null;
        }
        if (count($budget->seen) >= self::MAX_ASSETS) {
            return null;
        }

        $budget->curl->setopt(['CURLOPT_REFERER' => $baseurl]);
        $content = $budget->curl->get($url);
        $info = $budget->curl->get_info();

        if (!self::response_is_usable($budget->curl, $info, $content, $url, $expect)) {
            $budget->seen[$url] = ['text' => null, 'datauri' => null];
            return null;
        }

        $bytes = strlen($content);
        if ($bytes > self::MAX_ASSET_BYTES || ($budget->totalbytes + $bytes) > $budget->maxtotalbytes) {
            $budget->seen[$url] = ['text' => null, 'datauri' => null];
            return null;
        }

        $mime = trim(explode(';', $info['content_type'] ?? '')[0] ?? '');
        if (empty($mime) || $mime === 'application/octet-stream') {
            $mime = self::guess_mime_from_extension($url);
        }

        $budget->totalbytes += $bytes;
        $datauri = 'data:' . $mime . ';base64,' . base64_encode($content);
        $budget->seen[$url] = ['text' => null, 'datauri' => $datauri];
        return $datauri;
    }

    /**
     * Decides whether a fetched sub-resource response is genuinely usable,
     * rather than a network error or a "soft failure" (some CDNs answer a
     * blocked/hotlink-protected or missing asset request with HTTP 200 and
     * an HTML error page instead of a proper 4xx/5xx status). Embedding such
     * a response would silently corrupt the surrounding HTML/CSS with a
     * plausible-looking but useless data: URI.
     *
     * @param \curl $curl
     * @param array $info curl_getinfo() result for the request just made
     * @param mixed $content the fetched body
     * @param string $url the (already normalised) URL that was fetched, for logging
     * @param string $expect 'text', 'image' or 'font' — what kind of asset this should be
     * @return bool
     */
    protected static function response_is_usable(\curl $curl, array $info, $content, $url, $expect) {
        if ($curl->get_errno() || $content === '' || $content === false) {
            return false;
        }
        $httpcode = (int)($info['http_code'] ?? 0);
        if ($httpcode >= 400 || $httpcode === 0) {
            debugging('mod_learningapp: sub-resource fetch failed (HTTP ' . $httpcode . ') for ' . $url,
                DEBUG_DEVELOPER);
            return false;
        }

        $contenttype = strtolower(trim(explode(';', $info['content_type'] ?? '')[0] ?? ''));
        if (($expect === 'image' || $expect === 'font')
                && ($contenttype === 'text/html' || $contenttype === '')
                && preg_match('/^\s*<(!DOCTYPE|html)/i', (string)$content)) {
            // A binary asset request that actually came back as an HTML
            // document is virtually always an error/blocked-access page,
            // even with a 200 status — never a real image or font.
            debugging('mod_learningapp: expected ' . $expect . ' but got HTML for ' . $url, DEBUG_DEVELOPER);
            return false;
        }

        return true;
    }

    /**
     * Normalises a URL found in the markup (absolute, protocol-relative,
     * absolute-path, or relative) against the page it was found in, and
     * filters out anything we should not (or safely cannot) fetch.
     *
     * @param string $url
     * @param string $baseurl the URL of the document this reference was found in
     * @return string|null
     */
    protected static function normalise_url($url, $baseurl) {
        $url = trim($url);
        if ($url === ''
                || strpos($url, 'data:') === 0
                || strpos($url, '#') === 0
                || strpos($url, 'mailto:') === 0
                || strpos($url, 'javascript:') === 0) {
            return null;
        }
        if (strpos($url, '//') === 0) {
            return 'https:' . $url;
        }
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        $base = @parse_url($baseurl);
        if (empty($base['host'])) {
            return null;
        }
        $scheme = $base['scheme'] ?? 'https';

        if (strpos($url, '/') === 0) {
            return $scheme . '://' . $base['host'] . $url;
        }

        $basepath = isset($base['path']) ? preg_replace('#/[^/]*$#', '/', $base['path']) : '/';
        if ($basepath === '') {
            $basepath = '/';
        }
        return $scheme . '://' . $base['host'] . $basepath . $url;
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
            'eot' => 'application/vnd.ms-fontobject',
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
     * Deletes all locally cached files for an instance (does not touch the
     * shared app-id-tagged cache, which is intentionally kept for reuse by
     * other activities/courses).
     *
     * @param int $instanceid
     * @param \context_module $context
     */
    public static function purge($instanceid, \context_module $context) {
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_learningapp', self::FILEAREA, $instanceid);
    }
}
