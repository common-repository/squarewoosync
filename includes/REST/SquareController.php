<?php

namespace Pixeldev\SquareWooSync\REST;

use Pixeldev\SquareWooSync\Abstracts\RESTController;
use Pixeldev\SquareWooSync\Customer\Customers;
use Pixeldev\SquareWooSync\Logger\Logger;
use Pixeldev\SquareWooSync\Square\SquareInventory;
use Pixeldev\SquareWooSync\Square\SquareHelper;
use Pixeldev\SquareWooSync\Square\SquareImport;
use PO;
use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class SquareController extends RESTController
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
    protected $base = 'square-inventory';

    /**
     * Registers the routes for handling inventory.
     *
     * @return void
     */

    public function register_routes()
    {
        $routes = [
            ['', WP_REST_Server::READABLE, 'get_square_inventory', 'check_permission'],
            ['/import', WP_REST_Server::EDITABLE, 'import_to_woocommerce', 'check_permission'],
            ['/update', WP_REST_Server::EDITABLE, 'receive_square_update', 'check_ip_permission'],
            ['/saved-inventory', WP_REST_Server::READABLE, 'get_saved_inventory', 'check_permission'],
        ];

        foreach ($routes as $route) {
            $args = [
                'methods' => $route[1],
                'callback' => [$this, $route[2]],
                'permission_callback' => [$this, $route[3]],
            ];

            register_rest_route($this->namespace, '/' . $this->base . $route[0], $args);
        }
    }


    public function receive_square_update(WP_REST_Request $request)
    {
        $this->acknowledge_receipt();
        $body = json_decode($request->get_body(), true);
        $settings = get_option('square-woo-sync_settings', []);
        $canImport = isset($settings['squareAuto']['isActive']) ? $settings['squareAuto']['isActive'] : false;


        $logger = new Logger();

        $canStockUpdate = isset($settings['squareAuto']['stock']) ? $settings['squareAuto']['stock'] : false;

        $canCustomerUpdate = isset($settings['customers']['auto']['squareWoo']) ? $settings['customers']['auto']['squareWoo'] : false;
        $canCustomerMatch = isset($settings['customers']['autoMatching']['squareWoo']) ? $settings['customers']['autoMatching']['squareWoo'] : false;
        $canCreateCustomer = isset($settings['customers']['autoCreation']['squareWoo']) ? $settings['customers']['autoCreation']['squareWoo'] : false;
        // Check the event type
        $eventType = sanitize_text_field($body['type'] ?? '');

        switch ($eventType) {
            case 'inventory.count.updated':
                if ($canStockUpdate && $canImport) {
                    $this->handle_inventory_count_updated($body);
                }
                break;
            case 'catalog.version.updated':
                if ($canImport) {
                    $this->handle_catalog_version_updated($body, $settings['squareAuto']);
                }
                break;
            case 'customer.updated':
                if ($canCustomerUpdate) {
                    $this->handle_customer_updated($body, $settings['customers']['auto']['squareWoo']);
                }
            case 'customer.created':
                $customer = new Customers();
                if ($canCreateCustomer) {
                    $customer->match_or_create_from_square($body);
                }
                if ($canCustomerMatch) {
                    $customer->match_from_square($body);
                }
                break;
            default:
                // Handle unknown event type
                error_log('Received unknown event type: ' . $eventType);
                break;
        }
    }

    private function acknowledge_receipt()
    {
        status_header(200);
    }

    public function handle_customer_updated($data, $settings)
    {
        try {
            if (!$settings['is_active']) {
                return;
            }
            $uniqueProcessId = wp_generate_uuid4();
            $logger = new Logger();

            $square_customer_id = $data['data']['object']['customer']['id'];
            $updated_info = $data['data']['object']['customer'];
            $group_ids = $updated_info['group_ids'] ?? [];

            // Find the corresponding WooCommerce customer
            $args = array(
                'meta_key' => 'square_customer_id',
                'meta_value' => $square_customer_id,
                'number' => 1,
                'count_total' => false,
            );

            $users = get_users($args);

            if (!empty($users)) {
                $user_id = $users[0]->ID;

                // Check if the update is coming from Square
                if (get_user_meta($user_id, '_update_source', true) === 'woo') {
                    // Reset the source to avoid loops
                    update_user_meta($user_id, '_update_source', '');
                    return;
                }

                // Add a source identifier
                update_user_meta($user_id, '_update_source', 'square');

                // Update WordPress user
                $user = new \WP_User($user_id);

                if (isset($settings['first_name']) && $settings['first_name'] === true && !empty($updated_info['given_name'])) {
                    update_user_meta($user_id, 'first_name', $updated_info['given_name']);
                    update_user_meta($user_id, 'billing_first_name', $updated_info['given_name']);
                }
                if (isset($settings['last_name']) && $settings['last_name'] === true && !empty($updated_info['family_name'])) {
                    update_user_meta($user_id, 'last_name', $updated_info['family_name']);
                    update_user_meta($user_id, 'billing_last_name', $updated_info['family_name']);
                }
                if (isset($settings['phone']) && $settings['phone'] === true && !empty($updated_info['phone_number'])) {
                    update_user_meta($user_id, 'billing_phone', $updated_info['phone_number']);
                }
                if (isset($settings['address']) && $settings['address'] === true && !empty($updated_info['address'])) {
                    $address = $updated_info['address'];
                    if (!empty($address['address_line_1'])) {
                        update_user_meta($user_id, 'billing_address_1', $address['address_line_1']);
                    }
                    if (!empty($address['address_line_2'])) {
                        update_user_meta($user_id, 'billing_address_2', $address['address_line_2']);
                    }
                    if (!empty($address['locality'])) {
                        update_user_meta($user_id, 'billing_city', $address['locality']);
                    }
                    if (!empty($address['administrative_district_level_1'])) {
                        update_user_meta($user_id, 'billing_state', $address['administrative_district_level_1']);
                    }
                    if (!empty($address['postal_code'])) {
                        update_user_meta($user_id, 'billing_postcode', $address['postal_code']);
                    }
                }

                if (isset($settings['role']) && $settings['role'] === true) {
                    // Role Mapping
                    $settings = get_option('square-woo-sync_settings', []);
                    $role_mappings = $settings['customers']['roleMappings'] ?? [];
                    $primary_role = 'customer';
                    $additional_roles = [];

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

                    $user->set_role($primary_role);

                    foreach ($additional_roles as $additional_role) {
                        $user->add_role($additional_role);
                    }
                }

                $logger->log('success', 'Customer ' . $user->user_email . ' has been successfully updated in Woo', array('process_id' => $uniqueProcessId));
            }
        } catch (\Exception $e) {
            error_log('Error updating customer: ' . $e->getMessage());

            $logger->log('error', 'Error updating customer: ' . $e->getMessage(), array('process_id' => $uniqueProcessId));
        }
    }


    private function handle_catalog_version_updated($data, $canImportData)
    {
        static $logger = null;

        if (!$logger) {
            $logger = new Logger();
        }

        try {
            $updated_at = new \DateTime($data['data']['object']['catalog_version']['updated_at']);
            $updated_at->modify('-1 millisecond');
            $beginTime = $updated_at->format(\DateTime::ATOM);

            $requestBody = [
                'object_types' => ['ITEM'],
                'include_deleted_objects' => true, // Include deleted objects
                'include_related_objects' => false,
                'begin_time' => $beginTime
            ];

            SquareHelper::queue_request('/catalog/search', 'POST', $requestBody, null, function ($response) use ($canImportData) {

                static $squareImport = null;
                static $squareInventory = null;

                $logger = new Logger();
                $square = new SquareHelper();

                if (isset($response) && $response['success']) {
                    if (!empty($response['data']['objects'])) {
                        if (!$squareImport) {
                            $squareImport = new SquareImport();
                        }
                        if (!$squareInventory) {
                            $squareInventory = new SquareInventory();
                        }


                        $dataToImport = [
                            'sku' => $canImportData['sku'],
                            'title' => $canImportData['title'],
                            'description' => $canImportData['description'],
                            'stock' => false, // stock is handled by handle_inventory_count_updated
                            'price' => $canImportData['price'],
                            'categories' => $canImportData['category'],
                            'image' => $canImportData['images']
                        ];

                        $success = false;

                        foreach ($response['data']['objects'] as &$product) {
                            // Check if the product is deleted
                            if (isset($product['is_deleted']) && $product['is_deleted'] === true) {
                                // Get the current settings
                                $current_settings = get_option('square-woo-sync_settings', []);
                                // Check if auto product deletion is enabled in the settings
                                if (!empty($current_settings) && !empty($current_settings['squareAuto']['autoDeleteProduct']) && $current_settings['squareAuto']['autoDeleteProduct'] === true) {
                                    // Fetch WooCommerce product with minimal memory usage
                                    $woo_products = $this->get_woocommerce_products_square($product['id'], $product['id']);
                                    if (!empty($woo_products)) {
                                        foreach ($woo_products as $woo_product) {
                                            // Move product to trash
                                            wp_trash_post($woo_product['ID']);
                                            $logger->log('success', 'Moved WooCommerce product ' . $woo_product['name'] . ' to trash via Square update', ["name" => $woo_product['name']]);
                                        }
                                    }
                                    return; // Skip the update since the product is deleted
                                }
                            } elseif (isset($product['item_data']['is_archived']) && $product['item_data']['is_archived'] === true) {
                                // Fetch WooCommerce product with minimal memory usage
                                $woo_products = $this->get_woocommerce_products_square($product['id'], $product['id']);
                                if (!empty($woo_products)) {
                                    foreach ($woo_products as $woo_product) {
                                        // Set product status to draft
                                        wp_update_post([
                                            'ID' => $woo_product['ID'],
                                            'post_status' => 'draft'
                                        ]);
                                        $logger->log('success', 'Set WooCommerce product ' . $woo_product['name'] . ' to draft via Square update', ["name" => $woo_product['name']]);
                                    }
                                }
                                return; // Skip the update since the product is archived
                            }

                            $this->processProductVariations($product, $square);

                            if (!empty($product['item_data']['image_ids'])) {
                                $product['item_data']['image_urls'] = [];
                                foreach ($product['item_data']['image_ids'] as $id) {
                                    $product['item_data']['image_urls'][] = $squareInventory->fetch_image_url($id);
                                }
                            }

                            $categories = $squareInventory->get_all_square_categories();

                            if (isset($product['item_data']['category_id']) && isset($categories[$product['item_data']['category_id']])) {
                                $product['item_data']['category_name'] = $categories[$product['item_data']['category_id']];
                            }
                        }
                        unset($product); // Cleanup reference

                        $importResults = $squareImport->import_products($response['data']['objects'], $dataToImport, true);

                        if (!empty($importResults) && $importResults[0]['status'] === 'success') {
                            $success = true;
                        }
                    }
                    if ($success) {
                        $logger->log('success', 'Successfully synced product from Square update', ["product_id" => '']);
                    }
                } else {
                    $logger->log('error', 'Square API request failed', ["error_message" => wp_json_encode($response)]);
                }
            });
        } catch (\Exception $e) {
            $logger->log('error', 'Error in updating product via webhook', ["error_message" => $e->getMessage()]);
        }
    }

    private function processProductVariations(&$product, $square)
    {
        foreach ($product['item_data']['variations'] as &$variation) {
            if (isset($variation['item_variation_data']['item_option_values'])) {
                $newOptionValues = [];
                foreach ($variation['item_variation_data']['item_option_values'] as $option) {
                    $optionName = $this->fetchOptionName($square, $option['item_option_id']);
                    $optionValue = $this->fetchOptionValue($square, $option['item_option_value_id']);

                    $newOptionValues[] = [
                        "option_name" => $optionName,
                        "option_value" => $optionValue
                    ];
                }
                $variation['item_variation_data']['item_option_values'] = $newOptionValues;
            }
        }
        unset($variation);
    }

    private function fetchOptionName($square, $optionId)
    {
        $optionNameRequest = $square->square_api_request('/catalog/object/' . $optionId . '?include_related_objects=false');
        return $optionNameRequest['success'] === true ? $optionNameRequest['data']['object']['item_option_data']['name'] : null;
    }

    private function fetchOptionValue($square, $optionValueId)
    {
        $optionValueRequest = $square->square_api_request('/catalog/object/' . $optionValueId . '?include_related_objects=false');
        return $optionValueRequest['success'] === true ? $optionValueRequest['data']['object']['item_option_value_data']['name'] : null;
    }


    private function handle_inventory_count_updated($data)
    {
        static $logger = null;
        static $square = null;

        if (!$logger) {
            $logger = new Logger();
        }

        try {
            $catalogObjectId = $data['data']['object']['inventory_counts'][0]['catalog_object_id'] ?? null;
            if (!$catalogObjectId) {
                return; // No catalog object ID found, early exit
            }

            if (!$square) {
                $square = new SquareHelper();
            }

            SquareHelper::queue_request("/catalog/object/" . $catalogObjectId, 'GET', null, null, function ($response) use ($logger, $catalogObjectId, $data) {
                $squareItemDetails = $response['data'];
                if ($squareItemDetails) {
                    $wooProducts = $this->get_woocommerce_products_square(
                        $squareItemDetails['object']['item_variation_data']['item_id'],
                        $squareItemDetails['object']['id']
                    );

                    foreach ($wooProducts as $wcProduct) {
                        $wcSquareProductId = $wcProduct['square_product_id'] ?? null;
                        $wcProductId = $wcProduct['ID'] ?? null;

                        if ($wcProductId && $wcSquareProductId) {
                            $product = wc_get_product($wcProductId);
                            $id = $product->is_type('simple') ? $squareItemDetails['object']['item_variation_data']['item_id'] : $squareItemDetails['object']['id'];

                            if ($wcSquareProductId === $id) {
                                if (is_a($product, 'WC_Product')) {
                                    $newQuantity = $data['data']['object']['inventory_counts'][0]['quantity'];
                                    $product->set_manage_stock(true);
                                    $product->set_stock_quantity($newQuantity);
                                    $product->save();
                                    $uniqueProcessId = wp_generate_uuid4();
                                    $logger->log('success', 'Stock for ' . $product->get_name() . ' been successfully updated to: ' . $newQuantity, ["product_id" => $product->get_id(), 'process_id' => $uniqueProcessId]);
                                }
                                break; // Match found, exit loop
                            }
                        }
                    }
                } else {
                    $logger->log('error', 'Failed in updating product inventory via webhook', ["error_message" => "Square catalog object ID: " . $catalogObjectId]);
                }
            });
        } catch (\Exception $e) {
            $logger->log('error', 'Error in updating product inventory via webhook', ["error_message" => $e->getMessage()]);
        }
    }


    private function get_token_and_validate()
    {
        $square = new SquareHelper();
        $token = $square->get_access_token();

        if (!$token) {
            return new WP_Error(401, 'Access token not set');
        }

        if (!$square->is_token_valid()) {
            return new WP_Error(401, 'Invalid access token');
        }

        return $token;
    }

    /**
     * Retrieve WooCommerce product data with minimal memory usage, only for products with a specified square_product_id.
     *
     * @param string $squareProductId1 First Square product ID to filter by.
     * @param string $squareProductId2 Second Square product ID to filter by.
     * @return array Array of products with matching Square product IDs.
     */
    public function get_woocommerce_products_square($squareProductId1, $squareProductId2)
    {
        global $wpdb;

        $squareProductId1 = sanitize_text_field($squareProductId1);
        $squareProductId2 = sanitize_text_field($squareProductId2);

        // Execute the query directly and return results
        $results = $wpdb->get_results($wpdb->prepare("
        SELECT p.ID, p.post_title AS name, meta1.meta_value AS sku, meta2.meta_value AS square_product_id
        FROM {$wpdb->prefix}posts AS p
        LEFT JOIN {$wpdb->prefix}postmeta AS meta1 ON (p.ID = meta1.post_id AND meta1.meta_key = '_sku')
        LEFT JOIN {$wpdb->prefix}postmeta AS meta2 ON (p.ID = meta2.post_id AND meta2.meta_key = 'square_product_id')
        WHERE p.post_type IN ('product', 'product_variation')
        AND meta2.meta_value IN (%s, %s)
        ORDER BY p.ID", $squareProductId1, $squareProductId2), ARRAY_A);

        return $results;
    }


    /**
     * Retrieve WooCommerce product data with minimal memory usage.
     *
     * @return array
     */
    public function get_woocommerce_products()
    {
        global $wpdb;

        // Execute the query directly and return results
        $results = $wpdb->get_results("
    SELECT p.ID, p.post_title AS name, meta1.meta_value AS sku, meta2.meta_value AS square_product_id, meta3.meta_value AS square_variation_id
    FROM {$wpdb->prefix}posts AS p
    LEFT JOIN {$wpdb->prefix}postmeta AS meta1 ON (p.ID = meta1.post_id AND meta1.meta_key = '_sku')
    LEFT JOIN {$wpdb->prefix}postmeta AS meta2 ON (p.ID = meta2.post_id AND meta2.meta_key = 'square_product_id')
    LEFT JOIN {$wpdb->prefix}postmeta AS meta3 ON (p.ID = meta3.post_id AND meta3.meta_key = 'square_variation_id')
    WHERE p.post_type IN ('product', 'product_variation')
    AND p.post_status = 'publish'
    ORDER BY p.ID", ARRAY_A);

        return $results;
    }

    /**
     * Compares Square SKU with WooCommerce SKU for matching purposes and updates the import status.
     *
     * @param array $squareInventory
     * @param array $woocommerceProducts
     * @param object $square
     * @return array
     */
    public function compare_skus($squareInventory, $woocommerceProducts, $square)
    {
        $categories = $square->get_all_square_categories();
        $result = [];

        // Create a mapping of WooCommerce square_product_id to WooCommerce product IDs
        $squareProductIdMapping = [];
        foreach ($woocommerceProducts as $wcProduct) {
            $wcSquareProductId = $wcProduct['square_product_id'] ?? null;
            $wcSquareVariationId = $wcProduct['square_variation_id'] ?? null;
            $wcProductId = $wcProduct['ID'] ?? null;

            if ($wcSquareProductId && $wcProductId) {
                $squareProductIdMapping[$wcSquareProductId] = $wcProductId;
            }

            if ($wcSquareVariationId && $wcProductId) {
                $squareProductIdMapping[$wcSquareVariationId] = $wcProductId;
            }
        }

        foreach ($squareInventory as $squareItem) {
            $itemData = $squareItem;

            if (isset($itemData['item_data']['categories'])) {
                // Create an associative array for easy lookup
                $lookup = [];
                foreach ($categories as $item) {
                    $lookup[$item['id']] = $item;
                }

                // Merge the arrays
                foreach ($itemData['item_data']['categories'] as &$item) {
                    if (isset($lookup[$item['id']])) {
                        $item['name'] = $lookup[$item['id']]['name'];
                        $item['parent_id'] = $lookup[$item['id']]['parent_id'];
                    }
                }
                unset($item); // break the reference with the last element

                $itemData['item_data']['categories'] = $itemData['item_data']['categories'];
            }

            $squareProductId = $itemData['id'] ?? null;

            // Check if the Square product ID is in the matched square_product_id and add WooCommerce product ID
            $itemData['imported'] = false;
            if ($squareProductId && isset($squareProductIdMapping[$squareProductId])) {
                $itemData['imported'] = true;
                $itemData['woocommerce_product_id'] = $squareProductIdMapping[$squareProductId];
            }

            if (isset($itemData['item_data']['variations'])) {
                foreach ($itemData['item_data']['variations'] as &$variation) {
                    $variationId = $variation['id'] ?? null;

                    // Check if the Square variation ID is in the matched square_product_id and add WooCommerce product ID
                    $variation['imported'] = false;
                    if ($variationId && isset($squareProductIdMapping[$variationId])) {
                        $variation['imported'] = true;
                        $variation['woocommerce_product_id'] = $squareProductIdMapping[$variationId];
                    }
                }
                unset($variation); // Break the reference with the last element
            }

            // Additional check for simple products with square_variation_id in WooCommerce products
            foreach ($woocommerceProducts as $wcProduct) {
                if (isset($wcProduct['square_variation_id'])) {
                    $squareVariationId = $wcProduct['square_variation_id'];
                    if ($squareVariationId && in_array($squareVariationId, array_column($itemData['item_data']['variations'], 'id'))) {
                        $itemData['imported'] = true;
                        $itemData['woocommerce_product_id'] = $wcProduct['ID'];
                    }
                }
            }

            $result[] = $itemData;
        }

        return $result;
    }


    public function get_square_inventory(WP_REST_Request $request)
    {
        global $wpdb;
        try {
            $token = $this->get_token_and_validate();
            $woocommerceInstalled = $this->check_woocommerce();
            if (!$woocommerceInstalled) {
                return rest_ensure_response(new WP_Error(424, 'Woocommerce not installed or activated'));
            }
            if (is_wp_error($token)) {
                return rest_ensure_response(new WP_Error(401, 'Invalid access token'));
            }

            $force = $request->get_param('force') === 'true';
            $table_name = $wpdb->prefix . 'square_inventory';
            $cron_option = 'update_square_inventory_cron';

            if ($force) {
                // Clear the table and set the cron status to running
                $result = $wpdb->query("TRUNCATE TABLE $table_name");
                if ($result === false) {
                    throw new \Exception('Failed to truncate table.');
                }

                wp_schedule_single_event(time(), $cron_option);

                // Ensure settings exist
                $settings = get_option('square-woo-sync_settings', []);
                if (!isset($settings['inventory'])) {
                    $settings['inventory'] = [];
                }
                $settings['inventory']['isFetching'] = 1;

                update_option('square-woo-sync_settings', $settings);

                return new WP_REST_Response(['message' => 'Fetching data, please wait...', 'loading' => true, 'data' => []], 200);
            } else {
                // Ensure settings exist
                $settings = get_option('square-woo-sync_settings', []);
                if (!isset($settings['inventory'])) {
                    $settings['inventory'] = [];
                }
                $isRunning = isset($settings['inventory']['isFetching']) ? $settings['inventory']['isFetching'] : 0;

                if (wp_next_scheduled($cron_option) || $isRunning === 1) {
                    return new WP_REST_Response(['message' => 'Data is being fetched, please wait...', 'loading' => true, 'data' => []], 200);
                }

                // Fetch saved data from the database
                $saved_data = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);

                $inventory = array();
                foreach ($saved_data as $row) {
                    $inventory[] = maybe_unserialize($row['product_data']);
                }

                if ($inventory === null) {
                    throw new \Exception('Failed to fetch saved data.');
                }

                return new WP_REST_Response(['message' => 'Data has been fetched', 'loading' => false, 'data' => $inventory], 200);
            }
        } catch (\Exception $e) {
            error_log('Error in get_square_inventory: ' . $e->getMessage());
            return new WP_REST_Response(['message' => 'An error occurred: ' . $e->getMessage(), 'loading' => false, 'data' => []], 500);
        }
    }

    /**
     * Updates the Square inventory and saves it to the database.
     *
     * @return void
     */
    public static function update_square_inventory_function()
    {
        try {
            // Fetch the current settings
            $settings = get_option('square-woo-sync_settings', []);

            // Ensure the inventory key exists
            if (!isset($settings['inventory'])) {
                $settings['inventory'] = [];
            }

            // Set fetching status to true
            $settings['inventory']['isFetching'] = 1;
            update_option('square-woo-sync_settings', $settings);

            $squareInv = new SquareInventory();
            $instance = new self();
            $token = $instance->get_token_and_validate();
            if ($token) {
                // Set transient to indicate the cron job is running
                set_transient('update_square_inventory_cron', true, 3600);

                $inventory = $squareInv->retrieve_inventory();
                unset($woocommerceProducts);
                $woocommerceProducts = $instance->get_woocommerce_products();
                $matches = $instance->compare_skus($inventory, $woocommerceProducts, $squareInv);
                $instance->save_inventory_to_db($matches); // Save the inventory to the database

                // Delete the transient when the job is finished
                delete_transient('update_square_inventory_cron');

                // Set fetching status to false
                $settings['inventory']['isFetching'] = 0;
                update_option('square-woo-sync_settings', $settings);
            } else {
                // Set fetching status to false if token validation fails
                $settings['inventory']['isFetching'] = 0;
                update_option('square-woo-sync_settings', $settings);
                throw new \Exception('Access token not set');
            }
        } catch (\Exception $e) {
            // Set fetching status to false if an error occurs
            $settings['inventory']['isFetching'] = 0;
            update_option('square-woo-sync_settings', $settings);
            error_log('Error in update_square_inventory_function: ' . $e->getMessage());
        }
    }


    /**
     * Fetches saved inventory data from the database.
     *
     * @return WP_REST_Response
     */
    public function get_saved_inventory()
    {
        // Check if the cron job is already running
        if (get_transient('update_square_inventory_cron')) {
            return new WP_Error('update_square_inventory_cron', 'The cron job is currently running', array('status' => 503));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'square_inventory';
        $results = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);



        $inventory = array();
        foreach ($results as $row) {
            $inventory[] = maybe_unserialize($row['product_data']);
        }


        return rest_ensure_response($inventory);
    }

    /**
     * Saves the inventory data to the database.
     *
     * @param array $inventory Inventory data.
     * @return void
     */
    private function save_inventory_to_db($inventory)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'square_inventory';
        $batchSize = 30; // Define the size of each batch
        $batches = array_chunk($inventory, $batchSize);



        foreach ($batches as $batch) {
            $wpdb->query('START TRANSACTION');
            try {
                foreach ($batch as $product) {
                    $wpdb->replace(
                        $table_name,
                        array(
                            'product_id' => $product['id'],
                            'product_data' => maybe_serialize($product)
                        ),
                        array(
                            '%s',
                            '%s'
                        )
                    );
                }
                $wpdb->query('COMMIT');
            } catch (\Exception $e) {
                $wpdb->query('ROLLBACK');
                error_log('Error saving batch to database: ' . $e->getMessage());
            }
        }
    }

    /**
     * Clears the inventory table.
     */
    private function clear_inventory_table()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'square_inventory';

        // Check if the table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            // If the table doesn't exist, create it
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $table_name (
            id INT NOT NULL AUTO_INCREMENT,
            product_id VARCHAR(255) NOT NULL,
            product_data LONGTEXT NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        // Truncate the table
        $wpdb->query("TRUNCATE TABLE $table_name");
    }


    /**
     * Handles GET requests for inventory.
     *
     * @return WP_REST_Response
     */
    public function import_to_woocommerce(WP_REST_Request $request): WP_REST_Response
    {

        $response_obj = null;
        $license_message = null;

        $license_key = get_option("SquareWooSync_lic_Key");
        $lice_email = get_option("SquareWooSync_lic_email");


        $token = $this->get_token_and_validate();
        if (is_wp_error($token)) {
            return rest_ensure_response(new WP_Error(401, 'Invalid access token'));
        }

        $product = $request->get_param('product');
        $dataToImport = $request->get_param('datatoimport');

        $squareImport = new SquareImport();

        if ($token) {
            $wooProduct = $squareImport->import_products($product, $dataToImport);
            return rest_ensure_response($wooProduct);
        } else {
            return rest_ensure_response(new WP_Error(401, 'Access token not set'));
        }

    }
}
