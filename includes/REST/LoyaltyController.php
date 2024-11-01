<?php

namespace Pixeldev\SquareWooSync\REST;

use Pixeldev\SquareWooSync\Abstracts\RESTController;
use Pixeldev\SquareWooSync\Square\SquareHelper;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * API SettingsController class for plugin settings.
 *
 * @since 0.5.0
 */
class LoyaltyController extends RESTController
{

    /**
     * Endpoint namespace.
     *
     * @var string
     */
    protected $namespace = 'sws/v1';

    /**
     * Route base.
     *
     * @var string
     */
    protected $base = 'loyalty';

    /**
     * Register routes for settings.
     *
     * @return void
     */
    public function register_routes()
    {
        register_rest_route(
            $this->namespace,
            '/' . $this->base,
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_program'],
                    'permission_callback' => [$this, 'check_permission'],
                ],
            ]
        );
    }

    /**
     * Retrieves logs from dv
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function get_program(): WP_REST_Response
    {
        $response_obj = null;
        $license_message = null;

        $license_key = get_option("SquareWooSync_lic_Key");
        $lice_email = get_option("SquareWooSync_lic_email");

        $squareHelper = new SquareHelper();

        try {


            $programs = $squareHelper->square_api_request('/loyalty/programs/main');

            error_log(json_encode($programs));

            return new WP_REST_Response($programs, 200);
        } catch (\Exception $e) {
            // Log the error message for debugging.
            error_log('Error retrieving Square programs: ' . $e->getMessage());
            return new WP_Error('rest_square_error', esc_html($e->getMessage()), array('status' => 500));
        }
    }
}
