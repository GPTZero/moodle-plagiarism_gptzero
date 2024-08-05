<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Functions used by the GPTZero plagiarism plugin.
 *
 * @package    plagiarism_gptzero
 * @copyright  2024 Tyler Vu <tyler@gptzero.me>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); // It must be included from a Moodle page.
}

// Get global class.
require_once($CFG->dirroot.'/plagiarism/lib.php');
global $DB, $CFG, $USER, $PAGE;

define('PLAGIARISM_GPTZERO_DRAFTSUBMIT_IMMEDIATE', 0);
define('PLAGIARISM_GPTZERO_DRAFTSUBMIT_FINAL', 1);

/**
 * Class plagiarism_plugin_gptzero
 */
class plagiarism_plugin_gptzero extends plagiarism_plugin {
    /**
     * This function should be used to initialise settings and check if GPTZero is enabled.
     *
     * @return mixed - false if not enabled, or returns an array of relevant settings.
     */
    public function get_settings() {
        static $plagiarismsettings;
        if (!empty($plagiarismsettings) || $plagiarismsettings === false) {
            return $plagiarismsettings;
        }
        $plagiarismsettings = array_merge((array)get_config('plagiarism'),
            (array)get_config('plagiarism_gptzero'));
        // Check if enabled.
        if (isset($plagiarismsettings['gptzero_enabled']) && $plagiarismsettings['gptzero_enabled']) {
            return $plagiarismsettings;
        } else {
            return false;
        }
    }

    /**
     * Check whether GPTZero needs to be used in a particular instance.
     *
     * @param $cmid int Course module id
     * @return boolean whether GPTZero is enabled for the given cmid
     */
    public function is_gptzero_used($cmid) {
        global $DB;
        $useforcm = false;
        $cmenabled = false;
        $plagiarismvalues = $DB->get_records_menu('plagiarism_gptzero_config', array('cm' => $cmid), '', 'name, value');
        if (empty($plagiarismvalues)) {
            return false;
        }
        if ($plagiarismvalues['use_gptzero']) {
            // GPTZero is used for this cm.
            $useforcm = true;
        }

        // Check if the module associated with this event still exists.
        if ($DB->record_exists('course_modules', array('id' => $cmid))) {
            $cmenabled = true;
        }
        return ($useforcm && $cmenabled);
    }

