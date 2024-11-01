<?php
namespace Pixeldev\SquareWooSync\Logger;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Asynchronous Logger Class
 *
 * Handles logging operations asynchronously by storing log entries in a queue
 * and processing them at the end of the script execution.
 */
class Logger
{
    /**
     * Queue to store log entries.
     *
     * @var SplQueue
     */
    private $log_queue;

    /**
     * Constructor for Async_Logger.
     *
     * Initializes the log queue and registers the shutdown action.
     */
    public function __construct()
    {
        $this->log_queue = new \SplQueue();
        add_action('shutdown', array($this, 'process_log_queue'));
    }

    /**
     * Enqueues a log entry.
     *
     * @param string $level   The severity level of the log.
     * @param string $message The log message.
     * @param array  $context Additional context for the log entry.
     */
    public function log($level, $message, $context = array())
    {
        $this->log_queue->enqueue(array(
            'level'     => $level,
            'message'   => $message,
            'context'   => $context,
            'timestamp' => current_time('mysql')
        ));
    }

    /**
     * Processes the log queue.
     *
     * Writes each log entry in the queue to the database at the end of script execution.
     */
    public function process_log_queue()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'square_woo_sync_logs';

        // Ensure the table exists before processing the log queue
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
            error_log("Table '{$table_name}' does not exist.");
            return; // Exit if table does not exist
        }

        while (!$this->log_queue->isEmpty()) {
            $log_entry = $this->log_queue->dequeue();

            $result = $wpdb->insert(
                $table_name,
                [
                    'timestamp' => $log_entry['timestamp'],
                    'log_level' => $log_entry['level'],
                    'message'   => sanitize_text_field($log_entry['message']),
                    'context'   => maybe_serialize($log_entry['context'])
                ],
                ['%s', '%s', '%s', '%s']
            );

            if ($result === false) {
                error_log("Failed to insert log entry: " . $wpdb->last_error);
            } else {
                $this->maintain_log_limit();
            }
        }
    }

    /**
     * Maintains the log limit by deleting the oldest entry if the limit is exceeded.
     *
     * @param string $table_name The name of the table where logs are stored.
     */
    private function maintain_log_limit(): void
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'square_woo_sync_logs';
        $log_limit = 1000; // Set your desired log limit

        // Count the current number of logs
        $log_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `$table_name`"));

        // Calculate how many rows to delete
        $rows_to_delete = (int) ($log_count - $log_limit);

        // If the number of rows exceeds the limit, delete the oldest entries
        if ($rows_to_delete > 0) {
            $wpdb->query($wpdb->prepare("DELETE FROM `$table_name` ORDER BY timestamp ASC LIMIT %d", $rows_to_delete));
        }
    }

    /**
     * Retrieves log entries from the square_woo_sync_logs table.
     *
     * @param array $args Optional. Arguments to retrieve log entries.
     * @return array|\WP_Error Array of log entries or WP_Error on failure.
     */
    public function get_square_woo_sync_logs($args = [])
    {
        global $wpdb;

        $defaults = [
            'limit'  => 50,
            'offset' => 0,
            'level'  => '',
        ];

        $args = wp_parse_args($args, $defaults);

        $table_name = $wpdb->prefix . 'square_woo_sync_logs';
        $query_base = "SELECT * FROM $table_name";
        $where_clause = "";
        $query_args = [];

        // Assuming log_level is the only condition
        if (!empty($args['level'])) {
            $where_clause = " WHERE log_level = %s";
            $query_args[] = $args['level'];
        }

        // Ensure 'limit' and 'offset' are integers to avoid SQL injection
        $limit = absint($args['limit']);
        $offset = absint($args['offset']);

        // Execute the query
        $results = $wpdb->get_results($wpdb->prepare(
            "$query_base$where_clause LIMIT %d OFFSET %d",
            array_merge($query_args, [$limit, $offset])
        ), ARRAY_A);

        if (null === $results) {
            return new \WP_Error('database_error', esc_html__('Error fetching logs from the database.', 'squarewoosync'));
        }

        return $results;
    }
}
