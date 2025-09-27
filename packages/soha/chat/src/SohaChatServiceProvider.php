<?php

namespace Soha\Chat;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Soha\Chat\Http\Controllers\ConversationHistoryController;
use Soha\Chat\Http\Controllers\StreamChatController;
use Soha\Chat\Livewire\ChatWidget;

class SohaChatServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/chat.php', 'soha-chat');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'soha-chat');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'soha-chat');

        $this->registerPublishing();
        $this->registerLivewireComponent();
        $this->registerRoutes();
    }

    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/chat.php' => config_path('soha-chat.php'),
            ], 'soha-chat-config');

            $viewPath = realpath(__DIR__.'/../resources/views');

            if ($viewPath !== false) {
                $this->publishes([
                    $viewPath => resource_path('views/vendor/soha-chat'),
                ], 'soha-chat-views');
            }

            $langPath = realpath(__DIR__.'/../resources/lang');

            if ($langPath !== false) {
                $this->publishes([
                    $langPath => resource_path('lang/vendor/soha-chat'),
                ], 'soha-chat-lang');
            }
        }
    }

    protected function registerLivewireComponent(): void
    {
        Livewire::component('soha-chat.widget', ChatWidget::class);
    }

    protected function registerRoutes(): void
    {
        Route::middleware(config('soha-chat.middleware', ['web']))
            ->prefix(config('soha-chat.prefix', 'soha-chat'))
            ->group(function (): void {
                Route::post('/messages', [StreamChatController::class, '__invoke'])
                    ->name('soha-chat.messages');

                Route::get('/messages', [ConversationHistoryController::class, '__invoke'])
                    ->name('soha-chat.history');
            });
    }
}