    /**
     * hook to allow plagiarism specific information to be displayed beside a submission
     * @param array  $linkarraycontains all relevant information for the plugin to generate a link
     * @return string
     *
     */
    public function get_links($linkarray) {
        global $CFG, $SESSION;
        
        $cmid = $linkarray['cmid'];
        $userid = $linkarray['userid'];

        // Fetch plagiarism plugin settings for the course module.
        $plagiarismsettings = $this->get_settings();
        if (!$plagiarismsettings) {
            return false; // Exit if no settings found.
        }
        
        // Check if plagiarism checking is enabled for this course module.
        if (!$this->is_gptzero_used($cmid)) {
            return false;
        }
        
        if (!empty($linkarray['file'])) {
            $file = new stdClass();
            $file->filename = $linkarray['file']->get_filename();
            $file->identifier = $linkarray['file']->get_contenthash();
            $file->timestamp = time();
            $file->filepath = $linkarray['file']->get_filepath();
        } else if (!empty($linkarray['content'])) {
            $file = new stdClass();
            $contenthash = md5(trim($linkarray['content']));
            $file->filename = 'content_' . $contenthash;
            $file->identifier = $contenthash;
            $file->timestamp = time();
        }

        // Send notification if user does not have gptzero account
        $this->handle_grading_page_view();

        $results = $this->get_file_results($cmid, $userid, $file);
        
        $output = '';

        // Check if file has associated AI result
        if (empty($results['predicted_class'])) {
            return $output;
        }

        $classFormat = [
            'ai' => 'AI',
            'human' => 'Human',
            'mixed' => 'Mixed'
        ];
    
        $predicted_class = $results['predicted_class'];
        $display_class = $classFormat[$predicted_class] ?? 'Unknown';
        $class_probability = number_format($results['class_probability'] * 100, 0);
        $scanUrl = $this->get_scan_url($cmid, $userid, $file->identifier);
        
        $backgroundColors = [
            'ai' => '#FEBD69',
            'human' => '#8AD4BA',
            'mixed' => '#E9D2FF'
        ];
        $hoverColors = [
            'ai' => '#E19F4A',
            'human' => '#39B58A',
            'mixed' => '#CDA3F5'
        ];

        $bgColor = $backgroundColors[$predicted_class] ?? '#FEBD69';
        $hoverColor = $hoverColors[$predicted_class] ?? '#E19F4A';
        $logoUrl = $CFG->wwwroot . '/plagiarism/gptzero/pix/gptzero_logo.png';
    
        if ($results['predicted_class'] && $scanUrl) {
            $output .= "<br><a href='{$scanUrl}' target='_blank' style='text-decoration: none; display: flex; align-items: center; gap: 10px; margin-top: 6px'>";
            $output .= "<img src='{$logoUrl}' alt='GPTZero Logo' style='height: 20px;'>";
            $output .= "<div style='
                display: flex;
                width: 110px;
                height: 25px;
                padding: 1px;
                justify-content: center;
                align-items: center;
                background: {$bgColor};
                border-radius: 5px;
                gap: 10px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                cursor: pointer;
                transition: background-color 0.3s, box-shadow 0.3s;
                font-family: Inter, sans-serif;
                font-size: 13px;
                font-weight: 700;
                text-align: center;
                color: #000;
                ' onmouseover='this.style.backgroundColor=\"{$hoverColor}\"; this.style.boxShadow=\"0 4px 8px rgba(0,0,0,0.2)\";'
                onmouseout='this.style.backgroundColor=\"{$bgColor}\"; this.style.boxShadow=\"0 2px 4px rgba(0,0,0,0.1)\";'>";
            $output .= "{$display_class} - {$class_probability}%";
            $output .= "</div></a>";
        } elseif ($results['predicted_class']) {
            $output .= "<br>{$display_class}: {$class_probability}%";
        } else {
            $output .= "<br>Pending!";
        }
        return $output;
    }    

    public function get_scan_url($cmid, $userid, $identifier) {
        // Query to get the scan URL
        global $DB;
        $sql = "SELECT scanurl FROM {plagiarism_gptzero_files} WHERE cm = ? AND userid = ? AND identifier = ?";
        $record = $DB->get_record_sql($sql, [$cmid, $userid, $identifier]);
        return $record ? $record->scanurl : null;
    }    

    /**
     * hook to allow plagiarism specific information to be returned unformatted
     * @param int $cmid
     * @param int $userid
     * @param $file file object
     * @return array containing at least:
     *   - 'analyzed' - whether the file has been successfully analyzed
     *   - 'score' - similarity score - ('' if not known)
     *   - 'reporturl' - url of gptzero report - '' if unavailable
     */
    public function get_file_results($cmid, $userid, $file) {
        global $DB;
        // Fetch plagiarism plugin settings for the course module.
        $plagiarismsettings = $this->get_settings();
        if (!$plagiarismsettings) {
            return false; // Exit if no settings found.
        }

        // Check if plagiarism checking is enabled for this course module.
        if (!$this->is_gptzero_used($cmid)) {
            return false;
        }

        // Check permissions for viewing plagiarism info.
        $modulecontext = context_module::instance($cmid);
        $viewreport = has_capability('plagiarism/gptzero:viewreport', $modulecontext);
        $plagiarismvalues = $DB->get_records_menu('plagiarism_gptzero_config', array('cm' => $cmid), '', 'name, value');
        if (isset($plagiarismvalues['gptzero_show_student_plagiarism_info']) && $plagiarismvalues['gptzero_show_student_plagiarism_info']) {
            $viewreport = true; // Override if specific config allows students to view their own plagiarism info.
        }

        $filehash = $file->identifier;

        // Fetch stored results for this file.
        $storedfile = $DB->get_record_sql("SELECT * FROM {plagiarism_gptzero_files} WHERE cm = ? AND userid = ? AND identifier = ?",
            array($cmid, $userid, $filehash));

        if (!$storedfile) {
            return false; // No records found for the file.
        }

        // Prepare the results structure.
        $results = array(
            'analyzed' => 0, 'score' => '', 'reporturl' => '', 'error' => ''
        );

        if ($storedfile->predicted_class) {
            // File has been analyzed. Return stored results.
            $results['analyzed'] = 1;
            $results['predicted_class'] = $storedfile->predicted_class;
            $results['class_probability'] = $storedfile->class_probability;
        } else {
            // TODO: Add retry logic
            debugging("Not yet analyzed", DEBUG_DEVELOPER);
        }

        if (!$viewreport) {
            // User is not permitted to see any details.
            return false;
        }

        return $results;
    }

