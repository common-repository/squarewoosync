<?php

namespace Pixeldev\SquareWooSync\Woo;

use Pixeldev\SquareWooSync\Logger\Logger;
use Pixeldev\SquareWooSync\REST\OrdersController;
use Pixeldev\SquareWooSync\Square\SquareHelper;
use Pixeldev\SquareWooSync\Woo\WooImport;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class to handle WooCommerce product interactions.
 */
class SyncProduct
{
    private $customer_update_in_progress = false;
    private $inventory_update_in_progress = false;
    /**
     * Constructor.
     */
    public function __construct()
    {
        add_action('init', array($this, 'init_woo_product'));
    }

    /**
     * Initialize WooCommerce Product hooks.
     */
    public function init_woo_product()
    {
        if (class_exists('WooCommerce')) {
            add_action('add_meta_boxes', array($this, 'add_sync_meta_box'));
            add_action('admin_post_sync_to_square', array($this, 'handle_sync_to_square'));
            add_action('admin_post_export_to_square', array($this, 'handle_export_to_square'));
            add_action('admin_footer', array($this, 'add_ajax_script'));
            add_action('wp_ajax_sync_to_square', array($this, 'handle_ajax_sync_to_square'));
            add_action('wp_ajax_export_to_square', array($this, 'handle_ajax_export_to_square'));
            add_action('sws_sync_inventory_after_product_sold_event', [$this, 'sync_inventory_after_product_sold']);
            add_action('sws_sync_order_after_product_sold_event', [$this, 'create_order_in_background']);


            add_action('woocommerce_order_status_changed', array($this, 'create_square_order_after_woo_order'), 20, 4);


            // Export product to Square on new Woo Product
            add_action('transition_post_status',  array($this, 'export_to_square'), 10, 31);

            // Update square customer
            add_action('profile_update',  array($this, 'update_square_customer'), 10, 1);
            add_action('woocommerce_update_customer',  array($this, 'update_square_customer'), 10, 1);
        }
    }

    private function get_square_customer_payload($customer, $settings)
    {
        $first_name = sanitize_text_field($customer->get_first_name());
        $last_name = sanitize_text_field($customer->get_last_name());

        // Generate a unique idempotency key
        $idempotency_key = uniqid('square_', true);

        $payload = [
            'idempotency_key' => $idempotency_key,
        ];

        if (isset($settings['first_name']) && $settings['first_name'] === true && !empty($first_name)) {
            $payload['given_name'] = $first_name;
        }

        if (isset($settings['last_name']) && $settings['last_name'] === true && !empty($last_name)) {
            $payload['family_name'] = $last_name;
        }

        if (isset($settings['phone']) && $settings['phone'] === true && !empty($customer->get_billing_phone())) {
            $payload['phone_number'] = $customer->get_billing_phone();
        }

        if (isset($settings['address']) && $settings['address'] === true) {
            $address = [];
            if (!empty($customer->get_billing_address_1())) {
                $address['address_line_1'] = $customer->get_billing_address_1();
            }
            if (!empty($customer->get_billing_address_2())) {
                $address['address_line_2'] = $customer->get_billing_address_2();
            }
            if (!empty($customer->get_billing_city())) {
                $address['locality'] = $customer->get_billing_city();
            }
            if (!empty($customer->get_billing_state())) {
                $address['administrative_district_level_1'] = $customer->get_billing_state();
            }
            if (!empty($customer->get_billing_postcode())) {
                $address['postal_code'] = $customer->get_billing_postcode();
            }
            if (!empty($customer->get_billing_country())) {
                $address['country'] = $customer->get_billing_country();
            }
            if (!empty($address)) {
                $payload['address'] = $address;
            }
        }

        return $payload;
    }

