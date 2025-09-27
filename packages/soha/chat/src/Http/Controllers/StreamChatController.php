<?php

namespace Soha\Chat\Http\Controllers;

use App\Services\ChatAgentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StreamChatController
{
    public function __invoke(Request $request, ChatAgentService $chatAgent): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $result = $chatAgent->reply($request, $validated['message']);

        return response()->json($result);
    }
}
