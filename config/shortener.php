<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Homepage Behavior
    |--------------------------------------------------------------------------
    |
    | Configure how the root URL (/) should behave when accessed.
    |
    */
    'homepage' => [
        // Redirect to admin panel instead of showing landing page
        'redirect_to_admin' => env('HOMEPAGE_REDIRECT_TO_ADMIN', false),

        // Custom redirect URL (takes precedence over redirect_to_admin)
        'redirect_url' => env('HOMEPAGE_REDIRECT_URL'),

        // Custom view to show (default: welcome)
        'view' => env('HOMEPAGE_VIEW', 'welcome'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Not Found Behavior
    |--------------------------------------------------------------------------
    |
    | Configure what happens when a short code doesn't exist.
    |
    */
    'not_found' => [
        // URL to redirect to instead of showing 404
        'redirect_url' => env('NOT_FOUND_REDIRECT_URL'),

        // Whether to track 404 attempts for analytics
        'track_attempts' => env('NOT_FOUND_TRACK_ATTEMPTS', true),

        // Custom 404 view
        'view' => env('NOT_FOUND_VIEW'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Link Processing
    |--------------------------------------------------------------------------
    |
    | Configure how links are processed during creation and access.
    |
    */
    'link_processing' => [
        // Validate SSL certificates on destination URLs
        'validate_ssl' => false,

        // Maximum redirects to follow when checking URLs
        'max_redirects' => 5,

        // Timeout for URL validation (seconds)
        'validation_timeout' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Mobile App Handoff
    |--------------------------------------------------------------------------
    |
    | AppMetrica app-to-app links embed the UFarmer universal link in their
    | path. Short links unwrap supported routes so mobile operating systems can
    | open the exact in-app destination after a cross-domain redirect.
    |
    */
    'app_handoff' => [
        'universal_link_host' => env('APP_HANDOFF_UNIVERSAL_LINK_HOST', 'app.ufarmer.uz'),
        'scheme' => env('APP_HANDOFF_SCHEME', 'ufarmer'),
        'android_package' => env('APP_HANDOFF_ANDROID_PACKAGE', 'uz.ufarmer.app'),
        'routes' => [
            'p' => 'market/product',
            's' => 'shop',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics
    |--------------------------------------------------------------------------
    |
    | Configure analytics and tracking behavior.
    |
    */
    'analytics' => [
        // Whether to track clicks asynchronously
        'async_tracking' => true,

        // Click tracking method for high-traffic scenarios
        // Options: 'queue' (default), 'redis', 'none'
        // - 'queue': Uses Laravel queue (database/redis based on QUEUE_CONNECTION)
        // - 'redis': Direct Redis storage with batch processing (requires Redis)
        // - 'none': Only increment count, skip detailed click tracking
        'click_tracking_method' => env('CLICK_TRACKING_METHOD', 'queue'),

        // Redis settings for 'redis' tracking method
        'redis' => [
            // Redis key prefix for click data
            'prefix' => 'clicks:',

            // Batch size - how many clicks to process at once when triggered
            'batch_size' => env('REDIS_BATCH_SIZE', 500),

            // Trigger threshold - start processing when this many clicks are pending
            'trigger_threshold' => env('REDIS_TRIGGER_THRESHOLD', 100),

            // TTL for Redis click data (seconds)
            'ttl' => 86400, // 24 hours
        ],

        // Custom analytics providers to notify
        'providers' => [
            // 'custom_provider' => App\Analytics\CustomProvider::class,
        ],
    ],
];
