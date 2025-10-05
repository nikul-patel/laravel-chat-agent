<?php

namespace Soha\Chat\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Soha\Chat\Database\Factories\ChatMessageFactory;

class ChatMessage extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'session_id',
        'author_role',
        'content',
        'metadata',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Scope a query to the provided actor context.
     */
    public function scopeForActor(Builder $query, array $actor): Builder
    {
        if (! empty($actor['user_id'])) {
            return $query->where('user_id', $actor['user_id']);
        }

        return $query->whereNull('user_id')->where('session_id', $actor['session_id'] ?? null);
    }

    protected static function newFactory(): ChatMessageFactory
    {
        return ChatMessageFactory::new();
    }
}
