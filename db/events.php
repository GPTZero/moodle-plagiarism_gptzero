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
 * Event observers for the GPTZero plagiarism plugin.
 *
 * @package    plagiarism_gptzero
 * @copyright  2024 GPTZero <team@gptzero.me>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$observers = [
    // Event observers.
    [
        'eventname' => '\assignsubmission_file\event\assessable_uploaded',
        'callback' => 'plagiarism_gptzero_observer::assignsubmission_file_uploaded',
    ],
    [
        'eventname' => '\mod_workshop\event\assessable_uploaded',
        'callback' => 'plagiarism_gptzero_observer::workshop_file_uploaded',
    ],
    [
        'eventname' => '\mod_forum\event\assessable_uploaded',
        'callback' => 'plagiarism_gptzero_observer::forum_file_uploaded',
    ],
    [
        'eventname' => '\assignsubmission_onlinetext\event\assessable_uploaded',
        'callback' => 'plagiarism_gptzero_observer::assignsubmission_onlinetext_uploaded',
    ],
    [
        'eventname' => '\mod_assign\event\assessable_submitted',
        'callback' => 'plagiarism_gptzero_observer::assignsubmission_submitted',
    ],
    [
        'eventname' => '\mod_assign\event\grading_table_viewed',
        'callback' => 'plagiarism_gptzero_observer::grading_page_accessed',
    ],
];
