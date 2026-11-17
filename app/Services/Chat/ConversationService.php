<?php

namespace App\Services\Chat;

use App\Models\Conversation;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConversationService
{
    public function createConversation(?Lead $lead = null): Conversation
    {
        return Conversation::create([
            'lead_id' => $lead?->id,
            'session_token' => Str::random(64),
            'status' => 'active',
            'flow_state' => 'welcome',
            'last_interaction_at' => now(),
        ]);
    }

    public function findActiveConversation(string $token): ?Conversation
    {
        return Conversation::where('session_token', $token)
            ->where('status', 'active')
            ->first();
    }

    public function recoverConversation(string $token): ?Conversation
    {
        $conversation = $this->findActiveConversation($token);

        if (!$conversation) {
            return null;
        }

        if (
            $conversation->last_interaction_at &&
            $conversation->last_interaction_at->lt(now()->subHour())
        ) {
            $this->closeConversation($conversation);

            return null;
        }

        return $conversation->load('messages', 'lead', 'projects');
    }

    public function updateInteraction(Conversation $conversation): Conversation
    {
        $conversation->last_interaction_at = now();
        $conversation->save();

        return $conversation;
    }

    public function updateFlowState(
        Conversation $conversation,
        string $state
    ): Conversation {
        $conversation->flow_state = $state;
        $conversation->last_interaction_at = now();
        $conversation->save();

        return $conversation;
    }

    public function attachLead(
        Conversation $conversation,
        Lead $lead
    ): Conversation {
        $conversation->lead()->associate($lead);
        $conversation->save();

        return $conversation;
    }

    public function closeConversation(
        Conversation $conversation
    ): Conversation {
        $conversation->status = 'closed';
        $conversation->closed_at = now();
        $conversation->save();

        return $conversation;
    }

    public function getHistory(Conversation $conversation): Conversation
    {
        return $conversation->load('messages', 'lead', 'projects');
    }
}