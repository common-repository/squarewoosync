<?php

namespace Pixeldev\SquareWooSync\REST;

use Pixeldev\SquareWooSync\Abstracts\RESTController;
use Pixeldev\SquareWooSync\Logger\Logger;
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
class LogController extends RESTController
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
    protected $base = 'logs';

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
                    'callback'            => [$this, 'get_logs'],
                    'permission_callback' => [$this, 'check_permission'],
                ],
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$this, 'write_log'],
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
    public function get_logs(): WP_REST_Response
    {
        $logger = new Logger();
        $logs = $logger->get_square_woo_sync_logs(array('limit' => 1000, 'offset' => 0));
    
        if (isset($logs) && is_array($logs)) {
            // Process each log to ensure the context is JSON-friendly
            foreach ($logs as $key => $log) {
                // Check if 'context' exists and is not empty
                if (isset($log['context']) && !empty($log['context'])) {
                    $logs[$key]['context'] = maybe_unserialize($log['context']);
                }
            }
            
            // Return the modified logs with unserialized context and a 200 status code
            return rest_ensure_response(['logs' => $logs, 'status' => 200]);
        }
    
        // If logs are not set or an empty array, signify an error with a 501 status code
        return rest_ensure_response(['logs' => esc_html__('Unable to retrieve logs', 'squarewoosync'), 'status' => 501]);
    }
    

    /**
     * Writes logs
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function write_log(WP_REST_Request $request): WP_REST_Response
    {

        $logger = new Logger();

        try {
            $log = array_map('sanitize_text_field', $request->get_param('log'));

            if (!isset($log['type'], $log['message'], $log['context'])) {
                $logger->log('error', esc_html__('Invalid log parameters', 'squarewoosync'));
                return new WP_REST_Response(
                    new WP_Error(400, 'invalid_log_parameters', esc_html__('Log parameters are missing or incomplete', 'squarewoosync')),
                    400
                );
            }

            $logger->log($log['type'], $log['message'], $log['context']);
            return new WP_REST_Response(['message' => esc_html__('success', 'squarewoosync')], 200);
        } catch (\InvalidArgumentException $e) {
            $logger->log('error', esc_html__('Invalid argument: ', 'squarewoosync') . $e->getMessage());
            return new WP_REST_Response(
                new WP_Error(400, 'invalid_argument', esc_html($e->getMessage())),
                400
            );
        } catch (\Exception $e) {
            $logger->log('error', esc_html__('Exception encountered: ', 'squarewoosync') . $e->getMessage());

            return new WP_REST_Response(
                new WP_Error(500, 'internal_server_error', esc_html($e->getMessage())),
                500
            );
        }
    }
}