<?php

namespace App\Http\Controllers;

use App\Ai\Agents\ChatAgent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Files;
use Laravel\Ai\Streaming\Events\TextDelta;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        set_time_limit(0);

        $request->validate([
            'message' => ['required_without:attachment_token', 'nullable', 'string', 'max:2000'],
            'conversation_id' => ['nullable', 'string'],
            'attachment_token' => ['nullable', 'string'],
        ]);

        $message = $request->input('message') ?? '';
        $conversationId = $request->input('conversation_id');
        $attachment = null;
        $participant = $request->user();

        if ($conversationId !== null) {
            abort_unless($participant->conversations()->whereKey($conversationId)->exists(), 403);
        }

        if ($request->filled('attachment_token')) {
            try {
                $attachment = Crypt::decrypt($request->string('attachment_token')->toString());
            } catch (\Throwable) {
                throw ValidationException::withMessages(['attachment_token' => 'Invalid attachment. Please upload it again.']);
            }

            if (! is_array($attachment)
                || ! is_string($attachment['path'] ?? null)
                || ! str_starts_with($attachment['path'], 'chat-attachments/')
                || ! is_string($attachment['mime'] ?? null)
                || ! in_array($attachment['mime'], ['application/pdf', 'image/png', 'image/jpeg'], true)
                || (string) ($attachment['user_id'] ?? '') !== (string) $participant->getKey()
                || ! is_int($attachment['expires_at'] ?? null)
                || $attachment['expires_at'] < now()->timestamp
                || ! Storage::disk('s3')->exists($attachment['path'])) {
                throw ValidationException::withMessages(['attachment_token' => 'Attachment expired or unavailable. Please upload it again.']);
            }
        }

        return response()->stream(function () use ($message, $conversationId, $attachment, $participant) {

            try {
                $agent = new ChatAgent;

                if ($conversationId) {
                    $agent->continue($conversationId, $participant);
                } else {
                    $agent->forUser($participant);
                }

                $attachments = [];

                if ($attachment !== null) {
                    $attachments[] = str_starts_with($attachment['mime'], 'image/')
                        ? Files\Image::fromStorage($attachment['path'], disk: 's3')->withMimeType($attachment['mime'])
                        : Files\Document::fromStorage($attachment['path'], disk: 's3')->withMimeType($attachment['mime']);
                }

                $stream = $agent->stream($message, attachments: $attachments, timeout: 120);

                foreach ($stream as $event) {
                    if ($event instanceof TextDelta) {
                        echo 'data: '.json_encode([
                            'content' => $event->delta,
                        ])."\n\n";

                        ob_flush();
                        flush();
                    }
                }

                $stream->then(function ($response) {

                    Log::debug('AI stream conversation returned', [
                        'Response Conversation Id' => $response->conversationId,
                    ]);

                    echo 'data: '.json_encode([
                        'conversation_id' => $response->conversationId,
                        'usage' => [
                            'input_tokens' => $response->usage->inputTokens,
                            'output_tokens' => $response->usage->outputTokens,
                            'total_tokens' => $response->usage->totalTokens(),
                        ],
                    ])."\n\n";

                    ob_flush();
                    flush();
                });

            } catch (RateLimitedException $e) {
                $previous = $e->getPrevious();

                Log::warning('Gemini rate limited', [
                    'error' => $previous?->getMessage() ?? $e->getMessage(),
                ]);

                echo 'data: '.json_encode([
                    'error' => 'Gemini is temporarily rate limited. Please retry shortly.',
                ])."\n\n";
            } catch (\Throwable $e) {
                report($e);

                echo 'data: '.json_encode([
                    'error' => 'Something went wrong. Please try again!',
                ])."\n\n";
            }

            echo "data: [DONE]\n\n";
            ob_flush();
            flush();

        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);

    }
}
