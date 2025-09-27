<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Mcp\Tools\DatabaseQueryTool;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\ChatAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Laravel\Mcp\Request as McpRequest;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use OpenAI\Responses\Meta\MetaInformation;
use OpenAI\Testing\Responses\Fixtures\Chat\CreateResponseFixture;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatAgentServiceTest extends TestCase
{
    use RefreshDatabase;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (! extension_loaded('pdo_sqlite')) {
            static::markTestSkipped('The pdo_sqlite extension is required to run ChatAgentService tests.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    #[Test]
    public function guest_conversation_is_persisted_and_trimmed(): void
    {
        $this->fakeChatResponse('Hello from SOHA');

        Session::start();
        $sessionId = Session::getId();

        ChatMessage::factory()
            ->count(105)
            ->sequence(fn ($sequence) => [
                'author_role' => $sequence->index % 2 === 0 ? 'user' : 'assistant',
                'content' => 'Seed message '.$sequence->index,
                'created_at' => now()->subMinutes(200 - $sequence->index),
                'updated_at' => now()->subMinutes(200 - $sequence->index),
            ])
            ->forSession($sessionId)
            ->create();

        $sessionStore = Session::driver();
        $sessionStore->setId($sessionId);
        $sessionStore->start();

        $request = Request::create('/chat-agent/message', 'POST');
        $request->setLaravelSession($sessionStore);

        $response = app(ChatAgentService::class)->reply($request, 'Can you help me?');

        $this->assertSame('Hello from SOHA', $response['reply']);
        $this->assertCount(100, $response['history']);

        $this->assertDatabaseHas('chat_messages', [
            'session_id' => $sessionId,
            'author_role' => 'user',
            'content' => 'Can you help me?',
        ]);

        $this->assertDatabaseHas('chat_messages', [
            'session_id' => $sessionId,
            'author_role' => 'assistant',
            'content' => 'Hello from SOHA',
        ]);

        $this->assertDatabaseMissing('chat_messages', [
            'session_id' => $sessionId,
            'content' => 'Seed message 0',
        ]);

        $this->assertDatabaseMissing('chat_messages', [
            'session_id' => $sessionId,
            'content' => 'Seed message 1',
        ]);

        $this->assertDatabaseHas('chat_messages', [
            'session_id' => $sessionId,
            'content' => 'Seed message 7',
        ]);

        $this->assertSame(
            100,
            ChatMessage::where('session_id', $sessionId)->count()
        );
    }

    #[Test]
    public function history_endpoint_returns_messages_for_authenticated_user(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        Session::start();

        $otherUser = User::factory()->create(['role' => 'user']);

        ChatMessage::factory()->count(2)->sequence(
            fn ($sequence) => [
                'author_role' => $sequence->index === 0 ? 'user' : 'assistant',
                'created_at' => now()->subMinutes(10 - $sequence->index),
                'updated_at' => now()->subMinutes(10 - $sequence->index),
            ]
        )->create([
            'user_id' => $user->getKey(),
            'session_id' => null,
        ]);

        ChatMessage::factory()->create([
            'user_id' => $otherUser->getKey(),
            'author_role' => 'assistant',
            'session_id' => null,
            'content' => 'This should not leak.',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/chat-agent/history');

        $response->assertOk()
            ->assertJsonCount(2, 'messages')
            ->assertJsonMissing(['content' => 'This should not leak.']);
    }

    #[Test]
    public function guest_cannot_query_protected_tables(): void
    {
        $tool = app(DatabaseQueryTool::class)->forActor([
            'role' => 'guest',
            'session_id' => 'guest-session',
        ]);

        $response = $tool->handle(new McpRequest([
            'statement' => 'SELECT * FROM users',
        ]));

        $this->assertTrue($response->isError());
    }

    #[Test]
    public function admin_can_query_protected_tables(): void
    {
        $tool = app(DatabaseQueryTool::class)->forActor([
            'role' => 'admin',
            'user_id' => 1,
            'session_id' => 'admin-session',
        ]);

        DB::shouldReceive('select')->once()->andReturn([]);

        $response = $tool->handle(new McpRequest([
            'statement' => 'SELECT * FROM users',
        ]));

        $this->assertFalse($response->isError());
    }

    #[Test]
    public function non_restricted_queries_can_reference_user_columns(): void
    {
        $tool = app(DatabaseQueryTool::class)->forActor([
            'role' => 'sales',
            'user_id' => 2,
            'session_id' => 'sales-session',
        ]);

        DB::shouldReceive('select')->once()->andReturn([]);

        $response = $tool->handle(new McpRequest([
            'statement' => 'SELECT COUNT(*) FROM orders WHERE user_id = 42',
        ]));

        $this->assertFalse($response->isError());
    }

    protected function fakeChatResponse(string $content): void
    {
        $attributes = CreateResponseFixture::ATTRIBUTES;
        $attributes['choices'][0]['message']['content'] = $content;

        OpenAI::fake([
            CreateResponse::from($attributes, MetaInformation::from([])),
        ]);
    }
}
