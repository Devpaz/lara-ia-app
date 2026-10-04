<?php

use App\Ai\Agents\ChatAgent;
use App\Models\User;
use App\Services\TokenUsageSummary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

test('token usage is summarized by period for the authenticated user', function () {
    $this->travelTo('2026-10-15 12:00:00');

    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $storeUsage = function (User $participant, string $createdAt, int $inputTokens, int $outputTokens): void {
        DB::table('agent_conversation_messages')->insert([
            'id' => (string) Str::uuid(),
            'conversation_id' => (string) Str::uuid(),
            'participant_type' => $participant->getMorphClass(),
            'participant_id' => $participant->getKey(),
            'agent' => ChatAgent::class,
            'role' => 'assistant',
            'content' => 'Response',
            'attachments' => '[]',
            'steps' => '[]',
            'usage' => json_encode([
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
            ]),
            'meta' => '[]',
            'status' => 'completed',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    };

    $storeUsage($user, '2026-10-15 09:00:00', 10, 1);
    $storeUsage($user, '2026-10-13 09:00:00', 20, 2);
    $storeUsage($user, '2026-10-02 09:00:00', 30, 3);
    $storeUsage($user, '2026-06-01 09:00:00', 40, 4);
    $storeUsage($user, '2025-12-31 09:00:00', 100, 10);
    $storeUsage($otherUser, '2026-10-15 09:00:00', 1000, 100);

    $usage = app(TokenUsageSummary::class)->forUser($user);

    expect($usage)->toBe([
        'day' => ['input_tokens' => 10, 'output_tokens' => 1, 'total_tokens' => 11],
        'week' => ['input_tokens' => 30, 'output_tokens' => 3, 'total_tokens' => 33],
        'month' => ['input_tokens' => 60, 'output_tokens' => 6, 'total_tokens' => 66],
        'year' => ['input_tokens' => 100, 'output_tokens' => 10, 'total_tokens' => 110],
    ]);

    Livewire::actingAs($user)
        ->test('conversation-sidebar')
        ->assertSet('tokenUsage', $usage);
});

test('historical assistant messages display their token usage', function () {
    $user = User::factory()->create();
    $conversation = $user->conversations()->create([
        'id' => (string) Str::uuid(),
        'title' => 'Token history',
    ]);

    $conversation->messages()->create([
        'id' => (string) Str::uuid(),
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
        'agent' => ChatAgent::class,
        'role' => 'assistant',
        'content' => 'Measured response',
        'attachments' => [],
        'steps' => [],
        'usage' => ['input_tokens' => 123, 'output_tokens' => 45],
        'meta' => [],
        'status' => 'completed',
    ]);

    $this->actingAs($user)
        ->get(route('chat', ['conversationId' => $conversation->getKey()]))
        ->assertSee('123')
        ->assertSee('45')
        ->assertSee('168 total');
});

test('a completed streamed message keeps its token usage', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('chat-box')
        ->set('messages', [[
            'role' => 'assistant',
            'content' => '',
            'streaming' => true,
        ]])
        ->call('onStreamComplete', 'Completed response', [
            'input_tokens' => 75,
            'output_tokens' => 25,
        ])
        ->assertSet('messages.0.input_tokens', 75)
        ->assertSet('messages.0.output_tokens', 25)
        ->assertSee('100 total');
});
