<?php

namespace Pixeldev\SquareWooSync\Square;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Handles Square inventory management.
 */
class SquareInventory extends SquareHelper
{

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct($this->get_access_token());
    }

    /**
     * Retrieves inventory from Square.
     *
     * @return array Inventory data or error message.
     */
    public function retrieve_inventory($batch_ids = null)
    {
        try {

            if (isset($batch_ids)) {
                $square_products = $this->fetch_square_items_batch($batch_ids);
            } else {
                $square_products = $this->fetch_square_items();
            }
            $item_options = $this->fetch_square_item_options();
            $item_option_values = $this->fetch_square_item_option_values();

            $variation_ids = [];
            $image_ids = [];

            foreach ($square_products as &$product) {
                $product['inventory_count'] = 'Not Available';

                if (isset($product['item_data']['variations'])) {
                    foreach ($product['item_data']['variations'] as &$variation) {
                        $variation_id = $variation['id'] ?? null;
                        $variation['inventory_count'] = 'Not Available';
                        $variation_ids[] = $variation_id;
                    }
                    unset($variation);
                }

                if (isset($product['item_data']['image_ids'])) {
                    foreach ($product['item_data']['image_ids'] as $id) {
                        $image_ids[] = $id;
                    }
                }
            }
            unset($product);

            $inventory_counts = !empty($variation_ids) ? $this->fetch_inventory_counts(array_unique($variation_ids)) : [];
            $image_urls = !empty($image_ids) ? $this->fetch_square_image_urls(array_unique($image_ids)) : [];

            foreach ($square_products as &$product) {
                $this->enhance_product($product, $inventory_counts, $item_options, $item_option_values, $image_urls);
            }
            unset($product);

            return $square_products;
        } catch (\Exception $e) {
            error_log('Error in SquareInventory::retrieve_inventory: ' . $e->getMessage());
            return ['error' => esc_html__('Failed to retrieve inventory from Square.', 'square-woo-sync')];
        }
    }


    /**
     * Enhances product information with inventory counts and image URLs.
     *
     * @param array &$product           Reference to the product array.
     * @param array $inventory_counts   Array of inventory counts.
     * @param array $item_options       Array of item options.
     * @param array $item_option_values Array of item option values.
     * @param array $image_urls         Array of image URLs.
     */
    private function enhance_product(&$product, $inventory_counts, $item_options, $item_option_values, $image_urls)
    {
        $product_id = $product['id'] ?? null;
        $product['inventory_count'] = $inventory_counts[$product_id] ?? 'Not Available';

        if (isset($product['item_data']['variations'])) {
            foreach ($product['item_data']['variations'] as &$variation) {
                $variation_id = $variation['id'] ?? null;
                $variation['inventory_count'] = $inventory_counts[$variation_id] ?? 'Not Available';
                if (isset($variation['item_variation_data']['item_option_values'])) {
                    foreach ($variation['item_variation_data']['item_option_values'] as &$option_value) {
                        $option_id = $option_value['item_option_id'];
                        $option_value_id = $option_value['item_option_value_id'];
                        $option_value['option_name'] = $item_options[$option_id] ?? esc_html__('Unknown Option', 'squarewoosync');
                        $option_value['option_value'] = $item_option_values[$option_id][$option_value_id] ?? esc_html__('Unknown Value', 'squarewoosync');
                        unset($option_value['item_option_id'], $option_value['item_option_value_id']);
                    }
                    unset($option_value);
                }
            }
            unset($variation);
        }

        if (isset($product['item_data']['image_ids'])) {
            $product['item_data']['image_urls'] = array_map(
                fn ($id) => $image_urls[$id] ?? null,
                $product['item_data']['image_ids']
            );
        }
    }

    /**
     * Fetches image URLs for a given set of Square image IDs.
     *
     * @param array $image_ids Array of image IDs.
     * @return array Associative array of image URLs.
     */
    public function fetch_square_image_urls(array $image_ids)
    {
        $chunk_size = 500; // Adjust this value if needed.
        $all_image_urls = [];

        foreach (array_chunk($image_ids, $chunk_size) as $chunk) {
            $batch_request_data = ['object_ids' => $chunk];
            $response = $this->square_api_request('/catalog/batch-retrieve', 'POST', $batch_request_data);

            if ($response['success'] && isset($response['data']['objects'])) {
                foreach ($response['data']['objects'] as $object) {
                    if (isset($object['image_data']['url'])) {
                        $all_image_urls[$object['id']] = $object['image_data']['url'];
                    }
                }
            } else {
                error_log('Error fetching Square images for a chunk: ' . json_encode($response));
            }
            unset($response);
        }

        return $all_image_urls;
    }

    /**
     * Fetches the URL of a specific image from Square.
     *
     * @param string $image_id The ID of the image.
     * @return string URL of the image.
     */
    public function fetch_image_url($image_id)
    {
        $image_url = '';
        $response = $this->square_api_request('/catalog/object/' . $image_id);

        if ($response['success'] && isset($response['data']['object'])) {
            if (isset($response['data']['object']['image_data']['url'])) {
                $image_url = $response['data']['object']['image_data']['url'];
            }
        } else {
            error_log('Error fetching Square image: ' . json_encode($response));
        }
        unset($response);
        return $image_url;
    }

    /**
     * Fetches Square items.
     *
     * @return array List of Square items.
     */
    private function fetch_square_items()
    {
        $cursor = null;
        $items = [];
        do {
            $response = $this->square_api_request('/catalog/list?types=ITEM&cursor=' . $cursor);
            if ($response['success']) {
                foreach ($response['data']['objects'] as $item) {
                    $items[] = $this->filter_item_data($item);
                }
                $cursor = $response['data']['cursor'] ?? null;
            } else {
                error_log('Error fetching Square items: ' . $response['error']);
            }
        } while ($cursor);

        return $items;
    }

    /**
     * Retrieves Square catalog data
     *
     * @param array $square_ids Data from WooCommerce product.
     * @return array Response from the Square API.
     */
    public function fetch_square_items_batch($square_ids)
    {
        $items = [];
        $endpoint = "/catalog/batch-retrieve";
        $method = 'POST';
        $body = [
            'object_ids' => $square_ids
        ];
        $response = $this->square_api_request($endpoint, $method, $body);

        if ($response['success']) {
            foreach ($response['data']['objects'] as $item) {
                $items[] = $this->filter_item_data($item);
            }
        } else {
            error_log('Error fetching Square items: ' . $response['error']);
        }
        return $items;
    }

    /**
     * Retrieves Square catalog data
     *
     * @param array $square_ids Data from WooCommerce product.
     * @return array Response from the Square API.
     */
    public function batch_retrieve($square_ids)
    {
        $endpoint = "/catalog/batch-retrieve";
        $method = 'POST';
        $body = [
            'object_ids' => $square_ids
        ];
        
        $response = $this->square_api_request($endpoint, $method, $body);
        return $response['data'];
    }

    /**
     * Filters item data for relevant details.
     *
     * @param array $item The item data to filter.
     * @return array Filtered item data.
     */
    public function filter_item_data($item)
    {

        $filtered_item = [
            'id' => $item['id'] ?? null,
            'present_at_location_ids' => $item['present_at_location_ids'] ?? [],
            'item_data' => [
                'categories' => $item['item_data']['categories'] ?? null,
                'name' => $item['item_data']['name'] ?? null,
                'description' => $item['item_data']['description'] ?? $item['item_data']['description_plaintext'] ?? null,
                'image_ids' => $item['item_data']['image_ids'] ?? [],
                'variations' => []
            ],
            'version' => $item['version']
        ];

        foreach ($item['item_data']['variations'] as $variation) {
            $filtered_item['item_data']['variations'][] = [
                'id' => $variation['id'] ?? null,
                'item_variation_data' => [
                    'item_id' => $variation['item_variation_data']['item_id'] ?? null,
                    'name' => $variation['item_variation_data']['name'] ?? null,
                    'sku' => $variation['item_variation_data']['sku'] ?? null,
                    'price_money' => $variation['item_variation_data']['price_money'] ?? null,
                    'item_option_values' => $variation['item_variation_data']['item_option_values'] ?? null
                ],
                'version' => $variation['version']
            ];
        }

        return $filtered_item;
    }
    /**
     * Fetches item options from Square.
     *
     * @return array Associative array of item options.
     */
    private function fetch_square_item_options()
    {
        $cursor = null;
        $options = [];

        do {
            $response = $this->square_api_request('/catalog/list?types=ITEM_OPTION&cursor=' . $cursor);
            if ($response['success']) {
                foreach ($response['data']['objects'] as $option) {
                    $options[$option['id']] = $option['item_option_data']['name'];
                }
                $cursor = $response['data']['cursor'] ?? null;
            } else {
                error_log('Error fetching Square item options: ' . $response['error']);
            }
        } while ($cursor);

        return $options;
    }

    /**
     * Fetches item option values from Square.
     *
     * @return array Nested associative array of item option values.
     */
    private function fetch_square_item_option_values()
    {
        $cursor = null;
        $option_values = [];

        do {
            $response = $this->square_api_request('/catalog/list?types=ITEM_OPTION_VAL&cursor=' . $cursor);
            if ($response['success']) {
                foreach ($response['data']['objects'] as $value_object) {
                    $option_id = $value_object['item_option_value_data']['item_option_id'];
                    $value_id = $value_object['id'];
                    $value_name = $value_object['item_option_value_data']['name'];
                    $option_values[$option_id][$value_id] = $value_name;
                }
                $cursor = $response['data']['cursor'] ?? null;
            } else {
                error_log('Error fetching Square item option values: ' . $response['error']);
            }
        } while ($cursor);

        return $option_values;
    }

    /**
     * Fetches inventory counts for given catalog object IDs.
     *
     * @param array $catalog_object_ids Array of catalog object IDs.
     * @return array Associative array of inventory counts.
     */
    private function fetch_inventory_counts($catalog_object_ids)
    {
        $chunk_size = 30; // Max items per request.
        $inventory_counts = [];
        $settings = get_option('square-woo-sync_settings', []);
        $location = isset($settings['location']) ? $settings['location'] : null;



        foreach (array_chunk($catalog_object_ids, $chunk_size) as $chunk) {
            $post_data = ['catalog_object_ids' => $chunk];

            // Conditionally add location to the post data if it is set
            if ($location !== null) {
                $post_data['location_ids'] = [$location]; // Add location to the post data
            }

            $response = $this->square_api_request('/inventory/counts/batch-retrieve', 'POST', $post_data);

            if ($response['success'] && isset($response['data']['counts'])) {
                foreach ($response['data']['counts'] as $count) {
                    if ($count['state'] === 'IN_STOCK') {
                        $object_id = $count['catalog_object_id'];
                        $quantity = $count['quantity'] ?? '0';
                        $inventory_counts[$object_id] = $quantity;
                    }
                }
            } else {
                foreach ($chunk as $id) {
                    $inventory_counts[$id] = 'Not Available';
                }
            }
        }

        return $inventory_counts;
    }

    /**
     * Retrieves all categories from Square.
     *
     * @return array Array of categories or error message.
     */
    public function get_all_square_categories()
    {
        try {
            $response = $this->square_api_request('/catalog/list?types=CATEGORY');
            if ($response['success']) {
                $categories = [];
                if (isset($response['data']) && isset($response['data']['objects'])) {
                    foreach ($response['data']['objects'] as $object) {
                        if ($object['type'] === 'CATEGORY') {
                            $categories[] = array(
                                'id' => $object['id'],
                                'name' => $object['category_data']['name'],
                                'parent_id' => $object['category_data']['root_category'] ?? false,
                            );
                        }
                    }
                }
                return $categories;
            } else {
                error_log('Error fetching Square categories: ' . $response['error']);
                return ['error' => 'Error fetching Square categories: ' . $response['error']];
            }
        } catch (\Exception $e) {
            error_log('Error in SquareInventory::get_all_square_categories: ' . $e->getMessage());
            return ['error' => 'Failed to retrieve categories from Square.'];
        }
    }
}
