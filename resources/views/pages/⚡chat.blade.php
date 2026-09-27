<?php

use Livewire\Component;

new class extends Component {
    public ?string $conversationId = null;
    public array $messages = [];

    public function mount(?string $conversationId = null): void
    {
        $this->conversationId = $conversationId;

        if ($conversationId) {
            $this->loadMessages($conversationId);
        }
    }

    // Load Messages
    public function loadMessages($conversationId): void
    {
        $this->messages = DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversationId)
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('created_at')
            ->get()
            ->map(function ($message) {
                $attachment = null;

                if ($message->role === 'user') {
                    $decoded = json_decode($message->attachments, true) ?: [];
                    $meta = ($decoded[0]['type'] ?? null) === 'stored-document' || ($decoded[0]['type'] ?? null) === 'stored-image'
                        ? [
                            'path' => $decoded[0]['path'] ?? null,
                            'name' => $decoded[0]['name'] ?? null,
                            'mime' => match (strtolower(pathinfo($decoded[0]['path'] ?? '', PATHINFO_EXTENSION))) {
                                'png' => 'image/png',
                                'jpg', 'jpeg' => 'image/jpeg',
                                default => 'application/pdf',
                            },
                            'disk' => $decoded[0]['disk'] ?? null,
                        ]
                        : json_decode($decoded[0]['name'] ?? '', true);

                    if (is_array($meta)) {
                        $attachment = $meta;
                    }
                }

                return [
                    'role' => $message->role,
                    'content' => $message->content,
                    'streaming' => false,
                    'attachment_path' => $attachment['path'] ?? null,
                    'attachment_name' => $attachment['name'] ?? null,
                    'attachment_mime' => $attachment['mime'] ?? null,
                    'attachment_disk' => $attachment['disk'] ?? 'local',
                ];
            })
            ->all();
    }
};
?>

<div class="flex h-screen overflow-hidden bg-zinc-950">

    {{-- Sidebar Component --}}
    <livewire:conversation-sidebar :active-conversation-id="$conversationId" />

    {{-- Chatbox component --}}
    <livewire:chat-box :key="$conversationId ?? 'main-chat-box'" :initial-messages="$messages" :conversation-id="$conversationId" />

</div>
