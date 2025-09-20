<?php

namespace App\Mcp\Servers;

use App\Mcp\Resources\DatabaseSchemaResource;
use App\Mcp\Tools\DatabaseQueryTool;
use Illuminate\Support\Str;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Contracts\Transport;

class SupportChatServer extends Server
{
    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        DatabaseQueryTool::class,
    ];

    /**
     * The resources registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Resource>>
     */
    protected array $resources = [
        DatabaseSchemaResource::class,
    ];

    /**
     * Hydrate server metadata from configuration when instantiated.
     */
    public function __construct(Transport $transport)
    {
        parent::__construct($transport);

        $this->name = config('chat-agent.mcp.name', $this->name);
        $this->version = config('chat-agent.mcp.version', $this->version);
        $this->instructions = $this->renderInstructions();
    }

    public static function systemPrompt(): string
    {
        return static::renderStaticInstructions();
    }

    protected static function renderStaticInstructions(): string
    {
        $instructions = trim((string) config('chat-agent.instructions'));

        if ($instructions === '') {
            return 'Provide clear, data-backed answers using the available tools.';
        }

        return Str::finish($instructions, PHP_EOL.PHP_EOL.'Always confirm the data source and timestamp when sharing numbers.');
    }

    protected function renderInstructions(): string
    {
        return static::renderStaticInstructions();
    }
}
