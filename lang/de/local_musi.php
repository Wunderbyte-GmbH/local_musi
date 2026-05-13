<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin strings are defined here.
 *
 * @package     local_musi
 * @category    string
 * @copyright   2022 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['action'] = 'Aktion';
$string['add_sports_division'] = 'Die Sparten zu den Sportarten automatisch hinzufügen';
$string['addbookinginstance'] = '<span class="bg-danger font-weight-bold">Keine Semester-Instanz! Hier klicken, um eine einzustellen.</span>';
$string['additionalpricetext'] = '<div> Zuzüglich einer Buchungsgebühr von maximal €3,- </div>';
$string['additionalsettings'] = 'Sonstige Einstellungen';
$string['allcourses'] = 'Alle Kurse';
$string['archive'] = '<i class="fa fa-archive" aria-hidden="true"></i> Archiv';
$string['autoaddtosubstitutionspool'] = 'LehrerInnen automatisch in VertreterInnen-Pool ihrer Sportart eintragen?';
$string['birthdateprofilefield'] = 'Profilfeld für das Geburtsdatum';
$string['birthdateprofilefielddesc'] = 'Wählen Sie das benutzerdefinierte Nutzerprofilfeld, in dem das Geburtsdatum gespeichert wird.';
$string['bookable'] = 'Buchbar';
$string['bookedorder'] = 'Bezahlt';
$string['bookinganswersdeleted'] = "Gelöschte Buchungen";
$string['bookingsalt'] = 'Anzahl der freien und maximal verfügbaren Kursplätze';
$string['cachedef_cachedpaymenttable'] = 'Zahlungstransaktionen (Cache)';
$string['campaigns'] = 'Kampagnen';
$string['cashier'] = 'Kassa';
$string['changebookinginstance'] = 'Standard-Semester-Instanz setzen';
$string['checkstatus'] = 'Überprüfe Status';
$string['collapsedescriptionmaxlength'] = 'Beschreibungen einklappen (Zeichenanzahl)';
$string['collapsedescriptionmaxlength_desc'] = 'Geben Sie die maximale Anzahl an Zeichen, die eine Beschreibung haben darf, ein.
Beschreibungen, die länger sind werden eingeklappt.';
$string['collapsedescriptionoff'] = 'Beschreibungen nicht einklappen';
$string['contractformula'] = 'Vertragsformel';
$string['contractformula_desc'] = 'Hier können Sie eine JSON-Formel angeben, die festlegt, wie sich Verträge auf Abrechnungen
 auswirken und welche Sonderfälle es gibt.';
$string['contractformulatest'] = 'Vertragsformel testen';
$string['contractmanagementsettings'] = 'Vertragsmanagement-Einstellungen';
$string['contractmanagementsettings_desc'] = 'Konfigurieren Sie hier, wie sich Verträge auf Abrechnungen
 auswirken und welche Sonderfälle es gibt.';
$string['coursename'] = 'Kursname';
$string['coursesavailable'] = "Buchbare Kurse";
$string['coursesbooked'] = 'Gebuchte Kurse';
$string['coursesboughtcard'] = 'Gekaufte Kurse (Online)';
$string['coursesboughtcashier'] = 'Gekaufte Kurse (Kassa)';
$string['coursesdeleted'] = 'Gelöschte Kurse';
$string['coursesibooked'] = '<i class="fa fa-ticket" aria-hidden="true"></i> Kurse, die ich im aktuellen Semester gebucht habe:';
$string['coursesibookedarchive'] = 'Kurse, die ich in vergangenen Semestern gebucht habe:';
$string['coursesincart'] = 'Im Warenkorb';
$string['coursesiteach'] = '<i class="fa fa-graduation-cap" aria-hidden="true"></i> Kurse, die ich im aktuellen Semester unterrichte:';
$string['coursesiteacharchive'] = 'Kurse, die ich in vergangenen Semestern unterrichtet habe:';
$string['coursespending'] = 'Noch unbestätigte Kurse';
$string['create_sap_files'] = 'Die täglichen SAP Dateien erstellen.';
$string['createbotag'] = 'Neuen Tag anlegen...';
$string['createbotag:helptext'] = '<p>
<a data-toggle="collapse" data-bs-toggle="collapse" href="#collapseTagsHelptext" role="button" aria-expanded="false" aria-controls="collapseTagsHelptext">
  <i class="fa fa-question-circle" aria-hidden="true"></i><span>&nbsp;Hilfe: So können Sie Tags konfigurieren...</span>
