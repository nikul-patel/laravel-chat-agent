<?php

namespace Soha\Chat\Mcp\Tools;

use Illuminate\Database\QueryException;
use Illuminate\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class DatabaseQueryTool extends Tool
{
    /**
     * A human-readable identifier used by the agent when calling this tool.
     */
    protected string $name = 'run_database_query';

    /**
     * The tool's description supplied to the LLM.
     */
    protected string $description = 'Execute safe, read-only SQL queries against the primary application database.';

    /**
     * Holds the actor context for permission checks.
     *
     * @var array<string, mixed>
     */
    protected array $actorContext = [
        'role' => 'guest',
        'user_id' => null,
        'session_id' => null,
    ];

    public function forActor(array $actor): static
    {
        $this->actorContext = array_merge($this->actorContext, $actor);

        return $this;
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $payload = $request->validate([
            'statement' => ['required', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.config('chat-agent.max_rows', 25)],
        ]);

        $statement = trim($payload['statement']);
        $limit = $payload['limit'] ?? config('chat-agent.max_rows', 25);

        if ($this->isUnsafeStatement($statement)) {
            return Response::error('Only read-only SELECT queries are allowed.');
        }

        if ($denial = $this->guardAgainstRestrictedTables($statement)) {
            return $denial;
        }

        $statement = $this->enforceLimit($statement, $limit);

        try {
            $rows = collect(DB::select($statement))
                ->map(fn ($row) => (array) $row)
                ->values();
        } catch (QueryException $exception) {
            return Response::error('Query failed: '.$exception->getMessage());
        }

        return Response::json([
            'statement' => $statement,
            'row_count' => $rows->count(),
            'rows' => $rows->take($limit),
        ]);
    }

    /**
     * Get the tool's input schema.
     */
    public function schema(JsonSchema $schema): array
    {
        $maximum = config('chat-agent.max_rows', 25);

        return [
            'statement' => $schema->string()
                ->description('A single SELECT statement. Do not include multiple statements or modifiers that mutate data.')
                ->required(),
            'limit' => $schema->integer()
                ->description("Optional row limit between 1 and {$maximum}. Applied when the query omits an explicit LIMIT clause.")
                ->min(1)
                ->max($maximum)
                ->default($maximum),
        ];
    }

    protected function enforceLimit(string $statement, int $limit): string
    {
        $normalised = strtolower($statement);

        if (! str_contains($normalised, ' limit ')) {
            $statement = rtrim($statement, ';')." LIMIT {$limit}";
        }

        return $statement;
    }

    protected function isUnsafeStatement(string $statement): bool
    {
        $lower = strtolower($statement);

        if (Str::contains($lower, [';', '--', '/*', '*/'])) {
            return true;
        }

        return ! Str::startsWith($lower, ['select', 'show', 'describe', 'with']);
    }

    protected function guardAgainstRestrictedTables(string $statement): ?Response
    {
        $role = $this->actorContext['role'] ?? 'guest';

        if ($role === 'guest') {
            return Response::error('Please sign in to run database queries.');
        }

        $tables = $this->extractTableIdentifiers($statement);

        $rules = [
            'users' => ['admin'],
            'user_*' => ['admin'],
            'customer*' => ['admin', 'sales'],
            'sales*' => ['admin', 'sales'],
            'transaction*' => ['admin'],
            'salary*' => ['admin'],
        ];

        foreach ($tables as $table) {
            foreach ($rules as $pattern => $allowedRoles) {
                if (Str::is($pattern, $table) && ! in_array($role, $allowedRoles, true)) {
                    return Response::error('You do not have permission to query protected data sets.');
                }
            }
        }

        return null;
    }

    /**
     * Extract table identifiers from a SELECT statement.
     *
     * @return list<string>
     */
    protected function extractTableIdentifiers(string $statement): array
    {
        $tables = [];

        if (! preg_match('/\bfrom\b/i', $statement, $match, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $relevant = substr($statement, $match[0][1]);

        preg_match_all('/(?:\bfrom|\bjoin|,)\s*(?:`[^`]+`|"[^"]+"|\[[^\]]+\]|[a-z0-9_.]+)/i', $relevant, $matches);

        foreach ($matches[0] ?? [] as $segment) {
            $candidate = preg_replace('/^(?:from|join|,)\s*/i', '', $segment);
            $identifier = $this->normaliseIdentifier($candidate);

            if ($identifier !== null) {
                $tables[] = $identifier;
            }
        }

        return array_values(array_unique($tables));
    }

    protected function normaliseIdentifier(string $candidate): ?string
    {
        $trimmed = trim($candidate);

        if ($trimmed === '' || str_starts_with($trimmed, '(')) {
            return null;
        }

        $trimmed = preg_split('/\s+/', $trimmed)[0] ?? '';
        $trimmed = trim($trimmed, '`"[]');

        if ($trimmed === '') {
            return null;
        }

        if (str_contains($trimmed, '.')) {
            $segments = explode('.', $trimmed);
            $trimmed = end($segments) ?: $trimmed;
        }

        return strtolower($trimmed);
    }
}