    /**
     * Handles text submissions, storing it in gptzero_files table.
     * @param $cmid Course module ID
     * @param $userid User ID
     * @param $content Content of the text submission
     * @return bool Whether the store function was successful
     */
    public function handle_onlinetext($cmid, $userid, $content, $overflowenabled = false) {
        global $DB;
    
        $useremail = $DB->get_field('user', 'email', array('id' => $userid));
        $username = $DB->get_field('user', 'username', array('id' => $userid));
    
        // Get additional details about the assignment and user
        $moduleinfo = $DB->get_record_sql(
            "SELECT cm.instance, a.name FROM {course_modules} AS cm 
            JOIN {assign} AS a ON cm.instance = a.id 
            WHERE cm.id = ? AND cm.module = (SELECT id FROM {modules} WHERE name = 'assign')",
            array($cmid));
    
        $assignmentid = $moduleinfo->instance;
        $assignmentname = $moduleinfo->name;
    
        // Retrieves GPTZero assignment id associated with Moodle assignment id
        $record = $DB->get_record('plagiarism_gptzero_config', array('cm' => $cmid), 'gptzero_assignment_id');
        $gptzero_assignment_id = $record ? $record->gptzero_assignment_id : null;

        $formattedContent = $overflowenabled ? '<div class="no-overflow">'.$content.'</div>' : $content;
        // Hash the content to generate a unique identifier for tracking
        $filehash = md5(trim($content));
    
        // Prepare params for API submission
        $params = [
            'assignmentName' => $assignmentname,
            'assignmentId' => $gptzero_assignment_id,
            'userId' => $userid,
            'userName' => $username,
            'userEmail' => $useremail,
        ];

        $rawText = strip_tags($content);
    
        $api = new \plagiarism_gptzero\api();
        $response = $api->submit_text($rawText, $params);
    
        $response = json_decode($response, true);
    
        $plagiarismfile = new stdClass();
        $plagiarismfile->cm = $cmid;
        $plagiarismfile->userid = $userid;
        $plagiarismfile->useremail = $useremail;
        $plagiarismfile->identifier = $filehash;
        $plagiarismfile->content = $formattedContent;
        $plagiarismfile->timesubmitted = time();
    
        // Handle response
        if (isset($response['error'])) {
            debugging("API submission failed: " . $response['error'], DEBUG_DEVELOPER);
        } else {
            $plagiarismfile->predicted_class = $response['results']['predicted_class'];
            $plagiarismfile->class_probability = $response['results']['class_probability'];
            $plagiarismfile->confidence_category = $response['results']['confidence_category'];
            $plagiarismfile->scanid = $response['results']['scanId'];
            $plagiarismfile->scanurl = $response['results']['scanUrl'];
        }
    
        if (!$pid = $DB->insert_record('plagiarism_gptzero_files', $plagiarismfile)) {
            debugging("insert into gptzero_files failed", DEBUG_DEVELOPER);
        }
    
        return isset($pid);
    }    
    
