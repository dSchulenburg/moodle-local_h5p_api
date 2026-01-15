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
require_once($CFG->libdir . '/filelib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use context_system;
use context_course;
use core_contentbank\contentbank;

/**
 * Upload H5P content to Moodle content bank
 *
 * @package    local_h5p_api
 * @copyright  2026 Dirk Schulenburg
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upload_h5p extends external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'base64data' => new external_value(PARAM_RAW, 'Base64 encoded H5P file data'),
            'filename' => new external_value(PARAM_FILE, 'Filename for the H5P content', VALUE_DEFAULT, 'content.h5p'),
            'title' => new external_value(PARAM_TEXT, 'Title for the H5P content', VALUE_DEFAULT, ''),
            'contextid' => new external_value(PARAM_INT, 'Context ID (course context or system)', VALUE_DEFAULT, 0),
            'courseid' => new external_value(PARAM_INT, 'Course ID (alternative to contextid)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Upload H5P content to content bank
     *
     * @param string $base64data Base64 encoded H5P file
     * @param string $filename Filename
     * @param string $title Content title
     * @param int $contextid Context ID
     * @param int $courseid Course ID
     * @return array Result with content ID and embed info
     */
    public static function execute($base64data, $filename = 'content.h5p', $title = '', $contextid = 0, $courseid = 0) {
        global $USER, $CFG;

        // Validate parameters
        $params = self::validate_parameters(self::execute_parameters(), [
            'base64data' => $base64data,
            'filename' => $filename,
            'title' => $title,
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
        require_capability('moodle/contentbank:upload', $context);

        // Decode base64 data
        $filedata = base64_decode($params['base64data']);
        if ($filedata === false) {
            throw new \moodle_exception('invalidbase64', 'local_h5p_api');
        }

        // Get content bank instance and verify H5P content type is available
        $cb = new contentbank();
        $contenttypes = $cb->get_enabled_content_types();

        $h5pavailable = false;
        foreach ($contenttypes as $type) {
            if (strpos($type, 'h5p') !== false) {
                $h5pavailable = true;
                break;
            }
        }

        if (!$h5pavailable) {
            throw new \moodle_exception('h5pcontenttypenotfound', 'local_h5p_api');
        }

        try {
            // Create draft file for upload
            $fs = get_file_storage();
            $usercontext = \context_user::instance($USER->id);
            $draftitemid = file_get_unused_draft_itemid();

            $filerecord = [
                'contextid' => $usercontext->id,
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => $draftitemid,
                'filepath' => '/',
                'filename' => $params['filename'],
                'userid' => $USER->id,
            ];

            // Create the file from the base64 data
            $file = $fs->create_file_from_string($filerecord, $filedata);

            // Prepare title
            $contenttitle = !empty($params['title']) ? $params['title'] : pathinfo($params['filename'], PATHINFO_FILENAME);

            // Use contentbank's create_content_from_file method
            $content = $cb->create_content_from_file($context, $USER->id, $file);

            if (!$content) {
                throw new \moodle_exception('uploadfailed', 'local_h5p_api', '', 'Content creation failed');
            }

            // Update the name if a custom title was provided
            if (!empty($params['title']) && $content->get_name() !== $params['title']) {
                $content->set_name($params['title']);
            }

            // Clean up draft file
            $file->delete();

            // Get the stored file for embed URL
            $storedfile = $content->get_file();
            $storedfilename = $storedfile ? $storedfile->get_filename() : $params['filename'];

            // Build embed URL
            $embedurl = $CFG->wwwroot . '/h5p/embed.php?url=' .
                        urlencode(\moodle_url::make_pluginfile_url(
                            $content->get_contextid(),
                            'contentbank',
                            'public',
                            $content->get_id(),
                            '/',
                            $storedfilename
                        )->out(false));

            return [
                'success' => true,
                'contentid' => $content->get_id(),
                'name' => $content->get_name(),
                'contextid' => $content->get_contextid(),
                'embedurl' => $embedurl,
                'iframecode' => '<iframe src="' . $embedurl . '" width="100%" height="600" frameborder="0" allowfullscreen="allowfullscreen"></iframe>',
            ];

        } catch (\Exception $e) {
            throw new \moodle_exception('uploadfailed', 'local_h5p_api', '', $e->getMessage());
        }
    }

    /**
     * Returns description of method result value
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the upload was successful'),
            'contentid' => new external_value(PARAM_INT, 'Content bank content ID'),
            'name' => new external_value(PARAM_TEXT, 'Content name'),
            'contextid' => new external_value(PARAM_INT, 'Context ID where content is stored'),
            'embedurl' => new external_value(PARAM_URL, 'URL for embedding the H5P content'),
            'iframecode' => new external_value(PARAM_RAW, 'Ready-to-use iframe HTML code'),
        ]);
    }
}
