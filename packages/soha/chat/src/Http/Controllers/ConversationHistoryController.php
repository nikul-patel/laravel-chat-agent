<?php

namespace Soha\Chat\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Soha\Chat\Services\ChatAgentService;

class ConversationHistoryController
{
    public function __invoke(Request $request, ChatAgentService $chatAgent): JsonResponse
    {
        return response()->json([
            'messages' => $chatAgent->history($request),
        ]);
    }
}