    /**
     * Updates a file record to be processed by GPTZero.
     *
     * @param int $cmid course module id
     * @param int $userid  user id
     * @param mixed $file the file from file storage
     * @return bool Whether the file was successfully stored
     */
    public function update_plagiarism_file($cmid, $userid, $file) {
        global $DB;

        $useremail = $DB->get_field('user', 'email', array('id' => $userid));
        $username = $DB->get_field('user', 'username', array('id' => $userid));

        // Query to get the assignment ID and name
        $moduleinfo = $DB->get_record_sql(
            "SELECT cm.instance, a.name FROM {course_modules} AS cm 
            JOIN {assign} AS a ON cm.instance = a.id 
            WHERE cm.id = ? AND cm.module = (SELECT id FROM {modules} WHERE name = 'assign')",
            array($cmid));

        $assignmentid = $moduleinfo->instance;
        // retrieves gptzero assignment id associated with moodle assignment id
        $record = $DB->get_record('plagiarism_gptzero_config', array('cm' => $cmid), 'gptzero_assignment_id');
        $gptzero_assignment_id = $record->gptzero_assignment_id;

        $assignmentname = $moduleinfo->name;

        $filehash = (!empty($file->identifier)) ? $file->identifier : $file->get_contenthash();
        // Now update or insert record into gptzero_files.
        $plagiarismfile = $DB->get_record_sql(
            "SELECT * FROM {plagiarism_gptzero_files}
                                 WHERE cm = ? AND userid = ? AND " .
            "identifier = ?",
            array($cmid, $userid, $filehash));
        if (!empty($plagiarismfile)) {
            // File is already there, return true.
            return true;
        } else {
            $plagiarismfile = new stdClass();
            $plagiarismfile->cm = $cmid;
            $plagiarismfile->userid = $userid;
            $plagiarismfile->useremail = $useremail;
            $plagiarismfile->identifier = $filehash;
            $plagiarismfile->filename = (!empty($file->filename)) ? $file->filename : $file->get_filename();
            $plagiarismfile->attempt = 0;
            $plagiarismfile->timesubmitted = time();

            $params = [
                'assignmentName' => $assignmentname,
                'assignmentId' => $gptzero_assignment_id,
                'userId' => $userid,
                'userName' => $username,
                'userEmail' => $useremail,
            ];
            
            // Call the API to submit the file
            $api = new \plagiarism_gptzero\api();
            $response = $api->submit_file($file, $params);
            $response = json_decode($response, true);

            // $plagiarismfile->status = $response['success'] ? 'pending' : 'failed';
            if (isset($response['error'])) {
                debugging("insert into gptzero_files failed");
            } else {
                $plagiarismfile->predicted_class = $response['results']['predicted_class'];
                $plagiarismfile->class_probability = $response['results']['class_probability'];
                $plagiarismfile->confidence_category = $response['results']['confidence_category'];
                $plagiarismfile->scanid = $response['results']['scanId'];
                $plagiarismfile->scanurl = $response['results']['scanUrl'];
            }

            if (!$pid = $DB->insert_record('plagiarism_gptzero_files', $plagiarismfile)) {
                debugging("insert into gptzero_files failed");
            }

            return isset($pid);
        }
    }

    /**
     * hook to allow a disclosure to be printed notifying users what will happen with their submission
     * @param int $cmid - course module id
     * @return string
     */
    public function print_disclosure($cmid) {
        global $OUTPUT, $DB;
        $plagiarismsettings = $this->get_settings();
        if (!$plagiarismsettings) {
            return; // Exit if no settings found.
        }
        $plagiarismvalues = $DB->get_records_menu('plagiarism_gptzero_config', array('cm' => $cmid), '', 'name, value');
        if (empty($plagiarismvalues['use_gptzero'])) {
            // GPTZero not in use for this cm - return.
            return;
        }
        $outputhtml = '';
        $outputhtml .= $OUTPUT->box_start('generalbox boxaligncenter plagiarism_disclosure', 'intro');
        $formatoptions = new stdClass;
        $formatoptions->noclean = true;
        $outputhtml .= format_text($plagiarismsettings['gptzero_student_disclosure'], FORMAT_MOODLE, $formatoptions);
        $outputhtml .= $OUTPUT->box_end();
        return $outputhtml;
    }

