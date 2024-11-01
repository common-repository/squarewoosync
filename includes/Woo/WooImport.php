<?php

namespace Pixeldev\SquareWooSync\Woo;

use Pixeldev\SquareWooSync\Logger\Logger;
use Pixeldev\SquareWooSync\Square\SquareHelper;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Class to handle WooCommerce import to Square.
 */
class WooImport
{

    /**
     * Export products from WooCommerce to Square in batches of 50 and uses the Square API helper function.
     *
     * @param array $products Array of products to import.
     * @param bool $exportSynced Whether to export synced products or not.
     * @return array Aggregate of API responses.
     */
    public function import_products($products = [], $exportSynced = 1, $process_id)
    {
        // Check for WooCommerce dependency
        if (!class_exists('WooCommerce')) {
            return new \WP_Error('missing_dependency', esc_html__('WooCommerce must be installed and activated.', 'squarewoosync'));
        }

        $responses = [];
        $square = new SquareHelper();

        try {
            // Fetch all products if $products array is empty
            if (empty($products)) {
                $per_page = 50;
                $page = 1;

                do {
                    $args = array(
                        'status' => 'publish',
                        'limit' => $per_page,
                        'paginate' => true,
                        'page' => $page,
                        'return' => 'objects',
                    );

                    $result = wc_get_products($args);
                    $products = $result->products;
                    $responses = array_merge($responses, $this->process_products($products, $square, $exportSynced, $process_id));

                    $page++;
                } while ($page <= $result->max_num_pages);
            } else {
                // Process the provided products array directly
                $responses = $this->process_products($products, $square, $exportSynced, $process_id);
            }

            return $responses;
        } catch (\Exception $e) {
            $existing_options = get_option('square-woo-sync_settings', array());
            $existing_options['exportStatus'] = 0;
            $existing_options['exportResults'] = array('error' => $e->getMessage());
            update_option('square-woo-sync_settings', $existing_options);
            return new \WP_Error('import_failed', esc_html__('Failed to import products: ', 'squarewoosync') . esc_html($e->getMessage()));
        }
    }


    private function process_products($products, $square, $exportSynced, $process_id)
    {
        $square_options = $this->fetch_square_item_options($square);

        $square_data = [
            'idempotency_key' => wp_generate_uuid4(),
            'batches' => []
        ];

        $batch = [
            'objects' => []
        ];


        foreach ($products as $product) {
            // Check if exportSynced is false and the product has a Square product ID
            if ($exportSynced === 0 && get_post_meta($product->get_id(), 'square_product_id', true)) {
                continue; // Skip this product
            }

            $formatted_product = $this->format_product_for_square($product, $square_options, $square, $process_id);

            // Only add the product if it has valid data
            if ($formatted_product !== null) {
                $batch['objects'][] = $formatted_product;
            }
        }

        // Only add the batch if there are objects to upsert
        if (!empty($batch['objects'])) {
            $square_data['batches'][] = $batch;

            $response = $square->square_api_request('/catalog/batch-upsert', 'POST', $square_data);

            $this->update_wc_product_with_square_ids($response);

            return [$response];
        }

        return [];
    }


    private function fetch_square_item_options($square)
    {
        $response = $square->square_api_request('/catalog/list?types=ITEM_OPTION');
        if (isset($response['data']['objects'])) {
            return $response['data']['objects'];
        }
        return [];
    }


    public function update_square_inventory_counts($products)
    {
        $changes = [];
        $settings = get_option('square-woo-sync_settings', []);
        $location = $settings['location'] ?? null;

        foreach ($products as $product) {
            $wc_product_id = $product->get_id();
            $quantity = $product->get_stock_quantity();

            if ($product->is_type('simple')) {
                $square_product_id = get_post_meta($wc_product_id, 'square_product_id', true);
                $square_helper = new SquareHelper();
                $res = $square_helper->get_square_item_details($square_product_id);
                if (!empty($res) && isset($res['object']['item_data']['variations'][0]['id'])) {
                    $square_product_id = $res['object']['item_data']['variations'][0]['id'];
                    $this->prepare_inventory_change($changes, $square_product_id, $quantity, $location);
                }
            } elseif ($product->is_type('variable')) {
                $variations = $product->get_children();
                foreach ($variations as $variation_id) {
                    $variation = wc_get_product($variation_id);
                    $variation_square_id = $variation->get_meta('square_product_id', true);
                    $variation_quantity = $variation->get_stock_quantity();
                    $this->prepare_inventory_change($changes, $variation_square_id, $variation_quantity, $location);
                }
            }
        }
        if (!empty($changes)) {
            $this->send_inventory_updates_to_square($changes);
        }
    }

