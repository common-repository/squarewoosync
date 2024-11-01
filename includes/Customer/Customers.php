<?php

namespace Pixeldev\SquareWooSync\Customer;

use Pixeldev\SquareWooSync\Logger\Logger;
use Pixeldev\SquareWooSync\Square\SquareHelper;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Customer handling
 *
 * Handles auto customer matching and auto creation
 */
class Customers
{

    /**
     * Constructor for Async_Logger.
     *
     * Initializes the log queue and registers the shutdown action.
     */
    public function __construct()
    {
    }
}