    /**
     * Function which returns an array of all the module instance settings.
     *
     * @return array
     *
     */
    public function config_options() {
        return array('use_gptzero', 'gptzero_show_student_plagiarism_info',
            'gptzero_draft_submit');
    }
    public function handle_grading_page_view() {
        static $notification_displayed = false;

        if ($notification_displayed) {
            // Skip the function if the notification has already been handled during this request
            return;
        }

        global $USER, $DB, $COURSE;

        $plagiarismsettings = $this->get_settings();
        if (!$plagiarismsettings) {
            return; // Exit if no settings found.
        }

        if (!has_capability('mod/assign:grade', context_course::instance($COURSE->id))) {
            return;
        }

        $useremail = $DB->get_field('user', 'email', ['id' => $USER->id]);
        if (!$useremail) {
            debugging("No email found for user with ID: {$USER->id}", DEBUG_DEVELOPER);
            return;
        }

        $api = new \plagiarism_gptzero\api();
        $response = $api->has_gptzero_account($useremail);
        $response = json_decode($response, true);

        if (!empty($response['success']) && !$response['hasAccount']) {
            $message = "It looks like you have not yet created a GPTZero account. An account is required to see in-depth results in the GPTZero dashboard. An invitation was sent from api@gptzero.me to {$useremail} during assignment creation.";
            \core\notification::add($message, \core\notification::INFO);
            debugging('User does not have a GPTZero account. Check your email.', DEBUG_DEVELOPER);
            $notification_displayed = true;
        }
    }
}

/**
 * Handler for all plagiarism events, observers will route here.
 * @param $eventdata array Event data
 * @throws coding_exception
 */
function gptzero_handle_event($eventdata) {
    global $DB, $CFG, $USER;
    $gptzero = new plagiarism_plugin_gptzero();
    $plagiarismsettings = $gptzero->get_settings();
    if (!$plagiarismsettings) {
        return false; // Exit if no settings found.
    }
    if (!$gptzero->is_gptzero_used($eventdata['contextinstanceid'])) {
        return false;
    }
    $cmid = $eventdata['contextinstanceid'];

    // Normal scenario - this is an upload event with one or more attached files.
    if (!empty($eventdata['other']['pathnamehashes'])) {
        $eventDataOtherString = print_r($eventdata['other'], true);
        foreach ($eventdata['other']['pathnamehashes'] as $hash) {
            $fs = get_file_storage();
            $efile = $fs->get_file_by_hash($hash);

            if (empty($efile)) {
                mtrace("nofilefound!");
                continue;
            } else if ($efile->get_filename() === '.') {
                // This is a directory - nothing to do.
                continue;
            }

            // Check if assign group submission is being used.
            if ($eventdata['component'] == 'assignsubmission_file'
                || $eventdata['component'] == 'assignsubmission_onlinetext') {
                require_once("$CFG->dirroot/mod/assign/locallib.php");
                $modulecontext = context_module::instance($cmid);
                $assign = new assign($modulecontext, false, false);
                if (!empty($assign->get_instance()->teamsubmission)) {
                    $mygroups = groups_get_user_groups($assign->get_course()->id, $eventdata['userid']);
                    if (count($mygroups) == 1) {
                        $groupid = reset($mygroups)[0];
                        // Only users with single groups are supported - otherwise just use the normal userid on this record.
                        // Get all users from this group.
                        $userids = array();
                        $users = groups_get_members($groupid, 'u.id');
                        foreach ($users as $u) {
                            $userids[] = $u->id;
                        }
                        // Find the earliest plagiarism record for this cm with any of these users.
                        $sql = 'cm = ? AND userid IN (' . implode(',', $userids) . ')';
                        $previousfiles = $DB->get_records_select('plagiarism_gptzero_files', $sql, array($cmid), 'id');
                        $sanitycheckusers = 10; // Search through this number of users to find a valid previous submission.
                        $i = 0;
                        foreach ($previousfiles as $pf) {
                            if ($pf->userid == $eventdata['userid']) {
                                break; // The submission comes from this user so break.
                            }
                            // Sanity Check to make sure the user isn't in multiple groups.
                            $pfgroups = groups_get_user_groups($assign->get_course()->id, $pf->userid);
                            if (count($pfgroups) == 1) {
                                // This user made the first valid submission so use their id when sending the file.
                                $eventdata['userid'] = $pf->userid;
                                break;
                            }
                            if ($i >= $sanitycheckusers) {
                                // Don't cause a massive loop here and break at a sensible limit.
                                break;
                            }
                            $i++;
                        }
                    }
                }
            }   
            $update_stat = $gptzero->update_plagiarism_file($cmid, $eventdata['userid'], $efile);
            if (!$update_stat) {
                debugging('issue uploading', DEBUG_DEVELOPER);
            }
        }
    }
    if (!empty($eventdata['other']['content'])) {
        $overflowenabled = false;
        if ($eventdata['component'] == 'assignsubmission_onlinetext') {
            $overflowenabled = true;
        }
        // Online text submission scenario.
        return $gptzero->handle_onlinetext($cmid, $eventdata['userid'],
            $eventdata['other']['content'], $overflowenabled);
    }
}

