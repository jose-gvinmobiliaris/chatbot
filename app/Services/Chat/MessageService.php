<?php

namespace App\Services\Chat;

use App\Models\Conversation;
use App\Models\Message;

class MessageService
{
    public function createMessage(
        Conversation $conversation,
        string $role,
        string $content
    ): Message {
        if (!in_array($role, ['bot', 'client', 'error'], true)) {
            throw new \InvalidArgumentException('Rol de mensaje inválido.');
        }

        $message = $conversation->messages()->create([
            'role' => $role,
            'content' => $content,
        ]);

        $conversation->update([
            'last_interaction_at' => now(),
        ]);

        return $message;
    }

    public function getConversationMessages(
        Conversation $conversation
    ) {
        return $conversation->messages()
            ->orderBy('created_at')
            ->get();
    }

    public function getLatestMessages(
        Conversation $conversation,
        int $limit = 20
    ) {
        return $conversation->messages()
            ->latest()
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }
}