# Laravel Chat Agent

A Laravel 12 application that embeds a SOHA-style floating assistant backed by Laravel Boost, Laravel MCP, and OpenAI. The agent can inspect your MySQL schema, run safe read-only SQL queries, and reply to users in real time inside the app or over HTTP/MCP transports.

## Features
- Toggleable SOHA-inspired chat widget (Livewire + Flux) that remembers its open/closed state and works out of the box with the main layout.
- Automatic persistence of the latest 100 chat messages per signed-in user (or per browser session for guests), reused as context for future replies.
- Role-aware safeguards that tailor instructions and block access to protected datasets unless the actor has the required permissions.
- REST endpoints (`POST /chat-agent/message`, `GET /chat-agent/history`) for integrating custom front ends or automated checks.
- Laravel MCP server (`/mcp/support-chat`) exposing a read-only database query tool and schema resource to AI clients.
- Database-aware responses: the assistant auto-limits SQL, enforces safe-table rules, returns result tables, and cites the data it used.
- Configurable OpenAI settings (model, temperature, limits) via `config/chat-agent.php` and environment variables.
- Per-actor rate limiting keyed to the authenticated user or request IP to keep the agent responsive in production.

## Prerequisites
- PHP 8.3+
- Composer 2.x
- Node.js 20+ and npm 10+
- MySQL-compatible database (the schema tools assume MySQL/MariaDB `SHOW` statements)
- OpenAI API key with access to the Chat Completions API (adjust the model if needed)

## Getting Started
1. **Install dependencies**
   ```bash
   composer install
   npm install
   ```
2. **Create your environment file**
   ```bash
   cp .env.example .env
   ```
3. **Configure `.env`**
   - Database connection (default expects local MySQL at `127.0.0.1`).
   - `OPENAI_API_KEY` (required) and optional overrides such as `OPENAI_CHAT_MODEL`.
   - Update `APP_URL` if you are not using `http://localhost`.
4. **Generate application key & link storage**
   ```bash
   php artisan key:generate
   php artisan storage:link
   ```
5. **Run migrations (creates users, chat messages, sessions, etc.)**
   ```bash
   php artisan migrate
   ```
6. **Start the local servers**
   ```bash
   php artisan serve        # http://127.0.0.1:8000 by default
   npm run dev              # Vite dev server for the widget assets
   ```

### Optional: Install Laravel Boost Guidelines
Once your database driver works (i.e. the MySQL credentials are valid), you can install the full Boost guideline set for richer MCP output:
```bash
php artisan boost:install
```

## Configuration Overview
- `config/chat-agent.php` centralises agent behaviour, schema limits, role naming, and MCP metadata.
- The HTTP controller (`app/Http/Controllers/Api/ChatController.php`) delegates to `App\Services\ChatAgentService` for orchestration.
- The service coordinates OpenAI requests, resolves MCP tool calls, persists history, and enforces role-aware constraints.
- MCP routes live in `routes/ai.php`; REST/chat widget routes live in `routes/web.php`.
- Front-end assets: Livewire view + Alpine controller in `packages/soha/chat/resources/views/livewire/chat-widget.blade.php` with styles delivered via Vite (`resources/css/app.css`).
- To sync view tweaks from the package into your app, run `php artisan vendor:publish --tag=soha-chat-views --force` (the publish step copies to `resources/views/vendor/soha-chat`).
- Feature toggles: `config/soha-chat.php` exposes `features.show_reset` and `features.show_theme_toggle` so teams can disable the reset button or theme switcher when embedding the widget elsewhere.
- Translations: publish the language file with `php artisan vendor:publish --tag=soha-chat-lang --force` if you want to override the built-in end-user messaging.

