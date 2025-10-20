<?php

namespace Soha\Chat\Support;

use BackedEnum;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ActorContext
{
    /**
     * Resolve the actor context using the incoming request and configuration.
     *
     * @return array{user_id: mixed, session_id: string|null, role: string}
     */
    public static function fromRequest(Request $request): array
    {
        $session = $request->session();

        if (! $session->isStarted()) {
            $session->start();
        }

        $user = $request->user();

        return [
            'user_id' => $user?->getAuthIdentifier(),
            'session_id' => $session->getId(),
            'role' => static::resolveRole($user),
        ];
    }

    protected static function resolveRole(?Authenticatable $user): string
    {
        if ($user === null) {
            return config('soha-chat.actors.roles.guest', 'guest');
        }

        $defaultRole = config('soha-chat.actors.roles.authenticated', 'user');
        $attribute = config('soha-chat.actors.role_attribute', 'role');

        $value = null;

        if ($attribute !== '' && $attribute !== null) {
            $value = static::extractAttribute($user, $attribute);
        }

        if ($value === null) {
            $fallbackAttribute = config('soha-chat.actors.fallback_attribute');

            if (is_string($fallbackAttribute) && $fallbackAttribute !== '') {
                $value = static::extractAttribute($user, $fallbackAttribute);
            }
        }

        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        if (is_string($value)) {
            $value = trim($value);

            if ($value !== '') {
                return Str::lower($value);
            }
        }

        if (is_scalar($value)) {
            return Str::lower((string) $value);
        }

        return $defaultRole;
    }

    protected static function extractAttribute(Authenticatable $user, string $attribute): mixed
    {
        if ($attribute === '') {
            return null;
        }

        if (method_exists($user, $attribute)) {
            return $user->{$attribute}();
        }

        if (method_exists($user, 'getAttribute')) {
            return $user->getAttribute($attribute);
        }

        return Arr::get($user, $attribute);
    }
}
