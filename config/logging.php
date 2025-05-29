<?php

/**
 * Logging configuration for the Shoper AI Search application
 */

return [
    // Default log channel to use
    'default' => $_ENV['LOG_CHANNEL'] ?? 'stack',

    // Available log channels
    'channels' => [
        // Stack channel allows using multiple channels at once
        'stack' => [
            'channels' => ['file', 'stderr'],
        ],

        // File channel logs to a specific file
        'file' => [
            'path' => __DIR__ . '/../logs/app.log',
            'level' => $_ENV['LOG_LEVEL'] ?? 'debug',
            'days' => 14, // Keep logs for 14 days
        ],

        // Console output (for development)
        'stderr' => [
            'level' => $_ENV['LOG_LEVEL'] ?? 'debug',
        ],

        // Separate channel for API requests
        'api' => [
            'path' => __DIR__ . '/../logs/api.log',
            'level' => 'info',
            'days' => 7,
        ],

        // AI-specific log channel
        'ai' => [
            'path' => __DIR__ . '/../logs/ai.log',
            'level' => 'info',
            'days' => 30, // Keep AI logs longer for analysis
        ],
    ],
];

