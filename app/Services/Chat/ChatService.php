<?php

namespace App\Services\Chat;

use App\Models\Conversation;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;

class ChatService
{
    public function __construct(
        protected LeadService $leadService,
        protected ConversationService $conversationService,
        protected MessageService $messageService
    ) {}

    public function start(?string $token = null): array
    {
        if ($token) {
            $conversation = $this->conversationService
                ->recoverConversation($token);

            if ($conversation) {
                return [
                    'conversation' => $conversation,
                    'token' => $conversation->session_token,
                    'is_new' => false,
                ];
            }
        }

        $conversation = $this->conversationService
            ->createConversation();

        $this->messageService->createMessage(
            $conversation,
            'bot',
            '¡Hola! Bienvenido a GV Inmobiliaria. ¿En qué podemos ayudarte?'
        );

        return [
            'conversation' => $conversation->load('messages'),
            'token' => $conversation->session_token,
            'is_new' => true,
        ];
    }

    public function registerLead(
        Conversation $conversation,
        array $data
    ): Lead {
        return DB::transaction(function () use ($conversation, $data) {
            $lead = $this->leadService->createOrUpdate($data);

            $this->conversationService->attachLead(
                $conversation,
                $lead
            );

            return $lead;
        });
    }

    public function sendMessage(
        Conversation $conversation,
        string $content,
        string $role = 'client'
    ) {
        return $this->messageService->createMessage(
            $conversation,
            $role,
            $content
        );
    }
}