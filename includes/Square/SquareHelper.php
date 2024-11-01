<?php

namespace Pixeldev\SquareWooSync\Square;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Helper class for handling Square API requests.
 */
class SquareHelper
{
    private $access_token;
    private $encryption_key = 'EE8E1E71AA6E692DB5B7C6E2AEB7D';
    private $api_base_url = 'https://connect.squareup.com/v2'; // Base URL for Square API
    private static $request_queue = [];
    private static $processing = false;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->access_token = $this->get_access_token();
    }

    /**
     * Makes a request to the Square API with exponential backoff.
     * 
     * @param string $endpoint The API endpoint.
     * @param string $method   The request method.
     * @param mixed  $body     The request body.
     * @param string $op_token Optional token for the request.
     * 
     * @return array The response from the API.
     */
    public function square_api_request($endpoint, $method = 'GET', $body = null, $op_token = null, $use_backoff = true)
    {
        $settings = get_option('square-woo-sync_settings', []);
        $token = $op_token ?? $this->access_token;
        $url = isset($settings['environment']) && $settings['environment'] === 'sandbox' ? 'https://connect.squareupsandbox.com/v2' . $endpoint : 'https://connect.squareup.com/v2' . $endpoint;

        $args = [
            'method'    => $method,
            'headers'   => [
                'Authorization' => 'Bearer ' . esc_attr($token),
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'body'      => $body ? wp_json_encode($body) : '',
            'data_format' => 'body',
        ];

        $max_retries = 5;
        $retry_count = 0;
        $response = null;

        do {
            $response = wp_remote_request(esc_url_raw($url), $args);
            $code = wp_remote_retrieve_response_code($response);

            if ($code >= 200 && $code < 300) {
                break; // Success, exit the loop
            }

            if ($retry_count < $max_retries && $use_backoff) {
                sleep(pow(2, $retry_count)); // Exponential backoff
            }

            $retry_count++;
        } while ($retry_count <= $max_retries);

        $body = wp_remote_retrieve_body($response);

        if (is_wp_error($response)) {
            error_log(esc_html__('WP_Error: ', 'square-woo-sync') . $response->get_error_message());
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        if ($code >= 200 && $code < 300) {
            return ['success' => true, 'data' => json_decode($body, true)];
        } else {
            // Handle Error Response
            $error_message = sprintf(esc_html__('Square API request failed. Status Code: %s. Response: %s', 'square-woo-sync'), $code, $body);

            // Attempt to decode the body as JSON and extract the detailed error message.
            $decoded_body = json_decode($body, true);

            if (isset($decoded_body['errors'][0]['detail'])) {
                $error_message = $decoded_body['errors'][0]['detail'];
            }

            // Log the error
            error_log($error_message);

            // Return the extracted error message.
            return ['success' => false, 'error' => $error_message];
        }
    }

    /**
     * Adds a request to the queue.
     * 
     * @param string $endpoint The API endpoint.
     * @param string $method   The request method.
     * @param mixed  $body     The request body.
     * @param string $op_token Optional token for the request.
     * @param callable $callback A callback function to handle the response.
     */
    public static function queue_request($endpoint, $method = 'GET', $body = null, $op_token = null, callable $callback = null)
    {
        self::$request_queue[] = [
            'endpoint' => $endpoint,
            'method' => $method,
            'body' => $body,
            'op_token' => $op_token,
            'callback' => $callback
        ];

        self::process_queue();
    }

    /**
     * Processes the queued requests.
     */
    private static function process_queue()
    {
        if (self::$processing) {
            return;
        }

        self::$processing = true;

        while (!empty(self::$request_queue)) {
            $request = array_shift(self::$request_queue);
            $square_helper = new self();
            $response = $square_helper->square_api_request(
                $request['endpoint'],
                $request['method'],
                $request['body'],
                $request['op_token']
            );

            if (is_callable($request['callback'])) {
                call_user_func($request['callback'], $response);
            }
        }

        self::$processing = false;
    }

    /**
     * Validates the Access Token.
     *
     * @param string|null $op_token Optional token to validate.
     * @return bool True if the token is valid, false otherwise.
     */
    public function is_token_valid($op_token = null)
    {
        if (isset($op_token)) {
            $response = $this->square_api_request('/locations', 'GET', null, $op_token);
            return $response['success'] && $response['data'] !== null;
        }
        $response = $this->square_api_request('/locations');
        return $response['success'] && $response['data'] !== null;
    }

    /**
     * Encrypts the access token.
     *
     * @param string $token The access token to encrypt.
     * @return string|false The encrypted token, or false on failure.
     */
    public function encrypt_access_token($token)
    {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($token, 'aes-256-cbc', $this->encryption_key, 0, $iv);
        if ($encrypted === false) {
            error_log(esc_html__('Encryption failed.', 'square-woo-sync'));
            return false;
        }

        // Convert the binary $iv and $encrypted data to hexadecimal representation
        $hexIv = bin2hex($iv);
        $hexEncrypted = bin2hex($encrypted);

        // Concatenate the hex encoded $iv and $encrypted data, separated by '::'
        return $hexEncrypted . '::' . $hexIv;
    }

    /**
     * Decrypts the access token.
     *
     * @param string $encrypted_token The encrypted access token.
     * @return string|false The decrypted token, or false on failure.
     */
    public function decrypt_access_token($encrypted_token)
    {
        list($hexEncrypted_data, $hexIv) = explode('::', $encrypted_token, 2);

        // Convert hexadecimal back to binary for $iv and $encrypted data
        $iv = hex2bin($hexIv);
        $encrypted_data = hex2bin($hexEncrypted_data);

        if ($iv === false || $encrypted_data === false) {
            error_log(esc_html__('Decryption setup failed.', 'square-woo-sync'));
            return false;
        }

        $decrypted = openssl_decrypt($encrypted_data, 'aes-256-cbc', $this->encryption_key, 0, $iv);
        if ($decrypted === false) {
            error_log(esc_html__('Decryption failed.', 'square-woo-sync'));
            return false;
        }

        return $decrypted;
    }


    /**
     * Retrieves the access token from settings and decrypts it.
     *
     * @return string|null The access token, or null if not found.
     */
    public function get_access_token()
    {
        $settings = get_option('square-woo-sync_settings', []);
        $token = $settings['access_token'] ?? null;
        return $token ? $this->decrypt_access_token($token) : null;
    }


    /**
     * Retrieves details of a Square item.
     *
     * @param string $catalog_object_id The ID of the catalog object.
     * @return array Response array with success status and data or error message.
     */
    public function get_square_item_details($catalog_object_id)
    {
        $endpoint = "/catalog/object/" . $catalog_object_id;
        $response = $this->square_api_request($endpoint);

        if ($response['success']) {
            return $response['data'];
        } else {
            error_log(esc_html__('Failed to get Square item details: ', 'square-woo-sync') . $response['error']);
            return ['success' => false, 'error' => $response['error']];
        }
    }

    /**
     * Updates a Square product with WooCommerce data.
     *
     * @param array $woo_data    Data from WooCommerce product.
     * @param array $square_data Data from Square product.
     * @return array Response array with status of inventory and product update.
     */
    public function update_square_product($woo_data, $square_data, $data_to_import)
    {
        $idempotency_key = uniqid('sq_', true);

        // Update product details
        if (isset($data_to_import['title']) && $data_to_import['title'] === true) {
            if (isset($square_data['type']) && $square_data['type'] === 'ITEM') {
                $square_data['item_data']['name'] = $woo_data['name'];
            } else {
                $square_data['item_variation_data']['name'] = $woo_data['name'];
            }
        }
        if (isset($data_to_import['description']) && $data_to_import['description'] === true) {
            if (isset($square_data['type']) && $square_data['type'] === 'ITEM') {
                $square_data['item_data']['description'] = $woo_data['description'];
            } else {
                $square_data['item_variation_data']['description'] = $woo_data['description'];
            }
        }
        // Update variations and inventory
        $this->update_variations($square_data, $woo_data, $data_to_import);

        $inventory_update_status = null;
        if (isset($data_to_import['stock']) && $data_to_import['stock'] === true) {
            $inventory = $this->get_inventory($woo_data);
            if (!empty($inventory['success']) && !empty($inventory['data'])) {
                $updated_inventory_data = $this->updated_inventory_data($inventory['data']['counts'], $woo_data);
                $inventory_update_status = $this->update_inventory($updated_inventory_data);
            }
        }


        $body = [
            'idempotency_key' => $idempotency_key,
            'object' => $square_data
        ];

        $product_update_status = $this->square_api_request("/catalog/object", 'POST', $body);

        return [
            'inventoryUpdateStatus' => $inventory_update_status,
            'productUpdateStatus' => $product_update_status
        ];
    }

    /**
     * Updates variations of a variable product in Square.
     *
     * @param array &$square_variations Variations from Square.
     * @param array $woo_variations Variations from WooCommerce.
     */
    private function update_variable_product_variations(&$square_variations, $woo_variations, $data_to_import)
    {
        if (empty($woo_variations)) {
            return;
        }

        // Map WooCommerce variations using 'square_id' as key
        $woo_variation_map = array_column($woo_variations, null, 'square_id');

        // Check if $square_variations is already an array of arrays
        if (!isset($square_variations[0]) || !is_array($square_variations[0])) {
            // If not, wrap it in an array
            $square_variations = array($square_variations);
        }

        // Now $square_variations is guaranteed to be an array of arrays
        foreach ($square_variations as &$variation) {
            // Update the variation if it exists in the WooCommerce variation map
            if (isset($woo_variation_map[$variation['id']])) {
                $this->update_simple_product_variation($variation, $woo_variation_map[$variation['id']], $data_to_import);
            }
        }
    }


    /**
     * Updates inventory in Square.
     *
     * @param array $inventory Inventory data to update.
     * @return array Response from the Square API.
     */
    public function update_inventory($inventory)
    {
        $idempotency_key = uniqid('sq_', true);
        $occurred_at = date('Y-m-d\TH:i:s.') . sprintf("%03d", (microtime(true) - floor(microtime(true))) * 1000) . 'Z';

        $changes = array_map(function ($inv) use ($occurred_at) {
            return [
                'physical_count' => [
                    'catalog_object_id' => $inv['catalog_object_id'],
                    'location_id' => $inv['location_id'],
                    'occurred_at' => $occurred_at,
                    'state' => 'IN_STOCK',
                    'quantity' => (string) $inv['quantity']
                ],
                'type' => 'PHYSICAL_COUNT'
            ];
        }, array_filter($inventory, function ($inv) {
            return $inv['state'] === 'IN_STOCK';
        }));

        return $this->square_api_request("/inventory/changes/batch-create", 'POST', [
            'idempotency_key' => $idempotency_key,
            'changes' => $changes
        ]);
    }

    /**
     * Updates the variations in the Square product data.
     *
     * @param array &$square_data Data from Square product.
     * @param array $woo_data     Data from WooCommerce product.
     */
    private function update_variations(&$square_data, $woo_data, $data_to_import)
    {
        if (isset($square_data['type']) && $square_data['type'] === 'ITEM') {
            $this->update_variable_product_variations($square_data['item_data']['variations'], $woo_data['variations'], $data_to_import);
        } else {
            $this->update_variable_product_variations($square_data, $woo_data['variations'], $data_to_import);
        }
    }

    /**
     * Updates a single variation of a product in Square.
     *
     * @param array &$square_variation Variation data from Square.
     * @param array $woo_variation     Variation data from WooCommerce.
     */
    private function update_simple_product_variation(&$square_variation, $woo_variation, $data_to_import)
    {
        if (isset($data_to_import['price']) && $data_to_import['price'] === true) {
            // Ensure the amount is properly rounded and cast to an integer
            $square_variation['item_variation_data']['price_money']['amount'] = intval(round(floatval($woo_variation['price']) * 100));
        }
        if (isset($data_to_import['sku']) && $data_to_import['sku'] === true) {
            $square_variation['item_variation_data']['sku'] = $woo_variation['sku'];
        }
    }

    /**
     * Retrieves inventory data for WooCommerce product variations.
     *
     * @param array $woo_data Data from WooCommerce product.
     * @return array Response from the Square API.
     */
    public function get_inventory($woo_data)
    {
        $endpoint = "/inventory/counts/batch-retrieve";
        $method = 'POST';
        $body = [
            'catalog_object_ids' => []
        ];

        foreach ($woo_data['variations'] as $variation) {
            if (isset($variation['square_id'])) {
                $body['catalog_object_ids'][] = $variation['square_id'];
            }
        }

        return $this->square_api_request($endpoint, $method, $body);
    }


    /**
     * Updates the inventory data based on WooCommerce variations.
     *
     * @param array $inventory Current inventory data.
     * @param array $woo_data  Data from WooCommerce product.
     * @return array Modified inventory data.
     */
    public function updated_inventory_data($inventory, $woo_data)
    {
        if (!isset($woo_data['variations'])) {
            return $inventory;
        }

        foreach ($inventory as &$inv) {
            foreach ($woo_data['variations'] as $variation) {
                if (isset($variation['square_id']) && $variation['square_id'] === $inv['catalog_object_id']) {
                    $inv['quantity'] = $variation['stock'];
                    break;
                }
            }
        }
        unset($inv);

        return $inventory;
    }
}