    private function prepare_inventory_change(&$changes, $square_product_id, $quantity, $location)
    {
        if ($square_product_id && $quantity !== null) {
            $changes[] = [
                'physical_count' => [
                    'catalog_object_id' => $square_product_id,
                    'location_id' => $location,
                    'occurred_at' => gmdate('Y-m-d\TH:i:s\Z'),
                    'state' => 'IN_STOCK',
                    'quantity' => (string) $quantity
                ],
                'type' => 'PHYSICAL_COUNT'
            ];
        }
    }


    private function send_inventory_updates_to_square($changes)
    {
        $endpoint = '/inventory/changes/batch-create';
        $batch_size = 100; // Square API limit
        $square = new SquareHelper();

        // Break changes into smaller batches
        $chunks = array_chunk($changes, $batch_size);

        foreach ($chunks as $chunk) {
            $data = [
                'idempotency_key' => uniqid('sq_', true),
                'changes' => $chunk
            ];

            $response = $square->square_api_request($endpoint, 'POST', $data);

            // Log or handle the response
            if ($response['success']) {
                error_log('Successfully updated inventory in Square.');
            } else {
                error_log('Failed to update inventory in Square: ' . $response['error']);
            }
        }
    }




    /**
     * Formats a WooCommerce product to match the structure required by the Square API.
     *
     * @param \WC_Product $product The WooCommerce product object.
     * @return array The product data formatted for Square.
     */
    private function format_product_for_square(\WC_Product $product, &$square_options, $square, $process_id)
    {
        $settings = get_option('square-woo-sync_settings', []);
        $location = isset($settings['location']) ? $settings['location'] : null;

        $productId = $product->get_id() ? '#' . $product->get_id() : '#temp_' . wp_generate_uuid4();

        // Set temporary ID for the product
        update_post_meta($product->get_id(), '_temporary_square_id', $productId);

        // Ensure the description does not exceed 4096 characters
        $description = $product->get_description();
        if (strlen($description) > 4096) {
            $description = substr(esc_html($description), 0, 4095);
        }

        $product_data = [
            'type' => 'ITEM',
            'id' => $productId,
            'present_at_all_locations' => false,
            'present_at_location_ids' => $location ? [$location] : [],
            'item_data' => [
                'name' => $product->get_name(),
                'description' => $description,
                'variations' => [],
                'item_options' => [],
            ],
        ];

        $item_options_map = [];
        $item_options = [];
        $variation_combinations = [];

        if ($product->is_type('simple')) {
            $simple_price = (float) $product->get_price();
            $product_data['item_data']['variations'][] = [
                'type' => 'ITEM_VARIATION',
                'id' => $productId . '_var',
                'item_variation_data' => [
                    'sku' => $product->get_sku(),
                    'name' => $product->get_name(),
                    'pricing_type' => 'FIXED_PRICING',
                    'price_money' => [
                        'amount' => intval($simple_price * 100),
                        'currency' => get_woocommerce_currency()
                    ],
                ],
                'present_at_all_locations' => false,
                'present_at_location_ids' => $location ? [$location] : [],
            ];
        } elseif ($product->is_type('variable')) {
            $skip_product = false;

            foreach ($product->get_children() as $child_id) {
                $variation = wc_get_product($child_id);
                $var_price = (float) $variation->get_price();

                $variation_temp_id = '#' . $child_id;
                update_post_meta($child_id, '_temporary_square_id', $variation_temp_id); // Set temporary ID for the variation

                $variation_options = [];
                $attributes = [];
                foreach ($variation->get_variation_attributes() as $attr_key => $attr_value) {
                    $taxonomy = str_replace('attribute_', '', $attr_key);
                    $term = get_term_by('slug', $attr_value, $taxonomy);

                    if (!$term) {
                        // Handle local (product-specific) attributes
                        $attr_name = wc_attribute_label($taxonomy);
                        $attr_value_name = $attr_value;

                        if (!isset($item_options_map[$attr_name])) {
                            $square_option_id = $this->get_square_option_id($attr_name, $square_options, $square, $attr_value_name);
                            if (!$square_option_id) {
                                error_log("Failed to retrieve or create Square option ID for attribute: $attr_name with value: $attr_value_name");
                                $skip_product = true;
                                break; // Exit the loop if the Square option ID could not be retrieved or created
                            }
                            $item_options_map[$attr_name] = $square_option_id;
                            $item_options[] = [
                                'item_option_id' => $square_option_id
                            ];
                        } else {
                            $square_option_id = $item_options_map[$attr_name];
                        }

                        $attributes[] = "{$attr_name}: {$attr_value_name}";

                        $item_option_value_id = $this->get_square_option_value_id($square_option_id, $attr_value_name, $square_options, $square);

                        if (!$item_option_value_id) {
                            error_log("Failed to retrieve or create Square option value ID for attribute: $attr_name with value: $attr_value_name");
                            $skip_product = true;
                            break; // Exit the loop if the Square option value ID could not be retrieved or created
                        }
                        $variation_options[] = [
                            'item_option_id' => $square_option_id,
                            'item_option_value_id' => $item_option_value_id
                        ];
                    } else {
                        $attr_name = wc_attribute_label($taxonomy);
                        $attr_value_slug = $term->slug;

                        if (!isset($item_options_map[$attr_name])) {
                            $square_option_id = $this->get_square_option_id($attr_name, $square_options, $square, $attr_value_slug);
                            if (!$square_option_id) {
                                error_log("Failed to retrieve or create Square option ID for attribute: $attr_name with slug: $attr_value_slug");
                                $skip_product = true;
                                break; // Exit the loop if the Square option ID could not be retrieved or created
                            }
                            $item_options_map[$attr_name] = $square_option_id;
                            $item_options[] = [
                                'item_option_id' => $square_option_id
                            ];
                        } else {
                            $square_option_id = $item_options_map[$attr_name];
                        }

                        $attributes[] = "{$attr_name}: {$attr_value_slug}";

                        $item_option_value_id = $this->get_square_option_value_id($square_option_id, $attr_value_slug, $square_options, $square);

                        if (!$item_option_value_id) {
                            error_log("Failed to retrieve or create Square option value ID for attribute: $attr_name with slug: $attr_value_slug");
                            $skip_product = true;
                            break; // Exit the loop if the Square option value ID could not be retrieved or created
                        }
                        $variation_options[] = [
                            'item_option_id' => $square_option_id,
                            'item_option_value_id' => $item_option_value_id
                        ];
                    }
                }


                // Check if the variation has fewer than 2 item options
                // if (count($variation_options) < 2) {
                //     error_log("Skipping product {$product->get_name()} (ID: {$productId}) because it has a variation with fewer than 2 item option values.");
                //     $skip_product = true;
                //     break; // Exit the loop and skip the entire product
                // }

                // Check for duplicate item option value combinations
                $combination_key = implode('|', array_map(function ($option) {
                    return $option['item_option_id'] . ':' . $option['item_option_value_id'];
                }, $variation_options));

                if (isset($variation_combinations[$combination_key])) {
                    error_log("Skipping product {$product->get_name()} (ID: {$productId}) because it has duplicate variation option combinations.");
                    $skip_product = true;
                    break; // Exit the loop and skip the entire product
                }

                $variation_combinations[$combination_key] = true;

                $variation_name = implode(", ", $attributes);

                $product_data['item_data']['variations'][] = [
                    'type' => 'ITEM_VARIATION',
                    'id' => $variation_temp_id,
                    'item_variation_data' => [
                        'sku' => $variation->get_sku(),
                        'name' => $variation_name,
                        'pricing_type' => 'FIXED_PRICING',
                        'price_money' => [
                            'amount' => intval($var_price * 100),
                            'currency' => get_woocommerce_currency()
                        ],
                        'item_option_values' => $variation_options,
                        'ordinal' => $variation->get_menu_order(),
                        'track_inventory' => $variation->managing_stock(),
                        'sellable' => true,
                        'stockable' => true
                    ],
                    'present_at_all_locations' => false,
                    'present_at_location_ids' => $location ? [$location] : [],
                ];

                if (count($item_options) > 6) {
                    error_log('Square API limitation: An item can have at most 6 item options.');
                    break; // Avoid exceeding the limit
                }
            }

            if ($skip_product) {
                error_log("Product {$product->get_name()} (ID: {$productId}) is being skipped due to invalid variations.");
                $logger = new Logger(); // Ensure this Logger class is defined
                $logger->log('error', "Product {$product->get_name()} is being skipped because it contains invalid variations. Each variation must have at least two options selected.", array('parent_id' => $process_id));
                return null; // Skip the entire product if any variation is invalid
            }
        }

        $product_data['item_data']['item_options'] = array_slice($item_options, 0, 6); // Limit to 6 item options

        return $product_data;
    }


