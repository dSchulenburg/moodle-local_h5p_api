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
use core_contentbank\contentbank;

/**
 * Get embed code for H5P content
 *
 * @package    local_h5p_api
 * @copyright  2026 Dirk Schulenburg
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_embed extends external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'contentid' => new external_value(PARAM_INT, 'Content bank content ID'),
        ]);
    }

    /**
     * Get embed code for H5P content
     *
     * @param int $contentid Content ID
     * @return array Embed information
     */
    public static function execute($contentid) {
        global $CFG;

        // Validate parameters
        $params = self::validate_parameters(self::execute_parameters(), [
            'contentid' => $contentid,
        ]);

        // Get content bank instance
        $cb = new contentbank();
        $content = $cb->get_content_from_id($params['contentid']);

        if (!$content) {
            throw new \moodle_exception('contentnotfound', 'local_h5p_api');
        }

        $context = \context::instance_by_id($content->get_contextid());

        // Check capabilities
        require_capability('moodle/contentbank:access', $context);

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

        return [
            'success' => true,
            'contentid' => $content->get_id(),
            'name' => $content->get_name(),
            'embedurl' => $embedurl,
            'iframecode' => '<iframe src="' . $embedurl . '" width="100%" height="600" frameborder="0" allowfullscreen="allowfullscreen"></iframe>',
            'filtercode' => '{h5p:' . $content->get_id() . '}',
        ];
    }

    /**
     * Returns description of method result value
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the request was successful'),
            'contentid' => new external_value(PARAM_INT, 'Content ID'),
            'name' => new external_value(PARAM_TEXT, 'Content name'),
            'embedurl' => new external_value(PARAM_URL, 'URL for embedding'),
            'iframecode' => new external_value(PARAM_RAW, 'Ready-to-use iframe HTML'),
            'filtercode' => new external_value(PARAM_RAW, 'Moodle filter code for embedding'),
        ]);
    }
}
