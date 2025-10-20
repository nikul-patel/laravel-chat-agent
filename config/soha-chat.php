<?php

return [
    'middleware' => ['web'],
    'prefix' => 'soha-chat',

    'routes' => [
        'name' => 'soha-chat.',
        'api_prefix' => 'soha-chat',
    ],

    'actors' => [
        'roles' => [
            'guest' => 'guest',
            'authenticated' => 'user',
        ],
        'role_attribute' => 'role',
        'fallback_attribute' => null,
    ],

    'theme' => [
        'preset' => 'system',
        'variables' => [
            '--soha-bg' => '#f2f2f2',
            '--soha-fg' => '#111827',
            '--soha-accent' => '#4f46e5',
            '--soha-radius' => '1rem',
            '--soha-shadow' => '0 10px 30px rgba(15, 23, 42, 0.15)',
        ],
    ],

    'slash_commands' => [
        [
            'name' => 'reset',
            'description' => 'Clear the current conversation history.',
        ],
        [
            'name' => 'schema',
            'description' => 'Describe the available data schema.',
        ],
        [
            'name' => 'help',
            'description' => 'Show available commands and tips.',
        ],
    ],

    'features' => [
        'show_reset' => false,
        'show_theme_toggle' => false,
        'inline_assets' => true,
    ],

    'assets' => [
        'css' => ['vendor/soha-chat/css/chat-widget.css'],
        'js' => ['vendor/soha-chat/js/chat-widget.js'],
    ],
];