    private function get_square_option_id($attr_name, &$square_options, $square, $attr_value_slug)
    {
        foreach ($square_options as $option) {
            if (is_array($option) && isset($option['item_option_data']['name']) && strtolower($option['item_option_data']['name']) === strtolower($attr_name)) {
                return $option['id'];
            }
        }

        // Create the option with an initial placeholder value
        $new_option_id = '#' . wp_generate_uuid4();
        $new_value_id = '#' . wp_generate_uuid4();
        $new_option = [
            'type' => 'ITEM_OPTION',
            'id' => $new_option_id,
            'item_option_data' => [
                'name' => strtolower($attr_name),
                'show_colors' => false,
                'values' => [
                    [
                        'type' => 'ITEM_OPTION_VAL',
                        'id' => $new_value_id,
                        'item_option_value_data' => [
                            'item_option_id' => $new_option_id,
                            'name' => strtolower($attr_value_slug)
                        ]
                    ]
                ]
            ]
        ];

        $response = $square->square_api_request('/catalog/object', 'POST', [
            'idempotency_key' => wp_generate_uuid4(),
            'object' => $new_option
        ]);

        if (isset($response['data']['catalog_object'])) {
            $square_options[] = $response['data']['catalog_object'];
            return $response['data']['catalog_object']['id'];
        } else {
            error_log("Square API request failed when creating new item option. Response: " . json_encode($response));
            return null;
        }
    }


