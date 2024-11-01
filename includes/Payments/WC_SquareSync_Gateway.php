<?php

namespace Pixeldev\SquareWooSync\Payments;

use Pixeldev\SquareWooSync\Payments\Blocks\WC_SquareSync_Gateway_Blocks_Support;
use Pixeldev\SquareWooSync\Square\SquareHelper;
use Pixeldev\SquareWooSync\REST\OrdersController;
use WP_Error;
use WC_Payment_Gateway;

require_once plugin_dir_path(__FILE__) . '../../vendor/autoload.php';

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

class WC_SquareSync_Gateway extends WC_Payment_Gateway
{
	private $square_application_id;
	private $square_url;
	private $square_op_mode;
	private $location_id;

	public $page = null;
	public $total_label_suffix;

	public function __construct($paymentId = null, $paymentTitle = null, $paymentDescription = null)
	{
		$settings = get_option('square-woo-sync_settings', []);

		$this->id = 'squaresync_credit';
		$this->icon = '';
		$this->has_fields = false;
		$this->method_title = $paymentTitle ?? 'Square Payments by SquareSync for Woo';
		$this->method_description = $paymentDescription ?? 'Accept payments online using Square. Credit card, Apple Pay, and Google Pay supported';
		$this->supports = ['products'];

		$this->init_form_fields();
		$this->init_settings();

		$this->title = $this->get_option('title');
		$this->description = $this->get_option('description');
		$this->enabled = $this->get_option('enabled');
		$this->location_id = $settings['location'];

		$this->square_application_id = isset($settings['environment']) && $settings['environment'] == 'sandbox'
			? $this->get_option('square_application_id_sandbox')
			: $this->get_option('square_application_id_live');
		$this->square_op_mode = $settings['environment'] ?? 'live';

		$total_label_suffix       = apply_filters('woocommerce_square_payment_request_total_label_suffix', __('via WooCommerce', 'woocommerce-square'));
		$this->total_label_suffix = $total_label_suffix ? " ($total_label_suffix)" : '';

		add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
		add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
		add_filter('woocommerce_checkout_fields', [$this, 'remove_credit_card_fields']);
		// Hook for saving the options
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);

		add_action('wp_ajax_squaresync_credit_card_get_token_by_id', array($this, 'get_token_by_id'));

		add_action('wp_ajax_nopriv_squaresync_credit_card_get_token_by_id', array($this, 'get_token_by_id'));

		add_action('wc_ajax_square_digital_wallet_get_payment_request', array($this, 'ajax_get_payment_request'));
		add_action('wc_ajax__nopriv_square_digital_wallet_get_payment_request', array($this, 'ajax_get_payment_request'));

		add_action('wp_ajax_get_payment_request', array($this, 'ajax_get_payment_request'));
		add_action('wp_ajax_nopriv_get_payment_request', array($this, 'ajax_get_payment_request'));



		add_action('wc_ajax_square_digital_wallet_recalculate_totals', array($this, 'ajax_recalculate_totals'));

