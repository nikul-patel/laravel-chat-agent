<?php

namespace Soha\Chat\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Soha\Chat\Http\Requests\SendMessageRequest;
use Soha\Chat\Services\ChatAgentService;

class StreamChatController
{
    public function __invoke(SendMessageRequest $request, ChatAgentService $chatAgent): JsonResponse
    {
        $result = $chatAgent->reply($request, (string) $request->validated('message'));

        return response()->json($result);
    }
}