    function update_square_customer_roles($customer_id, $square_customer_id)
    {
        if ($this->customer_update_in_progress) {
            return;
        }

        $this->customer_update_in_progress = true;

        $user = get_user_by('id', $customer_id);
        if ($user) {
            $current_roles = $user->roles;
            $group_ids_to_add = [];
            $group_ids_to_remove = [];

            // Get role mappings from settings
            $settings = get_option('square-woo-sync_settings', []);
            $role_mappings = $settings['customers']['roleMappings'] ?? [];

            // Determine group IDs to add based on user's roles
            foreach ($current_roles as $current_role) {
                foreach ($role_mappings as $role => $mapping) {
                    if ($current_role === $role && isset($mapping['groupId'])) {
                        $group_ids_to_add[] = sanitize_text_field($mapping['groupId']);
                    }
                }
            }

            // Ensure group IDs are unique and not empty
            $group_ids_to_add = array_filter(array_unique($group_ids_to_add), function ($value) {
                return !empty($value) && $value !== 'N/A';
            });

            // Determine group IDs to remove
            foreach ($role_mappings as $role => $mapping) {
                if (!in_array($role, $current_roles) && isset($mapping['groupId'])) {
                    $group_ids_to_remove[] = sanitize_text_field($mapping['groupId']);
                }
            }

            $squareHelper = new SquareHelper();

            // Remove customer from groups
            foreach ($group_ids_to_remove as $group_id) {
                $remove_response = $squareHelper->square_api_request('/customers/' . $square_customer_id . '/groups/' . $group_id, 'DELETE');
                if (!$remove_response['success']) {
                    error_log('Failed to remove Square customer from group: ' . $group_id);
                }
            }

            // Add customer to groups
            foreach ($group_ids_to_add as $group_id) {
                $group_response = $squareHelper->square_api_request('/customers/' . $square_customer_id . '/groups/' . $group_id, 'PUT');
                if (!$group_response['success']) {
                    error_log('Failed to add Square customer to group: ' . $group_id);
                }
            }
        }

        $this->customer_update_in_progress = false;
    }

    public function update_square_customer($customer_id)
    {
        $uniqueProcessId = wp_generate_uuid4();
        $logger = new Logger();

        // Get the customer data
        $customer = new \WC_Customer($customer_id);

        // Get settings to check if fields are active
        $all_settings = get_option('square-woo-sync_settings', []);
        $settings = $all_settings['customers']['auto']['wooSquare'] ?? [];


        if (!$settings['is_active']) {
            return;
        }

        // Check if the update is coming from WooCommerce
        if (get_user_meta($customer_id, '_update_source', true) === 'square') {
            // Reset the source to avoid loops
            update_user_meta($customer_id, '_update_source', '');
            return;
        }

        // Add a source identifier
        update_user_meta($customer_id, '_update_source', 'woo');

        // Prepare customer data for update
        $square_customer_id = get_user_meta($customer_id, 'square_customer_id', true);
        if ($square_customer_id) {
            $customer_data = $this->get_square_customer_payload($customer, $settings);

            SquareHelper::queue_request('/customers/' . $square_customer_id, 'PUT', $customer_data, null, function ($response) use ($uniqueProcessId, $logger, $customer_id, $square_customer_id,  $customer) {
                if (!$response['success']) {
                    error_log('Failed to update Square customer: ' . json_encode($response['data']));

                    $logger->log('error', 'Failed to update Square customer: ' . json_encode($response['data']), array('error_message' => json_encode($response['data']), 'process_id' => $uniqueProcessId));
                } else {
                    if (isset($settings['role']) && $settings['role'] === true) {
                        $this->update_square_customer_roles($customer_id, $square_customer_id);
                    }
                    $logger->log('success', 'Customer ' . $customer->get_email() . ' has been successfully updated in Square', array('process_id' => $uniqueProcessId));
                }
            });
        }
    }

    /**
     * Adds a meta box for syncing with Square.
     */
    public function add_sync_meta_box()
    {
        add_meta_box(
            'sws_sync_square',
            'Sync with Square',
            array($this, 'sync_meta_box_html'),
            'product',
            'side',
            'high'
        );
    }

