<?php

namespace Soha\Chat;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Soha\Chat\Livewire\ChatWidget;
use Soha\Chat\Mcp\Resources\DatabaseSchemaResource;
use Soha\Chat\Mcp\Tools\DatabaseQueryTool;
use Soha\Chat\Services\ChatAgentService;

class SohaChatServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/chat.php', 'soha-chat');
        $this->mergeConfigFrom(__DIR__.'/../config/chat-agent.php', 'chat-agent');

        $this->app->singleton(ChatAgentService::class, function ($app): ChatAgentService {
            return new ChatAgentService(
                $app->make(DatabaseQueryTool::class),
                $app->make(DatabaseSchemaResource::class),
            );
        });
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'soha-chat');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'soha-chat');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->loadFactoriesFrom(__DIR__.'/../database/factories');
        }

        $this->registerPublishing();
        $this->registerLivewireComponent();
    }

    protected function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/chat.php' => config_path('soha-chat.php'),
        ], 'soha-chat-config');

        $this->publishes([
            __DIR__.'/../config/chat-agent.php' => config_path('chat-agent.php'),
        ], 'soha-chat-agent-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/soha-chat'),
        ], 'soha-chat-views');

        $this->publishes([
            __DIR__.'/../resources/lang' => resource_path('lang/vendor/soha-chat'),
        ], 'soha-chat-translations');

        $this->publishes([
            __DIR__.'/../resources/css' => public_path('vendor/soha-chat/css'),
            __DIR__.'/../resources/js' => public_path('vendor/soha-chat/js'),
        ], 'soha-chat-assets');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'soha-chat-migrations');
    }

    protected function registerLivewireComponent(): void
    {
        Livewire::component('soha-chat.widget', ChatWidget::class);
    }
}