</a>
</p>
<div class="collapse" id="collapseTagsHelptext">
<div class="card card-body">
  <p>Damit Sie Tags verwenden können, müssen Sie ein Benutzerdefiniertes Buchungsoptionsfeld vom Typ "Dynamic Dropdown menu" mit folgenden Einstellungen anlegen:</p>
  <ul>
  <li><strong>Kategorie: </strong>Tags</li>
  <li><strong>Name: </strong>Tags</li>
  <li><strong>Kurzname: </strong>botags</li>
  <li><strong>SQL query: </strong><code>SELECT botag as id, botag as data FROM {local_musi_botags}</code></li>
  <li><strong>Auto-complete: </strong><span class="text-success">aktiviert</span></li>
  <li><strong>Multi select: </strong><span class="text-success">aktiviert</span></li>
  </ul>
  <p>Nun können Sie die hier angelegten Tags den Buchungsoptionen zuordnen.<br>Sie müssen hier mindestens einen Tag angelegt haben, damit Sie Tagging verwenden können.</p>
</div>
</div>';
$string['customorderid'] = 'CustomOrderID';
$string['dashboard'] = 'Dashboard';
$string['dayofweek'] = 'Wochentag';
$string['dayofweekalt'] = 'Wochentag und Termin, an dem eine Kurseinheit stattfindet';
$string['easyavailability:closingtime'] = 'Kann gebucht werden bis';
$string['easyavailability:formincompatible'] = '<div class="alert alert-warning">Diese Buchungsoption verwendet Einschränkungen,
 die mit diesem Formular nicht kompatibel sind. Bitte wenden Sie sich an einen M:USI-Admin.</div>';
