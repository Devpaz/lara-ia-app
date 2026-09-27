<?php
namespace App\Http\Controllers;

use App\Ai\Agents\ChatAgent;
use GuzzleHttp\Exception\RequestException as ExceptionRequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Files;
use Laravel\Ai\Streaming\Events\TextDelta;

class StreamController extends Controller
{
    public function __invoke(Request $request)
    {
        set_time_limit(0);

        $request->validate([
            'message'         => ['nullable', 'string', 'max: 2000'],
            'conversation_id' => ['nullable', 'string'],
            'attachment_path' => ['nullable', 'string'],
            'attachment_mime' => ['nullable', 'string'],
        ]);

        $message        = $request->input('message');
        $conversationId = $request->input('conversation_id');
        $attachmentPath = $request->input('attachment_path');
        $attachmentMime = $request->input('attachment_mime');

        Log::debug($attachmentMime . " " . $attachmentPath);

        Log::debug('AI stream conversation returned', [
            'conversation_id' => $conversationId,
        ]);

        return response()->stream(function () use ($message, $conversationId, $attachmentPath, $attachmentMime) {

            try {
                $agent       = new ChatAgent();
                $participant = (object) ['id' => 'guest'];

                if ($conversationId) {
                    $agent->continue($conversationId, $participant);
                } else {
                    $agent->forUser($participant);
                }

                // Build attachment array
                $attachments = [];

                if ($attachmentPath && file_exists($attachmentPath)) {
                    $attachments[] = str_starts_with($attachmentMime, 'image/') ? Files\Image::fromPath($attachmentPath) : Files\Document::fromPath($attachmentPath);
                }

                \Log::debug($attachments);

                $stream = $agent->stream($message, attachments: $attachments, timeout: 120);

                foreach ($stream as $event) {
                    if ($event instanceof TextDelta) {
                        echo 'data: ' . json_encode([
                            'content' => $event->delta,
                        ]) . "\n\n";

                        ob_flush();
                        flush();
                    }
                }

                // Clean up the temp attachment file after use
                if ($attachmentPath && file_exists($attachmentPath)) {
                    @unlink($attachmentPath);
                }

                $stream->then(function ($response) use ($attachmentPath) {

                    Log::debug('AI stream conversation returned', [
                        'Response Conversation Id' => $response->conversationId,
                    ]);

                    echo 'data: ' . json_encode([
                        'conversation_id' => $response->conversationId,
                    ]) . "\n\n";

                    ob_flush();
                    flush();
                });

            } catch (ExceptionRequestException $e) {
                Log::error('Gemini request failed', [
                    'status' => $e->response?->status(),
                    'body'   => $e->response?->body(),
                ]);

                echo 'data: ' . json_encode([
                    'error' => 'Gemini request failed. Check the Laravel log.',
                ]) . "\n\n";

            } catch (RateLimitedException $e) {
                $previous = $e->getPrevious();

                Log::warning('Gemini rate limited', [
                    'body'        => $previous?->response?->body(),
                    'retry_after' => $previous?->response?->header('Retry-After'),
                ]);

                echo 'data: ' . json_encode([
                    'error' => 'Gemini is temporarily rate limited. Please retry shortly.',
                ]) . "\n\n";
            } catch (\Throwable $e) {
                report($e);

                echo 'data: ' . json_encode([
                    'error' => 'Something went wrong. Please try again!',
                ]) . "\n\n";
            }

            echo "data: [DONE]\n\n";
            ob_flush();
            flush();

        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);

        // ->prompt($userMessage);
    }
}