<?php

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public array $conversations = [];
    public ?string $activeConversationId = null;

    public function mount(): void
    {
        $this->loadConversations();
    }

    // Loading Conversations
    #[On('conversation-saved')]
    public function loadConversations(?string $conversationId = null): void
    {
        if ($conversationId) {
            $this->activeConversationId = $conversationId;
        }

        $this->conversations = auth()->user()->conversations()
            ->latest('updated_at')
            ->limit(20)
            ->get()
            ->map(
                fn($conversation) => [
                    'id' => $conversation->id,
                    'title' => Str::limit($conversation->title ?: 'New Conversation', 34),
                ],
            )
            ->all();
    }

    public function newConversation(): void
    {
        $this->activeConversationId = null;

        $this->dispatch('new-conversation');
    }

    // Delete Conversation
    public function deleteConversation(string $conversationId): void
    {
        $wasActiveConversation = $this->activeConversationId === $conversationId;
        $conversation = auth()->user()->conversations()->findOrFail($conversationId);

        DB::transaction(function () use ($conversation) {
            $conversation->messages()->delete();
            $conversation->delete();
        });

        if ($wasActiveConversation) {
            $this->redirectRoute('chat', navigate: true);

            return;
        }

        // Refresh the conversations
        $this->loadConversations();
    }
};
?>

<style>
    [x-cloak] {
        display: none !important;
    }
</style>

{{-- Sidebar --}}
<aside class="w-64 flex-shrink-0 flex flex-col bg-zinc-900 border-r border-zinc-800">

    {{-- Logo --}}
    <div class="p-5 border-b border-zinc-800">
        <div class="flex items-center gap-3">
            <div
                class="w-8 h-8 rounded-lg bg-gradient-to-br from-violet-500 to-indigo-600 flex items-center justify-center text-white text-sm font-bold shadow-lg">
                L
            </div>

            <div>
                <p class="text-sm font-semibold text-white">LaraChat </p>
                <p class="text-xs text-zinc-500">Powered by Gemini</p>
            </div>
        </div>
    </div>

    {{-- New chat button --}}
    <div class="p-4">
        <button wire:click="newConversation"
            class="w-full flex items-center gap-2 px-4 py-2.5 rounded-lg border border-zinc-700 text-zinc-400 hover:bg-zinc-800 hover:text-white transition-all duration-200 text-sm cursor-pointer">+
            New Conversation</button>
    </div>

    {{-- Render Conversations --}}
    <div class="flex-1 overflow-y-auto px-2 pb-4 space-y-1" x-data="{ openMenu: null }">

        @forelse ($conversations as $conversation)
            <div
                class="relative group rounded-lg transition-colors {{ $activeConversationId === $conversation['id'] ? 'bg-zinc-800 text-white' : 'text-zinc-400 hover:bg-zinc-800/70 hover:text-white' }}">

                <a href="{{ route('chat', ['conversationId' => $conversation['id']]) }}"
                    class="block truncate cursor-pointer w-full text-left px-2 pr-5 py-2 rounded-lg text-sm ">
                    {{ $conversation['title'] }}</a>

                {{-- Three dots button --}}
                <button
                    x-on:click.stop="openMenu = openMenu === @js($conversation['id']) ? null : @js($conversation['id'])"
                    class="cursor-pointer absolute right-2 top-1/2 -translate-y-1/2 rounded-md p-1 text-zinc-500 opacity-0 transition hover:bg-zinc-700 hover:text-white group-hover:opacity-100">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                        <circle cx="5" cy="12" r="1.8" />
                        <circle cx="12" cy="12" r="1.8" />
                        <circle cx="19" cy="12" r="1.8" />
                    </svg>
                </button>

                {{-- Dropdown menu --}}
                <div x-cloak x-show="openMenu === @js($conversation['id'])" x-on:click.outside="openMenu = null"
                    class="absolute right-2 top-9 z-50 w-36 rounded-lg border border-zinc-700 bg-zinc-900 p-1 shadow-xl">
                    <button wire:click.stop="deleteConversation('{{ $conversation['id'] }}')"
                        wire:confirm="Delete this conversation?" x-on:click="openMenu = null"
                        class="cursor-pointer flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm text-red-400 hover:bg-red-500/10">

                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                            <path d="M3 6h18" />
                            <path d="M8 6V4h8v2" />
                            <path d="M6 6l1 14h10l1-14" />
                        </svg>
                        Delete</button>
                </div>

            </div>
        @empty

            <p class="px-3 py-2 text-xs text-zinc-600">No conversations yet</p>
        @endforelse

    </div>



    {{-- Status footer --}}
    <div class="p-4 border-t border-zinc-800 space-y-2">
        <div class="flex items-center gap-2 px-2 py-2 rounded-lg bg-zinc-800/60">
            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
            <span class="text-xs text-zinc-400">Gemini 3.6 Flash </span>
        </div>

        <p class="text-xs text-zinc-600 px-2">Built with Laravel 13 AI SDK </p>
    </div>
</aside>
