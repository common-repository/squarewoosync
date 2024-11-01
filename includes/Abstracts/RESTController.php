<?php

namespace Pixeldev\SquareWooSync\Abstracts;

use WP_REST_Controller;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Rest Controller base class.
 *
 * @since 0.3.0
 */
abstract class RESTController extends WP_REST_Controller
{

    /**
     * Endpoint namespace.
     *
     * @var string
     */
    protected $namespace = 'sws/v1';

    /**
     * Handles permission callback.
     *
     * @return bool|WP_Error
     */
    public function check_permission($request) {
        // Verify nonce
        $nonce = $request->get_header('nonce');
        if (!wp_verify_nonce($nonce, 'sws_sec_nonce')) {
            return new \WP_Error('rest_forbidden', esc_html__('Nonce verification failed', 'squarewoosync'), array('status' => 403));
        }

        // Additional permission checks if needed
        return current_user_can('edit_posts'); // Adjust capability as needed
    }


    public function check_ip_permission() {
        $allowed_ips = ['54.245.1.154', '34.202.99.168', '54.212.177.79', '107.20.218.8'];
        $remote_ip = $_SERVER['REMOTE_ADDR'];

        if (in_array($remote_ip, $allowed_ips)) {
            return true;
        }

        return new \WP_Error('rest_forbidden', __('You do not have permissions to access this resource.'), ['status' => 403]);

    }

    /**
     * Handles woocommerce check
     *
     * @return bool|WP_Error
     */
    public function check_woocommerce()
    {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        if (is_plugin_active('woocommerce/woocommerce.php')) {
            return true;
        }
        return false;
    }


    /**
     * Format item's collection for response.
     *
     * @since  0.0.3
     *
     * @param object $response
     * @param object $request
     * @param array  $items
     * @param int    $total_items
     *
     * @return object
     */
    public function format_collection_response($response, $request, $total_items)
    {
        if ($total_items === 0) {
            return $response;
        }

        // Pagination values for headers
        $per_page = (int) (!empty($request['per_page']) ? $request['per_page'] : 20);
        $page     = (int) (!empty($request['page']) ? $request['page'] : 1);

        $response->header('X-WP-Total', (int) $total_items);

        $max_pages = ceil($total_items / $per_page);

        $response->header('X-WP-TotalPages', (int) $max_pages);
        $base = add_query_arg($request->get_query_params(), rest_url(sprintf('/%s/%s', $this->namespace)));

        if ($page > 1) {
            $prev_page = $page - 1;
            if ($prev_page > $max_pages) {
                $prev_page = $max_pages;
            }
            $prev_link = add_query_arg('page', $prev_page, $base);
            $response->link_header('prev', $prev_link);
        }
        if ($max_pages > $page) {
            $next_page = $page + 1;
            $next_link = add_query_arg('page', $next_page, $base);
            $response->link_header('next', $next_link);
        }

        return $response;
    }
}
