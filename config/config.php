<?php

return [

    'app' => [
        'timezone' => 'Asia/Shanghai',
    ],

    /**
     * Application base URL
     * Set this to your application's base path
     * - For root directory: '/'
     * - For subdirectory: '/subdirectory/'
     */
    'base_url' => '/AIChatforChildren/',

    'auth' => [
        /**
         * Enable or disable email verification for new users.
         *
         * - `true`  → Requires users to verify their email before accessing the system.
         * - `false` → No email verification required.
         *
         * Note: If set to `true`, ensure you have configured the email settings in the `.env` file.
         */
        'require_verification' => false,
    ]

];
