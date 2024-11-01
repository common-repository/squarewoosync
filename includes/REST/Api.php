<?php

namespace Pixeldev\SquareWooSync\REST;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * API Manager class.
 *
 * All API classes would be registered here.
 *
 * @since 0.3.0
 */
class Api
{

    /**
     * Class dir and class name mapping.
     *
     * @var array
     *
     * @since 0.3.0
     */
    protected $class_map;

    /**
     * Constructor.
     */
    public function __construct()
    {
        if (!class_exists('WP_REST_Server')) {
            return;
        }

        $this->class_map = apply_filters(
            'sws_rest_api_class_map',
            [
                \Pixeldev\SquareWooSync\REST\SettingsController::class,
                \Pixeldev\SquareWooSync\REST\SquareController::class,
                \Pixeldev\SquareWooSync\REST\LogController::class,
                \Pixeldev\SquareWooSync\REST\OrdersController::class,
                \Pixeldev\SquareWooSync\REST\LoyaltyController::class,
                \Pixeldev\SquareWooSync\REST\ProductMatcher::class,
                \Pixeldev\SquareWooSync\REST\CustomersController::class,
            ]
        );

        // Init REST API routes.
        add_action('rest_api_init', array($this, 'register_rest_routes'), 10);
    }

    /**
     * Register REST API routes.
     *
     * @since 0.3.0
     *
     * @return void
     */
    public function register_rest_routes(): void
    {
        foreach ($this->class_map as $controller_class) {
            $controller = new $controller_class();
            $controller->register_routes();
        }
    }
}
