<?php

namespace App\Services;

use App\Mcp\Resources\DatabaseSchemaResource;
use App\Mcp\Servers\SupportChatServer;
use App\Mcp\Tools\DatabaseQueryTool;
use App\Models\ChatMessage;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request as McpRequest;
use OpenAI\Exceptions\ErrorException;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use OpenAI\Responses\Chat\CreateResponseChoice;
use OpenAI\Responses\Chat\CreateResponseMessage;
use OpenAI\Responses\Chat\CreateResponseToolCall;
use Throwable;

class ChatAgentService
{
    protected int $historyLimit = 100;

    /**
     * @var array<string, mixed>
     */
    protected array $actorContext = [];

    public function __construct(
        protected DatabaseQueryTool $databaseQueryTool,
        protected DatabaseSchemaResource $schemaResource,
    ) {}

    /**
     * Generate a reply and persist the exchange.
     *
     * @return array{reply: string, tool_outputs: array<int, array<string, mixed>>, usage: array<string, mixed>|null, history: array<int, array<string, mixed>>}
     */
    public function reply(Request $request, string $message): array
    {
        $this->actorContext = $this->resolveActorContext($request);
        $history = $this->loadPersistedHistory($this->actorContext);

        $conversation = $this->buildConversation($this->actorContext, $history, $message);

        $tools = [$this->formatTool($this->databaseQueryTool)];

        $initialResponse = $this->askOpenAi($conversation, $tools);

        $result = $this->resolveToolCalls($initialResponse, $conversation, $tools);

        $reply = trim($result['message']->content ?? '');
        $usage = $result['response']->usage?->toArray();

        $this->storeExchange(
            $this->actorContext,
            $message,
            $reply,
            $result['tool_outputs'],
            $usage,
        );

        $updatedHistory = $this->loadPersistedHistory($this->actorContext);

        return [
            'reply' => $reply,
            'tool_outputs' => $result['tool_outputs'],
            'usage' => $usage,
            'history' => $this->transformHistory($updatedHistory),
        ];
    }

    /**
     * Return the persisted history for the current actor.
     *
     * @return array<int, array<string, mixed>>
     */
    public function history(Request $request): array
    {
        $this->actorContext = $this->resolveActorContext($request);

        return $this->transformHistory($this->loadPersistedHistory($this->actorContext));
    }

    public function reset(Request $request): void
    {
        $this->actorContext = $this->resolveActorContext($request);

        ChatMessage::query()->forActor($this->actorContext)->delete();
    }

    /**
     * Build the conversation array for OpenAI, including system instructions and prior context.
     */
    protected function buildConversation(array $actor, Collection|EloquentCollection $history, string $message): array
    {
        $conversation = [
            [
                'role' => 'system',
                'content' => $this->buildSystemPrompt($actor),
            ],
        ];

        foreach ($history as $entry) {
            $conversation[] = [
                'role' => $entry->author_role,
                'content' => $entry->content,
            ];
        }

        $conversation[] = [
            'role' => 'user',
            'content' => $message,
        ];

        return $conversation;
    }