$string['easyavailability:heading'] = '<div class="alert alert-info">Sie bearbeiten die Verfügbarkeit von "<b>{$a}</b>"</div>';
$string['easyavailability:openingtime'] = 'Kann gebucht werden ab';
$string['easyavailability:overbook'] = 'Sogar dann, wenn der Kurs <b>ausgebucht</b> ist';
$string['easyavailability:previouslybooked'] = 'Nutzer:innen, die bereits einen bestimmten USI-Kurs gebucht haben, dürfen immer buchen';
$string['easyavailability:selectusers'] = 'Ausgewählte Nutzer:innen dürfen außerhalb der Buchungszeiten buchen';
$string['editavailability'] = 'Verfügbarkeit bearbeiten';
$string['editavailabilityanddescription'] = 'Verfügbarkeit & Beschreibung bearbeiten';
$string['editbookinginstance'] = 'Semester-Instanz bearbeiten';
$string['editbookings'] = 'Kurs-Übersicht';
$string['editbotags'] = 'Tags verwalten';
$string['editcontractformula'] = 'Vertragsformel bearbeiten';
$string['editdescription'] = 'Beschreibung bearbeiten';
$string['editentities'] = 'Sportstätten bearbeiten';
$string['editentitiescategories'] = 'Kategorien der Sportstätten bearbeiten';
$string['editpricecategories'] = 'Preiskategorien bearbeiten';
$string['editsemesters'] = 'Semester bearbeiten';
$string['editsubstitutionspool'] = 'Vertretungspool bearbeiten';
$string['entities'] = 'Sportstätten';
$string['error:endtime'] = 'Ende muss nach dem Start sein.';
$string['error:starttime'] = 'Start muss vor dem Ende sein.';
$string['freeplaces'] = 'Freie Plätze';
$string['gateway'] = 'Gateway';
$string['hide_expired_options'] = 'Verstecke abgelaufene Optionen';
$string['icalexportuserevents'] = 'iCal-Datei Ihrer kommenden Veranstaltungen herunterladen';
$string['id'] = 'Eintrag';
$string['imagealt'] = 'Titelbild des Kurses';
$string['importentities'] = 'Sportstätten importieren';
$string['invisibleoption'] = '<i class="fa fa-eye-slash" aria-hidden="true"></i>';
$string['itemid'] = 'ItemID';
$string['listofsports'] = 'Sportarten';
$string['listofsports_desc'] = 'Zeige und editiere die Liste der Sportarten auf diesem System.';
$string['location'] = 'Ort';
$string['locationalt'] = 'Abhaltungsort des Kurses';
$string['mailtosubstitutionspool'] = 'E-Mail an Vertretungspool senden';
$string['merchantref'] = 'MerchantRef';
$string['messageprovider:sendmessages'] = 'Verschicke Nachrichten';
$string['musi'] = 'MUSI';
$string['musi:canedit'] = 'Nutzer:in darf verwalten';
$string['musi:cansendmessages'] = 'Kann Nachrichten schicken.';
$string['musi:editavailability'] = 'Kann die Verfügbarkeit von Buchungsoptionen ändern und Vorreservierungen anlegen';
$string['musi:editsubstitutionspool'] = 'Kann den Vertretungspool für einzelne Sportarten bearbeiten';
$string['musi:viewsubstitutionspool'] = 'Kann den Vertretungspool für einzelne Sportarten sehen und E-Mails an den Vertretungspool senden';
$string['musi:wettkampf'] = 'Darf Wettkämpfe erstellen';
$string['musicachebookingoptionsanswers'] = 'Booking-Answers-Caching in M:USI-Tabellen für bessere Performance aktivieren';
$string['musicachebookingoptionsettings'] = 'Booking-Options-Caching in M:USI-Tabellen für bessere Performance aktivieren';
$string['musicacheexpirationtimeinseconds'] = 'Cache-Ablaufzeit in Sekunden (Beispiel: 3600 bedeutet, dass der Cache nach einer Stunde neu generiert wird)';
$string['musishortcodes:showbookablefrom'] = '"Buchbar ab" anzeigen';
$string['musishortcodes:showbookableuntil'] = '"Buchbar bis" anzeigen';
$string['musishortcodes:showend'] = 'Kursende anzeigen';
$string['musishortcodes:showfilterbookable'] = 'Filter "Buchbar" anzeigen';
$string['musishortcodes:showfilterbookingtime'] = 'Filter "Anmeldezeiten" anzeigen';
$string['musishortcodes:showfiltercoursetime'] = 'Filter "Kurs beginnt um" anzeigen';
$string['musishortcodes:showoptiondates'] = 'Termine anzeigen';
$string['musishortcodes:showsortingfreeplaces'] = 'Sortiermöglichkeit "Freie Plätze" anzeigen';
$string['musishortcodes:showstart'] = 'Kursbeginn anzeigen';
$string['mycourses'] = 'Meine Kurse';
$string['myfavorites'] = 'Meine Favoriten';
$string['names'] = 'Buchungen';
$string['newsletterprofilefield'] = 'Profilfeld für die Newsletter-Anmeldung';
$string['newsletterprofilefielddesc'] = 'Wählen Sie das benutzerdefinierte Nutzerprofilfeld, in dem gespeichert wird, ob ein:e Benutzer:in für den Newsletter angemeldet ist.';
$string['newslettersettingsdesc'] = 'Nach korrekter Konfiguration können Sie folgende Shortcodes verwenden:<br>
<b>[newslettersubscribe], [newsletterunsubscribe], [newslettersubscribe button=true], [newsletterunsubscribe button=true]</b>';
$string['newslettersettingsheading'] = 'Newsletter-Einstellungen';
$string['newslettersubscribed'] = 'Wert für die Einschreibung in den Newsletter';
$string['newslettersubscribed:description'] = 'Sie wurden erfolgreich für den Newsletter angemeldet.';
$string['newslettersubscribed:error'] = 'Bei der Newsletter-Anmeldung ist ein Fehler aufgetreten. Bitte wenden Sie sich an einen Admin.';
$string['newslettersubscribed:title'] = 'Zum Newsletter anmelden';
$string['newsletterunsubscribed'] = 'Wert für die Ausschreibung aus dem Newsletter';
$string['newsletterunsubscribed:description'] = 'Sie wurden erfolgreich vom Newsletter abgemeldet.';
$string['newsletterunsubscribed:error'] = 'Bei der Newsletter-Abmeldung ist ein Fehler aufgetreten. Bitte wenden Sie sich an einen Admin.';
$string['newsletterunsubscribed:title'] = 'Vom Newsletter abmelden';
$string['nosportsdivision'] = 'Keine Sparten auf dieser Website verfügbar';
$string['notbookable'] = 'Nicht buchbar';
$string['numberofcourses'] = 'Kurse';
$string['numberofcourses_desc'] = 'Informationen über die Kurse und Buchungen auf der Plattform.';
$string['numberofentities'] = 'Anzahl der Sportstätten';
$string['numberofentities_desc'] = 'Informationen über die Sportstätten auf der Plattform.';
$string['openorder'] = 'Offen';
$string['parsing_failed'] = 'Parsing fehlgeschlagen';
$string['paymentsaborted'] = 'Abgebrochene Zahlungen';
$string['pluginname'] = 'M:USI Plugin';
$string['price'] = 'Betrag';
$string['roleaffiliation'] = 'Zugehörigkeit';
$string['roleoverview'] = 'Benutzerrollenübersicht';
$string['sapdailysums'] = 'SAP-Buchungsdateien';
$string['scheduler:description'] = 'Einstellung, ob die Taskliste abgearbeitet werden soll.';
$string['scheduler:enable'] = 'Aktiviere Scheduler';
$string['scheduler:tasklist'] = 'Taskliste JSON';
$string['scheduler:tasklistdescription'] = 'Tasks welche zu einer bestimmten Zeit abgearbeitet werden sollen - JSON konformes Format z.B. <br><br>
                <code>[{"config": "schedulerenable", "scope" : "local_musi", "time" : "27.02.2024 12:00",
                "value" : 0, "text" : "Deaktiviere Taskausführung um 12:00 Uhr"}]</code><br><br>Einmal abgearbeitet, wird der Task aus der
                Taskliste entfernt';
