<?php

namespace Pixeldev\SquareWooSync\Setup;

use Pixeldev\SquareWooSync\Common\Keys;
use Pixeldev\SquareWooSync\Cron\CronManager;

/**
 * Class Installer.
 *
 * Install necessary database tables and options for the plugin.
 */
class Installer
{

    /**
     * Run the installer.
     *
     * @since 0.0.1
     *
     * @return void
     */
    public function run(): void
    {
        // Update the installed version.
        $this->add_version();

        // Create plugin settings
        $this->set_settings();

        // Register and create tables.
        $this->create_tables();

        // Initialize and schedule the cron job.
        $this->init_cron();
    }

    /**
     * Get Plugin Settings
     *
     * @since 0.0.1
     * 
     * @return void
     */
    private function set_settings(): void
    {
        $settings = get_option('square-woo-sync_settings', []);
        if (!$settings) {
            update_option('square-woo-sync_settings', array(
                "location" => "",
                "squareAuto" => array(
                    "isActive" => false,
                    "stock" => true,
                    "sku" => true,
                    "title" => true,
                    "description" => true,
                    "images" => true,
                    "category" => true,
                    "price" => true,
                ),
                "wooAuto" =>  array(
                    "autoCreateProduct" => false,
                    "isActive" => false,
                    "stock" => false,
                    "title" => false,
                    "sku" => false,
                    "description" => false,
                    "images" => false,
                    "category" => false,
                    "price" => false,
                ),
                'orders' => [
                    'enabled' => false,
                    'stage' => 'processing'
                ],
                'cron' => [
                    'enabled' => false,
                    'source' => 'square',
                    'schedule' => 'hourly',
                    'batches' => 30,
                    'data_to_import' => [
                        'stock' => false,
                        'sku' => false,
                        'title' => false,
                        'description' => false,
                        'images' => false,
                        'category' => false,
                        'price' => false
                    ]
                ],
                'customers' => [
                    'isFetching' => 0,
                    'roleMappings' => [],
                    'filters' => ['group' => 0, 'segment' => 0 ],
                    'auto' => [
                        'squareWoo' => [
                            'is_active' =>  false,
                            'first_name'=> false,
                            'last_name'=> false,
                            'phone'=> false,
                            'role'=> false,
                            'address'=> false,
                        ],
                        'wooSquare' => [
                            'is_active' => false,
                            'first_name' => false,
                            'last_name' => false,
                            'phone' => false,
                            'role' => false,
                            'address' => false,
                        ]
                    ]
                ],
                'exportStatus' => 0,
                'exportResults' => null,
                'exportSynced' => 1,
            ));
        }
    }


    /**
     * Create necessary database tables.
     *
     * @since 0.0.1
     *
     * @return void
     */
    private function create_tables(): void
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Table for sync logs
        $sync_logs_table_name = $wpdb->prefix . 'square_woo_sync_logs';
        $safe_sync_logs_table_name = esc_sql($sync_logs_table_name);

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like($safe_sync_logs_table_name))) != $safe_sync_logs_table_name) {
            $sql = "CREATE TABLE IF NOT EXISTS `$safe_sync_logs_table_name` (
            id INT NOT NULL AUTO_INCREMENT,
            timestamp DATETIME NOT NULL,
            log_level VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            context TEXT,
            PRIMARY KEY (id)
        ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        // Table for Square inventory
        $inventory_table_name = $wpdb->prefix . 'square_inventory';
        $safe_inventory_table_name = esc_sql($inventory_table_name);

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like($safe_inventory_table_name))) != $safe_inventory_table_name) {
            $sql = "CREATE TABLE IF NOT EXISTS `$safe_inventory_table_name` (
            id INT NOT NULL AUTO_INCREMENT,
            product_id VARCHAR(255) NOT NULL,
            product_data LONGTEXT NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        $customers_table_name = $wpdb->prefix . 'square_woo_customers';
        $safe_customers_table_name = esc_sql($customers_table_name);
        $charset_collate = $wpdb->get_charset_collate();

        // Check if the table exists
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like($safe_customers_table_name))) === $safe_customers_table_name;

        if (!$table_exists) {
            // Create the table if it does not exist
            $sql = "CREATE TABLE `$customers_table_name` (
                id INT NOT NULL AUTO_INCREMENT,
                first_name VARCHAR(255),
                last_name VARCHAR(255),
                email VARCHAR(100) NOT NULL,
                source VARCHAR(50) NOT NULL,
                group_ids VARCHAR(100) DEFAULT 'N/A',
                group_names VARCHAR(255) DEFAULT 'N/A', -- Add the new column for group names
                role VARCHAR(100) DEFAULT 'N/A',
                status BOOLEAN DEFAULT 0,
                square_customer_id VARCHAR(255),
                PRIMARY KEY (id)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }


    /**
     * Initialize and schedule the cron job.
     *
     * @since 0.0.1
     *
     * @return void
     */
    private function init_cron(): void
    {
        // Assuming your CronManager takes care of checking if the cron job is already scheduled
        $cronManager = new CronManager();
        $cronManager->maybe_schedule_cron();
    }


    /**
     * Update plugin version and installation time options.
     *
     * @since 0.0.1
     *
     * @return void
     */
    public function add_version(): void
    {
        $installed = get_option(Keys::SquareWooSync_INSTALLED);

        if (!$installed) {
            update_option(Keys::SquareWooSync_INSTALLED, time());
        }

        update_option(Keys::SQUAREWOOSYNC_VERSION, SQUAREWOOSYNC_VERSION);
    }
}
