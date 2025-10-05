<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Soha\Chat\Http\Requests\SendMessageRequest;
use Soha\Chat\Services\ChatAgentService;

class ChatController extends Controller
{
    public function __construct(protected ChatAgentService $chatAgent)
    {
        //
    }

    public function __invoke(SendMessageRequest $request): JsonResponse
    {
        $result = $this->chatAgent->reply($request, (string) $request->validated('message'));

        return response()->json($result);
    }

    public function history(Request $request): JsonResponse
    {
        return response()->json([
            'messages' => $this->chatAgent->history($request),
        ]);
    }
}
