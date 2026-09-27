<?php

use App\Ai\Agents\ChatAgent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('a chat upload stays available to subsequent turns', function () {
    Storage::fake('s3');
    ChatAgent::fake(fn (string $prompt): string => $prompt === 'What did I upload?'
        ? 'The document is still available.'
        : 'Document received.')->preventStrayPrompts();

    $attachmentToken = null;

    Livewire::test('chat-box')
        ->set('attachment', UploadedFile::fake()->createWithContent('report.pdf', '%PDF-1.4 test document'))
        ->call('send')
        ->assertDispatched('start-stream', function (string $event, array $parameters) use (&$attachmentToken): bool {
            $attachmentToken = $parameters['attachmentToken'] ?? null;

            return $event === 'start-stream' && is_string($attachmentToken) && ! isset($parameters['attachmentPath']);
        });

    $path = Crypt::decrypt($attachmentToken)['path'];

    Storage::disk('s3')->assertExists($path);

    $first = $this->postJson(route('chat.stream'), [
        'message' => 'Summarize the attachment',
        'attachment_token' => $attachmentToken,
    ])->assertOk();

    $content = $first->streamedContent();
    expect($content)->toContain('"content":"Document"', '"content":" received."', 'conversation_id');

    $conversationId = DB::table('agent_conversations')->value('id');
    $storedAttachment = json_decode(DB::table('agent_conversation_messages')->where('role', 'user')->value('attachments'), true)[0];

    expect($storedAttachment)->toMatchArray(['type' => 'stored-document', 'disk' => 's3', 'path' => $path]);
    expect((new ChatAgent)->continue($conversationId)->messages()[0]->attachments->first()->content())
        ->toBe('%PDF-1.4 test document');

    $second = $this->postJson(route('chat.stream'), [
        'message' => 'What did I upload?',
        'conversation_id' => $conversationId,
    ])->assertOk()->assertStreamed();

    expect($second->streamedContent())->toContain('"content":"The"', 'conversation_id');
    Storage::disk('s3')->assertExists($path);
    expect(DB::table('agent_conversation_messages')->where('conversation_id', $conversationId)->count())->toBe(4);
});

test('the stream rejects an untrusted or expired attachment reference', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('chat-attachments/report.pdf', '%PDF-1.4 test document');
    ChatAgent::fake()->preventStrayPrompts();

    $this->postJson(route('chat.stream'), [
        'message' => 'Read it',
        'attachment_token' => 'not-an-encrypted-token',
    ])->assertUnprocessable()->assertJsonValidationErrors('attachment_token');

    $this->postJson(route('chat.stream'), [
        'message' => 'Read it',
        'attachment_token' => Crypt::encrypt([
            'path' => 'chat-attachments/report.pdf',
            'mime' => 'application/pdf',
            'expires_at' => now()->subMinute()->timestamp,
        ]),
    ])->assertUnprocessable()->assertJsonValidationErrors('attachment_token');

    Storage::disk('s3')->assertExists('chat-attachments/report.pdf');
    ChatAgent::assertNeverPrompted();
});

test('an uploaded image is stored as a reusable image attachment', function () {
    Storage::fake('s3');
    ChatAgent::fake(['Image received.'])->preventStrayPrompts();

    $attachmentToken = null;

    Livewire::test('chat-box')
        ->set('attachment', UploadedFile::fake()->image('photo.png'))
        ->call('send')
        ->assertDispatched('start-stream', function (string $event, array $parameters) use (&$attachmentToken): bool {
            $attachmentToken = $parameters['attachmentToken'] ?? null;

            return $event === 'start-stream' && is_string($attachmentToken);
        });

    $response = $this->postJson(route('chat.stream'), [
        'message' => 'Describe this image',
        'attachment_token' => $attachmentToken,
    ])->assertOk();

    expect($response->streamedContent())->toContain('conversation_id');

    $attachment = json_decode(DB::table('agent_conversation_messages')->where('role', 'user')->value('attachments'), true)[0];

    expect($attachment)->toMatchArray(['type' => 'stored-image', 'disk' => 's3']);
    Storage::disk('s3')->assertExists($attachment['path']);
});
