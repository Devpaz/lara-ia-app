<?php

use App\Services\TokenUsageSummary;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Number;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public array $conversations = [];
    public ?string $activeConversationId = null;
    public array $tokenUsage = [];

    public function mount(): void
    {
        $this->loadConversations();
        $this->loadTokenUsage();
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

    #[On('usage-updated')]
    public function loadTokenUsage(): void
    {
        $this->tokenUsage = app(TokenUsageSummary::class)->forUser(auth()->user());
    }

    public function logout(): void
    {
        Auth::logout();

        Session::invalidate();
        Session::regenerateToken();

        $this->redirectRoute('login', navigate: true);
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

    {{-- Personal token usage --}}
    <div class="border-t border-zinc-800 px-4 py-3">
        <p class="mb-2 px-1 text-[11px] font-medium uppercase tracking-wider text-zinc-600">Token usage</p>

        <div class="grid gap-1.5">
            @foreach (['day' => 'Today', 'week' => 'Week', 'month' => 'Month', 'year' => 'Year'] as $period => $label)
                <div wire:key="usage-{{ $period }}" class="flex items-center justify-between gap-2 px-1 text-xs">
                    <span class="text-zinc-500">{{ $label }}</span>
                    <span class="whitespace-nowrap text-zinc-400"
                        title="{{ number_format($tokenUsage[$period]['total_tokens'] ?? 0) }} total tokens">
                        <span class="text-sky-400">{{ Number::abbreviate($tokenUsage[$period]['input_tokens'] ?? 0, precision: 1) }}</span>
                        in ·
                        <span class="text-violet-400">{{ Number::abbreviate($tokenUsage[$period]['output_tokens'] ?? 0, precision: 1) }}</span>
                        out
                    </span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- User footer --}}
    <div class="border-t border-zinc-800 p-4">
        <div class="flex items-center gap-3 rounded-xl bg-zinc-800/60 p-3">
            <div
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-violet-600 text-xs font-semibold text-white">
                {{ auth()->user()->initials() }}
            </div>

            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium text-white">{{ auth()->user()->name }}</p>
                <p class="truncate text-xs text-zinc-500">{{ auth()->user()->email }}</p>
            </div>

            <button type="button" wire:click="logout" wire:loading.attr="disabled" wire:target="logout"
                title="Log out" aria-label="Log out"
                class="flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-lg text-zinc-500 transition-colors hover:bg-zinc-700 hover:text-red-400 disabled:cursor-wait disabled:opacity-50">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M10 17l5-5-5-5m5 5H3m9-8h5a2 2 0 012 2v12a2 2 0 01-2 2h-5" />
                </svg>
            </button>
        </div>
    </div>
</aside>
