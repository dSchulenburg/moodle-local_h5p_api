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
require_once($CFG->dirroot . '/contentbank/classes/contentbank.php');

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

        // Create temp file
        $tempdir = make_temp_directory('local_h5p_api');
        $tempfile = $tempdir . '/' . uniqid('h5p_') . '_' . $params['filename'];
        file_put_contents($tempfile, $filedata);

        try {
            // Get content bank instance
            $cb = new contentbank();

            // Get the H5P content type
            $contenttypes = $cb->get_contenttypes_for_context($context);
            $h5ptype = null;
            foreach ($contenttypes as $type) {
                $typename = get_class($type);
                if (strpos($typename, 'contenttype_h5p') !== false || $type->get_contenttype_name() === 'contenttype_h5p') {
                    $h5ptype = $type;
                    break;
                }
            }

            if (!$h5ptype) {
                throw new \moodle_exception('h5pcontenttypenotfound', 'local_h5p_api');
            }

            // Prepare title
            $contenttitle = !empty($params['title']) ? $params['title'] : pathinfo($params['filename'], PATHINFO_FILENAME);

            // Upload file using content type's upload method
            $file = self::create_file_from_path($tempfile, $params['filename'], $context, $USER->id);

            // Create content from the file
            $content = $h5ptype->upload_content($file, ['name' => $contenttitle]);

            // Clean up temp file
            @unlink($tempfile);

            if (!$content) {
                throw new \moodle_exception('uploadfailed', 'local_h5p_api', '', 'Content creation failed');
            }

            // Get the stored file for embed URL
            $storedfile = $content->get_file();
            $storedfilename = $storedfile ? $storedfile->get_filename() : $params['filename'];

            // Build embed URL
            $embedurl = $CFG->wwwroot . '/h5p/embed.php?url=' .
                        urlencode(\moodle_url::make_pluginfile_url(
                            $context->id,
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
                'contextid' => $context->id,
                'embedurl' => $embedurl,
                'iframecode' => '<iframe src="' . $embedurl . '" width="100%" height="600" frameborder="0" allowfullscreen="allowfullscreen"></iframe>',
            ];

        } catch (\Exception $e) {
            // Clean up on error
            @unlink($tempfile);
            throw new \moodle_exception('uploadfailed', 'local_h5p_api', '', $e->getMessage());
        }
    }

    /**
     * Create a stored_file from a path for upload
     *
     * @param string $filepath Path to the file
     * @param string $filename Desired filename
     * @param \context $context The context
     * @param int $userid User ID
     * @return \stored_file
     */
    private static function create_file_from_path($filepath, $filename, $context, $userid) {
        $fs = get_file_storage();

        // Use user draft area for temporary storage
        $usercontext = \context_user::instance($userid);
        $draftitemid = file_get_unused_draft_itemid();

        $filerecord = [
            'contextid' => $usercontext->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => $filename,
            'userid' => $userid,
        ];

        return $fs->create_file_from_pathname($filerecord, $filepath);
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
