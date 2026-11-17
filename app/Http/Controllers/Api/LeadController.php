<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeadRequest;
use App\Models\Conversation;
use App\Services\Chat\ChatService;
use Illuminate\Http\JsonResponse;

class LeadController extends Controller
{
    public function __construct(
        protected ChatService $chatService
    ) {}

    public function store(StoreLeadRequest $request): JsonResponse
    {
        $conversation = Conversation::where(
            'session_token',
            $request->validated('token')
        )
            ->where('status', 'active')
            ->first();

        if (
            !$conversation ||
            ($conversation->last_interaction_at &&
             $conversation->last_interaction_at->lt(now()->subHour()))
        ) {
            return response()->json([
                'message' => 'La conversación no existe o ha expirado.',
            ], 404);
        }

        $lead = $this->chatService->registerLead(
            $conversation,
            $request->safe()->except('token')
        );

        return response()->json([
            'message' => 'Lead registrado correctamente.',
            'lead' => $lead,
        ], 201);
    }
}