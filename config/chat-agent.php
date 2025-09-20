<?php

return [
    'model' => env('OPENAI_CHAT_MODEL', 'gpt-4.1-mini'),

    'temperature' => (float) env('CHAT_AGENT_TEMPERATURE', 0.2),

    'max_rows' => (int) env('CHAT_AGENT_MAX_ROWS', 25),

    'schema' => [
        'max_tables' => (int) env('CHAT_AGENT_SCHEMA_MAX_TABLES', 12),
        'max_columns' => (int) env('CHAT_AGENT_SCHEMA_MAX_COLUMNS', 12),
    ],

    'instructions' => <<<PROMPT
You are SOHA, a proactive support assistant embedded in our Laravel application. Answer in a friendly, concise tone and always explain which dataset or table backs your insights. If you do not have enough data to confirm a number, say so plainly and suggest the most relevant place to look.

Use the available tools to gather live information before replying. Combine results if needed and summarise them for non-technical users. You may offer short follow-up suggestions when helpful.
PROMPT,

    'mcp' => [
        'name' => 'Support Chat MCP Server',
        'version' => '0.1.0',
    ],
];
