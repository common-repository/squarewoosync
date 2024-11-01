<?php

/**
 * Plugin Name:     Square Sync for Woocommerce
 * Requires Plugins: woocommerce
 * Plugin URI:      https://squaresyncforwoo.com
 * Description:     Easily Sync your WooCommerce Square data in real-time with the SquareSync for Woo. Stock, titles, descriptions, orders and more. 
 * Author:          SquareSync for Woo
 * Author URI:      https://squaresyncforwoo.com
 * Text Domain:     squarewoosync
 * License:         GPLv2 or later
 * License URI:     http://www.gnu.org/licenses/gpl-2.0.html
 * Domain Path:     /languages
 * Version:         5.0.2
 * Requires at least: 5.4
 * Requires PHP:      7.4
 *
 * @package         SquareWooSync
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

final class SquareWooSync
{
    const VERSION = '5.0.2';
    const SLUG = 'squarewoosync';

    private $container = [];

    private function __construct()
    {
        require_once __DIR__ . '/vendor/autoload.php';
        $this->define_constants();

        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        add_action('plugins_loaded', [$this, 'init_plugin']);
        add_action('plugins_loaded', [$this, 'load_textdomain']);
    }

    public static function init()
    {
        static $instance = false;

        if (!$instance) {
            $instance = new SquareWooSync();
        }

        return $instance;
    }

    public function __get($prop)
    {
        if (array_key_exists($prop, $this->container)) {
            return $this->container[$prop];
        }

        return $this->{$prop};
    }

    public function __isset($prop)
    {
        return isset($this->{$prop}) || isset($this->container[$prop]);
    }

    public function define_constants()
    {
        define('SQUAREWOOSYNC_VERSION', self::VERSION);
        define('SQUAREWOOSYNC_SLUG', self::SLUG);
        define('SQUAREWOOSYNC_FILE', __FILE__);
        define('SQUAREWOOSYNC_DIR', __DIR__);
        define('SQUAREWOOSYNC_PATH', dirname(SQUAREWOOSYNC_FILE));
        define('SQUAREWOOSYNC_INCLUDES', SQUAREWOOSYNC_PATH . '/includes');
        define('SQUAREWOOSYNC_TEMPLATE_PATH', SQUAREWOOSYNC_PATH . '/templates');
        define('SQUAREWOOSYNC_URL', plugins_url('', SQUAREWOOSYNC_FILE));
        define('SQUAREWOOSYNC_BUILD', SQUAREWOOSYNC_URL . '/build');
        define('SQUAREWOOSYNC_ASSETS', SQUAREWOOSYNC_URL . '/assets');
    }

    public function init_plugin()
    {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', [$this, 'woocommerce_missing_notice']);
            return;
        }

        $this->includes();
        $this->init_hooks();
        
        add_filter('woocommerce_payment_gateways', [$this, 'add_gateway'], 5);

        add_action( 'woocommerce_blocks_payment_method_type_registration', array( $this, 'register_payment_method_block_integrations' ), 5, 1 );

        do_action('SQUARE_WOO_SYNC_loaded');
    }

    public function load_textdomain()
    {
        load_plugin_textdomain('squarewoosync', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }


    public function activate()
    {
        $this->install();
    }

    public function deactivate()
    {
        $cron_manager = new \Pixeldev\SquareWooSync\Cron\CronManager();
        $cron_manager->stop_cron();
    }

    public function woocommerce_missing_notice()
    {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('SquareWooSync requires WooCommerce to be installed and activated.', 'squarewoosync');
        echo '</p></div>';
    }

    public function remove_woocommerce_notice()
    {
        remove_action('admin_notices', [$this, 'woocommerce_missing_notice']);
    }

    private function install()
    {
        $installer = new \Pixeldev\SquareWooSync\Setup\Installer();
        $installer->run();
    }

    public function includes()
    {
        if ($this->is_request('admin')) {
            $this->container['admin_menu'] = new Pixeldev\SquareWooSync\Admin\Menu();
        }
        $this->container['assets'] = new Pixeldev\SquareWooSync\Assets\Manager();
        $this->container['rest_api'] = new Pixeldev\SquareWooSync\REST\Api();
        $this->container['sync_product'] = new Pixeldev\SquareWooSync\Woo\SyncProduct();
    }

    public function init_hooks()
    {
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'plugin_action_links']);

        $settings = new \Pixeldev\SquareWooSync\REST\SettingsController();
        add_action('export_products_to_square', [$settings, 'handle_export_to_square']);

        $square = new \Pixeldev\SquareWooSync\REST\SquareController();
        add_action('update_square_inventory_cron',  [$square, 'update_square_inventory_function']);
    }

    public function add_gateway($gateways)
    {
        // Initialize the gateway if it's not already set
        if (empty($this->container['gateway'])) {
            $this->container['gateway'] = new \Pixeldev\SquareWooSync\Payments\WC_SquareSync_Gateway(
                'squaresync_credit',
                'Square Payments by SquareSync for Woo',
                'Allow customers to use Square to securely pay with their credit cards, GooglePay, and ApplePay'
            );
        }

        // Add the gateway to WooCommerce gateways if it's not already added
        if (!in_array($this->container['gateway'], $gateways, true)) {
            $gateways[] = $this->container['gateway'];
        }

        return $gateways;
    }

    /**
	 * Register the Square Credit Card checkout block integration class
	 *
	 * @since 2.5.0
	 */
	public function register_payment_method_block_integrations( $payment_method_registry ) {
		if ( class_exists( '\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			$payment_method_registry->register( new \Pixeldev\SquareWooSync\Payments\Blocks\WC_SquareSync_Gateway_Blocks_Support() );
		}
	}

    private function is_request($type)
    {
        switch ($type) {
            case 'admin':
                return is_admin();

            case 'ajax':
                return defined('DOING_AJAX');

            case 'rest':
                return defined('REST_REQUEST');

            case 'cron':
                return defined('DOING_CRON');

            case 'frontend':
                return (!is_admin() || defined('DOING_AJAX')) && !defined('DOING_CRON');
        }
    }

    public function plugin_action_links($links)
    {
        $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=squarewoosync#/settings/general')) . '">' . esc_html__('Settings', 'squarewoosync') . '</a>';
        $documentation_link = '<a href="' . esc_url('https://squaresyncforwoo.com/documentation') . '" target="_blank">' . esc_html__('Documentation', 'squarewoosync') . '</a>';

        $pro_link = '<a href="' . esc_url('https://squaresyncforwoo.com/') . '" target="_blank">' . esc_html__('Go Pro', 'squarewoosync') . '</a>';

        $links[] = $settings_link;
        $links[] = $documentation_link;
        $links[] = $pro_link;

        return $links;
    }

    static function square_woo_sync_uninstall()
    {
        global $wpdb;

        require_once __DIR__ . '/includes/Cron/CronManager.php';

        $cron_manager = new \Pixeldev\SquareWooSync\Cron\CronManager();
        $cron_manager->stop_cron();

        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}square_woo_sync_logs");
        delete_option('square-woo-sync_settings');
        delete_option('square-woo-sync_installed');
        delete_option('square-woo-sync_version');
    }
}