## Using the Chat Widget
- Visit any page rendered with the default layout and click the “Chat with SOHA” bubble in the bottom-right corner (or press `⌘/Ctrl + K`).
- The widget remembers whether you left it open or closed (persisted to `localStorage`) so the same state is restored on the next visit.
- Tool call outputs appear as inline tables or JSON blocks right under the assistant’s reply.
- Guests receive high-level summaries; signed-in users inherit their `role` (stored on the `users` table) to unlock additional datasets.
- Use the header controls to cycle themes, reset the conversation, or collapse the widget without losing your history.

## API Usage
### POST `/chat-agent/message`
```json
{
  "message": "How many orders were created today?"
}
```
Returns:
```json
{
  "reply": "We logged 15 new orders today based on orders.created_at.",
  "tool_outputs": [
    {
      "tool_call_id": "call_123",
      "name": "run_database_query",
      "statement": "SELECT ...",
      "row_count": 15,
      "rows": [ {"created_at": "2025-01-21", "total": 15} ]
    }
  ],
  "usage": { "prompt_tokens": 512, "completion_tokens": 142 },
  "history": [
    { "role": "user", "content": "How many orders were created today?" },
    { "role": "assistant", "content": "We logged 15 new orders today based on orders.created_at.", "meta": { "tool_outputs": [ ... ] } }
  ]
}
```
Requests are throttled by the `chat-agent` limiter (30 requests/minute per authenticated user ID or, for guests, per IP address).

### GET `/chat-agent/history`
```json
{
  "messages": [
    { "role": "user", "content": "Show me last week's new users." },
    { "role": "assistant", "content": "Here are the counts per day...", "meta": { "tool_outputs": [ ... ] } }
  ]
}
```
The response contains the most recent 100 messages for the current actor (identified via `user_id` if authenticated, otherwise via the session id).

## MCP Integration
- HTTP transport: `POST https://your-app.test/mcp/support-chat`
- Health check (GET) returns 405 by design; follow the MCP spec for JSON-RPC.
- Local STDIO transport (for editors like Cursor/Claude Code):
  ```json
  {
    "mcpServers": {
      "laravel-support-chat": {
        "command": "php",
        "args": ["artisan", "mcp:start", "support-chat"]
      }
    }
  }
  ```
- Exposed tools/resources:
  - `run_database_query` – safe SQL SELECT execution with automatic LIMITs and role-aware table restrictions.
  - `database-schema-resource` – summarised schema snapshot (tables & columns).

## Available Scripts
| Command | Description |
| --- | --- |
| `php artisan serve` | Run the HTTP server. |
| `npm run dev` | Start the Vite dev server with hot reload. |
| `npm run build` | Compile and version production assets. |
| `php artisan test` | Execute the PHPUnit test suite. |
| `php artisan queue:work` | Run workers if you later offload agent tasks. |
| `php artisan mcp:start support-chat` | Launch MCP server over stdio for local tooling. |

## Testing
- Run `php artisan test` for backend coverage.
- You can mock OpenAI responses by binding a fake client or using `OpenAI::fake()` inside your tests.
- Frontend widget behaviour is covered via standard Vite/JS tooling (consider Playwright/Vitest if you need browser tests).

## Deployment Checklist
- `APP_ENV=production`, `APP_DEBUG=false`.
- Configure trusted proxy settings if serving behind load balancers.
- Use a persistent cache + session store (Redis or database tables already provided by migrations).
- Run `npm run build` and serve the compiled assets (e.g., via Laravel Mix manifest published to `public/build`).
- Ensure queue workers are supervised if you extend the agent with queued jobs.

## Troubleshooting
- **Boost install fails with `could not find driver`**: verify the PDO driver for MySQL is installed and `.env` has a working `DB_CONNECTION`.
- **OpenAI errors**: check `storage/logs/laravel.log` for the raw error and confirm the model name matches your API plan.
- **Widget not loading styles**: confirm Vite dev server is running or that you executed `npm run build` and `php artisan optimize:clear` after deploying.
- **Permission denied queries**: ensure the signed-in user’s `role` is set to `admin` or `sales` before querying restricted tables.

---
Released under the MIT license. Built on top of the Laravel framework and Laravel Boost/MCP ecosystem packages.
