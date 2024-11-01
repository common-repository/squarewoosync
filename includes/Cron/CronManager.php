<?php

namespace Pixeldev\SquareWooSync\Cron;

use Pixeldev\SquareWooSync\Logger\Logger;
use Pixeldev\SquareWooSync\Square\SquareHelper;
use Pixeldev\SquareWooSync\Square\SquareImport;
use Pixeldev\SquareWooSync\Square\SquareInventory;
use Pixeldev\SquareWooSync\Woo\SyncProduct;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Class CronManager
 * 
 * Manages cron jobs for the Square-Woo Sync Pro plugin.
 */
class CronManager
{
    /**
     * Option name to store plugin settings.
     * 
     * @var string
     */
    private $option_name = 'square-woo-sync_settings';

    /**
     * Cron hook name.
     * 
     * @var string
     */
    private $hook_name = 'square-woo-sync_pro_cron_hook';

    /**
     * Checks and schedules the cron job if not already scheduled.
     */
    public function maybe_schedule_cron()
    {
        $settings = get_option($this->option_name);
        $schedule = isset($settings['cron']['schedule']) ? sanitize_text_field($settings['cron']['schedule']) : 'hourly';

        if (!wp_next_scheduled($this->hook_name) && $settings['cron']['enabled']) {
            $this->start_cron($schedule);
        }
    }

    /**
     * Updates the cron settings and starts or stops the cron job accordingly.
     * 
     * @param array $settings Cron settings.
     */
    public function update_cron($enabled, $frequency)
    {
        if ($enabled) {
            // If cron is enabled, check if the frequency has changed or if the cron job doesn't exist
            $already_exists = wp_next_scheduled($this->hook_name);
            if (!$already_exists || $this->get_cron_frequency() !== $frequency) {
                $this->start_cron($frequency);
            }
        } else {
            // If cron is disabled, stop the cron job
            $this->stop_cron();
        }
    }

    // Helper function to get the current cron frequency
    private function get_cron_frequency()
    {
        $current_settings = get_option('square-woo-sync_settings', []);
        $current_cron_settings = isset($current_settings['cron']) ? $current_settings['cron'] : [];
        return isset($current_cron_settings['schedule']) ? $current_cron_settings['schedule'] : '';
    }

    /**
     * Starts the cron job based on the settings.
     */
    public function start_cron($schedule)
    {
        if (empty($schedule)) {
            return;
        }

        // If the event already exists, unschedule it first
        $timestamp = wp_next_scheduled($this->hook_name);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $this->hook_name);
        }

        // Add a small delay (e.g., 1 second) before scheduling the event
        sleep(1);

        // Schedule the event at the calculated next run time
        wp_schedule_event($this->calculate_next_run_time($schedule), $schedule, $this->hook_name);
    }

    // Helper function to calculate the next run time based on the schedule
    private function calculate_next_run_time($schedule)
    {
        switch ($schedule) {
            case 'hourly':
                // Calculate the timestamp for the start of the next hour
                $next_hour = strtotime('+1 hour', strtotime(date('Y-m-d H:00:00')));
                return $next_hour;
            case 'twicedaily':
                return strtotime('midnight');
            case 'daily':
                return strtotime('midnight'); // Tomorrow at midnight
            case 'weekly':
                return strtotime('next monday midnight'); // Next week
            default:
                return time(); // Default to current time
        }
    }

    /**
     * Stops the cron job if it is scheduled.
     */
    public function stop_cron()
    {
        $timestamp = wp_next_scheduled($this->hook_name);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $this->hook_name);
        }
    }
}
