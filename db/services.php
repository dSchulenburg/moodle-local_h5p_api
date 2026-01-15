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
 * Web service definitions for local_h5p_api
 *
 * @package    local_h5p_api
 * @copyright  2026 Dirk Schulenburg
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_h5p_api_upload' => [
        'classname'     => 'local_h5p_api\external\upload_h5p',
        'methodname'    => 'execute',
        'description'   => 'Upload H5P content to Moodle content bank',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'moodle/contentbank:upload',
    ],
    'local_h5p_api_list' => [
        'classname'     => 'local_h5p_api\external\list_h5p',
        'methodname'    => 'execute',
        'description'   => 'List H5P content in Moodle content bank',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'moodle/contentbank:access',
    ],
    'local_h5p_api_get_embed' => [
        'classname'     => 'local_h5p_api\external\get_embed',
        'methodname'    => 'execute',
        'description'   => 'Get embed code for H5P content',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'moodle/contentbank:access',
    ],
];

$services = [
    'H5P API Service' => [
        'functions' => [
            'local_h5p_api_upload',
            'local_h5p_api_list',
            'local_h5p_api_get_embed',
        ],
        'restrictedusers' => 0,
        'enabled' => 1,
        'shortname' => 'local_h5p_api',
    ],
];
