<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Mockery\MockInterface;
use Soha\Chat\Services\ChatAgentService;
use Tests\TestCase;

class SohaChatComponentTest extends TestCase
{
    public function test_widget_renders_with_default_state(): void
    {
        $this->mock(ChatAgentService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('history')->andReturn([]);
            $mock->shouldReceive('reset')->zeroOrMoreTimes();
            $mock->shouldReceive('reply')->zeroOrMoreTimes();
        });

        $this->get('/')
            ->assertOk()
            ->assertSee('SOHA Support');
    }

    public function test_history_endpoint_returns_messages(): void
    {
        $history = [
            ['role' => 'assistant', 'content' => 'Hello!'],
        ];

        $this->mock(ChatAgentService::class, function (MockInterface $mock) use ($history): void {
            $mock->shouldReceive('history')->andReturn($history);
            $mock->shouldReceive('reset')->zeroOrMoreTimes();
            $mock->shouldReceive('reply')->zeroOrMoreTimes();
        });

        $this->getJson(route('soha-chat.history'))
            ->assertOk()
            ->assertJson(['messages' => $history]);
    }

    public function test_message_endpoint_returns_chat_payload(): void
    {
        $payload = [
            'reply' => 'How can I assist you today?',
            'tool_outputs' => [],
            'usage' => null,
            'history' => [
                ['role' => 'user', 'content' => 'Hello'],
                ['role' => 'assistant', 'content' => 'How can I assist you today?'],
            ],
        ];

        $this->mock(ChatAgentService::class, function (MockInterface $mock) use ($payload): void {
            $mock->shouldReceive('reply')->andReturn($payload);
            $mock->shouldReceive('history')->andReturn($payload['history']);
            $mock->shouldReceive('reset')->zeroOrMoreTimes();
        });

        $this->withoutMiddleware(VerifyCsrfToken::class);

        $this->postJson(route('soha-chat.messages'), ['message' => 'Hello'])
            ->assertOk()
            ->assertJson($payload);
    }
}