function plagiarism_gptzero_is_plugin_configured($modulename)
{
    $apikey = get_config('plagiarism_gptzero', 'gptzero_apikey');
    if (empty($apikey)) {
        return false;
    }

    $moduleconfigname = 'gptzero_' . $modulename;
    $moduleenabled = get_config('plagiarism_gptzero', $moduleconfigname);
    if (!$moduleenabled) {
        return false;
    }
    return true;
}

function plagiarism_gptzero_coursemodule_standard_elements($formwrapper, $mform)
{
    global $DB;
    $context = context_course::instance($formwrapper->get_course()->id);
    $modulename = $formwrapper->get_current()->modulename;
    if (!$context || !isset($modulename)) {
        return;
    }
    if (has_capability('plagiarism/gptzero:enable', $context)) {

        // Return no form if the plugin isn't configured or not enabled.
        if (!plagiarism_gptzero_is_plugin_configured("mod_" . $modulename)) {
            return;
        }

        $gptzero = new plagiarism_plugin_gptzero();
        $plagiarismsettings = $gptzero->get_settings();
        if (!$plagiarismsettings) {
            return false; // Exit if no settings found.
        }

        $mform->addElement(
            'header',
            'plagiarism_gptzero_defaultsettings',
            get_string('gptzerocoursesettings', 'plagiarism_gptzero')
        );

        $mform->addElement(
            'advcheckbox',
            'use_gptzero',
            get_string('usegptzero', 'plagiarism_gptzero')
        );

        $cmid = optional_param('update', null, PARAM_INT);
        $savedvalues = $DB->get_records_menu('plagiarism_gptzero_config', array('cm' => $cmid), '', 'name,value');
        if (count($savedvalues) > 0) {
            $mform->setDefault(
                'use_gptzero',
                isset($savedvalues['use_gptzero']) ? $savedvalues['use_gptzero'] : 0
            );
        } else {
            $mform->setDefault('use_gptzero', false);
        }
    }
}

function plagiarism_gptzero_coursemodule_edit_post_actions($data, $course)
{
    global $DB, $USER;

    // Return no form if the plugin isn't configured or not enabled.
    if (empty($data->modulename) && plagiarism_gptzero_is_plugin_configured('mod_' . $data->modulename)) {
        return;
    }

    $gptzero = new plagiarism_plugin_gptzero();

    $plagiarismsettings = $gptzero->get_settings();
    if (!$plagiarismsettings) {
        return; // Exit if no settings found.
    }

    $useremail = $DB->get_field('user', 'email', array('id' => $USER->id));
    $username = $DB->get_field('user', 'username', array('id' => $USER->id));

    $savedrecord = $DB->get_record('plagiarism_gptzero_config', array('cm' => $data->coursemodule));

    if (!$savedrecord) {
        if ($data->use_gptzero) {
            // gptzero deep-linking
            $api = new \plagiarism_gptzero\api();
            $response = $api->create_assignment($username, $useremail, $USER->id);
            $response = json_decode($response, true);

            if (!empty($response['data']['gptzero_assignment_id'])) {
                $mod_config = new stdClass();
                $mod_config->cm = $data->coursemodule;
                $mod_config->name = 'use_gptzero';
                $mod_config->value = $data->use_gptzero;
                $mod_config->creatoremail = $useremail;
                $mod_config->gptzero_assignment_id = $response['data']['gptzero_assignment_id'];
                
                $DB->insert_record('plagiarism_gptzero_config', $mod_config);
            } else {
                debugging("GPTZero Assignment Creation Error", DEBUG_DEVELOPER);
            }
        }
    } else {
        //update existing record
        $savedrecord->value = $data->use_gptzero;
        $DB->update_record('plagiarism_gptzero_config', $savedrecord);
    }
    return $data;
}