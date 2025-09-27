<?php

return [
    'middleware' => ['web'],
    'prefix' => 'soha-chat',

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
    ],
];