function squarewoosync_init()
{
    return SquareWooSync::init();
}

if (class_exists('SquareWooSync')) {
    squarewoosync_init();
    register_uninstall_hook(__FILE__, 'SquareWooSync::square_woo_sync_uninstall');
}

// Display the custom field in product variation settings
add_action('woocommerce_product_after_variable_attributes', 'squarewoosync_variation_settings_fields', 10, 3);
function squarewoosync_variation_settings_fields($loop, $variation_data, $variation)
{
    $square_product_id = get_post_meta($variation->ID, 'square_product_id', true);

    woocommerce_wp_text_input(
        array(
            'id' => 'square_product_id[' . esc_attr($variation->ID) . ']',
            'label' => esc_html__('Square Product ID', 'squarewoosync'),
            'placeholder' => '',
            'description' => esc_html__('Enter the Square product ID here.', 'squarewoosync'),
            'desc_tip' => true,
            'value' => esc_attr($square_product_id)
        )
    );
}

add_action('woocommerce_save_product_variation', 'squarewoosync_save_variation_settings_fields', 10, 2);
function squarewoosync_save_variation_settings_fields($post_id)
{
    if (isset($_POST['square_product_id'][$post_id])) {
        $square_product_id = sanitize_text_field($_POST['square_product_id'][$post_id]);
        update_post_meta($post_id, 'square_product_id', esc_attr($square_product_id));
    }
}
