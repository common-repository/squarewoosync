<?php

namespace Pixeldev\SquareWooSync\Woo;

use Error;

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

/**
 * Class to handle WooCommerce product creation.
 */
class CreateProduct
{

  /**
   * Entry point for creating or updating a WooCommerce product.
   *
   * @param array $wc_product_data Data for the WooCommerce product.
   * @param array $data_to_import Data indicating what should be imported.
   * @param bool $update_only Flag indicating whether to only update existing products.
   * @return int|false Product ID on success, or false on failure.
   */
  public function create_or_update_product($wc_product_data, $data_to_import, $update_only)
  {
    try {
      $product = $this->get_or_create_product_by_square_id($wc_product_data, $update_only);

      if ($update_only && !$product) {
        return false;
      }

      $this->update_common_product_properties($product, $wc_product_data, $data_to_import);

      if ('variable' === $wc_product_data['type']) {
        return [];
      } else {
        $this->handle_simple_product($product, $wc_product_data, $data_to_import);
      }

      $product->set_status('publish');
      $product->save();

      // Set default attributes after publishing
      if ('variable' === $wc_product_data['type']) {
        $this->set_default_attributes($product);
      }


      return $product->get_id();
    } catch (\Exception $e) {
      error_log('Error creating/updating product: ' . $e->getMessage());
      return ['status' => 'failed', 'product_id' => null, 'square_id' => $wc_product_data['square_product_id'], 'message' => esc_html($e->getMessage())];
    }
  }

  /**
   * Set default attributes for a variable product.
   *
   * @param \WC_Product_Variable $product WooCommerce variable product object.
   */
  private function set_default_attributes($product)
  {
    $product_children = $product->get_children();
    if (!empty($product_children)) {
      $first_variation_id = $product_children[0];
      $first_variation = wc_get_product($first_variation_id);
      $default_attributes = $first_variation->get_attributes();

      $product->set_default_attributes($default_attributes);
      $product->save();
    }
  }

  /**
   * Retrieves or creates a product based on Square product ID.
   *
   * @param array $wc_product_data Data for the WooCommerce product.
   * @param bool $update_only Indicates whether to update existing products only.
   * @return \WC_Product|false WooCommerce product object or false if not found and update_only is true.
   */
  private function get_or_create_product_by_square_id($wc_product_data, $update_only)
  {
    global $wpdb;
    $meta_key = 'square_product_id';
    $meta_value = $wc_product_data['square_product_id'];

    $query = $wpdb->prepare(
      "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
      $meta_key,
      $meta_value
    );

    $product_id = $wpdb->get_var($query);

    // Check if the product exists. If $update_only is true and no product is found, return false
    if ($update_only && !$product_id) {
      return false;
    }

    // Create a new product if it doesn't exist
    if (!$product_id) {
      if ($wc_product_data['type'] === 'simple') {
        $product = new \WC_Product_Simple();
      } else {
        $product = new \WC_Product_Variable();
      }
      $product->save();
      $product_id = $product->get_id();

      // Assign the square_product_id meta to the new product
      update_post_meta($product_id, $meta_key, $meta_value);
    } else {
      $product = wc_get_product($product_id);
    }

    return $product;
  }


  /**
   * Retrieves or creates a product based on Square product ID.
   *
   * @param array $wc_product_data Data for the WooCommerce product.
   * @param bool $update_only Indicates whether to update existing products only.
   * @return \WC_Product|false WooCommerce product object or false if not found and update_only is true.
   */
  public function get_product_by_square_id($square_product_id)
  {
    global $wpdb;
    $meta_key = 'square_product_id';
    $meta_value = $square_product_id;

    $query = $wpdb->prepare(
      "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
      $meta_key,
      $meta_value
    );

    $product_id = $wpdb->get_var($query);


    return $product_id ? wc_get_product($product_id) : null;
  }

