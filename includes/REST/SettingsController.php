<?php

namespace Pixeldev\SquareWooSync\REST;

use Pixeldev\SquareWooSync\Abstracts\RESTController;
use Pixeldev\SquareWooSync\Square\SquareHelper;
use Pixeldev\SquareWooSync\Cron\CronManager;
use Pixeldev\SquareWooSync\Logger\Logger;
use Pixeldev\SquareWooSync\Woo\WooImport;
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
class SettingsController extends RESTController
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
    protected $base = 'settings';

    /**
     * Register routes for settings.
     *
     * @return void
     */
    public function register_routes()
    {
        register_rest_route($this->namespace, '/' . $this->base . '/access-token', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_access_token'],
                'permission_callback' => [$this, 'check_permission']
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'set_access_token'],
                'permission_callback' => [$this, 'check_permission']
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_access_token'],
                'permission_callback' => [$this, 'check_permission']
            ]
        ]);
        register_rest_route(
            $this->namespace,
            '/' . $this->base,
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_settings'],
                    'permission_callback' => [$this, 'check_permission'],
                ],
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$this, 'update_settings'],
                    'permission_callback' => [$this, 'check_permission'],
                ],
            ]
        );
        register_rest_route(
            $this->namespace,
            '/' . $this->base . '/get-locations',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_locations'],
                    'permission_callback' => [$this, 'check_permission'],
                ]
            ]
        );
        register_rest_route(
            $this->namespace,
            '/' . $this->base . '/get-cron',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_cron'],
                    'permission_callback' => [$this, 'check_permission'],
                ]
            ]
        );
        register_rest_route(
            $this->namespace,
            '/' . $this->base . '/get-gateway-settings',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_gateway_settings'],
                    'permission_callback' => [$this, 'check_permission'],
                ]
            ]
        );
        register_rest_route(
            $this->namespace,
            '/' . $this->base . '/update-gateway-settings',
            [
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$this, 'update_gateway_settings'],
                    'permission_callback' => [$this, 'check_permission'],
                ]
            ]
        );
    }

    private function get_all_products($offset = 0, $batch_size = 50)
    {
        // Query for retrieving product IDs
        $query_args = [
            'post_type' => 'product',
            'posts_per_page' => $batch_size,
            'offset' => $offset,
            'post_status' => 'publish',
            'fields' => 'ids'  // Retrieve only IDs for performance
        ];

        $query = new \WP_Query($query_args);
        $product_ids = $query->posts;

        // Retrieve product objects
        $products = array_map('wc_get_product', $product_ids);

        return $products;
    }


    public function update_inventory_for_all_products($import)
    {
        $offset = 0;
        $batch_size = 50;
        $continue = true;

        while ($continue) {
            $products = $this->get_all_products($offset, $batch_size);

            if (!empty($products)) {
                // Update inventory for the batch of products
                $import->update_square_inventory_counts($products);
                $offset += $batch_size;  // Increment the offset by the batch size
            } else {
                $continue = false;  // Stop the loop if no more products are found
            }
        } // Logging when process completes
    }


    public function get_access_token(WP_REST_Request $request): WP_REST_Response
    {
        $settings = get_option('square-woo-sync_settings', []);
        $token = isset($settings['access_token']) ? $settings['access_token'] : null;

        if ($token) {
            $squareHelper = new SquareHelper($token);
            $decryptedToken = $squareHelper->decrypt_access_token($token);
            $maskedToken = substr($decryptedToken, 0, 3) . '...' . substr($decryptedToken, -3);
            return rest_ensure_response(['access_token' => $maskedToken, 'status' => 200]);
        }

        return rest_ensure_response(['access_token' => 'Token not set or empty', 'status' => 400]);
    }

    // Set a new access token
    public function set_access_token(WP_REST_Request $request): WP_REST_Response
    {
        $token = sanitize_text_field($request->get_param('access_token'));
        $squareHelper = new SquareHelper();

        if (!$squareHelper->is_token_valid($token)) {
            $error_data = ['message' => 'The provided token is invalid', 'status' => 400];
            return rest_ensure_response($error_data, 400);
        }

        $encryptedToken = $squareHelper->encrypt_access_token($token);
        $settings = get_option('square-woo-sync_settings', []);
        $settings['access_token'] = $encryptedToken;
        update_option('square-woo-sync_settings', $settings);

        return rest_ensure_response(['message' => 'Access token updated successfully', 'status' => 200]);
    }

    public function get_locations(): WP_REST_Response
    {
        $square = new SquareHelper();
        $locations = $square->square_api_request('/locations');
        return rest_ensure_response(['locations' => $locations, 'status' => 200]);
    }


    // Delete the access token
    public function delete_access_token(): WP_REST_Response
    {
        $settings = get_option('square-woo-sync_settings', []);
        unset($settings['access_token']);
        unset($settings['location']);
        update_option('square-woo-sync_settings', $settings);

        return rest_ensure_response(['message' => 'Access token removed successfully']);
    }


    public function get_settings(WP_REST_Request $request): WP_REST_Response
    {
        $settings = get_option('square-woo-sync_settings', []);

        if ($settings === false) {
            // Error occurred while retrieving settings.
            $error_message = 'Error retrieving plugin settings.';
            $response = new WP_REST_Response(['error' => $error_message], 500);
        } else {
            $requested_setting = sanitize_text_field($request->get_param('setting'));
            if (!empty($requested_setting) && isset($settings[$requested_setting])) {
                $value = $settings[$requested_setting];
                // Check if the requested setting is "access_token"
                if ($requested_setting === 'access_token') {
                    // Exclude "access_token" from the response
                    $response = new WP_REST_Response([], 200);
                } else {
                    $response = new WP_REST_Response([$requested_setting => $value], 200);
                }
            } else {
                // Exclude "access_token" from the response if no specific setting is requested
                unset($settings['access_token']);
                $response = new WP_REST_Response($settings, 200);
            }
        }

        // Set the content type header to JSON.
        $response->set_headers(['Content-Type' => 'application/json']);

        return $response;
    }

    /**
     * Gets cron status
     *
     * @return WP_REST_Response|WP_Error
     */
    public function get_cron()
    {
        try {
            $current_settings = get_option('square-woo-sync_settings', []);

            if (!isset($current_settings['cron'])) {
                return new WP_REST_Response(array('status' => false), 200);
            }


            $next_run_gmt = wp_next_scheduled('square-woo-sync_pro_cron_hook');
            $next_run_local = $next_run_gmt ? get_date_from_gmt(date('Y-m-d H:i:s', $next_run_gmt), 'M j, Y g:i A') : 'Not scheduled';
            $current_time_gmt = current_time('timestamp', true);
            $schedule = $current_settings['cron']['schedule'] ?? '';

            switch ($schedule) {
                case 'hourly':
                    // Calculate the next run time for hourly schedules
                    $current_time_local = current_time('timestamp'); // Local time
                    $next_hour = strtotime(date('Y-m-d H:i:00', strtotime('+1 hour', $current_time_local)));
                    $next_run_local = 'Hourly';

                    // Check if the next scheduled time has passed
                    if ($next_run_gmt && $next_run_gmt <= $current_time_gmt) {
                        // Calculate time until the next hour as the next run time
                        $time_until_next_run_text = 'in ' . human_time_diff($current_time_gmt, $next_hour);
                    } else {
                        $time_until_next_run_text = $next_run_gmt ? 'in ' . human_time_diff($current_time_gmt, $next_run_gmt) : '';
                    }
                    break;

                case 'daily':
                    // For daily, specify the run time as 12:00 AM
                    $next_run_local = 'Daily at 12:00 AM';
                    $time_until_next_run_text = '';
                    break;

                case 'twicedaily':
                    // For twice daily, specify the run times as 12:00 PM and 12:00 AM
                    $next_run_local = 'Twice daily at 12:00 PM and 12:00 AM';
                    $time_until_next_run_text = '';
                    break;

                case 'weekly':
                    // For twice daily, specify the run times as 12:00 PM and 12:00 AM
                    $next_run_local = 'Monday at 12:00 AM';
                    $time_until_next_run_text = '';
                    break;

                default:
                    // Default handling if schedule is not recognized
                    $next_run_local = 'Not scheduled';
                    $time_until_next_run_text = '';
                    break;
            }

            $cron_status = [
                'status' => $current_settings['cron']['enabled'],
                'next_run' => $next_run_local,
                'data_to_import' => $current_settings['cron']['dataToUpdate'],
                'direction' => $current_settings['cron']['source'] === 'square' ? 'WooCommerce' : 'Square',
                'time_until_next_run' => $time_until_next_run_text
            ];

            return new WP_REST_Response($cron_status, 200);
        } catch (\Exception $e) {
            // Log the error message for debugging.
            error_log('Error retrieving cron: ' . $e->getMessage());
            return new WP_Error('rest_cron_error', $e->getMessage(), array('status' => 500));
        }
    }




    /**
     * Updates the plugin settings.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function update_settings(WP_REST_Request $request)
    {
        $params = $request->get_json_params();

        if (empty($params)) {
            $error_response = new WP_Error('empty_request', 'No settings provided in the request.', ['status' => 400]);
            return rest_ensure_response($error_response);
        }

        $current_settings = get_option('square-woo-sync_settings', []);

        if ($current_settings === false) {
            $error_response = new WP_Error('get_option_error', 'Failed to retrieve current settings.', ['status' => 500]);
            return rest_ensure_response($error_response);
        }

        // Check if 'cron' key exists in params
        if (array_key_exists('cron', $params)) {
            $cronManager = new CronManager();
            $cronManager->update_cron($params['cron']['enabled'], $params['cron']['schedule']);
        }

        $updated = [];
        foreach ($params as $key => $value) {
            $current_settings[$key] = $value;
            $updated[$key] = $value;
        }

        update_option('square-woo-sync_settings', $current_settings);

        if ($updated === false) {
            $error_response = new WP_Error('update_option_error', 'Failed to update settings.', ['status' => 500]);
            return rest_ensure_response($error_response);
        }

        return rest_ensure_response($updated);
    }

    public function get_gateway_settings()
    {
        try {
            $gateway_id = 'squaresync_credit';
            $gateway_settings = get_option('woocommerce_' . $gateway_id . '_settings', array());
            return new WP_REST_Response($gateway_settings, 200);
        } catch (\Exception $e) {
            // Log the error message for debugging.
            error_log('Error retrieving settings: ' . $e->getMessage());
            return new WP_Error('rest_cron_error', $e->getMessage(), array('status' => 500));
        }
    }

    public function update_gateway_settings(WP_REST_Request $request)
    {
        try {
            // Retrieve the gateway ID and the settings from the request.
            $gateway_id = 'squaresync_credit'; // Same as used in your get function
            $settings_key = 'woocommerce_' . $gateway_id . '_settings';

            // Get the current settings from the database.
            $current_settings = get_option($settings_key, array());

            // Get the updated settings from the request body.
            $updated_settings = $request->get_params(); // This retrieves all the fields sent in the POST request

            // Merge the updated settings with the existing settings.
            $new_settings = array_merge($current_settings, $updated_settings);

            // Update the settings in the database.
            update_option($settings_key, $new_settings);

            // Return the updated settings.
            return new WP_REST_Response($new_settings, 200);
        } catch (\Exception $e) {
            // Log the error message for debugging.
            error_log('Error updating settings: ' . $e->getMessage());
            return new WP_Error('rest_update_error', $e->getMessage(), array('status' => 500));
        }
    }
}