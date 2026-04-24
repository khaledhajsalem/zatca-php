<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ZATCA Dashboard Enabled
    |--------------------------------------------------------------------------
    |
    | This option determines whether the ZATCA dashboard and its routes
    | will be registered. Set to false to disable the dashboard entirely.
    |
    */
    'enabled' => env('ZATCA_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Dashboard Path
    |--------------------------------------------------------------------------
    |
    | The URL path where the ZATCA dashboard will be accessible.
    | By default, it will be available at /zatca.
    |
    */
    'path' => env('ZATCA_PATH', 'zatca'),

    /*
    |--------------------------------------------------------------------------
    | Dashboard Domain
    |--------------------------------------------------------------------------
    |
    | Optionally restrict the dashboard to a specific domain.
    |
    */
    'domain' => env('ZATCA_DOMAIN', null),

    /*
    |--------------------------------------------------------------------------
    | Dashboard Middleware
    |--------------------------------------------------------------------------
    |
    | The middleware stack applied to the ZATCA dashboard routes.
    |
    */
    'middleware' => ['web', 'auth'],

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | The ZATCA environment to use. Options: sandbox, simulation, production.
    |
    */
    'environment' => env('ZATCA_ENVIRONMENT', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | Default Device
    |--------------------------------------------------------------------------
    |
    | The UUID of the default EGS device to use when none is specified.
    |
    */
    'default_device' => env('ZATCA_DEFAULT_DEVICE', null),

    /*
    |--------------------------------------------------------------------------
    | Seller Information (Defaults)
    |--------------------------------------------------------------------------
    |
    | Default seller information used when creating invoices.
    |
    */
    'seller' => [
        'registration_name'    => env('ZATCA_SELLER_NAME', ''),
        'registration_name_ar' => env('ZATCA_SELLER_NAME_AR', ''),
        'vat_number'           => env('ZATCA_SELLER_VAT', ''),
        'party_id'             => env('ZATCA_SELLER_PARTY_ID', ''),
        'party_id_scheme'      => env('ZATCA_SELLER_PARTY_ID_SCHEME', 'CRN'),
        'street'               => env('ZATCA_SELLER_STREET', ''),
        'building_number'      => env('ZATCA_SELLER_BUILDING', ''),
        'city'                 => env('ZATCA_SELLER_CITY', ''),
        'district'             => env('ZATCA_SELLER_DISTRICT', ''),
        'postal_code'          => env('ZATCA_SELLER_POSTAL', ''),
        'country'              => env('ZATCA_SELLER_COUNTRY', 'SA'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Certificate Storage
    |--------------------------------------------------------------------------
    |
    | How certificates are stored. 'database' uses encrypted columns.
    | 'file' uses the filesystem (legacy compatibility with zatca-php).
    |
    */
    'certificate_storage' => env('ZATCA_CERT_STORAGE', 'database'),

    'certificate_paths' => [
        'certificate' => env('ZATCA_CERT_PATH', ''),
        'private_key' => env('ZATCA_KEY_PATH', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    */
    'api' => [
        'timeout'        => env('ZATCA_API_TIMEOUT', 30),
        'retry_attempts' => env('ZATCA_API_RETRIES', 3),
        'retry_delay_ms' => env('ZATCA_API_RETRY_DELAY', 1000),
        'verify_ssl'     => env('ZATCA_VERIFY_SSL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto Chain Management
    |--------------------------------------------------------------------------
    |
    | When true, ICV and PIH are automatically managed per device.
    |
    */
    'auto_chain' => env('ZATCA_AUTO_CHAIN', true),

    /*
    |--------------------------------------------------------------------------
    | Store Signed XML
    |--------------------------------------------------------------------------
    |
    | Whether to persist the full signed XML in the database.
    | Disable to save storage for high-volume systems.
    |
    */
    'store_xml' => env('ZATCA_STORE_XML', true),

    /*
    |--------------------------------------------------------------------------
    | Pruning
    |--------------------------------------------------------------------------
    |
    | Auto-delete old records. Set to 0 to keep forever.
    |
    */
    'pruning' => [
        'invoices_days' => env('ZATCA_PRUNE_INVOICES', 365),
        'api_logs_days' => env('ZATCA_PRUNE_API_LOGS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        'certificate_expiry_days' => env('ZATCA_CERT_EXPIRY_ALERT', 30),
        'channels'                => ['mail'],
        'recipients'              => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Connection
    |--------------------------------------------------------------------------
    |
    | Optionally use a separate database connection for ZATCA tables.
    |
    */
    'storage' => [
        'database' => [
            'connection' => env('ZATCA_DB_CONNECTION', null),
        ],
    ],
];
