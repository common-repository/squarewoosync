<?php

namespace Pixeldev\SquareWooSync\Payments\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_SquareSync_Gateway_Blocks_Support extends AbstractPaymentMethodType
{

    private $gateway;
    protected $name = 'squaresync_credit';
    public $page = null;

    public function __construct() {}

    public function initialize()
    {
        $gateways = WC()->payment_gateways->payment_gateways();
        $this->gateway  = $gateways[$this->name];
    }

    public function is_active()
    {
        return $this->gateway ? $this->gateway->is_available() : false;
    }

    public function get_payment_method_script_handles()
    {
        $asset_path   = SQUAREWOOSYNC_URL . '/build/blocks/gateway.asset.php';
        $version      = SQUAREWOOSYNC_VERSION;
        $dependencies = array();

        if (file_exists($asset_path)) {
            $asset        = require $asset_path;
            $version      = is_array($asset) && isset($asset['version']) ? $asset['version'] : $version;
            $dependencies = is_array($asset) && isset($asset['dependencies']) ? $asset['dependencies'] : $dependencies;
        }

        wp_enqueue_style('squaresync-checkout-block',  SQUAREWOOSYNC_URL . '/build/assets/frontend/wallet.css', array(), SQUAREWOOSYNC_VERSION);

        wp_register_script(
            'square-block',
            SQUAREWOOSYNC_URL . '/build/blocks/gateway.js',
            $dependencies,
            $version,
            true
        );

        return array('square-block');
    }

    public function get_payment_method_data()
    {
        $page            = $this->get_current_page();
        $payment_request = false;
        $settings = get_option('square-woo-sync_settings', []);

        $gateway_id = 'squaresync_credit';
        $gateway_settings = get_option('woocommerce_' . $gateway_id . '_settings', array());

        try {
            // Reload the gateway instance
            $gateways = WC()->payment_gateways->payment_gateways();
            $this->gateway = isset($gateways[$gateway_id]) ? $gateways[$gateway_id] : null;
    
            if ($this->gateway) {
                // Optionally set the new settings for the gateway if needed
                $this->gateway->settings = $gateway_settings;
    
                // Generate payment request for the current page
                $payment_request = $this->gateway->get_payment_request_for_context($page);
            }
        } catch (\Exception $e) {
            error_log('Error: ' . $e->getMessage());
        }

        // Ensure the gateway object is initialized and available
        if ($this->gateway) {
            // Retrieve general settings related to the Square gateway
            $applicationId = isset($settings['environment']) && $settings['environment'] == 'sandbox' 
            ? $this->gateway->get_option('square_application_id_sandbox') 
            : $this->gateway->get_option('square_application_id_live');

            // Additional location settings
            $settings = get_option('square-woo-sync_settings', []);
            $locationId = isset($settings['location']) ? $settings['location'] : '';

            // Return all payment method data including the additional settings
            return array(
                'title'       => $this->gateway->get_option('title'),
                'description' => $this->gateway->get_option('description'),
                'supports'    => $this->gateway->supports,
                'accepted_credit_cards' => $this->gateway->get_option('accepted_credit_cards'),
                'payment_token_nonce' => wp_create_nonce('payment_token_nonce'),
                'payment_request_nonce'    => wp_create_nonce('squaresync-get-payment-request'),
                'payment_request'          => $payment_request,
                'context' =>  $page,
                'recalculate_totals_nonce'   => wp_create_nonce('squaresync-recalculate-totals'),
                'applicationId' => $applicationId,
                'locationId' => $locationId,
                'enable_google_pay' => $this->gateway->get_option('enable_google_pay'),
                'enable_apple_pay' => $this->gateway->get_option('enable_apple_pay'),
                'general_error'            => __('An error occurred, please try again or try an alternate form of payment.', 'woocommerce-square'),
                'ajax_url'                 => \WC_AJAX::get_endpoint('%%endpoint%%'),
                'process_checkout_nonce'   => wp_create_nonce('woocommerce-process_checkout')
            );
        }

        return array(); // Return an empty array if the gateway is not initialized
    }

    /**
     * Returns the current page.
     *
     * Stores the result in $this->page to avoid recalculating multiple times per request
     *
     * @since 2.3
     * @return string
     */
    public function get_current_page()
    {
        if (null === $this->page) {
            $is_cart    = is_cart() && ! WC()->cart->is_empty();
            $is_product = is_product() || wc_post_content_has_shortcode('product_page');
            $this->page = null;

            if ($is_cart) {
                $this->page = 'cart';
            } elseif ($is_product) {
                $this->page = 'product';
            } elseif (is_checkout() || (function_exists('has_block') && has_block('woocommerce/checkout'))) {
                $this->page = 'checkout';
            }
        }

        return $this->page;
    }
}
