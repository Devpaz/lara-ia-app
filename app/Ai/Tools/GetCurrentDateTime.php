<?php
namespace App\Ai\Tools;

use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetCurrentDateTime implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable | string
    {
        return 'Get the current date, time, and day of the weeks.'
            . 'Use this whenever the user asks about today\'s date,'
            . 'the current time, or what day it is.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable | string
    {
        $now = Carbon::now();

        $format = $request['format'] ?? 'full';

        return match ($format) {
            'date'  => $now->toFormattedDateString(),
            'time'  => $now->format('h:i A'),
            default => $now->format('l, F j, Y - h:i A'),
        };
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'format' => $schema->string()
                ->enum(['full', 'date', 'time'])
                ->description('The format of the response: full (date + time + day), date only, or time only.'),
        ];
    }
}