    private function get_square_option_value_id($square_option_id, $attr_value_slug, &$square_options, $square)
    {
        foreach ($square_options as &$option) {
            if (is_array($option) && $option['id'] === $square_option_id) {
                foreach ($option['item_option_data']['values'] as $value) {
                    if (strtolower($value['item_option_value_data']['name']) === strtolower($attr_value_slug)) {
                        return $value['id'];
                    }
                }

                // Create the value and link it to the existing option
                $new_value_id = '#' . wp_generate_uuid4();
                $new_value = [
                    'type' => 'ITEM_OPTION_VAL',
                    'id' => $new_value_id,
                    'item_option_value_data' => [
                        'item_option_id' => $square_option_id,
                        'name' => strtolower($attr_value_slug)
                    ]
                ];

                $response = $square->square_api_request('/catalog/object', 'POST', [
                    'idempotency_key' => wp_generate_uuid4(),
                    'object' => $new_value
                ]);

                if (isset($response['data']['catalog_object'])) {
                    $option['item_option_data']['values'][] = $response['data']['catalog_object']; // Update local options array
                    return $response['data']['catalog_object']['id'];
                } else {
                    error_log("Square API request failed when creating new item option value. Response: " . json_encode($response));
                    return null;
                }
            }
        }

        error_log("Failed to find or create Square option value ID for option ID: $square_option_id with slug: $attr_value_slug");
        return null;
    }


    public function update_wc_product_with_square_ids($square_response)
    {
        if ($square_response['success'] && !empty($square_response['data']['id_mappings'])) {
            foreach ($square_response['data']['id_mappings'] as $id_map) {
                $wc_product_id = $this->wc_get_product_id_by_temp_id($id_map['client_object_id']);
                if ($wc_product_id) {
                    $product = wc_get_product($wc_product_id);
                    if ($product) {
                        if ($product->is_type('variable')) {
                            // Set the Square ID for the main variable product
                            if ($id_map['client_object_id'] === '#' . $product->get_id()) {
                                update_post_meta($product->get_id(), 'square_product_id', $id_map['object_id']);
                            }
                            // For variable products, map the variations
                            $variations = $product->get_children();
                            foreach ($variations as $variation_id) {
                                $variation = wc_get_product($variation_id);
                                $variation_temp_id = '#' . $variation_id;
                                if ($variation && $variation_temp_id === $id_map['client_object_id']) {
                                    update_post_meta($variation_id, 'square_product_id', $id_map['object_id']);
                                }
                            }
                        } else {
                            // For simple products, map directly
                            if ($id_map['client_object_id'] === '#' . $product->get_id()) {
                                update_post_meta($product->get_id(), 'square_product_id', $id_map['object_id']);
                            }
                        }
                    }
                }
            }
        } else {
            error_log("Invalid square response or no id_mappings");
        }
    }

    private function wc_get_product_id_by_temp_id($temp_id)
    {
        global $wpdb;
        // Find a product or variation with the matching temporary ID
        $query = "SELECT pm.post_id 
                  FROM {$wpdb->postmeta} pm
                  INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                  WHERE pm.meta_key = '_temporary_square_id' 
                  AND pm.meta_value = %s 
                  AND (p.post_type = 'product' OR p.post_type = 'product_variation')";

        return $wpdb->get_var($wpdb->prepare($query, $temp_id));
    }
};
