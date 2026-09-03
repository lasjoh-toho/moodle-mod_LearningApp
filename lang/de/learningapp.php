<?php
// This file is part of Moodle - http://moodle.org/
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * German language strings for mod_learningapp.
 *
 * @package     mod_learningapp
 * @copyright   2026 lasjoh-toho
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'LearningApp';
$string['modulename'] = 'LearningApp';
$string['modulenameplural'] = 'LearningApps';
$string['modulename_help'] = 'Mit der Aktivität LearningApp können Sie eine interaktive Übung von learningapps.org direkt in Ihren Moodle-Kurs einbetten. Die eingegebene URL wird automatisch in das korrekte Anzeigeformat umgewandelt, und Lernende können ihre Bearbeitung als erledigt kennzeichnen, um eine Bewertung im Notenbuch zu erhalten.';
$string['pluginadministration'] = 'LearningApp-Administration';

$string['learningappname'] = 'Name der Aktivität';
$string['learningappname_help'] = 'Der Name, unter dem diese Aktivität im Kurs angezeigt wird.';

$string['externalurl'] = 'LearningApps-URL';
$string['externalurl_help'] = 'Fügen Sie den Link zu einer App von learningapps.org ein. Folgende Linkformate werden automatisch erkannt und in das Anzeigeformat (watch?v=…) umgewandelt:

* https://learningapps.org/display?v=XXXXX
* https://learningapps.org/show?v=XXXXX
* https://learningapps.org/viewXXXXX
* https://learningapps.org/watch?v=XXXXX (bereits im Zielformat)';

$string['invalidurl'] = 'Diese URL konnte nicht als gültiger LearningApps-Link erkannt werden. Bitte prüfen Sie den Link und versuchen Sie es erneut.';

$string['grademax'] = 'Maximale Punktzahl';
$string['grademax_help'] = 'Die Punktzahl, die im Notenbuch eingetragen wird, sobald eine Lernperson die Aktivität als erledigt/bestanden abgibt.';
$string['grademaxpositive'] = 'Die maximale Punktzahl muss größer als 0 sein.';

$string['storelocally'] = 'App-Daten lokal in Moodle speichern';
$string['storelocally_help'] = 'Wenn aktiviert, lädt Moodle die Inhalte der App einmalig herunter und speichert eine lokale Kopie im Dateisystem des Kurses. So bleibt die App auch bei Änderungen oder Ausfällen von learningapps.org im Kurs nutzbar. Hinweis: Sehr dynamische Apps können unter Umständen nicht vollständig offline dargestellt werden; in diesem Fall wird automatisch wieder die externe Quelle eingebunden.';
$string['usinglocalcopy'] = 'Diese Aktivität wird aus einer lokal gespeicherten Kopie angezeigt.';

$string['enablelocalstorage'] = 'Lokale Wiederverwendung erlauben';
$string['enablelocalstorage_desc'] = 'Erlaubt Lehrkräften, beim Anlegen einer LearningApp-Aktivität die Option "App-Daten lokal in Moodle speichern" zu nutzen.';

$string['enablehtmldownload'] = 'HTML-Download erlauben';
$string['enablehtmldownload_desc'] = 'Erlaubt Lehrkräften (und weiteren Nutzer:innen mit der Fähigkeit mod/learningapp:downloadhtml), die Aktivität als eigenständige HTML-Datei herunterzuladen. Nutzt bei Bedarf denselben lokalen Schnappschuss-Mechanismus wie "Lokale Wiederverwendung" – siehe README für dessen Einschränkungen bei dynamischen Apps.';
$string['downloadhtml'] = 'Als HTML herunterladen';
$string['htmldownloaddisabled'] = 'Der HTML-Download wurde vom Administrator/von der Administratorin für diese Website nicht freigegeben.';
$string['htmldownloadfailed'] = 'Die App konnte nicht als HTML-Datei heruntergeladen werden. Bitte versuchen Sie es später erneut.';
$string['learningapp:downloadhtml'] = 'LearningApp-Aktivität als HTML herunterladen';

$string['playercontrols'] = 'Player-Steuerung';
$string['fullscreen'] = 'Vollbild';
$string['zoomin'] = 'Vergrößern';
$string['zoomout'] = 'Verkleinern';
$string['zoomreset'] = 'Zoom zurücksetzen';

$string['markascomplete'] = 'Als Erledigt / Bestanden abgeben';
$string['alreadysubmitted'] = 'Bereits abgegeben';
$string['submitsuccess'] = 'Abgabe erfolgreich gespeichert.';
$string['submiterror'] = 'Die Abgabe konnte nicht gespeichert werden. Bitte versuchen Sie es erneut.';

$string['eventcoursemoduleviewed'] = 'LearningApp-Aktivität angesehen';

$string['learningapp:addinstance'] = 'Neue LearningApp-Aktivität anlegen';
$string['learningapp:view'] = 'LearningApp-Aktivität ansehen';
$string['learningapp:submit'] = 'LearningApp-Aktivität als erledigt abgeben';
$string['learningapp:managesubmissions'] = 'Abgaben der LearningApp-Aktivität verwalten';

$string['privacy:metadata:learningapp_submissions'] = 'Informationen über die Abgaben von Nutzer:innen bei einer LearningApp-Aktivität.';
$string['privacy:metadata:learningapp_submissions:userid'] = 'Die ID der Nutzerin/des Nutzers, die/der die Aktivität abgegeben hat.';
$string['privacy:metadata:learningapp_submissions:grade'] = 'Die für die Abgabe vergebene Punktzahl.';
$string['privacy:metadata:learningapp_submissions:timesubmitted'] = 'Der Zeitpunkt der Abgabe.';
$string['privacy:metadata:learningappserver'] = 'Um die Übung darzustellen, tauscht das LearningApp-Modul Daten mit der externen Plattform learningapps.org aus.';
$string['privacy:metadata:learningappserver:externalurl'] = 'Die URL der eingebetteten App wird an learningapps.org übermittelt, um die Inhalte anzuzeigen.';

$string['missingidandcmid'] = 'Es muss entweder die Kurs-Modul-ID oder die Instanz-ID angegeben werden.';
