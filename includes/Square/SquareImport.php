<?php

namespace Pixeldev\SquareWooSync\Square;

use Pixeldev\SquareWooSync\Square\SquareHelper;
use Pixeldev\SquareWooSync\Woo\CreateProduct;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class responsible for importing products from Square to WooCommerce.
 */
class SquareImport extends SquareHelper
{

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Imports products from Square to WooCommerce.
     *
     * @param array $square_products Products to import.
     * @param array $data_to_import Data to be imported.
     * @param bool $update_only Flag to update existing products only.
     * @return array Results of the import process.
     */
    public function import_products($square_products, $data_to_import, $update_only = false)
    {
        global $wpdb;
        $results = [];
        $table_name = $wpdb->prefix . 'square_inventory';

        foreach ($square_products as $square_product) {
            try {
                $create_product = new CreateProduct();
                $existing_product = $create_product->get_product_by_square_id($square_product['id']);

                $wc_product_data = $this->map_square_product_to_woocommerce($square_product, $existing_product, $update_only);

                $product_id = $create_product->create_or_update_product($wc_product_data, $data_to_import, $update_only);

                $existing_row = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $table_name WHERE product_id = %s",
                    $square_product['id']
                ), ARRAY_A);

                $decoded_data = maybe_unserialize($existing_row['product_data']);


                if (!is_array($product_id)) {
                    $product = wc_get_product($product_id);
                    if ($product) {
                        $name = $product->get_name();
                        $result_entry = [
                            'status' => 'success',
                            'product_id' => $product_id,
                            'square_id' => $square_product['id'],
                            'message' => esc_html__('Successfully synced: ', 'squarewoosync') . esc_html($name) . esc_html__(' from Square to Woo', 'squarewoosync')
                        ];

                        // Initialize the variations array
                        $variation_ids = [];

                        // Check if the product is a variable product and handle its variations
                        if ($product->is_type('variable')) {
                            $variations = $product->get_children();
                            foreach ($variations as $variation_id) {
                                $variation_square_product_id = get_post_meta($variation_id, 'square_product_id', true);
                                if ($variation_square_product_id) {
                                    $variation_ids[] = ['variation_id' => $variation_id, 'square_product_id' => $variation_square_product_id];

                                    foreach ($decoded_data['item_data']['variations'] as &$variation) {
                                        if ($variation['id'] === $variation_square_product_id) {
                                            $variation['imported'] = true; // Set imported to true for the current variation
                                            $variation['woocommerce_product_id'] = $variation_id; // Optionally, add the WooCommerce variation ID
                                        }
                                    }
                                    unset($variation); // Break the reference with the last element
                                }
                            }
                        }

                        // Check if the product is a simple product and has the square_variation_id meta data
                        if ($product->is_type('simple')) {
                            $square_variation_id = get_post_meta($product->get_id(), 'square_variation_id', true);
                            if ($square_variation_id) {
                                $variation_ids[] = ['variation_id' => $product->get_id(), 'square_product_id' => $square_variation_id];
                            }

                            foreach ($decoded_data['item_data']['variations'] as &$variation) {
                                if ($variation['id'] === $square_variation_id) {
                                    $variation['imported'] = true; 
                                    $variation['woocommerce_product_id'] = $product->get_id();
                                }
                            }
                            unset($variation); // Break the reference with the last element
                        }

                        // Add variations to the result entry if there are any
                        if (!empty($variation_ids)) {
                            $result_entry['variations'] = $variation_ids;
                        }

                        $results[] = $result_entry;

                        // Set the imported status and WooCommerce product ID
                        $decoded_data['imported'] = true;
                        $decoded_data['woocommerce_product_id'] = $product_id;

                        // Serialize the updated data
                        $updated_data_serialized = maybe_serialize($decoded_data);

                        // Update the existing row in the database
                        $update_result = $wpdb->update(
                            $table_name,
                            array(
                                'product_data' => $updated_data_serialized
                            ),
                            array('product_id' => $square_product['id']),
                            array('%s'),
                            array('%s')
                        );

                        // Log the result of the update operation
                        if ($update_result === false) {
                            error_log('Failed to update row for product_id: ' . $square_product['id']);
                        } 
                    }
                } else {
                    $results[] = $product_id;
                }
            } catch (\Exception $e) {
                error_log('Error importing product: ' . $e->getMessage());
                $results[] = ['status' => 'failure', 'product_id' => null, 'message' => 'Exception occurred: ' . $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Maps a Square product to a WooCommerce product format.
     *
     * @param array $square_product The Square product to map.
     * @return array The WooCommerce product data.
     */
    public function map_square_product_to_woocommerce($square_product, $existing_product, $update_only)
    {

        $wc_product_data = [];

        // Map basic product details from Square to WooCommerce
        $wc_product_data['name'] = $square_product['item_data']['name'];
        $wc_product_data['description'] = $square_product['item_data']['description'] ?? '';
        if (isset($square_product['variable']) && $square_product['variable'] === true) {
            if ($existing_product && $existing_product->get_type() === 'variable') {
                $wc_product_data['type'] = 'variable';
            } else {
                if (count($square_product['item_data']['variations']) === 1) {
                    $wc_product_data['type'] = 'simple';
                    $wc_product_data['variation_id'] = $square_product['item_data']['variations'][0]['id'];
                } else {
                    if ($update_only && $existing_product) {
                        $wc_product_data['type'] = $existing_product->get_type();
                    } else {
                        $wc_product_data['type'] = 'variable';
                    }
                }
            }
        } else {
            if ($existing_product && $existing_product->get_type() === 'variable') {
                $wc_product_data['type'] = 'variable';
            } else {
                if (count($square_product['item_data']['variations']) > 1) {
                    if ($update_only && $existing_product) {
                        $wc_product_data['type'] = $existing_product->get_type();
                    } else {
                        $wc_product_data['type'] = 'variable';
                    }
                } else {
                    if ($update_only && $existing_product) {
                        $wc_product_data['type'] = $existing_product->get_type();
                    } else {
                        $wc_product_data['type'] = 'simple';
                    }
                }
            }
        }


        if ($existing_product) {
            $square_variation_id = get_post_meta($existing_product->get_id(), 'square_variation_id', true);
            if ($square_variation_id) {
                // Iterate through the variations to find the matching square_variation_id
                foreach ($square_product['item_data']['variations'] as $variation) {
                    if ($variation['id'] === $square_variation_id) {
                        $wc_product_data['sku'] = $variation['item_variation_data']['sku'];
                        $wc_product_data['stock'] = intval($variation['inventory_count'] ?? '0');
                        $wc_product_data['price'] = $variation['item_variation_data']['price_money']['amount'] / 100;
                        break;
                    }
                }
            } else {
                // Use the data from the first variation if no square_variation_id is found
                $wc_product_data['sku'] = $square_product['item_data']['variations'][0]['item_variation_data']['sku'];
                $wc_product_data['stock'] = intval($square_product['item_data']['variations'][0]['inventory_count'] ?? '0');
                $wc_product_data['price'] = $square_product['item_data']['variations'][0]['item_variation_data']['price_money']['amount'] / 100;
            }
        }

        $wc_product_data['square_product_id'] = $square_product['id'];

        $categories = $square_product['item_data']['categories'] ?? '';
        $wc_product_data['categories'] = $categories;


        // Map pricing, SKU, and variations for variable products
        $wc_product_data['variations'] = [];
        foreach ($square_product['item_data']['variations'] as $variation) {
            $variation_data = [
                'name' => $variation['item_variation_data']['name'],
                'sku' => $variation['item_variation_data']['sku'],
                'price' => $variation['item_variation_data']['price_money'] ? $variation['item_variation_data']['price_money']['amount'] / 100 : 0,
                'attributes' => [],
                'stock' => intval($variation['inventory_count'] ?? 0),
                'variation_square_id' => $variation['id']
            ];

            if (isset($variation['item_variation_data']['item_option_values'])) {
                foreach ($variation['item_variation_data']['item_option_values'] as $option) {
                    $variation_data['attributes'][] = [
                        'name' => $option['option_name'],
                        'option' => $option['option_value']
                    ];
                }
            }

            $wc_product_data['variations'][] = $variation_data;
        }

        if (!empty($square_product['item_data']['image_urls']) && !empty($square_product['item_data']['image_ids'])) {
            // Handle Multiple Image Imports
            $image_urls = $square_product['item_data']['image_urls'];
            $square_image_ids = $square_product['item_data']['image_ids'];

            // Instead of importing images here, just store the image URLs and Square image IDs
            $wc_product_data['images'] = array_combine($square_image_ids, $image_urls);
        }

        return $wc_product_data;
    }
}
