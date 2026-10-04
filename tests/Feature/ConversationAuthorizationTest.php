<?php

use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Livewire;

test('users only see their own conversations', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $ownConversation = $user->conversations()->create([
        'id' => (string) Str::uuid(),
        'title' => 'My conversation',
    ]);
    $otherUser->conversations()->create([
        'id' => (string) Str::uuid(),
        'title' => 'Private conversation',
    ]);

    Livewire::actingAs($user)
        ->test('conversation-sidebar')
        ->assertViewHas('conversations', fn (array $conversations): bool => count($conversations) === 1)
        ->assertSee('My conversation')
        ->assertDontSee('Private conversation');

    $this->actingAs($user)
        ->get(route('chat', ['conversationId' => $ownConversation->getKey()]))
        ->assertOk();
});

test('users cannot open another users conversation', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $conversation = $otherUser->conversations()->create([
        'id' => (string) Str::uuid(),
        'title' => 'Private conversation',
    ]);

    $this->actingAs($user)
        ->get(route('chat', ['conversationId' => $conversation->getKey()]))
        ->assertForbidden();

    $this->postJson(route('chat.stream'), [
        'message' => 'Continue',
        'conversation_id' => $conversation->getKey(),
    ])->assertForbidden();
});