		add_action('wp_ajax_nopriv_get_needs_shipping', array($this, 'get_needs_shipping'));
		add_action('wp_ajax_get_needs_shipping', array($this, 'get_needs_shipping'));
	}

	public function process_admin_options()
	{
		// Call the parent method to process and save the options
		parent::process_admin_options();

		// Now check if square_mode has changed
		$new_square_mode = $this->get_option('square_mode');
		$current_square_mode = get_option('woocommerce_squaresync_credit_square_mode');

		// Compare the new value with the current value
		if ($new_square_mode !== $current_square_mode) {
			// Update the square_mode setting in your custom option
			update_option('woocommerce_squaresync_credit_square_mode', $new_square_mode);

			// Optionally update other settings, such as Square environment
			$settings = get_option('square-woo-sync_settings', []);
			$settings['environment'] = $new_square_mode;
			update_option('square-woo-sync_settings', $settings);
		}
		// Get the new value from the submitted settings form
		$new_square_mode = isset($_POST['woocommerce_squaresync_credit_square_mode']) ? sanitize_text_field($_POST['woocommerce_squaresync_credit_square_mode']) : '';

		// Get the current value from the gateway settings
		$current_square_mode = $this->get_option('square_mode');

		// Compare the new value with the current value
		if ($new_square_mode && $new_square_mode !== $current_square_mode) {
			// Update the square_mode setting
			$this->update_option('square_mode', $new_square_mode);

			// Update other relevant settings if necessary
			$settings = get_option('square-woo-sync_settings', []);
			$settings['environment'] = $new_square_mode;
			update_option('square-woo-sync_settings', $settings);

			// Set the new operation mode
			$this->square_op_mode = $new_square_mode;
		}
	}

	public function get_needs_shipping()
	{
		// Check if WooCommerce cart is available
		if (WC()->cart) {
			$needs_shipping = WC()->cart->needs_shipping();
			wp_send_json_success(array('needs_shipping' => $needs_shipping));
		} else {
			wp_send_json_error('Cart not found');
		}
	}

	public function remove_credit_card_fields($fields)
	{
		unset($fields['billing']['billing_credit_card_number'], $fields['billing']['billing_credit_card_expiry'], $fields['billing']['billing_credit_card_cvc']);
		return $fields;
	}

	public function init_form_fields()
	{
		$settings = get_option('square-woo-sync_settings', []);

		$this->form_fields = [
			'enabled' => [
				'title' => 'Enable/Disable',
				'type' => 'checkbox',
				'label' => 'Enable this payment method',
				'default' => 'yes',
			],
			'title' => [
				'title' => 'Title',
				'type' => 'text',
				'description' => 'Title that the user sees during checkout.',
				'default' => 'Credit Card',
				'desc_tip' => true,
			],
			'description' => [
				'title' => 'Description',
				'type' => 'textarea',
				'description' => 'Description that the user sees during checkout.',
				'default' => 'Pay securely using your credit card.',
			],

			'accepted_credit_cards' => [
				'title'       => 'Accepted Credit Cards',
				'type'        => 'multiselect',
				'options'     => [
					'visa'       => 'Visa',
					'mastercard' => 'MasterCard',
					'amex'       => 'American Express',
					'discover'   => 'Discover',
					'jcb'        => 'JCB',
					'diners'     => 'Diners Club',
					'union'     => 'UnionPay',
				],
				'default'     => ['visa', 'mastercard', 'amex', 'jcb', 'diners', 'union'],
				'description' => 'Hold control and click to select multiple',

			],

			'square_mode' => [
				'title'       => 'Environment Mode',
				'type'        => 'select',
				'description' => 'Choose the environment mode. You must also change your Square access token and location via SquareSync plugin settings <a href="/wp-admin/admin.php?page=squarewoosync-pro#/settings/general">here</a>',
				'default'     =>  $settings['environment'] ?? 'live',
				'options'     => ['sandbox' => 'Sandbox', 'live' => 'Live'],
			],

			'square_api_settings_sandbox' => [
				'title' => '<legend><h2>Sandbox Settings</h2></legend>',
				'type'  => 'title',
			],
			'square_application_id_sandbox' => [
				'title' => 'Square Sandbox Application ID',
				'type' => 'text',
				'description' => 'Enter your Square Sandbox Application ID.',
				'default' => '',
			],
			'square_api_settings_production' => [
				'title' => '<legend><h2>Production Settings</h2></legend>',
				'type'  => 'title',
			],
			'square_application_id_live' => [
				'title' => 'Square Production Application ID',
				'type' => 'text',
				'description' => 'Enter your Square Production Application ID.',
				'default' => '',
			],

			// Digital Wallets Section
			'digital_wallets' => [
				'title'       => '<legend><h2>Digital Wallets</h2></legend>',
				'type'        => 'title',
			],
			'enable_google_pay' => [
				'title'       => 'Enable Google Pay',
				'type'        => 'checkbox',
				'label'       => 'Enable Google Pay as a payment method',
				'default'     => 'no',
			],
			'enable_apple_pay' => [
				'title'       => 'Enable Apple Pay',
				'type'        => 'checkbox',
				'label'       => 'Enable Apple Pay as a payment method',
				'default'     => 'no',
				'description' => 'Apple Pay requires domain authentication. To authorize your domain follow <a href="">this guide.</a>',
			],
		];
	}

	public function enqueue_scripts()
	{

		$is_checkout = is_checkout() || is_cart();

		// bail if not a checkout page or cash app pay is not enabled
		if (!$is_checkout) {
			return;
		}



		if ($this->square_op_mode === 'sandbox') {
			$url = 'https://sandbox.web.squarecdn.com/v1/square.js';
		} else {
			$url = 'https://web.squarecdn.com/v1/square.js';
		}

		wp_enqueue_style('squaresync-payments-sdk-css', SQUAREWOOSYNC_URL . '/assets/styles/checkout.css', array(), SQUAREWOOSYNC_VERSION, true);

		wp_enqueue_script('squaresync-payments-sdk', $url, array(), SQUAREWOOSYNC_VERSION, true);

		if (!has_block('woocommerce/checkout')) {

			wp_register_script('utils-js', SQUAREWOOSYNC_URL . '/assets/js/utils.js', array('jquery'), null, true);
			wp_register_script('credit-card-js', SQUAREWOOSYNC_URL . '/assets/js/credit-card.js', array('jquery'), null, true);
			wp_register_script('wallets-js', SQUAREWOOSYNC_URL . '/assets/js/wallets.js', array('jquery'), null, true);

			wp_enqueue_script('utils-js');
			wp_enqueue_script('credit-card-js');
			wp_enqueue_script('wallets-js');


			wp_enqueue_script('squaresync-legacy', SQUAREWOOSYNC_URL . '/assets/js/square-gateway.js', null, null, true);

			// Localize script with WooCommerce parameters
			$params = array(
				'applicationId' => $this->square_application_id,
				'locationId' => $this->location_id,
				'applePayEnabled' => $this->get_option('enable_apple_pay'),
				'googlePayEnabled' => $this->get_option('enable_google_pay'),
				'availableCardTypes' => $this->get_option('accepted_credit_cards'),
				'total' => 0,
				'currency' => get_woocommerce_currency(),
				'paymentRequestNonce' => wp_create_nonce('squaresync-get-payment-request'),
				'context' => $this->get_current_page(),
				'countryCode' => 'AUD',
				'ajax_url' => admin_url('admin-ajax.php')
			);

			// Apply localization to the correct script

			wp_localize_script('utils-js', 'SquareConfig', $params);
			wp_localize_script('squaresync-legacy', 'SquareConfig', $params);
		}
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

	/**
	 * Recalculate shipping methods and cart totals and send the updated information
	 * data as a square payment request json object.
	 *
	 * @since 2.3
	 * @return void
	 */
	public function ajax_recalculate_totals()
	{
		check_ajax_referer('squaresync-recalculate-totals', 'security');


		if (!WC()->cart) {
			wp_send_json_error(__('Cart not available.', 'woocommerce-square'));
			return;
		}

		$chosen_methods   = WC()->session->get('chosen_shipping_methods');
		$shipping_address = array();
		$payment_request  = array();

		$is_pay_for_order_page = isset($_POST['is_pay_for_order_page']) ? 'true' === sanitize_text_field(wp_unslash($_POST['is_pay_for_order_page'])) : is_wc_endpoint_url('order-pay');
		$order_id              = isset($_POST['order_id']) ? (int) sanitize_text_field(wp_unslash($_POST['order_id'])) : absint(get_query_var('order-pay'));
		$order_data            = array();

		if (WC()->cart->needs_shipping() || $is_pay_for_order_page) {
			if (! empty($_POST['shipping_contact'])) {
				$shipping_address = wp_parse_args(
					wc_clean(wp_unslash($_POST['shipping_contact'])),
					array(
						'countryCode' => null,
						'state'       => null,
						'city'        => null,
						'postalCode'  => null,
						'address'     => null,
						'address_2'   => null,
					)
				);

				/**
				 * WooCommerce requires state code but for few countries, Google Pay
				 * returns the state's full name instead of the state code.
				 *
				 * The following line converts state name to code.
				 */
				if (isset($shipping_address['countryCode']) && isset($shipping_address['state'])) {
					$shipping_address['state'] = self::get_state_code_by_name($shipping_address['countryCode'], $shipping_address['state']);
				}

				$this->calculate_shipping($shipping_address);

				$packages = WC()->shipping->get_packages();
				$packages = array_values($packages); /// reindex the array.

				if (! empty($packages)) {
					foreach ($packages[0]['rates'] as $method) {
						$payment_request['shippingOptions'][] = array(
							'id'     => $method->id,
							'label'  => $method->get_label(),
							'amount' => number_format($method->cost, 2, '.', ''),
						);
					}
				}

				// sort the shippingOptions so that the default/chosen shipping method is the first option so that it's displayed first in the Apple Pay/Google Pay window
				if (isset($payment_request['shippingOptions'][0])) {
					if (isset($chosen_methods[0])) {
						$chosen_method_id         = $chosen_methods[0];
						$compare_shipping_options = function ($a, $b) use ($chosen_method_id) {
							if ($a['id'] === $chosen_method_id) {
								return -1;
							}

							if ($b['id'] === $chosen_method_id) {
								return 1;
							}

							return 0;
						};

						usort($payment_request['shippingOptions'], $compare_shipping_options);
					}

					$first_shipping_method_id = $payment_request['shippingOptions'][0]['id'];
					$this->update_shipping_method(array($first_shipping_method_id));
				}
			} elseif (! empty($_POST['shipping_option'])) {
				$chosen_methods = array(wc_clean(wp_unslash($_POST['shipping_option'])));
				$this->update_shipping_method($chosen_methods);
			}
		}

		if (!$is_pay_for_order_page) {
			WC()->cart->calculate_totals();
		}

		if ($is_pay_for_order_page) {
			$order      = wc_get_order($order_id);
			$order_data = array(
				'subtotal' => $order->get_subtotal(),
				'discount' => $order->get_discount_total(),
				'shipping' => $order->get_shipping_total(),
				'fees'     => $order->get_total_fees(),
				'taxes'    => $order->get_total_tax(),
			);
		}

		$payment_request['lineItems'] = $this->build_payment_request_line_items($order_data);


		if ($is_pay_for_order_page) {
			$total_amount = $order->get_total();
		} else {
			$total_amount = WC()->cart->total;
		}

		$payment_request['total'] = array(
			'label'   => get_bloginfo('name', 'display') . esc_html($this->total_label_suffix),
			'amount'  => number_format($total_amount, 2, '.', ''),
			'pending' => false,
		);

		wp_send_json_success($payment_request);
	}

	/**
	 * Returns location's state code by state name.
	 *
	 * @param string $country_code The country's 2 letter ISO 3166-1 alpha-2 code.
	 * @param string $state_name   The full name of the state that is to be search for its code.
	 *
	 * @return string
	 */
	public static function get_state_code_by_name($country_code = '', $state_name = '')
	{
		if (empty($country_code) || empty($state_name)) {
			return '';
		}

		$states = WC()->countries->get_states($country_code);

		/**
		 * Check for valid country code that don't have list of states,
		 * return state code as it is.
		 */
		$countries = WC()->countries->get_countries();

		if (false === $states && isset($countries[$country_code])) {
			return $state_name;
		}

		if (is_array($states)) {
			/** Return the state code if $state_name already contains a valid state code. */
			if (isset($states[$state_name])) {
				return $state_name;
			}

			foreach ($states as $code => $name) {
				if ($name === $state_name) {
					return $code;
				}
			}
		}

		return '';
	}

	/**
	 * Reset shipping and calculate the latest shipping options/package with the given address.
	 *
	 * If no address, use the store's base address as default.
	 *
	 * @since 2.3
	 * @param array $address
	 * @return void
	 */
	public function calculate_shipping($address = array())
	{
		if (!WC()->cart) {
			throw new \Exception('Cart not available.');
		}

		WC()->shipping->reset_shipping();

		if ($address['countryCode']) {
			WC()->customer->set_location(strtoupper($address['countryCode']), $address['state'], $address['postalCode'], $address['city']);
			WC()->customer->set_shipping_location(strtoupper($address['countryCode']), $address['state'], $address['postalCode'], $address['city']);
		} else {
			WC()->customer->set_billing_address_to_base();
			WC()->customer->set_shipping_address_to_base();
		}

		WC()->customer->set_calculated_shipping(true);
		WC()->customer->save();

		$packages                                = array();
		$packages[0]['contents']                 = WC()->cart->get_cart();
		$packages[0]['contents_cost']            = 0;
		$packages[0]['applied_coupons']          = WC()->cart->applied_coupons;
		$packages[0]['user']['ID']               = get_current_user_id();
		$packages[0]['destination']['country']   = $address['countryCode'];
		$packages[0]['destination']['state']     = $address['state'];
		$packages[0]['destination']['postcode']  = $address['postalCode'];
		$packages[0]['destination']['city']      = $address['city'];
		$packages[0]['destination']['address']   = $address['address'];
		$packages[0]['destination']['address_2'] = $address['address_2'];

		foreach (WC()->cart->get_cart() as $item) {
			if ($item['data']->needs_shipping()) {
				if (isset($item['line_total'])) {
					$packages[0]['contents_cost'] += $item['line_total'];
				}
			}
		}

		/**
		 * Hook to filter shipping packages.
		 *
		 * @param array Array of shipping packages.
		 * @since 2.3
		 */
		$packages = apply_filters('woocommerce_cart_shipping_packages', $packages);

		WC()->shipping->calculate_shipping($packages);
	}

	/**
	 * Updates shipping method in WC session
	 *
	 * @since 2.3
	 * @param array $shipping_methods Array of selected shipping methods ids
	 * @return void
	 */
	public function update_shipping_method($shipping_methods)
	{
		$chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');

		if (is_array($shipping_methods)) {
			foreach ($shipping_methods as $i => $value) {
				$chosen_shipping_methods[$i] = wc_clean($value);
			}
		}

		WC()->session->set('chosen_shipping_methods', $chosen_shipping_methods);
	}

	/**
	 * Get the payment request object in an ajax request
	 *
	 * @since 2.3
	 * @return void
	 */
	public function ajax_get_payment_request()
	{
		check_ajax_referer('squaresync-get-payment-request', 'security');

		$payment_request = array();
		$context         = ! empty($_POST['context']) ? wc_clean(wp_unslash($_POST['context'])) : '';

		try {
			if ('product' === $context) {
				$product_id = ! empty($_POST['product_id']) ? wc_clean(wp_unslash($_POST['product_id'])) : 0;
				$quantity   = ! empty($_POST['quantity']) ? wc_clean(wp_unslash($_POST['quantity'])) : 1;
				$attributes = ! empty($_POST['attributes']) ? wc_clean(wp_unslash($_POST['attributes'])) : array();

				try {
					$payment_request = $this->get_product_payment_request($product_id, $quantity, $attributes);
				} catch (\Exception $e) {
					wp_send_json_error($e->getMessage());
				}
			} else {
				$payment_request = $this->get_payment_request_for_context($context);
			}

			if (empty($payment_request)) {
				/* translators: Context (product, cart, checkout or page) */
				throw new \Exception(sprintf(esc_html__('Empty payment request data for %s.', 'woocommerce-square'), ! empty($context) ? $context : 'page'));
			}
		} catch (\Exception $e) {
			wp_send_json_error($e->getMessage());
		}

		wp_send_json_success(wp_json_encode($payment_request));
	}

	/**
	 * Build the payment request object for the given context (i.e. product, cart or checkout page)
	 *
	 * Payment request objects are used by the Payments and need to be in a specific format.
	 * Reference: https://developer.squareup.com/docs/api/paymentform#paymentform-paymentrequestobjects
	 *
	 * @since 2.3
	 * @param string $context
	 * @return array
	 */
	public function get_payment_request_for_context($context)
	{
		// Ignoring nonce verification checks as it is already handled in the parent function.
		$payment_request       = array();
		$is_pay_for_order_page = isset($_POST['is_pay_for_order_page']) ? 'true' === sanitize_text_field(wp_unslash($_POST['is_pay_for_order_page'])) : is_wc_endpoint_url('order-pay'); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$order_id              = isset($_POST['order_id']) ? (int) sanitize_text_field(wp_unslash($_POST['order_id'])) : absint(get_query_var('order-pay')); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		switch ($context) {
			case 'product':
				try {
					$payment_request = $this->get_product_payment_request(get_the_ID());
				} catch (\Exception $e) {
					error_log($e);
				}
				break;

			case 'cart':
			case 'checkout':
				if (is_wc_endpoint_url('order-pay') || $is_pay_for_order_page) {
					$order           = wc_get_order($order_id);
					$payment_request = $this->build_payment_request(
						$order->get_total(),
						array(
							'order_id'              => $order_id,
							'is_pay_for_order_page' => $is_pay_for_order_page,
						)
					);
				} elseif (isset(WC()->cart) && $this->allowed_for_cart()) {
					WC()->cart->calculate_totals();
					$payment_request = $this->build_payment_request(WC()->cart->total);
				}

				break;
		}

		return $payment_request;
	}

	/**
	 * Checks the cart to see if Square Digital Wallets is allowed to purchase all cart items.
	 *
	 * @since 2.3
	 * @return bool
	 */
	public function allowed_for_cart()
	{
		if (!WC()->cart) {
			throw new \Exception('Cart not available.');
		}
		foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
			/**
			 * Hook to filter cart item product.
			 *
			 * @param array  $cart_item['data] Product object.
			 * @param array  $cart_item        Cart item.
			 * @param string $cart_item_key    Cart item key.
			 * @since 2.3
			 */
			$_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);

			if (! in_array($_product->get_type(), $this->supported_product_types(), true)) {
				return false;
			}

			// Trial subscriptions with shipping are not supported
			if (class_exists('WC_Subscriptions_Cart') && class_exists('WC_Subscriptions_Product') && \WC_Subscriptions_Cart::cart_contains_subscription() && $_product->needs_shipping() && \WC_Subscriptions_Product::get_trial_length($_product) > 0) {
				return false;
			}

			// Pre Orders compatibility where we don't support charge upon release.
			if (class_exists('WC_Pre_Orders_Cart') && class_exists('WC_Pre_Orders_Product') && \WC_Pre_Orders_Cart::cart_contains_pre_order() && \WC_Pre_Orders_Product::product_is_charged_upon_release(\WC_Pre_Orders_Cart::get_pre_order_product())) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Returns a list the supported product types that can be used to purchase a digital wallet
	 *
	 * @since 2.3
	 * @return array
	 */
	public function supported_product_types()
	{
		/**
		 * Hook to filter array of post types that can support digital wallets.
		 *
		 * @param array Array of supported post types.
		 * @since 2.3
		 */
		return apply_filters(
			'wc_square_digital_wallets_supported_product_types',
			array(
				'simple',
				'variable',
				'variation',
				'booking',
				'bundle',
				'composite',
				'mix-and-match',
			)
		);
	}

	/**
	 * Build a payment request object to be sent to Payments on the product page
	 *
	 * Documentation: https://developer.squareup.com/docs/api/paymentform#paymentform-paymentrequestobjects
	 *
	 * @since 2.3
	 * @param int $product_id
	 * @param bool $add_to_cart - whether or not the product needs to be added to the cart before building the payment request
	 * @return array
	 */
	public function get_product_payment_request($product_id = 0, $quantity = 1, $attributes = array(), $add_to_cart = false)
	{
		$data         = array();
		$items        = array();
		$product_id   = ! empty($product_id) ? $product_id : get_the_ID();
		$product      = wc_get_product($product_id);
		$variation_id = 0;

		if (! is_a($product, 'WC_Product')) {
			/* translators: product ID */
			throw new \Exception(sprintf(esc_html__('Product with the ID (%d) cannot be found.', 'woocommerce-square'), absint($product_id)));
		}

		$quantity = $product->is_sold_individually() ? 1 : $quantity;

		if ('variable' === $product->get_type() && ! empty($attributes)) {
			$data_store   = \WC_Data_Store::load('product');
			$variation_id = $data_store->find_matching_product_variation($product, $attributes);

			if (! empty($variation_id)) {
				$product = wc_get_product($variation_id);
			}
		}

		if (! $product->has_enough_stock($quantity)) {
			/* translators: 1: product name 2: quantity in stock */
			throw new \Exception(sprintf(esc_html__('You cannot add that amount of "%1$s"; to the cart because there is not enough stock (%2$s remaining).', 'woocommerce-square'), esc_html($product->get_name()), esc_html(wc_format_stock_quantity_for_display($product->get_stock_quantity(), $product))));
		}

		if (! $product->is_purchasable()) {
			/* translators: 1: product name */
			throw new \Exception(sprintf(esc_html__('You cannot purchase "%1$s" because it is currently not available.', 'woocommerce-square'), esc_html($product->get_name())));
		}

		if ($add_to_cart) {
			WC()->cart->empty_cart();
			WC()->cart->add_to_cart($product->get_id(), $quantity, $variation_id, $attributes);

			WC()->cart->calculate_totals();
			return $this->build_payment_request(WC()->cart->total);
		}

		$amount         = number_format($quantity * $product->get_price(), 2, '.', '');
		$quantity_label = 1 < $quantity ? ' x ' . $quantity : '';

		$items[] = array(
			'label'   => $product->get_name() . $quantity_label,
			'amount'  => $amount,
			'pending' => false,
		);

		if (wc_tax_enabled()) {
			$items[] = array(
				'label'   => __('Tax', 'woocommerce-square'),
				'amount'  => '0.00',
				'pending' => false,
			);
		}

		$data['requestShippingContact'] = $product->needs_shipping();
		$data['lineItems']              = $items;

		return $this->build_payment_request($amount, $data);
	}

	/**
	 * Build a payment request object to be sent to Payments.
	 *
	 * Documentation: https://developer.squareup.com/docs/api/paymentform#paymentform-paymentrequestobjects
	 *
	 * @since 2.3
	 * @param string $amount - format '100.00'
	 * @param array $data
	 * @return array
	 */
	public function build_payment_request($amount, $data = array())
	{
		if (!WC()->cart) {
			throw new \Exception('Cart not available.');
		}

		$is_pay_for_order_page = isset($data['is_pay_for_order_page']) ? $data['is_pay_for_order_page'] : false;
		$order_id = isset($data['order_id']) ? $data['order_id'] : 0;

		if ($is_pay_for_order_page) {
			$request_shipping_contact = false;
		} else {
			$request_shipping_contact = isset(WC()->cart) && WC()->cart->needs_shipping();
		}

		$order_data = array();
		$data = wp_parse_args(
			$data,
			array(
				'requestShippingContact' => $request_shipping_contact,
				'requestEmailAddress'    => true,
				'requestBillingContact'  => true,
				'countryCode'            => substr(get_option('woocommerce_default_country'), 0, 2),
				'currencyCode'           => get_woocommerce_currency(),
			)
		);

		if ($is_pay_for_order_page) {
			$order = wc_get_order($order_id);
			$order_data = array(
				'subtotal' => $order->get_subtotal(),
				'discount' => $order->get_discount_total(),
				'shipping' => $order->get_shipping_total(),
				'fees'     => $order->get_total_fees(),
				'taxes'    => $order->get_total_tax(),
			);

			unset($data['is_pay_for_order_page'], $data['order_id']);
		}

		// Retrieve shipping method from WooCommerce session
		if (true === $data['requestShippingContact']) {
			$chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
			$shipping_packages = WC()->shipping()->get_packages();

			$shippingOptions = array();

			if (!empty($shipping_packages)) {
				foreach ($shipping_packages[0]['rates'] as $method) {
					$shippingOptions[] = array(
						'id'     => $method->id,
						'label'  => $method->get_label(),
						'amount' => number_format($method->cost, 2, '.', ''),
					);
				}
			}

			// Sort shipping options to make sure the chosen method is at the top
			if (!empty($chosen_shipping_methods[0])) {
				usort($shippingOptions, function ($a, $b) use ($chosen_shipping_methods) {
					return $a['id'] === $chosen_shipping_methods[0] ? -1 : 1;
				});
			}

			// Assign the sorted shipping options
			$data['shippingOptions'] = $shippingOptions;
		}

		$data['total'] = array(
			'label'   => get_bloginfo('name', 'display') . esc_html($this->total_label_suffix),
			'amount'  => number_format($amount, 2, '.', ''),
			'pending' => false,
		);

		// Ensure line items are included in the payment request
		$data['lineItems'] = $this->build_payment_request_line_items($order_data);


		return $data;
	}


	/**
	 * Returns cart totals in an array format
	 *
	 * @since 2.3
	 * @throws \Exception if no cart is found
	 * @return array
	 */
	public function get_cart_totals()
	{
		if (! isset(WC()->cart)) {
			throw new \Exception('Cart data cannot be found.');
		}

		return array(
			'subtotal' => WC()->cart->subtotal_ex_tax,
			'discount' => WC()->cart->get_cart_discount_total(),
			'shipping' => WC()->cart->shipping_total,
			'fees'     => WC()->cart->fee_total,
			'taxes'    => WC()->cart->tax_total + WC()->cart->shipping_tax_total,
		);
	}

	public function build_payment_request_line_items($totals = array())
	{
		// If no totals are provided, get them from the cart.
		$totals     = empty($totals) ? $this->get_cart_totals() : $totals;
		$line_items = array();
		$order_id   = isset($_POST['order_id']) ? (int) sanitize_text_field(wp_unslash($_POST['order_id'])) : absint(get_query_var('order-pay')); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		// Determine whether we are working with an order or the current cart
		if ($order_id) {
			$order    = wc_get_order($order_id);
			$iterable = $order->get_items();
		} else {
			$iterable = WC()->cart->get_cart();
		}

		// Iterate through the items and build the line items array
		foreach ($iterable as $item) {
			$quantity = $order_id ? $item->get_quantity() : $item['quantity'];
			$amount = $order_id ? $item->get_total() : $item['line_total'];

			// Add a line item for each product
			$line_items[] = array(
				'label'   => $order_id ? $item->get_name() : $item['data']->get_name(),
				'amount'  => number_format($amount, 2, '.', ''),
				'pending' => false,
			);
		}

		// Add shipping line item if applicable
		if (isset($totals['shipping']) && $totals['shipping'] > 0) {
			$line_items[] = array(
				'label'   => __('Shipping', 'woocommerce-square'),
				'amount'  => number_format($totals['shipping'], 2, '.', ''),
				'pending' => false,
			);
		}

		// Add tax line item if applicable
		if (isset($totals['taxes']) && $totals['taxes'] > 0) {
			$line_items[] = array(
				'label'   => __('Tax', 'woocommerce-square'),
				'amount'  => number_format($totals['taxes'], 2, '.', ''),
				'pending' => false,
			);
		}

		// Add discount line item if applicable
		if (isset($totals['discount']) && $totals['discount'] > 0) {
			$line_items[] = array(
				'label'   => __('Discount', 'woocommerce-square'),
				'amount'  => '-' . number_format(abs($totals['discount']), 2, '.', ''), // Ensure discount is negative
				'pending' => false,
			);
		}

		// Add fees line item if applicable
		if (isset($totals['fees']) && $totals['fees'] > 0) {
			$line_items[] = array(
				'label'   => __('Fees', 'woocommerce-square'),
				'amount'  => number_format($totals['fees'], 2, '.', ''),
				'pending' => false,
			);
		}

		return $line_items;
	}

	/**
	 * Ajax callback to return payment token by token ID.
	 *
	 * @since 4.2.0
	 */
	public function get_token_by_id()
	{
		$nonce = isset($_GET['nonce']) ? sanitize_text_field(wp_unslash($_GET['nonce'])) : false;

		if (! wp_verify_nonce($nonce, 'payment_token_nonce')) {
			wp_send_json_error(esc_html__('Nonce verification failed.', 'woocommerce-square'));
		}

		$token_id = isset($_GET['token_id']) ? absint(wp_unslash($_GET['token_id'])) : false;

		if (! $token_id) {
			wp_send_json_error(esc_html__('Token ID missing.', 'woocommerce-square'));
		}

		$token_obj = \WC_Payment_Tokens::get($token_id);

		if (is_null($token_obj)) {
			wp_send_json_error(esc_html__('No payment token exists for this ID.', 'woocommerce-square'));
		}

		wp_send_json_success($token_obj->get_token());
	}

	public function payment_fields()
	{
?>
		<form id="payment-form">
			<div class="wallets-container" style="display: flex; column-gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap;">
				<div id="google-pay-button"></div>
				<div id="apple-pay-button" class="apple-pay-button squaresync-wallet-buttons" role="button" style="height: 40px; width: 240px; display: none;">
					<span class="text"></span>
					<span class="logo"></span>
				</div>
			</div>

			<div id="card-container"></div>
		</form>
		<div id="payment-loader" style="display: none;">
			<div class="loader">Verifying, please wait...</div>
		</div>
		<div style="color: red;" id="payment-status-container"></div>
<?php
	}

	public function process_payment($order_id)
	{
		try {
			$order = wc_get_order($order_id);
			$settings = get_option('square-woo-sync_settings', []);
	
			// Sanitize and validate token
			$token = sanitize_text_field($_POST['wc-squaresync_credit-payment-nonce'] ?? $_POST['square_payment_token'] ?? '');
	
			if (empty($token)) {
				return $this->handle_error($order_id, 'Payment token is missing.');
			}
	
			$squareHelper = new SquareHelper();
			$ordersController = new OrdersController();
			$total_amount = intval(round($order->get_total() * 100));
			$currency = $order->get_currency();
	
			// Retrieve or create the Square customer ID, log warning if missing but continue
			$square_customer_id = $ordersController->getOrCreateSquareCustomer($order, $squareHelper);
			if (!$square_customer_id) {
				error_log("Warning: Square customer ID not available for Order ID: $order_id.");
			}
	
			// Prepare and attempt to create the Square order, log warning if failed but continue
			$square_order_response = $this->attempt_create_square_order($ordersController, $order, $square_customer_id, $squareHelper, $order_id);
			if (!$square_order_response) {
				error_log("Warning: Square order could not be created for Order ID: $order_id.");
			}
	
			$this->check_net_amount_due($square_order_response, $total_amount, $currency, $order_id);
	
			// Prepare payment data and check if 'order_id' is included
			$payment_data = $this->prepare_payment_data($token, $order_id, $total_amount, $currency, $square_customer_id, $settings['location'], $square_order_response);
			$is_order_included = isset($payment_data['order_id']);
	
			// Check for verification token and add to payment data if it exists
			if (!empty($_POST['square_verification_token'])) {
				$verification_token = sanitize_text_field($_POST['square_verification_token']);
				$payment_data['verification_token'] = $verification_token;
			}
	
			// First payment attempt with order ID (if included)
			$payment_response = $squareHelper->square_api_request("/payments", 'POST', $payment_data, null, false);
	
			// Retry without order ID if payment fails and order ID was included
			if (!$this->validate_payment_response($payment_response, $order_id) && $is_order_included) {
				// Remove order_id from payment data and try again
				unset($payment_data['order_id']);
				$payment_response = $squareHelper->square_api_request("/payments", 'POST', $payment_data, null, false);
	
				// If the second attempt also fails, return the error
				if (!$this->validate_payment_response($payment_response, $order_id)) {
					// Extract the error message from the Square response.
					$error_message = 'Square payment failed.';
					if (isset($payment_response['error'])) {
						$error_message = $payment_response['error'];
					}
	
					// Pass the extracted error message to the handle_error method.
					return $this->handle_error($order_id, $error_message);
				}
			} 
			
			if (!$this->validate_payment_response($payment_response, $order_id)) {
				// If no order ID was included initially and payment failed, return the error directly
				$error_message = 'Square payment failed without order ID.';
				if (isset($payment_response['error'])) {
					$error_message = $payment_response['error'];
				}
		
				// Pass the extracted error message to the handle_error method
				return $this->handle_error($order_id, $error_message);
			}
	
			// Finalize the payment and update order status
			$this->finalize_order_payment($order, $payment_response, $square_order_response, $total_amount);
	
			// Clear the cart and return success
			WC()->cart->empty_cart();
	
			unset($token);
			unset($_POST);
	
			return ['result' => 'success', 'redirect' => $this->get_return_url($order)];
		} catch (\Exception $e) {
			return $this->handle_exception($order_id, $e);
		}
	}

	private function check_net_amount_due($square_order_response, $total_amount, $currency, $order_id)
	{
		$net_amount_due = $square_order_response['data']['order']['net_amount_due_money']['amount'] ?? 0;
		$net_currency = $square_order_response['data']['order']['net_amount_due_money']['currency'] ?? '';

		if ($net_amount_due != $total_amount || $net_currency != $currency) {
			error_log("Warning: Net amount due ($net_amount_due $net_currency) does not match the expected amount ($total_amount $currency) for Order ID: $order_id.");
		}
	}

	// Error handling function
	private function handle_error($order_id, $message)
	{
		wc_add_notice(__('Payment error: ', 'squarewoosync-pro') . $message, 'error');
		error_log("Order ID $order_id: $message");
		return ['result' => 'failure', 'redirect' => wc_get_checkout_url()];
	}

	// Attempt to create Square order, return false if unsuccessful but do not halt process
	private function attempt_create_square_order($ordersController, $order, $square_customer_id, $squareHelper, $order_id)
	{
		if (is_array($square_customer_id) || empty($square_customer_id)) return false;

		$order_data = $ordersController->prepareSquareOrderData($order, $square_customer_id);
		$square_order_response = $ordersController->createOrderInSquare($order_data, $squareHelper);

		if (!isset($square_order_response['success']) || $square_order_response['success'] === false) {
			error_log("Square order error for Order ID: $order_id - " . json_encode($square_order_response['errors']));
			return false;
		}
		return $square_order_response;
	}

	// Prepare payment data
	private function prepare_payment_data($token, $order_id, $total_amount, $currency, $square_customer_id, $location_id, $square_order_response)
	{
		$payment_data = [
			'idempotency_key' => wp_generate_uuid4(),
			'source_id' => $token,
			'reference_id' => "Woo Order #$order_id",
			'autocomplete' => true,
			'amount_money' => ['amount' => $total_amount, 'currency' => $currency],
			'location_id' => $location_id,
		];

		// Only add customer ID if it's a valid string (not an array with error)
		if (!is_array($square_customer_id) && !empty($square_customer_id)) {
			$payment_data['customer_id'] = $square_customer_id;
		}

		// Only add order ID if net amount due matches the total amount and currency
		$net_amount_due = $square_order_response['data']['order']['net_amount_due_money']['amount'] ?? 0;
		$net_currency = $square_order_response['data']['order']['net_amount_due_money']['currency'] ?? '';

		if ($net_amount_due == $total_amount && $net_currency == $currency && isset($square_order_response['data']['order']['id'])) {
			$payment_data['order_id'] = $square_order_response['data']['order']['id'];
		} else {
			error_log("Skipping Square order ID in payment data due to amount mismatch for Order ID: $order_id.");
		}

		return $payment_data;
	}

	// Validate payment response
	private function validate_payment_response($payment_response, $order_id)
	{
		if (!isset($payment_response['success']) || $payment_response['success'] === false) {
			error_log("Square payment error for Order ID: $order_id - " . json_encode($payment_response['error']));
			return false;
		}
		return true;
	}

	// Finalize the order payment
	private function finalize_order_payment($order, $payment_response, $square_order_response, $total_amount)
	{
		$square_data = ['order' => $square_order_response, 'payment' => $payment_response];
		$order->update_meta_data('square_data', wp_json_encode($square_data));
		$order->payment_complete($payment_response['data']['payment']['id']);
		$order->add_order_note(sprintf(
			__('Payment of %1$s via Square successfully completed (Square Transaction ID: %2$s)', 'squarewoosync-pro'),
			wc_price($total_amount / 100),
			$payment_response['data']['payment']['id']
		));
	}

	// Handle exceptions
	private function handle_exception($order_id, $exception)
	{
		wc_add_notice(__('Payment error: An unexpected error occurred. Please try again.', 'squarewoosync-pro'), 'error');
		error_log("Payment processing exception for Order ID: $order_id - " . $exception->getMessage());
		return ['result' => 'failure', 'redirect' => wc_get_checkout_url()];
	}


	// public function process_refund($order_id, $amount = null, $reason = '')
	// {
	// 	if (!$amount || $amount <= 0) {
	// 		return new WP_Error('invalid_amount', __('Refund amount must be greater than zero.', 'woocommerce'));
	// 	}

	// 	$order = wc_get_order($order_id);
	// 	$token = $this->square_access_token;
	// 	$squareHelper = new SquareHelper();

	// 	$orderMeta = json_decode(get_post_meta($order_id, '_order_success_object', true), true)['order'];
	// 	$payment_methods = $orderMeta['tenders'];

	// 	$payment_id = null;
	// 	foreach ($payment_methods as $method) {
	// 		if ($method['type'] !== 'SQUARE_GIFT_CARD') {
	// 			$payment_id = $method['id'];
	// 			break;
	// 		}
	// 	}

	// 	if (!$payment_id) {
	// 		return new WP_Error('refund_failed', __('Refund failed: Payment method not found.', 'woocommerce'));
	// 	}

	// 	$refund_data = [
	// 		"idempotency_key" => wp_generate_uuid4(),
	// 		"payment_id" => $payment_id,
	// 		"amount_money" => ['amount' => $amount * 100, 'currency' => $order->get_currency()],
	// 		"reason" => $reason,
	// 	];

	// 	$response = $squareHelper->CurlApi($refund_data, $this->square_url . "/refunds", 'POST', $token);
	// 	if (is_wp_error($response)) {
	// 		return $response;
	// 	}

	// 	$refundResp = json_decode($response[1]);
	// 	if (isset($refundResp->refund->status) && in_array($refundResp->refund->status, ['PENDING', 'COMPLETED'])) {
	// 		$order->add_order_note(sprintf(__('Refunded %1$s - Reason: %2$s', 'woocommerce'), wc_price($amount), $reason));
	// 		return true;
	// 	}

	// 	return new WP_Error('refund_failed', __('Refund failed: Could not complete the refund process.', 'woocommerce'));
	// }
}
