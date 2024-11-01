<?php

namespace Pixeldev\SquareWooSync\REST;

use Pixeldev\SquareWooSync\Abstracts\RESTController;
use Pixeldev\SquareWooSync\Logger\Logger;
use Pixeldev\SquareWooSync\Square\SquareHelper;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * API SettingsController class for plugin settings.
 *
 * @since 0.5.0
 */
class OrdersController extends RESTController
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
    protected $base = 'orders';

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
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => [$this, 'get_orders'],
                    'permission_callback' => [$this, 'check_permission'],
                ],
                [
                    'methods' => WP_REST_Server::EDITABLE,
                    'callback' => [$this, 'create_square_order'],
                    'permission_callback' => [$this, 'check_permission'],
                ]
            ]
        );
    }

    /**
     * Create an order in Square based on WooCommerce order data.
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function create_square_order(WP_REST_Request $request): WP_REST_Response
    {
        // Initialize SquareHelper
        $square = new SquareHelper();
        $order_id = intval($request->get_param('order_id'));
        $order = wc_get_order($order_id);

        if (!$order) {
            return new WP_REST_Response(['error' => 'Order not found'], 404);
        }

        $logger = new Logger();

        try {
            // Retrieve or create Square customer ID
            $square_customer_id = $this->getOrCreateSquareCustomer($order, $square);

            if (isset($square_customer_id['error'])) {
                $logger->log('error', esc_html__('Square Orders error: ', 'squarewoosync') . esc_html($square_customer_id['error']));
                // Return the detailed error message in the response
                return new WP_REST_Response(['error' => esc_html__('Square Orders error: ', 'squarewoosync') . esc_html($square_customer_id['error'])], 400);
            }

            // Prepare order data for Square
            $order_data = $this->prepareSquareOrderData($order, $square_customer_id);
            $response = $this->createOrderInSquare($order_data, $square);

            // Check for errors in the response
            if (isset($response['error'])) {
                $logger->log('error', esc_html__('Square Orders API error: ', 'squarewoosync') . esc_html($response['error']));
                // Log this error appropriately
                return new WP_REST_Response(['error' => esc_html__('Square API error: ', 'squarewoosync') . esc_html($response['error'])], 502);
            }

            $settings = get_option('square-woo-sync_settings', []);
            if (isset($settings['orders']['transactions']) && $settings['orders']['transactions'] === true) {
                $payResponse = $this->payForOrder($response['data']['order'], $square);

                if (isset($payResponse['error'])) {
                    $logger->log('error', esc_html__('Square Payment API error: ', 'squarewoosync') . esc_html($payResponse['error']));
                    // Line 93
                    return new WP_REST_Response(['error' => esc_html__('Square API error: ', 'squarewoosync') . esc_html($payResponse['error'])], 502);
                }

                if (isset($response['data']['order']['id']) && isset($payResponse['data']['payment']['id'])) {
                    $square_data = ['order' => $response, 'payment' => $payResponse];

                    // Save Square order ID and payment ID to WooCommerce order meta
                    $order->update_meta_data('square_data', wp_json_encode($square_data));

                    // Save changes to the order
                    $order->save();
                }

                $logger->log('success', esc_html__('Order and Transaction created in Square, receipt: #', 'squarewoosync') . sanitize_text_field($payResponse['data']['payment']['receipt_number']));
                return new WP_REST_Response(['data' => ['order' => $response, 'payment' => $payResponse]], 200);
            } else {



                // No transaction ID, so no payment update response
                $square_data = [
                    'order' => $response,
                    'payment' => '',
                ];

                // Save the order details
                $order->update_meta_data('square_data', wp_json_encode($square_data));
                $order->save();


                $logger->log('success', esc_html__('Order created in Square', 'squarewoosync'));
                return new WP_REST_Response(['data' => $square_data], 200);
            }
        } catch (\Exception $e) {
            if ($e->getMessage() == 'Square location not set') {
                return new WP_REST_Response(['error' => $e->getMessage()], 401);
            }
            $logger->log('error', esc_html__('Failed to create order: ', 'squarewoosync') . esc_html($e->getMessage()));

            return new WP_REST_Response(['error' => esc_html($e->getMessage())], 500);
        }
    }


    public function payForOrder(array $order_data, $square): array
    {
        $settings = get_option('square-woo-sync_settings', []);
        if (!isset($settings['location'])) {
            throw new \Exception('Square location not set', 401);
        }
        // The endpoint for creating an order in Square
        $endpoint = '/payments';

        $payment_data = [];
        $payment_data['idempotency_key'] = uniqid();
        $payment_data['location_id'] = $settings['location'];
        $payment_data['amount_money'] = [
            'amount' => (int)$order_data['total_money']['amount'], // Ensure this is an integer in the smallest currency unit
            //'currency' => 'AUD',
            'currency' => $order_data['total_money']['currency'], // e.g., 'USD'
        ];
        $payment_data['source_id'] = 'EXTERNAL'; // Make sure this is appropriate for your payment method
        $payment_data['order_id'] = $order_data['id'];
        $payment_data['external_details'] = ['source' => 'Website Order', 'type' => 'OTHER'];

        // Making the API request to Square to create the order
        $response = $square->square_api_request($endpoint, 'POST', $payment_data);

        return $response;
    }

    public function createOrderInSquare(array $order_data, $square): array
    {

        // The endpoint for creating an order in Square
        $endpoint = '/orders';

        // Making the API request to Square to create the order
        $response = $square->square_api_request($endpoint, 'POST', $order_data);

        return $response;
    }


    public function getOrCreateSquareCustomer($order, $square)
    {
        $woo_customer_email = $order->get_billing_email();
        $search_customer_result = $square->square_api_request('/customers/search', 'POST', [
            'query' => ['filter' => ['email_address' => ["exact" => $woo_customer_email]]]
        ]);

        // If customer already exists, return the ID
        if (!empty($search_customer_result['data']['customers'])) {
            return $search_customer_result['data']['customers'][0]['id'];
        }

        // Customer doesn't exist, attempt to create a new one
        $new_customer_data = $this->formatNewCustomerData($order);
        $create_customer_result = $square->square_api_request('/customers', 'POST', $new_customer_data);


        if (!empty($create_customer_result['data']['customer']['id'])) {
            // Return new customer ID if retries to fetch the customer fail
            return $create_customer_result['data']['customer']['id'];
        }

        // Handle cases where customer creation failed
        return $create_customer_result;
    }


    public function calculateTotalOrderTaxDetails($order)
    {
        // Get the total tax for the order
        $total_tax = $order->get_total_tax();
        $total_tax_cents = round($total_tax * 100); // Convert tax to cents for Square

        // Check if WooCommerce prices include tax in settings
        $is_tax_inclusive = get_option('woocommerce_prices_include_tax') === 'yes';

        // Get the total excluding tax, ensuring that discounts and shipping are considered correctly
        // WooCommerce tax-exclusive stores need this to determine the proper base for tax calculation
        $total_excluding_tax = $order->get_total() - $total_tax;

        // Calculate the tax percentage relative to the actual total before tax
        if ($total_excluding_tax > 0) {
            $tax_percentage = ($total_tax / $total_excluding_tax) * 100;
        } else {
            $tax_percentage = 0;
        }

        return [
            'amount' => $total_tax_cents,
            'percentage' => sprintf("%.2f", $tax_percentage),  // Tax percentage as a string
            'currency' => $order->get_currency(),  // Currency for the order
            'is_tax_inclusive' => $is_tax_inclusive,  // Whether prices are tax-inclusive or exclusive
        ];
    }


    public function prepareSquareOrderData($order, string $square_customer_id, $location_id = null): array
    {
        $settings = get_option('square-woo-sync_settings', []);
        if (!isset($settings['location'])) {
            throw new \Exception('Square location not set', 401);
        }

        $locationId = isset($settings['location']) && !empty($settings['location']) && !is_null($settings['location']) ? $settings['location'] : ($location_id ?? '');

        // Fetch line items with updated tax handling
        $line_items = $this->getOrderLineItems($order);

        // Add shipping as a line item if applicable
        $shipping_line_item = $this->addShippingLineItem($order);
        if (!empty($shipping_line_item)) {
            $line_items[] = $shipping_line_item;
        }

        // Prepare tax details
        $tax_details = $this->calculateTotalOrderTaxDetails($order);

        // Prepare the final order data with all line items, taxes, and fulfillment details
        $order_data = [
            'order' => [
                'location_id' => $locationId,
                'customer_id' => $square_customer_id,
                'metadata' => ['woo_order_id' => strval($order->get_id())],
                'state' => $this->mapOrderStatus($order->get_status()),
                'ticket_name' => 'WooCommerce - #' . $order->get_id(),
                'reference_id' => 'WooCommerce - #' . $order->get_id(),
                'source' => ['name' => 'WooCommerce - #' . $order->get_id()],
                'line_items' => $line_items,
            ]
        ];

        $taxes_enabled = get_option('woocommerce_calc_taxes') === 'yes';
        // Apply tax as additive, Square will calculate based on this.
        if ($taxes_enabled) {
            $order_data['order']['taxes'] = [[
                'uid' => 'order-tax',
                'name' => 'Tax',
                'percentage' => $tax_details['percentage'], // Tax percentage
                'scope' => 'ORDER',
                'inclusion_type' => get_option('woocommerce_prices_include_tax') === 'yes' ? 'INCLUSIVE' : 'ADDITIVE',
            ]];
        }

        // Handle discounts (ensure discounts are subtracted after tax is calculated)
        $discounts = $this->getOrderDiscounts($order);
        if (!empty($discounts)) {
            $order_data['order']['discounts'] = $discounts;
        }

        // Fulfillments
        $fulfillments = $this->getOrderFulfillments($order);
        if (!empty($fulfillments)) {
            $order_data['order']['fulfillments'] = $fulfillments;
        }

        return $order_data;
    }

    public function getOrderDiscounts($order): array
    {
        $discounts = [];
        $total_discount = $order->get_total_discount();

        if ($total_discount > 0) {
            $discounts[] = [
                'uid' => 'order-discount',  // Unique ID for the discount
                'name' => 'Total Discount',
                'amount_money' => [
                    'amount' => round($total_discount * 100),  // Convert to cents
                    'currency' => $order->get_currency(),
                ],
                'scope' => 'ORDER',  // Apply the discount to the entire order
            ];
        }

        return $discounts;
    }


    public function formatNewCustomerData($order): array
    {
        // Initialize the base structure with potentially always available data
        $customerData = [
            "address" => [], // Ensures the address is structured correctly
        ];

        // Define a helper function to safely add data if not empty
        $addIfNotEmpty = function ($key, $value) use (&$customerData) {
            if (!empty($value)) {
                // Check if the key is related to the address
                if (strpos($key, 'address_') === 0) { // Address-related key
                    // Remove the first occurrence of 'address_' from the key
                    // This is done by replacing 'address_' with '', but only for the first occurrence
                    $adjustedKey = preg_replace('/^address_/', '', $key, 1);
                    // Add to the 'address' array with the correctly formatted key
                    $customerData['address'][$adjustedKey] = $value;
                } else {
                    $customerData[$key] = $value;
                }
            }
        };

        // Add address details with correct key formatting
        $addIfNotEmpty('address_address_line_1', $order->get_billing_address_1());
        $addIfNotEmpty('address_address_line_2', $order->get_billing_address_2());
        $addIfNotEmpty('address_country', $order->get_billing_country());
        $addIfNotEmpty('address_administrative_district_level_1', $order->get_billing_state());
        $addIfNotEmpty('address_locality', $order->get_billing_city());
        $addIfNotEmpty('address_postal_code', $order->get_billing_postcode());
        $addIfNotEmpty('address_first_name', $order->get_billing_first_name());
        $addIfNotEmpty('address_last_name', $order->get_billing_last_name());

        // Add other details
        $addIfNotEmpty('company_name', $order->get_billing_company());
        $addIfNotEmpty('email_address', $order->get_billing_email());
        $addIfNotEmpty('family_name', $order->get_billing_last_name());
        $addIfNotEmpty('given_name', $order->get_billing_first_name());

        return $customerData;
    }



    public function mapOrderStatus(string $status): string
    {
        $statusMap = [
            'pending' => 'DRAFT',
            'processing' => 'OPEN',
            'on-hold' => 'OPEN',
            'completed' => 'COMPLETED',
            'cancelled' => 'CANCELLED',
            'refunded' => 'CANCELLED',
            'failed' => 'CANCELLED',
        ];

        //return $statusMap[$status] ?? 'DRAFT';
        return 'OPEN';
    }

    public function getOrderLineItems($order): array
    {
        $line_items = [];
        $currency = $order->get_currency();

        $taxes_enabled = get_option('woocommerce_calc_taxes') === 'yes';
        $is_tax_inclusive = get_option('woocommerce_prices_include_tax') === 'yes';

        // Fallback: If WooCommerce tax settings are missing, assume prices are tax-inclusive
        if ($is_tax_inclusive || !$taxes_enabled) {
            $is_tax_inclusive = true;
        }

        // Get the discount information
        // $discounts = $this->getOrderDiscounts($order);

        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $quantity = strval($item->get_quantity());

            // Calculate the price based on whether tax is inclusive or not
            $price = $taxes_enabled ? wc_get_price_excluding_tax($product) : $product->get_price();
            $price_cents = round($price * 100);  // Square API expects prices in the smallest currency unit

            // Collect meta data (if any) for the product
            $note_parts = [];
            foreach ($item->get_meta_data() as $attribute) {
                $note_parts[] = strip_tags(wc_attribute_label($attribute->key) . ': ' . $attribute->value);
            }
            $note = implode("\n", array_filter($note_parts));

            // Prepare line item for Square
            $line_item = [
                'name' => $item->get_name(),
                'quantity' => $quantity,
                'base_price_money' => [
                    'amount' => $price_cents,
                    'currency' => $currency,
                ],
                'item_type' => 'ITEM',
                'note' => $note,
            ];

            // If tax is not inclusive, link the applied tax to the line item
            if ($taxes_enabled) {
                $line_item['applied_taxes'] = [
                    [
                        'tax_uid' => 'order-tax',  // Link to the top-level tax UID
                    ]
                ];
            }

            // // If there are discounts, link the applied discount to the line item
            // if (!empty($discounts)) {
            //     $line_item['applied_discounts'] = [
            //         [
            //             'discount_uid' => 'order-discount',  // Link to the top-level discount UID
            //         ]
            //     ];
            // }


            $line_items[] = $line_item;
        }

        return $line_items;
    }

    public function getCommonProductTaxRate($order)
    {
        foreach ($order->get_items() as $item_id => $item) {
            $_product = $item->get_product();
            if (!$_product) continue;

            $tax_class = $_product->get_tax_class();
            $tax_rates = \WC_Tax::get_base_tax_rates($tax_class); // Retrieve tax rates based on product's tax class

            $rate = !empty($tax_rates) ? reset($tax_rates) : null;
            return $rate ? $rate['rate'] : 0; // Return the first product's tax rate, or 0 if not applicable
        }

        return 0; // Default to 0 if no products found
    }


    public function addShippingLineItem($order): array
    {
        $shipping_total = $order->get_shipping_total(); // Total shipping cost
        $currency = $order->get_currency(); // Order currency

        if ($shipping_total > 0) {
            return [
                'name' => 'Shipping',
                'quantity' => '1',
                'base_price_money' => [
                    'amount' => round($shipping_total * 100), // Square expects the amount in cents
                    //'currency' => 'AUD',
                    'currency' => $currency,
                ],
                'item_type' => 'ITEM',
            ];
        }

        return [];
    }

    public function addFeeLineItems($order): array
    {
        $line_items = [];
        $currency = $order->get_currency(); // Order currency

        foreach ($order->get_fees() as $fee_key => $fee) {
            $fee_amount = $fee->get_amount(); // Fee amount
            if ($fee_amount != 0) {
                $line_items[] = [
                    'name' => $fee->get_name() ?: 'Additional Fee',
                    'quantity' => '1',
                    'base_price_money' => [
                        'amount' => round($fee_amount * 100), // Convert to cents
                        //'currency' => 'AUD',
                        'currency' => $currency,
                    ],
                    'item_type' => 'ITEM',
                ];
            }
        }

        return $line_items;
    }





    public function getProductTaxRate($_product)
    {
        if (!$_product) return 0;

        $tax_class = $_product->get_tax_class();
        $tax_rates = \WC_Tax::get_base_tax_rates($tax_class); // Retrieve tax rates based on product's tax class

        // Assuming single tax rate per class for simplicity, otherwise, you'd need to handle multiple rates
        $rate = !empty($tax_rates) ? reset($tax_rates) : null;

        return $rate ? $rate['rate'] : 0; // Return the tax rate, or 0 if not applicable
    }


    public function getOrderFulfillments($order): array
    {
        // Check if the order has shipping details
        $has_shipping = $order->get_shipping_address_1() || $order->get_shipping_address_2() || $order->get_shipping_city() || $order->get_shipping_postcode() || $order->get_shipping_country();

        // Get the shipping method used in the order
        $shipping_lines = $order->get_shipping_methods();
        $is_pickup = false;

        foreach ($shipping_lines as $shipping_item) {
            $method_id = $shipping_item->get_method_id();

            // Check if the shipping method ID is pickup_location
            if ($method_id === 'pickup_location') {
                $is_pickup = true;
                break;
            }
        }

        if ($has_shipping && !$is_pickup) {
            // Shipment type fulfillment
            return [
                [
                    'type' => 'SHIPMENT',
                    'state' => 'PROPOSED',
                    'shipment_details' => [
                        'recipient' => [
                            'address' => [
                                'address_line_1' => $order->get_shipping_address_1(),
                                'address_line_2' => $order->get_shipping_address_2(),
                                'administrative_district_level_1' => $order->get_shipping_state(),
                                'locality' => $order->get_shipping_city(),
                                'postal_code' => $order->get_shipping_postcode(),
                                'country' => $order->get_shipping_country(),
                                'first_name' => $order->get_shipping_first_name(),
                                'last_name' => $order->get_shipping_last_name(),
                            ],
                            'display_name' => $order->get_formatted_billing_full_name(),
                            'email_address' => $order->get_billing_email(),
                            'phone_number' => $order->get_billing_phone(),
                        ],
                        'carrier' => '', // Optionally specify
                        'shipping_note' => '', // Optionally specify
                        'tracking_number' => '', // Optionally specify
                        'tracking_url' => '', // Optionally specify
                    ]
                ]
            ];
        } else {
            // Pickup type fulfillment
            $expires_at = (new \DateTime('now', new \DateTimeZone('UTC')))->add(new \DateInterval('P7D'))->format(\DateTime::RFC3339);

            return [
                [
                    'type' => 'PICKUP',
                    'state' => 'PROPOSED',
                    'pickup_details' => [
                        'recipient' => [
                            'display_name' => $order->get_formatted_billing_full_name(),
                            'email_address' => $order->get_billing_email(),
                            'phone_number' => $order->get_billing_phone(),
                        ],
                        'expires_at' => $expires_at,
                        'auto_complete_duration' => 'P1W', // Automatically complete after 1 week
                        'schedule_type' => 'SCHEDULED',
                        'pickup_at' => (new \DateTime('now', new \DateTimeZone('UTC')))->format(\DateTime::RFC3339), // Current time in UTC
                        'pickup_window_duration' => 'P1D', // 1 day pickup window
                        'prep_time_duration' => 'PT1H', // 1 hour preparation time
                        'note' => 'Please pick up your order at the front desk.', // Optional note
                    ]
                ]
            ];
        }
    }

    /**
     * Retrieves orders from WooCommerce with pagination.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response object on success, or a response object encapsulating WP_Error on failure.
     */
    public function get_orders(WP_REST_Request $request): WP_REST_Response
    {
        $woocommerceInstalled = $this->check_woocommerce();
        if (!$woocommerceInstalled) {
            // Consistently create a WP_REST_Response object for the error
            return new WP_REST_Response(['error' => esc_html__('Woocommerce not installed or activated', 'squarewoosync')], 424);
        }

        $page = $request->get_param('page') ? intval($request->get_param('page')) : 1;
        $per_page = $request->get_param('per_page') ? intval($request->get_param('per_page')) : 10;

        $args = [
            'limit' => $per_page ? $per_page : -1,
            'pagination' => true,
            'page' => $page ? $page : 0,
        ];

        $orders = wc_get_orders($args);

        if (is_wp_error($orders)) {
            $error_message = $orders->get_error_message();
            $error_data = $orders->get_error_data();
            $status = isset($error_data['status']) ? $error_data['status'] : 500;
            // Return a consistent error object
            return new WP_REST_Response(['error' => esc_html($error_message)], $status);
        }

        if (empty($orders)) {
            return new WP_REST_Response([], 200);
        }

        $orders_data = array_map(function ($order) {
            if (!($order instanceof \WC_Order)) {
                // Handle non-WC_Order objects, such as WC_Order_Refund, or simply skip them
                return null; // or continue with some other logic
            }

            // Directly retrieving customer information from the order
            $customer_data = [
                'email' => $order->get_billing_email(),
                'company' => $order->get_billing_company(),
                'first_name' => $order->get_billing_first_name(),
                'last_name' => $order->get_billing_last_name(),
                // Include additional customer details as necessary
            ];

            // Conditionally add the customer ID if it exists
            if (method_exists($order, 'get_customer_id') && $order->get_customer_id()) {
                $customer_data['id'] = $order->get_customer_id();
            }

            // Line items
            $line_items_data = array_map(function ($item) {
                // Get the product object
                $product = $item->get_product();

                // Check if $product is not false
                if (!$product) {
                    return [
                        'product_name' => $item->get_name(),
                        'product_id' => $item->get_product_id(),
                        'sku' => '',
                        'image' => '',
                        'quantity' => $item->get_quantity(),
                        'price' => '',
                        'square_product_id' => '',
                        'total' => $item->get_total(),
                        'meta_data' => [], // No meta data if product is not found
                    ];
                }

                $product_id = $product->get_id(); // Get the product ID
                $image_id = get_post_thumbnail_id($product_id);
                $featured_image_url = wp_get_attachment_image_url($image_id, 'thumbnail');
                // Retrieve the square_product_id meta value for the product
                $square_product_id = $product ? $product->get_meta('square_product_id') : '';

                return [
                    'product_name' => $item->get_name(),
                    'product_id' => $item->get_product_id(),
                    'sku' => $product->get_sku(),
                    'image' => $featured_image_url,
                    'quantity' => $item->get_quantity(),
                    'price' => $product->get_price(),
                    'square_product_id' => $square_product_id,
                    'total' => $item->get_total(),
                ];
            }, $order->get_items());

            $square_data = $order->get_meta('square_data', true);

            // Retrieve all order meta data
            $order_meta = [];
            $meta_data = $order->get_meta_data();
            foreach ($meta_data as $meta) {
                $order_meta[$meta->key] = $meta->value;
            }

            return [
                'id' => $order->get_id(),
                'status' => $order->get_status(),
                'total' => $order->get_total(),
                'order_total' => $order->get_total(), // Total amount of the order.
                'order_subtotal' => $order->get_subtotal(), // Total amount before taxes and shipping.
                'total_tax' => $order->get_total_tax(), // Total tax amount for the order.
                'shipping_total' => $order->get_shipping_total(), // Total shipping amount for the order.
                'discount_total' => $order->get_total_discount(), // Total discount amount applied to the order.
                'date' => get_date_from_gmt($order->get_date_created(), 'M jS Y, g:ia'),
                'customer' => $customer_data,
                'line_items' => array_values($line_items_data), // Ensure the array is reindexed
                'square_data' => !empty($square_data) ? $square_data : null,
                'meta_data' => $order_meta, // Include all order meta data
            ];
        }, $orders);

        return new WP_REST_Response([
            'orders' => $orders_data,
            'total' => count($orders),
            'max_num_pages' => ceil(count($orders) / $per_page),
        ], 200);
    }
}