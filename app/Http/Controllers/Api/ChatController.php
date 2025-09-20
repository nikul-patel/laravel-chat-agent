<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ChatAgentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(protected ChatAgentService $chatAgent)
    {
        //
    }

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $result = $this->chatAgent->reply($request, $validated['message']);

        return response()->json($result);
    }

    public function history(Request $request): JsonResponse
    {
        return response()->json([
            'messages' => $this->chatAgent->history($request),
        ]);
    }
}
