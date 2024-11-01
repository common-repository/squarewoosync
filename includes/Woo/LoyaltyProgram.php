<?php

namespace Pixeldev\SquareWooSync\Woo;

use Pixeldev\SquareWooSync\Square\SquareHelper;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}


/**
 * Class to handle Square Loyalty Program.
 */
class LoyaltyProgram
{
    /**
     * Get Loyalty Accounts
     *
     * @param int $order_id The order ID.
     */
    public function get_loyalty_account($square_client_id)
    {
        $square = new SquareHelper();
        $res = $square->square_api_request('/loyalty/accounts/search', 'POST', array('query' => array('customer_ids' => array($square_client_id))));

        if (isset($res['success']) && $res['success'] === true && isset($res['data']['loyalty_accounts'])) {
            return $res['data']['loyalty_accounts'][0]['id'];
        }
        return null;
    }

    /**
     * Calculate Points
     *
     * @param int $order_id The order ID.
     */
    public function calculate_points($order_id, $program_id)
    {
        $square = new SquareHelper();
        $res = $square->square_api_request('/loyalty/programs/' . $program_id . '/calculate', 'POST', array('order_id' => $order_id));

        if (isset($res['success']) && $res['success'] === true) {
            return $res['data']['points'];
        }

        return 0;
    }

    /**
     * Handle loyal points.
     *
     * @param int $order_id The order ID.
     */
    public function accumulate_loylty_points($order_id, $program_id, $square_client_id)
    {

        // find loyalty account
        $account = $this->get_loyalty_account($square_client_id);
        $points = $this->calculate_points($order_id, $program_id);

        if (isset($account) && $points > 0) {
            $current_settings = get_option('square-woo-sync_settings', []);
            $location = $current_settings['location'];

            $square = new SquareHelper();
            $square->square_api_request('/loyalty/accounts/' . $account . '/accumulate', 'POST', array('accumulate_points' => array('order_id' => $order_id), 'location_id' => $location,  'idempotency_key' => uniqid('sq_', true)));
        }
    }
};
