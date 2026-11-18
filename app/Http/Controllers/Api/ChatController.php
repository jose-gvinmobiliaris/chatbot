<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StartConversationRequest;
use App\Http\Requests\StoreMessageRequest;
use App\Models\Conversation;
use App\Services\Chat\ChatService;
use App\Services\Chat\ConversationService;
use App\Services\Chat\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(
        protected ChatService $chatService,
        protected ConversationService $conversationService,
        protected MessageService $messageService
    ) {}

    public function start(
        StartConversationRequest $request
    ): JsonResponse {
        $result = $this->chatService->start(
            $request->validated('token')
        );

        return response()->json($result);
    }

    public function history(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string', 'size:64'],
        ]);

        $conversation = $this->conversationService
            ->recoverConversation($request->token);

        if (!$conversation) {
            return response()->json([
                'message' => 'La conversación no existe o ha expirado.',
            ], 404);
        }

        return response()->json([
            'conversation' => $conversation,
            'messages' => $this->messageService
                ->getConversationMessages($conversation),
        ]);
    }


    public function message(
        StoreMessageRequest $request
    ): JsonResponse {
        $conversation = $this->getActiveConversation(
            $request->validated('token')
        );

        if (!$conversation) {
            return response()->json([
                'success' => false,
                'message' => 'La conversación no existe o ha expirado.',
            ], 404);
        }

        try {
            $result = $this->chatService->processMessage(
                $conversation,
                $request->validated('content')
            );

            return response()->json($result);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error(
                'Error procesando mensaje del chatbot',
                [
                    'conversation_id' => $conversation->id,
                    'message' => $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'No se pudo procesar el mensaje.',
            ], 500);
        }
    }

    public function selectProject(Request $request): JsonResponse
{
    $validated = $request->validate([
        'token' => ['required', 'string', 'size:64'],
        'wordpress_project_id' => ['required', 'integer', 'min:1'],
    ]);

    $conversation = $this->getActiveConversation(
        $validated['token']
    );

    if (!$conversation) {
        return response()->json([
            'success' => false,
            'message' => 'La conversación no existe o ha expirado.',
        ], 404);
    }

    try {
        $result = $this->chatService->selectProject(
            $conversation,
            (int) $validated['wordpress_project_id']
        );

        return response()->json(
            $result,
            $result['success'] ? 200 : 422
        );

    } catch (\Throwable $e) {

        \Illuminate\Support\Facades\Log::error(
            'Error registrando interés en proyecto',
            [
                'conversation_id' => $conversation->id,
                'project_id' => $validated['wordpress_project_id'],
                'message' => $e->getMessage(),
            ]
        );

        return response()->json([
            'success' => false,
            'message' => 'No se pudo registrar el interés en el proyecto.',
        ], 500);
    }
}


    public function close(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string', 'size:64'],
        ]);

        $conversation = $this->getActiveConversation(
            $request->token
        );

        if (!$conversation) {
            return response()->json([
                'message' => 'Conversación no encontrada.',
            ], 404);
        }

        $this->conversationService->closeConversation($conversation);

        return response()->json([
            'message' => 'Conversación cerrada correctamente.',
        ]);
    }

    private function getActiveConversation(string $token): ?Conversation
    {
        $conversation = $this->conversationService
            ->findActiveConversation($token);

        if (
            $conversation &&
            $conversation->last_interaction_at &&
            $conversation->last_interaction_at->lt(now()->subHour())
        ) {
            $this->conversationService->closeConversation($conversation);

            return null;
        }

        return $conversation;
    }
}