    /**
     * Adds JavaScript for AJAX synchronization.
     */
    public function add_ajax_script()
    {
        $screen = get_current_screen();
        if ('product' !== $screen->id) {
            return;
        }

        $js_file_url =  plugins_url('', SQUAREWOOSYNC_FILE) . '/assets/js/sync-metabox.js';

        wp_enqueue_script('sws-custom-script', $js_file_url, array('jquery'), '1.0', true);
        wp_localize_script('sws-custom-script', 'swsAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('sws_ajax_nonce'),
        ));
    }


    public function sync_inventory_after_product_sold($order_id)
    {
        $order = wc_get_order($order_id);
        // Check if $order is a valid WC_Order object and return early if not
        if ($this->inventory_update_in_progress) {
            return;
        }

        // Get the current settings once, and return early if the sync isn't enabled or configured correctly
        $current_settings = get_option('square-woo-sync_settings', []);
        if (empty($current_settings) || !$current_settings['wooAuto']['isActive'] || !$current_settings['wooAuto']['stock']) {
            return;
        }

        $this->inventory_update_in_progress = true;

        // Initialize variables
        $uniqueProcessId = wp_generate_uuid4();
        $hasSquareLinkedProduct = false;
        $logger = null; // Defer logger initialization
        $square_product_ids = []; // To store all square_product_ids with corresponding WooCommerce product IDs

        // Loop through order items
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product || !$product->managing_stock()) {
                continue; // Skip if the product is not managing stock
            }

            // Check if the product is a variation
            if ($product->is_type('variation')) {
                $parent_product = wc_get_product($product->get_parent_id());
                $square_product_id = $parent_product ? $parent_product->get_meta('square_product_id') : null;
            } else {
                $square_product_id = $product->get_meta('square_product_id');
            }

            if (!$square_product_id) {
                continue; // Skip if the product is not linked to Square
            }

            // Add the square_product_id and WooCommerce product ID to the list for syncing
            $square_product_ids[] = [
                'square_product_id' => $square_product_id,
                'woo_product_id' => $product->get_id()
            ];

            // At least one product is linked to Square
            $hasSquareLinkedProduct = true;
        }

        // Log the parent entry only if at least one product is linked to Square
        if ($hasSquareLinkedProduct) {
            if (!$logger) {
                $logger = new Logger();
            }
            $logger->log('info', 'Initiating inventory sync from WooCommerce to Square', ['process_id' => $uniqueProcessId]);
        }

        // Queue request to retrieve Square catalog objects
        SquareHelper::queue_request('/catalog/batch-retrieve', 'POST', [
            'object_ids' => array_column($square_product_ids, 'square_product_id')
        ], null, function ($response) use ($uniqueProcessId, $logger, $square_product_ids, $current_settings) {
            if (!$logger) {
                $logger = new Logger();
            }

            if (!$response['success']) {
                error_log('Failed to update Square inventory: ' . json_encode($response['data']));
                $logger->log('error', 'Failed to retrieve Square catalog: ' . json_encode($response['data']), [
                    'error_message' => json_encode($response['data']),
                    'process_id' => $uniqueProcessId
                ]);
                $this->inventory_update_in_progress = false;
            } else {

                if (isset($response['data']['objects']) && !empty($response['data']['objects'])) {

                    $body = [
                        'idempotency_key' => wp_generate_uuid4(),
                        'changes' => []
                    ];

                    foreach ($response['data']['objects'] as $square_object) {
                        // Ensure 'item_data' exists and has 'variations'
                        if (isset($square_object['item_data']['variations']) && is_array($square_object['item_data']['variations'])) {
                            foreach ($square_object['item_data']['variations'] as $variation) {
                                // Find the WooCommerce product ID associated with this Square variation
                                $woo_product_key = array_search($square_object['id'], array_column($square_product_ids, 'square_product_id'));

                                if ($woo_product_key !== false) {
                                    $woo_product_id = $square_product_ids[$woo_product_key]['woo_product_id'];
                                    $woo_product = wc_get_product($woo_product_id);
                                    $stock_quantity = $woo_product->get_stock_quantity();

                                    // Add the change to the body array
                                    if ($stock_quantity !== null) {
                                        $body['changes'][] = [
                                            'physical_count' => [
                                                'quantity' => (string)$stock_quantity,
                                                'occurred_at' => current_time('c'),
                                                'location_id' => $current_settings['location'],
                                                'catalog_object_id' => $variation['id'],
                                                'state' => 'IN_STOCK'
                                            ],
                                            'type' => 'PHYSICAL_COUNT'
                                        ];
                                    } else {
                                        $logger->log('error', 'Stock quantity is null for product ID: ' . $woo_product_id, [
                                            'process_id' => $uniqueProcessId
                                        ]);
                                    }
                                }
                            }
                        }
                    }

                    // Ensure that there are changes to be synced
                    if (!empty($body['changes'])) {
                        // Queue the request to update the inventory on Square
                        SquareHelper::queue_request('/inventory/changes/batch-create', 'POST', $body, null, function ($response) use ($uniqueProcessId, $logger) {
                            if (!$logger) {
                                $logger = new Logger();
                            }

                            if ($response['success']) {
                                $logger->log('success', 'Square inventory successfully updated.', [
                                    'process_id' => $uniqueProcessId
                                ]);
                            } else {
                                $logger->log('error', 'Failed to update Square inventory: ' . json_encode($response), [
                                    'error_message' => json_encode($response),
                                    'process_id' => $uniqueProcessId
                                ]);
                            }

                            // Reset inventory update flag here
                            $this->inventory_update_in_progress = false;
                        });
                    } else {
                        $logger->log('info', 'No valid inventory changes found to sync with Square.', [
                            'process_id' => $uniqueProcessId
                        ]);

                        // Reset inventory update flag here
                        $this->inventory_update_in_progress = false;
                    }
                } else {
                    $logger->log('info', 'No valid objects returned from Square API.', [
                        'process_id' => $uniqueProcessId
                    ]);

                    // Reset inventory update flag here
                    $this->inventory_update_in_progress = false;
                }
            }
        });
    }


    public function create_order_in_background($order_id)
    {
        $order = wc_get_order($order_id);
        $uniqueProcessId = wp_generate_uuid4();
        $logger = new Logger();

        $logger->log('info', 'Initiating order sync to Square for order #' . $order_id, array('process_id' => $uniqueProcessId));

        $ordersController = new OrdersController();
        $square = new SquareHelper();

        try {
            // Retrieve or create Square customer ID
            $square_customer_id = $ordersController->getOrCreateSquareCustomer($order, $square);


            if (isset($square_customer_id['error'])) {
                $logger->log('error', 'Square Orders error: ' .  $square_customer_id['error'], array('parent_id' => $uniqueProcessId));
            }

            // Prepare order data for Square
            $order_data = $ordersController->prepareSquareOrderData($order, $square_customer_id);
            $response = $ordersController->createOrderInSquare($order_data, $square);

            // Check for errors in the response
            if (isset($response['error'])) {
                $logger->log('error', 'Square Orders API error: ' .  $response['error'], array('parent_id' => $uniqueProcessId));
            }

            $payReponse = $ordersController->payForOrder($response['data']['order'], $square);

            if (isset($payReponse['error'])) {
                $logger->log('error', 'Square Payment API error: ' . $payReponse['error'], array('parent_id' => $uniqueProcessId));
            }


            if (isset($response['data']['order']['id']) && isset($payReponse['data']['payment']['id'])) {
                $square_data =  ['order' => $response, 'payment' => $payReponse];

                // Save Square order ID and payment ID to WooCommerce order meta
                $order->update_meta_data('square_data', wp_json_encode($square_data));

                // Save changes to the order
                $order->save();
            }

            // if (!empty($current_settings) || !empty($current_settings['loyalty']) || $current_settings['loyalty']['enabled'] === true) {
            //     $loyalty = new LoyaltyProgram();
            //     $loyalty->accumulate_loylty_points($response['data']['order']['id'], $current_settings['loyalty']['program_id'], $square_customer_id);
            // }

            $logger->log('success', 'Order and Transaction created in Square, receipt: #' . $payReponse['data']['payment']['receipt_number'], array('parent_id' => $uniqueProcessId));
        } catch (\Exception $e) {
            if ($e->getMessage() == 'Square location not set') {
                $logger->log('error', 'Square location not set: ' . $e->getMessage(), array('parent_id' => $uniqueProcessId));
            }
            $logger->log('error', 'Failed to create order: ' . $e->getMessage(), array('parent_id' => $uniqueProcessId));
        }
    }


    /**
     * Handle actions on order status change.
     *
     * @param int    $order_id The order ID.
     * @param string $old_status The old order status.
     * @param string $new_status The new order status.
     * @param  $order The order object.
     */
    public function create_square_order_after_woo_order($order_id, $old_status = '', $new_status = '', $order = null)
    {
        error_log('test');
        // Check if the event is already scheduled
        $timestamp = wp_next_scheduled('sws_sync_inventory_after_product_sold_event', [$order_id]);

        if (!$timestamp) {
            // Schedule the sync_inventory_after_product_sold function to run in the background
            wp_schedule_single_event(time() + 10, 'sws_sync_inventory_after_product_sold_event', [$order_id]);
        }



        // Check if the order already has Square data to prevent duplication
        if ($order && $order->get_meta('square_data')) {
            return;
        }

        $current_settings = get_option('square-woo-sync_settings', []);

        if (empty($current_settings) || empty($current_settings['orders']) || $current_settings['orders']['enabled'] !== true) {
            return;
        }

        if (empty($current_settings) || empty($current_settings['orders']) || $current_settings['orders']['stage'] !== $new_status) {
            return;
        }

        // Check if the event is already scheduled
        $timestamp = wp_next_scheduled('sws_sync_order_after_product_sold_event', [$order_id]);

        if (!$timestamp) {
            // Schedule the sync_inventory_after_product_sold function to run in the background
            wp_schedule_single_event(time(), 'sws_sync_order_after_product_sold_event', [$order_id]);
        }
    }

    /**
     * AJAX handler for exporting products to Square.
     */
    public function handle_ajax_export_to_square()
    {
        check_ajax_referer('sws_ajax_nonce', 'nonce');

        $product_id = intval($_POST['product_id']);
        $product = wc_get_product($product_id);

        $uniqueProcessId = wp_generate_uuid4();

        if ($product_id && $product) {
            $exporter = new WooImport();

            $result = $exporter->import_products([$product], 1, $uniqueProcessId);
            $exporter->update_square_inventory_counts([$product]);


            if (is_array($result) && isset($result[0]) && is_array($result[0]) && isset($result[0]['success']) && $result[0]['success'] === true) {
                wp_send_json_success(array('message' => 'Successfully exported and linked product to Square.'));
            } else {
                wp_send_json_error(array('message' => json_encode($result)));
            }
        } else {
            wp_send_json_error(array('message' => 'Invalid product ID.'));
        }
    }

    /**
     * Delete Square Product from Woo ID
     */
    public function delete_square_product($post_id)
    {
        // Check if the post type is 'product'
        if (get_post_type($post_id) === 'product') {

            // Get the current settings
            $current_settings = get_option('square-woo-sync_settings', []);

            // Check if auto product deletion is enabled in the settings
            if (!empty($current_settings) && !empty($current_settings['wooAuto']['autoDeleteProduct']) && $current_settings['wooAuto']['autoDeleteProduct'] === true) {

                // Get the square_product_id from post meta
                $square_product_id = get_post_meta($post_id, 'square_product_id', true);

                if ($square_product_id) {
                    $product = wc_get_product($post_id);
                    $product_name = $product ? $product->get_name() : 'Unknown Product';

                    $logger = new Logger();
                    $square_helper = new SquareHelper();
                    $uniqueProcessId = wp_generate_uuid4();

                    try {
                        // Log the initiation of the deletion process
                        $logger->log('info', 'Initiating deletion of Square product ' . $product_name, array(
                            'product_id' => $post_id,
                            'product_name' => $product_name,
                            'square_product_id' => $square_product_id,
                            'process_id' => $uniqueProcessId
                        ));

                        // Send a request to the Square API to delete the product
                        $response = $square_helper->square_api_request("/catalog/object/" . $square_product_id, 'DELETE');

                        if (!$response['success']) {
                            // Log the error if the deletion failed
                            $logger->log('error', 'Failed to delete ' . $product_name . ' from Square library', array(
                                'product_id' => $post_id,
                                'product_name' => $product_name,
                                'square_product_id' => $square_product_id,
                                'error_message' => $response['error'],
                                'parent_id' => $uniqueProcessId
                            ));
                        } else {
                            // Log the success if the deletion was successful
                            $logger->log('success', 'Successfully deleted ' . $product_name . ' from Square library', array(
                                'product_id' => $post_id,
                                'product_name' => $product_name,
                                'square_product_id' => $square_product_id,
                                'parent_id' => $uniqueProcessId
                            ));
                        }
                    } catch (\Exception $e) {
                        // Log the exception if an error occurred during the deletion process
                        $logger->log('error', 'Exception occurred while deleting Square product', array(
                            'product_id' => $post_id,
                            'product_name' => $product_name,
                            'square_product_id' => $square_product_id,
                            'error_message' => $e->getMessage(),
                            'process_id' => $uniqueProcessId
                        ));
                    }
                }
            }
        }
    }







    /**
     * AJAX handler for syncing products to Square.
     */
    public function handle_ajax_sync_to_square()
    {
        check_ajax_referer('sws_ajax_nonce', 'nonce');

        $logger = new Logger();

        $product_id = intval($_POST['product_id']);

        if ($product_id) {

            $product = wc_get_product($product_id);
            $data_to_import = array(
                'stock' => true,
                'title' => true,
                'description' => true,
                'price' => true,
                'sku' => true,
            );



            $result = $this->on_product_update($product_id, $data_to_import, true);

            if ($result && $this->is_sync_successful($result)) {
                $logger->log('success', 'Successfully synced: ' .  $product->get_title() . ' to Square', array('product_id' => $product_id));
                wp_send_json_success(array('message' => 'Product synced successfully with Square.'));
            } else {
                wp_send_json_error(array('message' => $result['error']));
            }
        } else {
            wp_send_json_error(array('message' => 'Invalid product ID.'));
        }
    }

    /**
     * Check if sync result is successful.
     * 
     * @param array $result The result array.
     * 
     * @return bool
     */
    private function is_sync_successful($result)
    {
        return (isset($result['inventoryUpdateStatus']['success']) && $result['inventoryUpdateStatus']['success'] === true) ||
            (isset($result['productUpdateStatus']['success']) && $result['productUpdateStatus']['success'] === true);
    }


    /**
     * Renders the HTML for the meta box.
     * 
     * @param WP_Post $post The post object.
     */
    public function sync_meta_box_html($post)
    {
        $square_product_id = get_post_meta($post->ID, 'square_product_id', true);

        if (!empty($square_product_id)) {
            echo '<p>' . esc_html__('Sync this product to Square', 'squarewoosync') . '</p>';
            echo '<button id="sync_to_square_button" class="update-button button button-primary button-large" data-product-id="' . esc_attr($post->ID) . '">' . esc_html__('Sync to Square', 'squarewoosync') . '</button>';
            echo '<p class="sws-notice">' . esc_html__('Update the product and then run the above sync. For a full tutorial, please read the documentation.', 'squarewoosync') . '</p>';
        } else {
            echo '<p>' . esc_html__('No Square product ID found. Unable to sync to square. Only products imported from square can be synced.', 'squarewoosync') . '</p>';
            echo '<button id="export_to_square_button" class="update-button button button-primary button-large" data-product-id="' . esc_attr($post->ID) . '">' . esc_html__('Export to Square', 'squarewoosync') . '</button>';
        }
    }


    /**
     * Handles the product update process.
     * 
     * @param int $product_id The product ID.
     * @return mixed
     */
    public function on_product_update($product_id, $data_to_import, $force = false)
    {


        $settings = get_option('square-woo-sync_settings', []);

        if (empty($settings['wooAuto']) && !$force) {
            return null;
        }

        $product = wc_get_product($product_id);


        if (!$product instanceof \WC_Product) {
            return null; // Optionally log this error.
        }

        $square_product_id = get_post_meta($product_id, 'square_product_id', true);
        $woo_data = $this->get_woo_product_data($product, $square_product_id);
        if ($square_product_id && !empty($woo_data)) {
            return $this->update_square_product($square_product_id, $woo_data, $data_to_import);
        }
    }

    /**
     * Retrieves WooCommerce product data.
     * 
     * @param \WC_Product $product          WooCommerce product object.
     * @param string      $square_product_id Square product ID.
     * @return array
     */
    public function get_woo_product_data(\WC_Product $product, $square_product_id)
    {
        $woo_data = [
            'name'        => $product->get_name(),
            'description' => $product->get_description(),
            'variations'  => []
        ];

        if ($product->is_type('variable')) {
            foreach ($product->get_children() as $variation_id) {
                $variation = wc_get_product($variation_id);
                if (!$variation) {
                    continue;
                }

                $variation_product_id = get_post_meta($variation->get_id(), 'square_product_id', true);
                $woo_data['variations'][] = $this->format_variation_data($variation, $variation_product_id);
            }
        } else {
            $woo_data['variations'][] = $this->format_variation_data($product, $square_product_id);
        }

        return $woo_data;
    }

    /**
     * Formats variation data for synchronization.
     * 
     * @param \WC_Product $product          WooCommerce product object.
     * @param string      $square_product_id Square product ID.
     * @return array
     */
    private function format_variation_data(\WC_Product $product, $square_product_id)
    {
        return [
            'price'     => $product->get_price(),
            'sku'       => $product->get_sku(),
            'stock'     => $product->get_stock_quantity(),
            'square_id' => $square_product_id,
        ];
    }

    /**
     * Updates the Square product with WooCommerce data.
     * 
     * @param string $square_product_id Square product ID.
     * @param array  $woo_data          WooCommerce product data.
     * @return mixed
     */
    public function update_square_product($square_product_id, $woo_data, $data_to_import)
    {
        $square_helper = new SquareHelper();
        $square_product_data = $square_helper->get_square_item_details($square_product_id);

        if (isset($square_product_data['object']) && isset($square_product_data['object']['type'])) {
            // Check if it's an ITEM or ITEM_VARIATION and proceed accordingly
            if ($square_product_data['object']['type'] === 'ITEM') {
                if (count($woo_data['variations']) === 1 && isset($square_product_data['object']['item_data']['variations'][0]['id'])) {
                    $woo_data['variations'][0]['square_id'] = $square_product_data['object']['item_data']['variations'][0]['id'];
                }
            } elseif ($square_product_data['object']['type'] === 'ITEM_VARIATION' && isset($square_product_data['object']['item_variation_data']['id'])) {
                // Assuming the structure to access the ID for an ITEM_VARIATION is correct
                // Adjust based on actual structure if needed
                if (count($woo_data['variations']) === 1) {
                    $woo_data['variations'][0]['square_id'] = $square_product_data['object']['item_variation_data']['id'];
                }
            }

            $updated_response = $square_helper->update_square_product($woo_data, $square_product_data['object'], $data_to_import);
            return $updated_response;
        } else {
            return $square_product_data; // Return the original data if 'object' or 'type' key is not set
        }
    }

    public function export_to_square($new_status, $old_status, $post)
    {
        // Ensure WooCommerce is loaded
        if (!class_exists('WooCommerce')) {
            error_log('WooCommerce not loaded.');
            return;
        }

        // Check if the post type is 'product' and the new status is 'publish'
        if ($post->post_type === 'product' && $new_status === 'publish') {
            // Get the post's published date
            $published_date = get_the_date('Y-m-d', $post);

            // Get the current date
            $current_date = current_time('Y-m-d');

            // If the post has not been previously published (published date is current date)
            if ($published_date == $current_date) {

                // Get current settings to check if auto-create is enabled
                $current_settings = get_option('square-woo-sync_settings', []);
                if (empty($current_settings) || empty($current_settings['wooAuto']['autoCreateProduct']) || !$current_settings['wooAuto']['autoCreateProduct']) {
                    return;
                }

                // Fetch the product
                $product_id = $post->ID;
                $product = wc_get_product($product_id);
                $square_product_id = get_post_meta($product_id, 'square_product_id', true);

                if (!$product || $square_product_id) {
                    return;
                }

                $logger = new Logger(); // Ensure this Logger class is defined
                $uniqueProcessId = wp_generate_uuid4();

                // Assuming WooImport is a class responsible for handling the export
                $exporter = new WooImport(); // Ensure this class is defined or included

                // Export the product
                $result = $exporter->import_products([$product], 1, $uniqueProcessId);
                $inventoryResult = $exporter->update_square_inventory_counts([$product]);

                // Handle the export result
                if (is_wp_error($result)) {
                    error_log('Error exporting product to Square');
                } else {
                    $logger->log('success', 'Product exported and linked to Square: ' . $product->get_name(), array('process_id' => $uniqueProcessId));

                    set_transient('square_sync_success', 'Product "' . $product->get_name() . '" was successfully created in Square and linked for automatic syncing (if enabled).', 30);
                }
            }
        }
    }
}

// Display the WooCommerce notice
add_action('admin_notices', function () {
    if ($message = get_transient('square_sync_success')) {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p>' . esc_html($message) . '</p>';
        echo '</div>';
        delete_transient('square_sync_success');
    }
});
