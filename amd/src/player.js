// This file is part of Moodle - http://moodle.org/
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Player controls (fullscreen, zoom) and AJAX completion submission for mod_learningapp.
 *
 * @module     mod_learningapp/player
 * @copyright  2026 lasjoh-toho
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    var ZOOM_STEP = 0.1;
    var ZOOM_MIN = 0.5;
    var ZOOM_MAX = 2.0;

    /**
     * Initialise the player controls.
     *
     * @param {Object} params cmid, sesskey, ajaxurl, strings
     */
    var init = function(params) {
        var container = document.getElementById('learningapp-container');
        var frameWrap = document.getElementById('learningapp-frame-wrap');
        var zoomResetBtn = document.querySelector('.la-zoom-reset');
        var zoomInBtn = document.querySelector('.la-zoom-in');
        var zoomOutBtn = document.querySelector('.la-zoom-out');
        var fullscreenBtn = document.querySelector('.la-fullscreen');
        var submitBtn = document.getElementById('learningapp-submit');
        var feedback = document.getElementById('learningapp-submit-feedback');

        var scale = 1.0;

        var applyZoom = function() {
            if (frameWrap) {
                frameWrap.style.transform = 'scale(' + scale + ')';
                frameWrap.style.transformOrigin = 'top left';
            }
            if (zoomResetBtn) {
                zoomResetBtn.textContent = Math.round(scale * 100) + '%';
            }
        };

        if (zoomInBtn) {
            zoomInBtn.addEventListener('click', function() {
                scale = Math.min(ZOOM_MAX, parseFloat((scale + ZOOM_STEP).toFixed(2)));
                applyZoom();
            });
        }
        if (zoomOutBtn) {
            zoomOutBtn.addEventListener('click', function() {
                scale = Math.max(ZOOM_MIN, parseFloat((scale - ZOOM_STEP).toFixed(2)));
                applyZoom();
            });
        }
        if (zoomResetBtn) {
            zoomResetBtn.addEventListener('click', function() {
                scale = 1.0;
                applyZoom();
            });
        }

        if (fullscreenBtn && container) {
            fullscreenBtn.addEventListener('click', function() {
                if (container.requestFullscreen) {
                    container.requestFullscreen();
                } else if (container.webkitRequestFullscreen) {
                    container.webkitRequestFullscreen();
                } else if (container.msRequestFullscreen) {
                    container.msRequestFullscreen();
                }
            });
        }

        if (submitBtn) {
            submitBtn.addEventListener('click', function() {
                submitBtn.setAttribute('disabled', 'disabled');
                var body = 'cmid=' + encodeURIComponent(params.cmid) +
                    '&sesskey=' + encodeURIComponent(params.sesskey);

                window.fetch(params.ajaxurl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: body,
                    credentials: 'same-origin'
                }).then(function(resp) {
                    return resp.json();
                }).then(function(data) {
                    if (data.success) {
                        submitBtn.textContent = params.strings.alreadysubmitted;
                        if (feedback) {
                            feedback.textContent = params.strings.submitsuccess;
                        }
                    } else {
                        submitBtn.removeAttribute('disabled');
                        if (feedback) {
                            feedback.textContent = data.message || params.strings.submiterror;
                        }
                    }
                }).catch(function() {
                    submitBtn.removeAttribute('disabled');
                    if (feedback) {
                        feedback.textContent = params.strings.submiterror;
                    }
                });
            });
        }
    };

    return {init: init};
});