$string['settingsandreports'] = 'Einstellungen & Berichte';
$string['settingsandreports_desc'] = 'Verschiedene Einstellungen und Berichte, die für M:USI relevant sind.';
$string['shortcodelists'] = 'Shortcode-Listen';
$string['shortcodelists_desc'] = 'Hier können Sie Listen konfigurieren, die durch Shortcodes (z.B. [allekurseliste]) generiert werden.';
$string['shortcodelists_showdescriptions'] = 'Beschreibungen von Buchungsoptionen anzeigen';
$string['shortcodesarchivecmids'] = 'Liste von IDs für das "Meine Kurse"-Archiv';
$string['shortcodesarchivecmids_desc'] = 'Geben Sie eine Komma-getrennte Liste von Kursmodul-IDs (cmids) der Semester-Instanzen (Buchungsinstanzen) an,
die im "Meine Kurse"-Archiv aufscheinen sollen. Leer lassen, um ALLE Instanzen anzuzeigen, einzelne Instanzen können im nächsten Setting ausgeschlossen werden';
$string['shortcodesarchivecmidsexclude'] = 'Liste von IDs, die im "Meine Kurse"-Archiv NICHT angezeigt werden sollen';
$string['shortcodeslistofbookingoptions'] = 'Alle Kurse als Liste';
$string['shortcodeslistofbookingoptionsascards'] = 'Alle Kurse als Karten';
$string['shortcodeslistofmybookingoptionsascards'] = 'Meine Kurse als Karten';
$string['shortcodeslistofmybookingoptionsaslist'] = 'Meine Kurse als Liste';
$string['shortcodeslistofmyfavoritesascards'] = 'Meine Favoriten als Karten';
$string['shortcodeslistofmytaughtbookingoptionsascards'] = 'Kurse, die ich unterrichte, als Karten';
$string['shortcodeslistofteachersascards'] = 'Liste aller Trainer als Karten';
$string['shortcodesnewslettersubscribe'] = "Zum Newsletter anmelden";
$string['shortcodesnewsletterunsubscribe'] = "Vom Newsletter abmelden";
$string['shortcodesnobookinginstance'] = '<div class="text-danger font-weight-bold">Noch keine Buchungsinstanz erstellt!</div>';
$string['shortcodesnobookinginstancedesc'] = 'Sie müssen mindestens eine Buchungsinstanz in einem Moodle-Kurs erstellen, bevor Sie hier eine auswählen können.';
$string['shortcodessetdefaultinstance'] = 'Setze eine Standard-Instanz für Shortcodes';
$string['shortcodessetdefaultinstancedesc'] = 'Damit kann eine Standard-Buchungsinstanz definiert werden, die dann verwendet wird,
wenn keine ID definiert wurde. Dies erlaubt den schnellen Wechsel (zum Beispiel von einem Semster zum nächsten), wenn es Überblicks-
Seiten für unterschiedliche Kategorien gibt.';
$string['shortcodessetinstance'] = 'Definiere die Buchungsinstanz, die standardmäßig verwendet werden soll.';
$string['shortcodessetinstancedesc'] = 'Wenn Du hier einen Wert setzt, kann der Shortcode so verwendet werden: [allekurseliste category="philosophy"]
Es ist also nicht mehr nötig, eine ID zu übergeben.';
$string['shortcodesshowallsports'] = "Liste aller Sportarten";
$string['showdescription'] = 'Zeige Beschreibung';
$string['sport'] = 'Sportart';
$string['sportsdivision'] = 'Sparte';
$string['sportsdivisions'] = 'Sparten';
$string['status'] = 'Status';
$string['statuschanged'] = 'Status geändert';
$string['statusnotchanged'] = 'Status nicht geändert';
$string['substitutionspool'] = 'Vertretungspool für {$a}';
$string['substitutionspool:copypastemails'] = 'Kopieren Sie die folgenden E-Mail-Adressen in das BCC-Feld Ihres E-Mail-Programms:';
$string['substitutionspool:infotext'] = 'Trainer:innen, die <b>{$a}</b> vertreten dürfen:';
$string['substitutionspool:mailproblems'] = 'Hier klicken, wenn Sie Probleme beim Versenden der E-Mails haben...';
$string['substitutionspoolshowphonenumbers'] = 'Telefonnummern der Trainer:innen im Vertretungspool anzeigen';
$string['task_executed'] = 'Taskausführung (MUSI scheduler extension)';
$string['taskrunner'] = 'Task runner';
$string['teacheralt'] = 'Leiter des Kurses';
$string['teachersinstancereport'] = 'Trainer:innen-Gesamtbericht (Kurse, Fehlstunden, Vertretungen)';
$string['timeofdaycoursestart'] = 'Kurs beginnt um';
$string['titleprefix'] = 'Kursnummer';
$string['tocoursecontent'] = 'Zu den Kursmaterialien';
$string['transactionid'] = 'Interne ID';
$string['transactionslist'] = 'Zahlungstransaktionen';
$string['unknown'] = 'Unbekannt';
$string['username'] = 'Nutzer';
$string['viewsubstitutionspool'] = 'Vertretungspool ansehen';
$string['viewteachers'] = 'Trainer:innen-Übersicht';
$string['youneedcustomfieldsport'] = 'Das benutzerdefinierte Feld mit dem Shortname "sport" ist bei dieser Buchungsoption nicht gesetzt.';