  /**
   * Updates common properties of a WooCommerce product.
   *
   * @param \WC_Product $product WooCommerce product object.
   * @param array $wc_product_data Data for the WooCommerce product.
   * @param array $data_to_import Data indicating what should be imported.
   */
  private function update_common_product_properties(&$product, $wc_product_data, $data_to_import)
  {
    // Set common product properties
    if (isset($data_to_import['title']) && $data_to_import['title']) {
      $product->set_name($wc_product_data['name']);
    }

    $product->update_meta_data('square_product_id', $wc_product_data['square_product_id']);

    if (isset($wc_product_data['variation_id'])) {
      $product->update_meta_data('square_variation_id', $wc_product_data['variation_id']);
    }

    if (isset($data_to_import['description']) && $data_to_import['description'] && isset($wc_product_data['description'])) {
      $product->set_description($wc_product_data['description']);
    }
  }

  /**
   * Handles simple products.
   *
   * @param \WC_Product_Simple $product WooCommerce simple product object.
   * @param array $wc_product_data Data for the WooCommerce product.
   * @param array $data_to_import Data indicating what should be imported.
   */
  private function handle_simple_product(&$product, $wc_product_data, $data_to_import)
  {

    // For simple products
    if (isset($data_to_import['price']) && $data_to_import['price'] && isset($wc_product_data['variations'][0]['price'])) {
      $product->set_regular_price($wc_product_data['variations'][0]['price']);
    }

    if (isset($data_to_import['sku']) && $data_to_import['sku'] && isset($wc_product_data['variations'][0]['sku']) && !wc_get_product_id_by_sku($wc_product_data['variations'][0]['sku'])) {
      $product->set_sku($wc_product_data['variations'][0]['sku']);
    }

    if (isset($data_to_import['stock']) && $data_to_import['stock'] && isset($wc_product_data['variations'][0]['stock'])) {
      $product->set_manage_stock(true);
      $product->set_stock_quantity($wc_product_data['variations'][0]['stock']);
    }

    if (isset($wc_product_data['variations'][0]['attributes'])) {
      $all_attribute_options = $wc_product_data['variations'][0]['attributes'];
      $attributes = [];

      foreach ($all_attribute_options as $attribute_data) {
        $attribute_name = $attribute_data['name'];
        $terms = [$attribute_data['option']];

        // Retrieve existing terms from the product's attributes
        $attribute_slug = wc_sanitize_taxonomy_name($attribute_name);
        $existing_attributes = $product->get_attributes();
        $existing_terms = [];

        // Check for local attribute
        if (isset($existing_attributes[$attribute_name])) {
          $existing_terms = $existing_attributes[$attribute_name]->get_options();
        } else {
          // Check for global attribute
          $taxonomy = 'pa_' . $attribute_slug;
          if (isset($existing_attributes[$taxonomy])) {
            $existing_terms = $existing_attributes[$taxonomy]->get_options();
          }
        }

        // Merge existing terms with new terms
        $merged_terms = array_unique(array_merge($existing_terms, $terms));

        // Get or create the attribute
        $attribute = $this->get_or_create_attribute($attribute_name, $merged_terms, $product);
        if ($attribute) {
          $attributes[$attribute->get_name()] = $attribute;
        }
      }

      // Set all attributes to the simple product
      $product->set_attributes($attributes);
    }

    $product->save();
  }

