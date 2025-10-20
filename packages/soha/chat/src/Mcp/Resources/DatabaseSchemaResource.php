<?php

namespace Soha\Chat\Mcp\Resources;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Resource;

class DatabaseSchemaResource extends Resource
{
    /**
     * A brief explanation of the resource exposed to the agent.
     */
    protected string $description = 'Summarised relational schema for the primary database connection.';

    /**
     * Provide a plain-text snapshot of the database schema.
     */
    public function handle(Request $request): Response
    {
        $schema = $this->buildSchemaSnapshot();

        return Response::text($schema);
    }

    protected function buildSchemaSnapshot(): string
    {
        $connection = DB::connection();
        $databaseName = $connection->getDatabaseName();

        try {
            $tables = collect($connection->select('SHOW TABLES'))
                ->map(fn ($row) => array_values((array) $row)[0] ?? null)
                ->filter();
        } catch (\Throwable $exception) {
            return 'Unable to inspect tables: '.$exception->getMessage();
        }

        $tables = $tables->take(config('chat-agent.schema.max_tables', 10));

        if ($tables->isEmpty()) {
            return 'No tables were found for the current connection.';
        }

        $lines = ["Database: {$databaseName}", 'Tables:'];

        foreach ($tables as $table) {
            $lines[] = "- {$table}";

            try {
                $columns = collect($connection->select("SHOW COLUMNS FROM `{$table}`"))
                    ->map(fn ($column) => (array) $column);
            } catch (\Throwable $exception) {
                $lines[] = '  (unable to read columns: '.$exception->getMessage().')';

                continue;
            }

            $columns = $columns->take(config('chat-agent.schema.max_columns', 12));

            if ($columns->isEmpty()) {
                $lines[] = '  (no column metadata available)';

                continue;
            }

            $lines = array_merge($lines, $this->formatColumns($columns));
        }

        return implode(PHP_EOL, $lines);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $columns
     * @return array<int, string>
     */
    protected function formatColumns(Collection $columns): array
    {
        return $columns->map(function (array $column): string {
            $name = $column['Field'] ?? 'column';
            $type = $column['Type'] ?? 'unknown';
            $nullable = ($column['Null'] ?? '') === 'YES' ? 'nullable' : 'not null';
            $key = $column['Key'] ?? '';

            $keyLabel = match ($key) {
                'PRI' => 'primary key',
                'UNI' => 'unique index',
                'MUL' => 'indexed',
                default => null,
            };

            $notes = array_filter([$nullable, $keyLabel]);

            return '  • '.$name.' ('.$type.')'.($notes ? ' — '.implode(', ', $notes) : '');
        })->all();
    }
}
