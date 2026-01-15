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

namespace local_h5p_api\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;
use context_system;
use context_course;
use core_contentbank\contentbank;

/**
 * List H5P content in Moodle content bank
 *
 * @package    local_h5p_api
 * @copyright  2026 Dirk Schulenburg
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class list_h5p extends external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'Context ID to list content from (0 = system)', VALUE_DEFAULT, 0),
            'courseid' => new external_value(PARAM_INT, 'Course ID (alternative to contextid)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * List H5P content
     *
     * @param int $contextid Context ID
     * @param int $courseid Course ID
     * @return array List of H5P content
     */
    public static function execute($contextid = 0, $courseid = 0) {
        global $CFG;

        // Validate parameters
        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'courseid' => $courseid,
        ]);

        // Determine context
        if ($params['contextid'] > 0) {
            $context = \context::instance_by_id($params['contextid']);
        } else if ($params['courseid'] > 0) {
            $context = context_course::instance($params['courseid']);
        } else {
            $context = context_system::instance();
        }

        // Check capabilities
        require_capability('moodle/contentbank:access', $context);

        // Get content bank instance
        $cb = new contentbank();
        $contents = $cb->search_contents('', $context->id, ['contenttype_h5p']);

        $result = [];
        foreach ($contents as $content) {
            $file = $content->get_file();
            $filename = $file ? $file->get_filename() : '';

            $embedurl = $CFG->wwwroot . '/h5p/embed.php?url=' .
                        urlencode(\moodle_url::make_pluginfile_url(
                            $context->id,
                            'contentbank',
                            'public',
                            $content->get_id(),
                            '/',
                            $filename
                        )->out(false));

            $result[] = [
                'contentid' => $content->get_id(),
                'name' => $content->get_name(),
                'contextid' => $content->get_contextid(),
                'timecreated' => $content->get_timecreated(),
                'timemodified' => $content->get_timemodified(),
                'filename' => $filename,
                'embedurl' => $embedurl,
            ];
        }

        return [
            'success' => true,
            'count' => count($result),
            'items' => $result,
        ];
    }

    /**
     * Returns description of method result value
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the request was successful'),
            'count' => new external_value(PARAM_INT, 'Number of items found'),
            'items' => new external_multiple_structure(
                new external_single_structure([
                    'contentid' => new external_value(PARAM_INT, 'Content ID'),
                    'name' => new external_value(PARAM_TEXT, 'Content name'),
                    'contextid' => new external_value(PARAM_INT, 'Context ID'),
                    'timecreated' => new external_value(PARAM_INT, 'Time created'),
                    'timemodified' => new external_value(PARAM_INT, 'Time modified'),
                    'filename' => new external_value(PARAM_FILE, 'Filename'),
                    'embedurl' => new external_value(PARAM_URL, 'Embed URL'),
                ])
            ),
        ]);
    }
}