  /**
   * Retrieves or creates a WooCommerce attribute.
   *
   * @param string $attribute_name The name of the attribute.
   * @param array $terms Array of term slugs or term IDs to associate with the attribute.
   * @param \WC_Product $product The product object for local attributes.
   * @return WC_Product_Attribute|bool The attribute object if successful, or false on failure.
   */
  private function get_or_create_attribute($attribute_name, $terms, $product)
  {
    // Normalize attribute name casing
    $attribute_name = strtolower($attribute_name);

    // Sanitize attribute name for taxonomy
    $attribute_taxonomy = wc_sanitize_taxonomy_name($attribute_name);
    $taxonomy = 'pa_' . $attribute_taxonomy;

    // Convert slugs to term IDs and keep existing IDs
    $terms_with_ids = [];
    foreach ($terms as $term) {
      if (is_numeric($term)) {
        $terms_with_ids[] = $term;
      } else {
        $term_obj = get_term_by('slug', $term, $taxonomy);
        if (!$term_obj) {
          $term_data = wp_insert_term($term, $taxonomy);
          if (!is_wp_error($term_data)) {
            $terms_with_ids[] = $term_data['term_id'];
          }
        } else {
          $terms_with_ids[] = $term_obj->term_id;
        }
      }
    }

    // Check if local attribute exists on the product
    $existing_attributes = $product->get_attributes();

    if (isset($existing_attributes[$attribute_name])) {
      $local_attribute = $existing_attributes[$attribute_name];
      $existing_terms = $local_attribute->get_options();
      $merged_terms_with_ids = array_unique(array_merge($existing_terms, $terms_with_ids));
      $local_attribute->set_options($merged_terms_with_ids);
      return $local_attribute;
    }

    // Check if the taxonomy exists globally
    if (taxonomy_exists($taxonomy)) {
      // Get the ID of the existing global attribute
      $attribute_id = wc_attribute_taxonomy_id_by_name($taxonomy);

      // Check if attribute ID is valid
      if (!$attribute_id) {
        error_log("Invalid global attribute ID for taxonomy: $taxonomy");
        return false;
      }

      // Fetch existing terms from the product for this attribute
      $existing_terms = [];
      if (isset($existing_attributes[$taxonomy])) {
        $existing_terms = $existing_attributes[$taxonomy]->get_options();
      }


      $merged_terms_with_ids = array_unique(array_merge($existing_terms, $terms_with_ids));


      $attribute = new \WC_Product_Attribute();
      $attribute->set_id($attribute_id);
      $attribute->set_name($taxonomy);
      $attribute->set_options($merged_terms_with_ids); // Set term IDs as options
      $attribute->set_position(0);
      $attribute->set_visible(true);
      $attribute->set_variation(true);

      return $attribute;
    } else {
      // Create the global attribute if it doesn't exist
      $attribute_data = [
        'name'             => ucfirst($attribute_name),
        'slug'             => $attribute_taxonomy, // No 'pa_' prefix
        'type'             => 'select',
        'order_by'         => 'menu_order',
        'has_archives'     => 1, // 1 means it will be a global attribute
      ];

      // Check if the attribute data is correct
      if (empty($attribute_data['name'])) {
        error_log("Attribute name is missing in the attribute data");
        return false;
      }

      $attribute_id = wc_create_attribute($attribute_data);

      if (is_wp_error($attribute_id)) {
        error_log("Error creating global attribute: " . $attribute_id->get_error_message());
        return false;
      }

      // Register the taxonomy for the new attribute
      register_taxonomy(
        $taxonomy,
        'product',
        [
          'label' => ucfirst($attribute_name),
          'public' => true,
          'hierarchical' => false,
          'show_ui' => true,
          'query_var' => true,
          'rewrite' => ['slug' => $taxonomy],
        ]
      );

      // Add the terms to the newly created taxonomy
      foreach ($terms as $term) {
        if (!is_numeric($term) && !term_exists($term, $taxonomy)) {
          wp_insert_term($term, $taxonomy);
        }
      }

      // Convert slugs to term IDs again after creating the terms
      $terms_with_ids = [];
      foreach ($terms as $term) {
        if (is_numeric($term)) {
          $terms_with_ids[] = $term;
        } else {
          $term_obj = get_term_by('slug', $term, $taxonomy);
          if ($term_obj) {
            $terms_with_ids[] = $term_obj->term_id;
          }
        }
      }

      $attribute = new \WC_Product_Attribute();
      $attribute->set_id($attribute_id);
      $attribute->set_name($taxonomy);
      $attribute->set_options($terms_with_ids); // Set term IDs as options
      $attribute->set_position(0);
      $attribute->set_visible(true);
      $attribute->set_variation(true);

      return $attribute;
    }
  }
}
