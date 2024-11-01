<?php

namespace Pixeldev\SquareWooSync\REST;

use Pixeldev\SquareWooSync\Abstracts\RESTController;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WC_Product;
use WC_Product_Variation;

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly
}

/**
 * API ProductMatcher class for matching products.
 *
 * @since 0.5.0
 */
class ProductMatcher extends RESTController
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
  protected $base = 'matcher';

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
          'methods'             => WP_REST_Server::EDITABLE,
          'callback'            => [$this, 'match_products'],
          'permission_callback' => [$this, 'check_permission'],
        ],
      ]
    );
  }

  /**
   * Matches products based on the given match_key and inventory data.
   *
   * @param WP_REST_Request $request Request object.
   * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
   */
  public function match_products(WP_REST_Request $request)
  {
    $response_obj = null;
    $license_message = null;

    $license_key = get_option("SquareWooSync_lic_Key");
    $lice_email = get_option("SquareWooSync_lic_email");

    try {

      // Get the match_key and inventory from the request
      $match_key = sanitize_text_field($request->get_param('match_key'));
      $inventory = $request->get_param('inventory');


      if (empty($match_key) || empty($inventory)) {
        return new WP_REST_Response(['message' => esc_html__('Invalid payload', 'squarewoosync')], 400);
      }

      // Process the inventory data here
      foreach ($inventory as $item) {
        $item_id = $item['id'];
        $variations = $item['item_data']['variations'];

        foreach ($variations as $variation) {
          $match_value = $match_key === 'sku' ? $variation['item_variation_data']['sku'] : $item['item_data']['name'];

          if ($match_value) {
            // Query WooCommerce products
            $args = [
              'post_type' => ['product', 'product_variation'],
              'meta_query' => [
                [
                  'key' => $match_key === 'sku' ? '_sku' : '_wp_post_title',
                  'value' => $match_value,
                  'compare' => '='
                ]
              ]
            ];
            $query = new \WP_Query($args);

            if ($query->have_posts()) {
              while ($query->have_posts()) {
                $query->the_post();
                $product_id = get_the_ID();
                $product = wc_get_product($product_id);

                // Check if the product or variation already has a square_product_id
                $existing_square_product_id = get_post_meta($product_id, 'square_product_id', true);
                if (!empty($existing_square_product_id)) {
                  continue;
                }

                if ($product instanceof WC_Product_Variation) {
                  // Update the variation's square_product_id
                  update_post_meta($product_id, 'square_product_id', $variation['id']);
                  // Update the parent product's square_product_id
                  $parent_product_id = $product->get_parent_id();
                  $existing_parent_square_product_id = get_post_meta($parent_product_id, 'square_product_id', true);
                  if (empty($existing_parent_square_product_id)) {
                    update_post_meta($parent_product_id, 'square_product_id', $item_id);
                  }
                } else if ($product instanceof WC_Product) {
                  // Update the product's square_product_id
                  update_post_meta($product_id, 'square_product_id', $item_id);
                }
              }
            }
            wp_reset_postdata();
          }
        }
      }

      // Return a success response
      return new WP_REST_Response(['success' => true, 'message' => esc_html__('Products matched successfully', 'squarewoosync')], 200);
    } catch (\Exception $e) {
      // Log the error message for debugging.
      error_log('Error matching products: ' . esc_html($e->getMessage()));
      return new WP_Error('rest_square_error', esc_html($e->getMessage()), ['status' => 500]);
    }
  }
}
