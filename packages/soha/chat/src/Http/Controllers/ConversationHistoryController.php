<?php

namespace Soha\Chat\Http\Controllers;

use App\Services\ChatAgentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationHistoryController
{
    public function __invoke(Request $request, ChatAgentService $chatAgent): JsonResponse
    {
        return response()->json([
            'messages' => $chatAgent->history($request),
        ]);
    }
}
