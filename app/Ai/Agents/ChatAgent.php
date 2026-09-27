<?php
namespace App\Ai\Agents;

use App\Ai\Tools\GetCurrentDateTime;
use App\Ai\Tools\SearchOrders;
use App\Ai\Tools\SearchProducts;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[MaxSteps(2)]
class ChatAgent implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable | string
    {
        return <<<PROMPT
        You are LaraChat, a smart and friendly AI assistant built with Laravel 13 and Google Gemini.

        Guidelines:
        - Be helpful, concise and friendly.
        - For code questions, provide clean examples.
        - Be honest when you don't know something.
        - Avoid unnecessary filler text.
        PROMPT;
    }

    /**
     * Get the tools available to the agent.
     *
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [
            new GetCurrentDateTime,
            new SearchProducts,
            new SearchOrders,
            // (new WebFetch)->max(2)->allow(['https://laravel.com/docs/13.x']),
            // // (new WebSearch)->max(5)->allow(['laravel.com', 'php.net']),
            // (new WebSearch)->location(
            //     city: 'Bengaluru',
            //     region: 'KA',
            //     country: 'IN'
            // ),
            // new ProductAnalyzerAgent,
        ];
    }
}