    protected function buildSystemPrompt(array $actor): string
    {
        try {
            $schemaSnapshot = (string) $this->schemaResource->handle(new McpRequest)->content();
        } catch (Throwable $exception) {
            Log::warning('Failed to build schema snapshot for chat agent', ['exception' => $exception]);
            $schemaSnapshot = 'Schema snapshot unavailable: '.$exception->getMessage();
        }

        $role = $actor['role'] ?? 'guest';
        $safety = [
            'User role: '.ucfirst($role).'.',
        ];

        if (! empty($actor['user_id'])) {
            $safety[] = 'Authenticated user ID: '.$actor['user_id'].'. Restrict personal data queries to this identifier only.';
        } else {
            $safety[] = 'The user is unauthenticated. Provide only high-level summaries and never expose row-level personal or sales data.';
        }

        $safety[] = 'Never disclose sensitive tables such as users, transactions, or salaries unless explicitly permitted by the role. Only the sales role may reference aggregated sales tables; never leak other customers’ data.';
        $safety[] = 'Use stored conversation history to maintain context but confirm figures against fresh data when accuracy matters.';

        return trim(implode(PHP_EOL.PHP_EOL, array_filter([
            SupportChatServer::systemPrompt(),
            implode(' ', $safety),
            'Current timestamp: '.Date::now()->toDateTimeString().' (application timezone).',
            'Database schema overview:'.PHP_EOL.$schemaSnapshot,
            'When responding, cite the tables or columns used so users can verify the answer.',
        ])));
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     */
    protected function askOpenAi(array $messages, array $tools): CreateResponse
    {
        try {
            return OpenAI::chat()->create([
                'model' => config('chat-agent.model'),
                'temperature' => config('chat-agent.temperature'),
                'messages' => $messages,
                'tools' => $tools,
                'tool_choice' => 'auto',
            ]);
        } catch (ErrorException $exception) {
            Log::error('OpenAI chat request failed', ['exception' => $exception]);
            throw $exception;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $conversation
     * @param  array<int, array<string, mixed>>  $tools
     * @return array{response: CreateResponse, message: CreateResponseMessage, tool_outputs: array<int, array<string, mixed>>}
     */
    protected function resolveToolCalls(CreateResponse $response, array &$conversation, array $tools): array
    {
        $choice = $response->choices[0] ?? null;

        if (! $choice instanceof CreateResponseChoice) {
            throw new \RuntimeException('Unable to read assistant response.');
        }

        $assistantMessage = $choice->message;
        $toolOutputs = [];

        if ($assistantMessage->toolCalls === []) {
            return [
                'response' => $response,
                'message' => $assistantMessage,
                'tool_outputs' => $toolOutputs,
            ];
        }

        $conversation[] = $assistantMessage->toArray();

        foreach ($assistantMessage->toolCalls as $toolCall) {
            $toolOutputs[] = $this->callTool($toolCall);
            $conversation[] = [
                'role' => 'tool',
                'tool_call_id' => $toolCall->id,
                'content' => $toolOutputs[array_key_last($toolOutputs)]['content'] ?? '',
            ];
        }

        $followUp = $this->askOpenAi($conversation, $tools);
        $followUpChoice = $followUp->choices[0] ?? null;

        if (! $followUpChoice instanceof CreateResponseChoice) {
            throw new \RuntimeException('Unable to read assistant response after tool usage.');
        }

        return [
            'response' => $followUp,
            'message' => $followUpChoice->message,
            'tool_outputs' => $toolOutputs,
        ];
    }

    protected function callTool(CreateResponseToolCall $toolCall): array
    {
        $arguments = json_decode($toolCall->function->arguments, true);

        if (! is_array($arguments)) {
            return [
                'content' => 'Failed to decode tool arguments.',
                'is_error' => true,
                'tool_call_id' => $toolCall->id,
            ];
        }

        try {
            $response = $this->databaseQueryTool
                ->forActor($this->actorContext)
                ->handle(new McpRequest($arguments));
        } catch (Throwable $exception) {
            Log::warning('Database query tool execution failed', ['exception' => $exception]);

            return [
                'content' => 'Tool execution error: '.$exception->getMessage(),
                'is_error' => true,
                'tool_call_id' => $toolCall->id,
                'arguments' => $arguments,
            ];
        }

        $content = (string) $response->content();
        $json = json_decode($content, true);

        return [
            'tool_call_id' => $toolCall->id,
            'name' => $toolCall->function->name,
            'arguments' => $arguments,
            'content' => $content,
            'data' => json_last_error() === JSON_ERROR_NONE ? $json : null,
            'is_error' => $response->isError(),
        ];
    }

    protected function formatTool(DatabaseQueryTool $tool): array
    {
        $schema = JsonSchema::object(fn (JsonSchema $schema) => $tool->forActor($this->actorContext)->schema($schema))->toArray();

        return [
            'type' => 'function',
            'function' => [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'parameters' => $schema,
            ],
        ];
    }

    protected function loadPersistedHistory(array $actor): EloquentCollection
    {
        return ChatMessage::forActor($actor)
            ->orderByDesc('created_at')
            ->take($this->historyLimit)
            ->get()
            ->reverse()
            ->values();
    }

    protected function storeExchange(array $actor, string $userMessage, string $assistantMessage, array $toolOutputs, ?array $usage): void
    {
        DB::transaction(function () use ($actor, $userMessage, $assistantMessage, $toolOutputs, $usage): void {
            ChatMessage::create([
                'user_id' => $actor['user_id'],
                'session_id' => $actor['session_id'],
                'author_role' => 'user',
                'content' => $userMessage,
            ]);

            $metadata = [];
            if ($toolOutputs !== []) {
                $metadata['tool_outputs'] = $toolOutputs;
            }
            if ($usage !== null) {
                $metadata['usage'] = $usage;
            }

            ChatMessage::create([
                'user_id' => $actor['user_id'],
                'session_id' => $actor['session_id'],
                'author_role' => 'assistant',
                'content' => $assistantMessage,
                'metadata' => $metadata ?: null,
            ]);

            $this->pruneHistory($actor);
        });
    }

    protected function pruneHistory(array $actor): void
    {
        $ids = ChatMessage::forActor($actor)
            ->orderByDesc('created_at')
            ->skip($this->historyLimit)
            ->take(PHP_INT_MAX)
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            ChatMessage::whereIn('id', $ids)->delete();
        }
    }

    protected function resolveActorContext(Request $request): array
    {
        $session = $request->session();

        if (! $session->isStarted()) {
            $session->start();
        }

        $user = $request->user();

        return [
            'user_id' => $user?->getKey(),
            'session_id' => $session->getId(),
            'role' => $user?->role ?? 'guest',
        ];
    }

    protected function transformHistory(Collection|EloquentCollection $history): array
    {
        return $history
            ->map(fn (ChatMessage $message): array => [
                'role' => $message->author_role,
                'content' => $message->content,
                'meta' => $message->metadata,
            ])
            ->values()
            ->all();
    }
}
