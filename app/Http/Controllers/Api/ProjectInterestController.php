<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Chat\ChatService;
use App\Services\Chat\ConversationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class ProjectInterestController extends Controller
{
    public function __construct(
        private ChatService $chatService,
        private ConversationService $conversationService
    ) {}

    public function store(Request $request)
    {
        $data = $request->validate([
            'session_token' => ['required', 'string', 'size:64'],
            'wordpress_project_id' => ['required', 'integer', 'min:1'],
        ]);

        $conversation = $this->conversationService
            ->recoverConversation($data['session_token']);

        if (!$conversation) {
            return response()->json([
                'success' => false,
                'message' => 'La conversación no existe o ha expirado.',
            ], 404);
        }

        try {
            $this->chatService->registerProjectInterest(
                $conversation,
                (int) $data['wordpress_project_id']
            );

            return response()->json([
                'success' => true,
                'message' => 'Interés registrado correctamente.',
            ]);

        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);

        } catch (Throwable $e) {
            Log::error('Error registrando interés', [
                'conversation_id' => $conversation->id,
                'wordpress_project_id' => $data['wordpress_project_id'],
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo registrar el interés.',
            ], 500);
        }
    }
}