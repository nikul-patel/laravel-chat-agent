<?php

namespace Soha\Chat\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use OpenAI\Exceptions\ErrorException;
use Soha\Chat\Services\ChatAgentService;

class ChatWidget extends Component
{
    public array $messages = [];

    public string $input = '';

    public bool $isStreaming = false;

    public string $streamingResponse = '';

    public string $activeTheme = 'system';

    public function boot(ChatAgentService $chatAgent): void
    {
        $this->messages = Collection::make($chatAgent->history(request()))
            ->map(fn (array $entry): array => [
                'role' => $entry['role'] ?? 'assistant',
                'content' => $entry['content'] ?? '',
                'meta' => $entry['meta'] ?? [],
            ])
            ->all();

        $this->activeTheme = config('soha-chat.theme.preset', 'system');
    }

    public function render(): View
    {
        return view('soha-chat::livewire.chat-widget', [
            'commands' => config('soha-chat.slash_commands', []),
            'themeVariables' => config('soha-chat.theme.variables', []),
            'showReset' => (bool) config('soha-chat.features.show_reset', true),
            'showThemeToggle' => (bool) config('soha-chat.features.show_theme_toggle', true),
        ]);
    }

    public function send(ChatAgentService $chatAgent): void
    {
        if ($this->input === '') {
            return;
        }

        $message = trim($this->input);
        $this->input = '';

        if ($message === '') {
            return;
        }

        $this->appendMessage('user', $message);

        $normalizedMessage = $this->normalizeMessage($message, $chatAgent);

        if ($normalizedMessage === null) {
            $this->dispatch('scroll-to-latest');

            return;
        }

        $message = $normalizedMessage;

        $this->isStreaming = true;
        $this->streamingResponse = '';

        try {
            $result = $chatAgent->reply(request(), $message);
        } catch (ErrorException $exception) {
            Log::warning('SOHA chat reply failed', ['exception' => $exception]);

            $this->isStreaming = false;
            $this->appendMessage('assistant', __($this->errorTranslationKey()));

            return;
        }

        $reply = $result['reply'] ?? '';
        $history = $result['history'] ?? [];

        if ($reply !== '') {
            $this->dispatch('streaming-chunk', content: $reply);
        }

        $this->messages = Collection::make($history)->map(fn (array $entry): array => [
            'role' => $entry['role'] ?? 'assistant',
            'content' => $entry['content'] ?? '',
            'meta' => $entry['meta'] ?? [],
        ])->all();

        $this->isStreaming = false;

        $this->dispatch('streaming-complete');

        $this->dispatch('scroll-to-latest');
    }

    public function resetConversation(ChatAgentService $chatAgent): void
    {
        $this->handleReset($chatAgent);
        $this->dispatch('scroll-to-latest');
    }

    public function executeCommand(string $command, ChatAgentService $chatAgent): void
    {
        $command = trim($command);

        if ($command === '') {
            return;
        }

        $this->input = '/'.ltrim($command, '/');

        $this->send($chatAgent);
    }

    protected function appendMessage(string $role, string $content): void
    {
        $this->messages[] = [
            'role' => $role,
            'content' => $content,
            'meta' => [],
        ];
    }

    protected function normalizeMessage(string $message, ChatAgentService $chatAgent): ?string
    {
        if (! str_starts_with($message, '/')) {
            return $message;
        }

        $command = strtolower(trim(substr($message, 1)));

        return match ($command) {
            'reset' => $this->handleReset($chatAgent),
            'help' => $this->handleHelp(),
            'schema' => 'Summarise the available datasets and tables for me.',
            default => $message,
        };
    }

    protected function handleReset(ChatAgentService $chatAgent): ?string
    {
        $chatAgent->reset(request());
        $this->messages = [];

        $message = 'Conversation history cleared. Start fresh with a new question.';

        $this->appendMessage('assistant', $message);
        $this->dispatch('streaming-chunk', content: $message);
        $this->dispatch('streaming-complete');

        return null;
    }

    protected function handleHelp(): ?string
    {
        $commands = Collection::make(config('soha-chat.slash_commands', []))
            ->map(fn (array $command): string => sprintf('/%s — %s', $command['name'], $command['description']))
            ->implode(PHP_EOL);

        $message = $commands !== '' ? $commands : 'No slash commands configured.';

        $this->appendMessage('assistant', $message);
        $this->dispatch('streaming-chunk', content: $message);
        $this->dispatch('streaming-complete');

        return null;
    }

    protected function errorTranslationKey(): string
    {
        return 'soha-chat::messages.api_error';
    }
}
