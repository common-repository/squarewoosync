<?php

namespace Pixeldev\SquareWooSync\REST;

use Error;
use Pixeldev\SquareWooSync\Abstracts\RESTController;
use Pixeldev\SquareWooSync\Square\SquareHelper;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class CustomersController extends RESTController
{
    protected $namespace = 'sws/v1';
    protected $base = 'customers';

    public function register_routes()
    {
        register_rest_route(
            $this->namespace,
            '/' . $this->base,
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_customers'],
                    'permission_callback' => [$this, 'check_permission'],
                ],
            ]
        );

    
        register_rest_route(
            $this->namespace,
            '/' . $this->base . '/get-groups',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_groups'],
                    'permission_callback' => [$this, 'check_permission'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->base . '/groups-segments',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_groups_segments'],
                    'permission_callback' => [$this, 'check_permission'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->base . '/role-mappings',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_role_mappings'],
                    'permission_callback' => [$this, 'check_permission'],
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'set_role_mappings'],
                    'permission_callback' => [$this, 'check_permission'],
                    'args'                => [
                        'roleMappings' => [
                            'required' => true,
                            'validate_callback' => function ($param, $request, $key) {
                                return is_array($param);
                            }
                        ],
                    ],
                ],
            ]
        );
    }

    public function handle_process_customers(WP_REST_Request $request)
    {
        $ids = $request->get_json_params()['ids'];
        $import_data = $request->get_json_params()['importData'];

        if (empty($ids) || !is_array($ids)) {
            return new WP_Error('invalid_ids', 'Invalid or missing IDs parameter', ['status' => 400]);
        }


        return new WP_REST_Response(['success' => true, 'message' => esc_html__('Customers processed', 'squarewoosync'), 'data' => $this->process_customers($ids, $import_data)], 200);
    }

    public function process_customers(array $ids, array $import_data)
    {
        global $wpdb;

        if (empty($ids) || !is_array($ids)) {
            return new WP_Error('invalid_ids', 'Invalid or missing IDs parameter', ['status' => 400]);
        }

        $request = new WP_REST_Request();
        if (isset($import_data) && $import_data['setRole'] === true) {
            $request->set_param('setrole', 'true');
        }
        $this->match_customers($request);

        $table_name = $wpdb->prefix . 'square_woo_customers';
        $new_square_customers = [];
        $errors = [];
        // Import Square Customers
        if (isset($import_data) && ($import_data['squareToWoo'] === true || $import_data['wooToSquare'] === true || $import_data['sync'] === true)) {
            foreach ($ids as $id) {
                $customer = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id), ARRAY_A);

                if ($customer !== null) {
                    if ($customer['source'] === 'Square') {
                        if ($import_data['squareToWoo'] === true) {
                            $result = $this->create_wordpress_user($customer, $import_data);

                            if (is_wp_error($result)) {
                                $errors[] = $result;
                            } else {
                                $new_square_customers[] =  $result;
                            }
                        }
                    } else if ($customer['source'] === 'Woo') {
                        if ($import_data['wooToSquare'] === true) {
                            $result = $this->create_square_user($customer, $import_data);
                            if (is_wp_error($result)) {
                                $errors[] = $result;
                            } else {
                                $new_square_customers[] =  $result;
                            }
                        }
                    } else if ($customer['source'] === 'Both') {
                        if ($import_data['sync'] === true) {
                            $result = $this->sync_user($customer, $import_data);
                            if (is_wp_error($result)) {
                                $errors[] = $result;
                            } else {
                                $new_square_customers[] =  $result;
                            }
                        }
                    }
                    continue;
                }
            }
        }

        return ['success' => $new_square_customers, 'failed' => $errors];
    }

    private function sync_user(array $customer, array $import_data)
    {

        if ($import_data['source'] === 'WooCommerce') {
            return $this->update_square_user($customer, $import_data);
        }

        if ($import_data['source'] === 'Square') {
            return $this->update_woo_user($customer, $import_data);
        }

        return [];
    }

    private function update_woo_user(array $customer, array $import_data)
    {
        try {
            // Retrieve the Square customer ID from the customer data
            $square_customer_id = $customer['square_customer_id'];

            $squareHelper = new SquareHelper();
            $response = $squareHelper->square_api_request('/customers/' . $square_customer_id, 'GET');

            if (!$response['success']) {
                throw new \Exception('Failed to retrieve Square customer');
            }

            $square_customer = $response['data']['customer'];

            // Find the WordPress user by the Square customer ID
            $user_query = new \WP_User_Query([
                'meta_key' => 'square_customer_id',
                'meta_value' => $square_customer_id,
                'number' => 1,
            ]);

            $users = $user_query->get_results();

            if (empty($users)) {
                return new WP_Error('sync_user', 'Unable to find WordPress user with Square customer ID: ' . $square_customer_id);
            }

            $user = $users[0];
            $user_id = $user->ID;

            // Update user meta with Square customer data
            update_user_meta($user_id, 'first_name', $square_customer['given_name'] ?? '');
            update_user_meta($user_id, 'last_name', $square_customer['family_name'] ?? '');
            update_user_meta($user_id, 'billing_first_name', $square_customer['given_name'] ?? '');
            update_user_meta($user_id, 'billing_last_name', $square_customer['family_name'] ?? '');
            update_user_meta($user_id, 'billing_phone', $square_customer['phone_number'] ?? '');

            $address = $square_customer['address'] ?? [];
            update_user_meta($user_id, 'billing_address_1', $address['address_line_1'] ?? '');
            update_user_meta($user_id, 'billing_address_2', $address['address_line_2'] ?? '');
            update_user_meta($user_id, 'billing_city', $address['locality'] ?? '');
            update_user_meta($user_id, 'billing_postcode', $address['postal_code'] ?? '');
            update_user_meta($user_id, 'billing_country', $address['country'] ?? '');
            update_user_meta($user_id, 'billing_state', $address['administrative_district_level_1'] ?? '');

            // Get current roles to ensure we don't remove administrator roles
            $current_roles = $user->roles;

            // Set user roles
            $group_ids = array_map('trim', $square_customer['group_ids'] ?? []);
            $settings = get_option('square-woo-sync_settings', []);

            $primary_role = 'customer';
            $additional_roles = [];

            if (isset($import_data) && $import_data['setRole'] === true) {
                $role_mappings = $settings['customers']['roleMappings'] ?? [];
                $mapped_roles = [];
                foreach ($group_ids as $group_id) {
                    foreach ($role_mappings as $role => $mapping) {
                        if (isset($mapping['groupId']) && $mapping['groupId'] === $group_id) {
                            $mapped_roles[$role] = $mapping['priority'] ?? PHP_INT_MAX;
                        }
                    }
                }

                if (!empty($mapped_roles)) {
                    asort($mapped_roles); // Sort roles by priority, ascending
                    $primary_role = key($mapped_roles); // The role with the lowest priority
                    $additional_roles = array_keys(array_slice($mapped_roles, 1)); // Any additional roles
                }
            }

            if (!in_array('administrator', $current_roles)) {
                wp_update_user([
                    'ID' => $user_id,
                    'role' => $primary_role,
                ]);
            }

            // Add additional roles
            $user = new \WP_User($user_id);
            foreach ($additional_roles as $additional_role) {
                $user->add_role($additional_role);
            }

            // Ensure administrator role is not removed
            if (in_array('administrator', $current_roles) && !in_array('administrator', $user->roles)) {
                $user->add_role('administrator');
            }

            return $square_customer['given_name'] ?? $customer['first_name'];
        } catch (\Exception $e) {
            return new WP_Error('update_woo_user_error', $e->getMessage());
        }
    }


    private function update_square_user(array $customer, array $import_data)
    {
        try {
            $user = get_user_by('email', $customer['email']);

            if (!$user) {
                return new WP_Error('sync_user', 'Unable to find user with email: ' . $customer['email']);
            }

            $user_id = $user->ID;
            $first_name = get_user_meta($user_id, 'first_name', true);
            $last_name = get_user_meta($user_id, 'last_name', true);
            $billing_first_name = get_user_meta($user_id, 'billing_first_name', true);
            $billing_last_name = get_user_meta($user_id, 'billing_last_name', true);
            $billing_phone = get_user_meta($user_id, 'billing_phone', true);
            $billing_address_1 = get_user_meta($user_id, 'billing_address_1', true);
            $billing_address_2 = get_user_meta($user_id, 'billing_address_2', true);
            $billing_city = get_user_meta($user_id, 'billing_city', true);
            $billing_postcode = get_user_meta($user_id, 'billing_postcode', true);
            $billing_country = get_user_meta($user_id, 'billing_country', true);
            $billing_state = get_user_meta($user_id, 'billing_state', true);
            $roles = $user->roles;

            // Use billing first name and last name if first name and last name are not set
            $display_first_name = !empty($first_name) ? $first_name : $billing_first_name;
            $display_last_name = !empty($last_name) ? $last_name : $billing_last_name;

            // Get role mappings from settings
            $settings = get_option('square-woo-sync_settings', []);
            $role_mappings = $settings['customers']['roleMappings'] ?? [];

            // Determine group IDs based on user's roles
            $group_ids = [];
            foreach ($roles as $current_role) {
                foreach ($role_mappings as $role => $mapping) {
                    if ($current_role === $role && isset($mapping['groupId'])) {
                        $group_ids[] = sanitize_text_field($mapping['groupId']);
                    }
                }
            }
            // Ensure group IDs are unique and not empty
            $group_ids = array_filter(array_unique($group_ids), function ($value) {
                return !empty($value) && $value !== 'N/A';
            });

            // Prepare payload for updating Square customer
            $payload = [];
            if (!empty($display_first_name)) $payload['given_name'] = $display_first_name;
            if (!empty($display_last_name)) $payload['family_name'] = $display_last_name;
            if (!empty($billing_phone)) $payload['phone_number'] = $billing_phone;

            $address = [];
            if (!empty($billing_address_1)) $address['address_line_1'] = $billing_address_1;
            if (!empty($billing_address_2)) $address['address_line_2'] = $billing_address_2;
            if (!empty($billing_city)) $address['locality'] = $billing_city;
            if (!empty($billing_postcode)) $address['postal_code'] = $billing_postcode;
            if (!empty($billing_country)) $address['country'] = $billing_country;
            if (!empty($billing_state)) $address['administrative_district_level_1'] = $billing_state;

            if (!empty($address)) $payload['address'] = $address;

            // Check if there is at least one field to update
            if (empty($payload)) {
                return new WP_Error('square_api_error', 'At least one field must be set to update a customer.');
            }

            $squareHelper = new SquareHelper();

            // Retrieve the Square customer ID from your user meta or elsewhere
            $square_customer_id = get_user_meta($user_id, 'square_customer_id', true);

            if (!$square_customer_id) {
                return new WP_Error('square_customer_id_missing', 'Square customer ID is missing for user.');
            }

            $response = $squareHelper->square_api_request('/customers/' . $square_customer_id, 'PUT', $payload);

            if (!$response['success']) {
                // Handle specific error response from Square API
                if (isset($response['data']['errors']) && is_array($response['data']['errors'])) {
                    foreach ($response['data']['errors'] as $error) {
                        if ($error['code'] === 'BAD_REQUEST' && $error['detail'] === 'At least one field must be set to update a customer.') {
                            return new WP_Error('square_api_error', 'At least one field must be set to update a customer.');
                        }
                    }
                }
                throw new \Exception('Failed to update Square customer');
            }

            // Add customer to groups
            foreach ($group_ids as $group_id) {
                $group_response = $squareHelper->square_api_request('/customers/' . $square_customer_id . '/groups/' . $group_id, 'PUT');
                if (!$group_response['success']) {
                    throw new \Exception('Failed to add Square customer to group');
                }
            }

            return $display_first_name; // Return display_first_name if success
        } catch (\Exception $e) {
            return new WP_Error('square_api_error', $e->getMessage());
        }
    }

    private function create_square_user(array $customer, array $import_data)
    {
        try {
            if (!isset($customer['email'])) {
                return new WP_Error('invalid_data', 'Email is required');
            }

            $email = sanitize_email($customer['email']);
            $first_name = isset($customer['first_name']) ? sanitize_text_field($customer['first_name']) : '';
            $last_name = isset($customer['last_name']) ? sanitize_text_field($customer['last_name']) : '';
            $group_ids = isset($customer['group_ids']) ? explode(',', sanitize_text_field($customer['group_ids'])) : [];

            // Generate a unique idempotency key
            $idempotency_key = uniqid('square_', true);

            $payload = [
                'idempotency_key' => $idempotency_key,
                'email_address' => $email,
                'given_name' => $first_name,
                'family_name' => $last_name
            ];

            // Retrieve WordPress user by email
            $user = get_user_by('email', $email);
            if ($user) {
                $current_roles = $user->roles;
            } else {
                $current_roles = [];
            }

            // Get role mappings from settings
            $settings = get_option('square-woo-sync_settings', []);
            $role_mappings = $settings['customers']['roleMappings'] ?? [];

            // Determine group IDs based on user's roles
            foreach ($current_roles as $current_role) {
                foreach ($role_mappings as $role => $mapping) {
                    if ($current_role === $role && isset($mapping['groupId'])) {
                        $group_ids[] = sanitize_text_field($mapping['groupId']);
                    }
                }
            }

            // Ensure group IDs are unique and not empty
            $group_ids = array_filter(array_unique($group_ids), function ($value) {
                return !empty($value) && $value !== 'N/A';
            });


            $squareHelper = new SquareHelper();
            $response = $squareHelper->square_api_request('/customers', 'POST', $payload);

            if (!$response['success']) {
                return new WP_Error('square_api_error', 'Failed to create Square customer');
            }


            $square_customer_id = $response['data']['customer']['id'];

            // Add customer to groups
            foreach ($group_ids as $group_id) {
                $group_response = $squareHelper->square_api_request('/customers/' . $square_customer_id . '/groups/' . $group_id, 'PUT');
                if (!$group_response['success']) {
                    return new WP_Error('square_api_group_error', 'Failed to add Square customer to group');
                }
            }

            update_user_meta($user->ID, 'square_customer_id', $square_customer_id);


            return $first_name;
        } catch (\Exception $e) {
            error_log('Error in create_square_user: ' . $e->getMessage());
            return new WP_Error('rest_create_square_user', esc_html($e->getMessage()));
        }
    }



    private function create_wordpress_user(array $customer, array $import_data)
    {
        try {
            global $wpdb;

            if (!isset($customer['email']) || !isset($customer['first_name'])) {
                return new WP_Error('invalid_data', 'email or name doesn\'t exist');
            }

            $email = sanitize_email($customer['email']);
            $name = sanitize_user(sanitize_text_field($customer['first_name']));
            $group_ids = array_map('trim', explode(',', sanitize_text_field($customer['group_ids'])));
            $square_id = sanitize_text_field($customer['square_customer_id']);

            if (email_exists($email)) {
                return new WP_Error('existing_user', 'A user with email: ' . $email . ' already exists');
            }

            $username = sanitize_user(strtolower(preg_replace('/[^a-zA-Z0-9._-]/', '', $name)));
            $original_name = $username;
            $suffix = 1;

            while (username_exists($username)) {
                $username = sanitize_user($original_name . '.' . $suffix);
                $suffix++;
            }

            $settings = get_option('square-woo-sync_settings', []);
            $role_mappings = $settings['customers']['roleMappings'] ?? [];
            $password = wp_generate_password();
            $primary_role = 'customer';
            $additional_roles = [];

            if (isset($import_data) && $import_data['setRole'] === true) {
                $mapped_roles = [];
                foreach ($group_ids as $group_id) {
                    foreach ($role_mappings as $role => $mapping) {
                        if (isset($mapping['groupId']) && $mapping['groupId'] === $group_id) {
                            $mapped_roles[$role] = $mapping['priority'] ?? PHP_INT_MAX;
                        }
                    }
                }

                if (!empty($mapped_roles)) {
                    asort($mapped_roles); // Sort roles by priority, ascending
                    $primary_role = key($mapped_roles); // The role with the lowest priority
                    $additional_roles = array_keys(array_slice($mapped_roles, 1)); // Any additional roles
                }
            }


            if (isset($import_data) && $import_data['emails'] === true) {
                add_filter('wp_new_user_notification_email', '__return_false');
                add_filter('send_password_change_email', '__return_false');
            }

            $user_id = wp_insert_user([
                'user_login' => $username,
                'user_email' => $email,
                'user_pass'  => $password,
                'role'       => $primary_role,
                'display_name' => $name,
                'first_name' => $customer['first_name'] ?? '',
                'last_name' => $customer['last_name'] ?? ''
            ]);

            if (isset($import_data) && $import_data['emails'] === true) {
                remove_filter('wp_new_user_notification_email', '__return_false');
                remove_filter('send_password_change_email', '__return_false');
            }

            if (is_wp_error($user_id)) {
                return new WP_Error('creating_user', 'Unable to create user with email: ' . $email);
            }

            update_user_meta($user_id, 'square_customer_id', $square_id);

            // Add additional roles
            $user = new \WP_User($user_id);
            foreach ($additional_roles as $additional_role) {
                $user->add_role($additional_role);
            }

            $wpdb->update(
                $wpdb->prefix . 'square_woo_customers',
                ['status' => 1, 'source' => 'Both'],
                ['square_customer_id' => $square_id]
            );

            return $name;
        } catch (\Exception $e) {
            error_log('Error in create_wordpress_user: ' . $e->getMessage());
            return new WP_Error('rest_create_wordpress_user', esc_html($e->getMessage()));
        }
    }


    public function match_customers(WP_REST_Request $request)
    {
        global $wpdb;

        try {
            $set_role = $request->get_param('setrole') === 'true';
            $batch_size = 100;
            $total_users = $wpdb->get_var("SELECT COUNT(ID) FROM {$wpdb->users}");

            if ($total_users === null) {
                throw new \Exception('Error retrieving the total number of WordPress users');
            }

            $settings = get_option('square-woo-sync_settings', []);
            $role_mappings = $settings['customers']['roleMappings'] ?? [];

            for ($offset = 0; $offset < $total_users; $offset += $batch_size) {
                $wp_users = new \WP_User_Query([
                    'number' => $batch_size,
                    'offset' => $offset,
                ]);

                $user_results = $wp_users->get_results();

                if ($user_results === null) {
                    throw new \Exception('Error retrieving WordPress users');
                }

                $user_emails = array_map(function ($user) {
                    return strtolower($user->user_email);
                }, $user_results);

                if (!empty($user_emails)) {
                    $placeholders = implode(',', array_fill(0, count($user_emails), '%s'));
                    $query = $wpdb->prepare("SELECT id, LOWER(email) as email, square_customer_id, group_ids FROM {$wpdb->prefix}square_woo_customers WHERE source = 'Square' AND status != 1 AND LOWER(email) IN ($placeholders)", ...$user_emails);
                    $square_customers = $wpdb->get_results($query, ARRAY_A);

                    if ($square_customers === null) {
                        throw new \Exception('Error retrieving Square customers from the database');
                    }

                    $square_customers_map = [];
                    foreach ($square_customers as $customer) {
                        $square_customers_map[$customer['email']] = [
                            'square_customer_id' => $customer['square_customer_id'],
                            'group_ids' => $customer['group_ids']
                        ];
                    }

                    foreach ($user_results as $user) {
                        $lowercase_email = strtolower($user->user_email);
                        if (isset($square_customers_map[$lowercase_email])) {
                            $square_customer_id = sanitize_text_field($square_customers_map[$lowercase_email]['square_customer_id']);
                            $group_ids = array_map('trim', explode(',', sanitize_text_field($square_customers_map[$lowercase_email]['group_ids'])));

                            if (!empty($square_customer_id) && $square_customer_id !== 'n/a') {
                                $existing_meta = get_user_meta($user->ID, 'square_customer_id', true);
                                if ($existing_meta === $square_customer_id) {
                                    continue;
                                }

                                if ($existing_meta) {
                                    $result = update_user_meta($user->ID, 'square_customer_id', $square_customer_id);
                                    if ($result === false) {
                                        error_log("Failed to update user meta for user ID {$user->ID} with square_customer_id {$square_customer_id}");
                                        throw new \Exception("Failed to update user meta for user ID {$user->ID}");
                                    }
                                } else {
                                    $result = add_user_meta($user->ID, 'square_customer_id', $square_customer_id, true);
                                    if ($result === false) {
                                        error_log("Failed to add user meta for user ID {$user->ID} with square_customer_id {$square_customer_id}");
                                        throw new \Exception("Failed to add user meta for user ID {$user->ID}");
                                    }
                                }

                                $meta_result = $wpdb->replace(
                                    $wpdb->prefix . 'usermeta',
                                    [
                                        'user_id' => $user->ID,
                                        'meta_key' => 'square_customer_id',
                                        'meta_value' => $square_customer_id
                                    ],
                                    ['%d', '%s', '%s']
                                );

                                if ($meta_result === false) {
                                    error_log("Direct database update failed for user ID {$user->ID} with square_customer_id {$square_customer_id}");
                                    throw new \Exception("Failed to update or add user meta for user ID {$user->ID}");
                                }

                                if ($set_role) {
                                    $mapped_roles = [];
                                    foreach ($group_ids as $group_id) {
                                        foreach ($role_mappings as $role => $mapping) {
                                            if (isset($mapping['groupId']) && $mapping['groupId'] === $group_id) {
                                                $mapped_roles[$role] = $mapping['priority'] ?? PHP_INT_MAX;
                                            }
                                        }
                                    }

                                    if (!empty($mapped_roles)) {
                                        asort($mapped_roles); // Sort roles by priority, ascending
                                        $primary_role = key($mapped_roles); // The role with the lowest priority
                                        $additional_roles = array_keys(array_slice($mapped_roles, 1)); // Any additional roles

                                        $user->set_role($primary_role);

                                        // Add additional roles
                                        foreach ($additional_roles as $additional_role) {
                                            $user->add_role($additional_role);
                                        }
                                    }
                                }

                                $wpdb->update(
                                    $wpdb->prefix . 'square_woo_customers',
                                    ['status' => 1, 'source' => 'Both'],
                                    ['email' => $lowercase_email]
                                );

                                $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}square_woo_customers WHERE email = %s AND source = 'Woo'", $lowercase_email));
                            }
                        }
                    }
                }
            }

            return new WP_REST_Response(['success' => true, 'message' => esc_html__('Customers matched successfully', 'squarewoosync')], 200);
        } catch (\Exception $e) {
            error_log('Error in match_customers: ' . $e->getMessage());
            return new WP_Error('rest_match_customers_error', esc_html($e->getMessage()), ['status' => 500]);
        }
    }


    public function get_customers(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        try {
            $this->maybe_create_customers_table();

            $square = new SquareHelper();
            if ($square->get_access_token() !== null && $square->is_token_valid()) {
                $force = $request->get_param('force') === 'true';
                $table_name = $wpdb->prefix . 'square_woo_customers';
                $cron_option = 'squarewoosync_get_customers';

                if ($force) {
                    // Clear the table and set the cron status to running
                    $result = $wpdb->query("TRUNCATE TABLE $table_name");
                    if ($result === false) {
                        throw new \Exception('Failed to truncate table.');
                    }

                    wp_schedule_single_event(time(), $cron_option);

                    // Ensure settings exist
                    $settings = get_option('square-woo-sync_settings', []);
                    if (!isset($settings['customers'])) {
                        $settings['customers'] = [];
                    }
                    $settings['customers']['isFetching'] = 1;

                    $update_result = update_option('square-woo-sync_settings', $settings);

                    if ($update_result === false) {
                        throw new \Exception('Failed to update settings.');
                    }

                    return new WP_REST_Response(['message' => 'Fetching data, please wait...', 'loading' => true, 'data' => []], 200);
                } else {
                    // Ensure settings exist
                    $settings = get_option('square-woo-sync_settings', []);
                    if (!isset($settings['customers'])) {
                        $settings['customers'] = [];
                    }
                    $isRunning = isset($settings['customers']['isFetching']) ? $settings['customers']['isFetching'] : 0;

                    if (wp_next_scheduled($cron_option) || $isRunning === 1) {
                        return new WP_REST_Response(['message' => 'Data is being fetched, please wait...', 'loading' => true, 'data' => []], 200);
                    }

                    // Fetch saved data from the database
                    $saved_data = $wpdb->get_results("
                        SELECT 
                            id,
                            first_name,
                            last_name,
                            email,
                            source,
                            group_ids,
                            group_names,
                            role,
                            CASE WHEN status = 1 THEN true ELSE false END as status,
                            square_customer_id
                        FROM $table_name
                    ", ARRAY_A);

                    if ($saved_data === null) {
                        throw new \Exception('Failed to fetch saved data.');
                    }

                    return new WP_REST_Response(['message' => 'Data has been fetched', 'loading' => false, 'data' => $saved_data], 200);
                }
            } else {
                error_log('Invalid Access Token');
                return new WP_REST_Response(['message' => 'Invalid Access Token', 'loading' => false], 500);
            }
        } catch (\Exception $e) {
            error_log('Error in get_customers: ' . $e->getMessage());
            return new WP_REST_Response(['message' => 'An error occurred: ' . $e->getMessage(), 'loading' => false, 'data' => []], 500);
        }
    }

    private function maybe_create_customers_table()
    {
        global $wpdb;
        $customers_table_name = $wpdb->prefix . 'square_woo_customers';
        $safe_customers_table_name = esc_sql($customers_table_name);
        $charset_collate = $wpdb->get_charset_collate();

        // Check if the table exists
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like($safe_customers_table_name))) === $safe_customers_table_name;

        if (!$table_exists) {
            // Create the table if it does not exist
            $sql = "CREATE TABLE `$customers_table_name` (
                id INT NOT NULL AUTO_INCREMENT,
                first_name VARCHAR(255),
                last_name VARCHAR(255),
                email VARCHAR(100) NOT NULL,
                source VARCHAR(50) NOT NULL,
                group_ids VARCHAR(100) DEFAULT 'N/A',
                group_names VARCHAR(255) DEFAULT 'N/A', -- Add the new column for group names
                role VARCHAR(100) DEFAULT 'N/A',
                status BOOLEAN DEFAULT 0,
                square_customer_id VARCHAR(255),
                PRIMARY KEY (id)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }



    public function schedule_customer_cron()
    {
        try {
            // Ensure settings exist
            $settings = get_option('square-woo-sync_settings', []);
            if (!isset($settings['customers'])) {
                $settings['customers'] = [];
            }
            $settings['customers']['isFetching'] = 1;
            update_option('square-woo-sync_settings', $settings);

            $this->fetch_and_store_square_customers();
            $this->process_wp_users();

            $settings['customers']['isFetching'] = 0;
            update_option('square-woo-sync_settings', $settings);
        } catch (\Exception $e) {
            error_log('Error in schedule_customer_cron: ' . $e->getMessage());

            // Ensure fetching status is reset on error
            $settings = get_option('square-woo-sync_settings', []);
            $settings['customers']['isFetching'] = 0;
            update_option('square-woo-sync_settings', $settings);
        }
    }


    public function fetch_and_store_square_customers(): void
    {
        global $wpdb;
        $squareHelper = new SquareHelper();
        $cursor = null;

        $settings = get_option('square-woo-sync_settings', []);

        try {
            $groups = $squareHelper->square_api_request('/customers/groups');
            $groups = $groups['data']['groups'] ?? [];

            $groupIdToNameMap = [];
            foreach ($groups as $group) {
                $groupIdToNameMap[$group['id']] = $group['name'];
            }

            do {
                $filter = [];

                if (!empty($settings['customers']['filters']['group']) && $settings['customers']['filters']['group'] !== "0") {
                    $filter['group_ids'] = ['any' => [$settings['customers']['filters']['group']]];
                }

                if (!empty($settings['customers']['filters']['segment']) && $settings['customers']['filters']['segment'] !== "0") {
                    $filter['segment_ids'] = ['any' => [$settings['customers']['filters']['segment']]];
                }

                $body = [];

                if (!empty($filter)) {
                    $body['query'] = [
                        'filter' => $filter
                    ];
                }

                if ($cursor) {
                    $body['cursor'] = $cursor;
                }


                $url = '/customers/search';

                $response = $squareHelper->square_api_request($url, 'POST', $body);

                if (!$response['success']) {
                    throw new \Exception('Failed to fetch customers from Square');
                }

                if (isset($response['data']['customers']) && is_array($response['data']['customers'])) {
                    foreach ($response['data']['customers'] as $customer) {
                        if (empty($customer['email_address'])) {
                            continue;
                        }

                        $group_ids = isset($customer['group_ids']) ? implode(', ', $customer['group_ids']) : '';
                        $group_names = array_map(function ($id) use ($groupIdToNameMap) {
                            return $groupIdToNameMap[$id] ?? '';
                        }, explode(', ', $group_ids));

                        $customer_data = [
                            'first_name' => sanitize_text_field(($customer['given_name'] ?? '')),
                            'last_name' => sanitize_text_field(($customer['family_name'] ?? '')),
                            'email' => sanitize_email($customer['email_address']),
                            'source' => 'Square',
                            'group_ids' => sanitize_text_field($group_ids),
                            'group_names' => sanitize_text_field(implode(', ', $group_names)),
                            'role' => '',
                            'status' => 0,
                            'square_customer_id' => sanitize_text_field($customer['id'])
                        ];

                        $existing_customer = $wpdb->get_var($wpdb->prepare("
                        SELECT id FROM {$wpdb->prefix}square_woo_customers
                        WHERE square_customer_id = %s
                    ", $customer['id']));

                        if ($existing_customer) {
                            $wpdb->update(
                                $wpdb->prefix . 'square_woo_customers',
                                $customer_data,
                                ['id' => $existing_customer]
                            );
                        } else {
                            $wpdb->insert(
                                $wpdb->prefix . 'square_woo_customers',
                                $customer_data
                            );
                        }
                    }
                }

                $cursor = $response['data']['cursor'] ?? null;
            } while ($cursor);
        } catch (\Exception $e) {
            error_log('Error in fetch_and_store_square_customers: ' . $e->getMessage());
        }
    }

    private function process_wp_users(): void
    {
        global $wpdb;

        try {
            $squareHelper = new SquareHelper();
            $groups = $squareHelper->square_api_request('/customers/groups');
            $groups = $groups['data']['groups'] ?? [];

            $groupIdToNameMap = [];
            foreach ($groups as $group) {
                $groupIdToNameMap[$group['id']] = $group['name'];
            }

            $wp_users = new \WP_User_Query([
                'number' => -1
            ]);

            $user_results = $wp_users->get_results();

            if (!empty($user_results)) {
                foreach ($user_results as $user) {
                    $square_customer_id = get_user_meta($user->ID, 'square_customer_id', true);
                    $square_customer = null;

                    $billing_first_name = get_user_meta($user->ID, 'billing_first_name', true);
                    $billing_last_name = get_user_meta($user->ID, 'billing_last_name', true);

                    $first_name = sanitize_text_field($user->first_name ?: $billing_first_name);
                    $last_name = sanitize_text_field($user->last_name ?: $billing_last_name);

                    if ($square_customer_id) {
                        $square_customer = $wpdb->get_row($wpdb->prepare("
                        SELECT * FROM {$wpdb->prefix}square_woo_customers
                        WHERE square_customer_id = %s
                    ", $square_customer_id), ARRAY_A);
                    }

                    if ($square_customer) {
                        $group_ids = explode(', ', $square_customer['group_ids']);
                        $group_names = array_map(function ($id) use ($groupIdToNameMap) {
                            return $groupIdToNameMap[$id] ?? '';
                        }, $group_ids);

                        $customer_data = [
                            'first_name' => $first_name ?: '',
                            'last_name' => $last_name ?: '',
                            'email' => sanitize_email($user->user_email ?: ''),
                            'source' => 'Both',
                            'group_ids' => sanitize_text_field($square_customer['group_ids']),
                            'group_names' => sanitize_text_field(implode(', ', $group_names)),
                            'role' => sanitize_text_field(implode(', ', $user->roles)),
                            'status' => 1,
                            'square_customer_id' => $square_customer_id
                        ];

                        $wpdb->update(
                            $wpdb->prefix . 'square_woo_customers',
                            $customer_data,
                            ['id' => $square_customer['id']]
                        );
                    } else {
                        $group_ids = '';
                        $group_names = '';

                        $user_group_ids = get_user_meta($user->ID, 'square_group_ids', true);
                        if (!empty($user_group_ids)) {
                            $group_ids = sanitize_text_field($user_group_ids);
                            $group_names_array = array_map(function ($id) use ($groupIdToNameMap) {
                                return $groupIdToNameMap[$id] ?? '';
                            }, explode(', ', $group_ids));
                            $group_names = sanitize_text_field(implode(', ', $group_names_array));
                        }

                        $customer_data = [
                            'first_name' => $first_name ?: '',
                            'last_name' => $last_name ?: '',
                            'email' => sanitize_email($user->user_email ?: ''),
                            'source' => 'Woo',
                            'group_ids' => $group_ids,
                            'group_names' => $group_names,
                            'role' => sanitize_text_field(implode(', ', $user->roles)),
                            'status' => 0,
                            'square_customer_id' => sanitize_text_field($square_customer_id ?: '')
                        ];

                        $wpdb->insert(
                            $wpdb->prefix . 'square_woo_customers',
                            $customer_data
                        );
                    }
                }
            }
        } catch (\Exception $e) {
            error_log('Error in process_wp_users: ' . $e->getMessage());
        }
    }


    public function get_groups_segments(WP_REST_Request $request): WP_REST_Response
    {
        $squareHelper = new SquareHelper();

        try {
            if ($squareHelper->get_access_token() !== null && $squareHelper->is_token_valid()) {
                $groups = $squareHelper->square_api_request('/customers/groups');
                $segments = $squareHelper->square_api_request('/customers/segments');
                $groups = $groups['data']['groups'] ?? [];
                $segments = $segments['data']['segments'] ?? [];

                $response_data = [
                    'groups' => $groups,
                    'segments' => $segments,
                ];

                return new WP_REST_Response($response_data, 200);
            } else {
                return new WP_REST_Response('invalid access token', 401);
            }
        } catch (\Exception $e) {
            error_log('Error retrieving Square Customer Groups or Segments: ' . $e->getMessage());
            return new WP_Error('rest_square_error', esc_html($e->getMessage()), ['status' => 500]);
        }
    }

    public function get_role_mappings(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $settings = get_option('square-woo-sync_settings', []);
            $roleMappings = $settings['customers']['roleMappings'] ?? [];

            return new WP_REST_Response(['roleMappings' => $roleMappings], 200);
        } catch (\Exception $e) {
            error_log('Error retrieving role mappings: ' . $e->getMessage());
            return new WP_Error('rest_square_error', esc_html($e->getMessage()), ['status' => 500]);
        }
    }

    public function set_role_mappings(WP_REST_Request $request)
    {
        $roleMappings = $request->get_param('roleMappings');

        if (is_array($roleMappings)) {
            $settings = get_option('square-woo-sync_settings', []);
            $settings['customers']['roleMappings'] = $roleMappings;
            update_option('square-woo-sync_settings', $settings);

            return new WP_REST_Response(['status' => 'success', 'roleMappings' => $roleMappings], 200);
        }

        return new WP_REST_Response(['status' => 'error', 'message' => 'Invalid role mappings data'], 400);
    }

    public function schedule_cron()
    {
        $this->fetch_and_store_square_customers();
        $this->process_wp_users();
    }

    public function get_groups(WP_REST_Request $request): WP_REST_Response
    {
        $squareHelper = new SquareHelper();

        try {
            if ($squareHelper->get_access_token() !== null && $squareHelper->is_token_valid()) {
                $groups = $squareHelper->square_api_request('/customers/groups');
                $groups = $groups['data']['groups'] ?? [];

                global $wp_roles;
                if (!isset($wp_roles)) {
                    $wp_roles = new \WP_Roles();
                }
                $roles = $wp_roles->get_names();

                $settings = get_option('square-woo-sync_settings', []);
                $roleMappings = $settings['customers']['roleMappings'] ?? [];

                $response_data = [
                    'square_groups' => $groups,
                    'wp_user_roles' => $roles,
                    'roleMappings' => $roleMappings,
                ];

                return new WP_REST_Response($response_data, 200);
            } else {
                return new WP_REST_Response('invalid access token', 401);
            }
        } catch (\Exception $e) {
            error_log('Error retrieving Square Customer Groups or WordPress User Roles: ' . $e->getMessage());
            return new WP_Error('rest_square_error', esc_html($e->getMessage()), ['status' => 500]);
        }
    }
}

add_action('square_woo_sync_cron', [new CustomersController(), 'schedule_cron']);
