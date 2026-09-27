<?php
namespace App\Ai\Agents;

use App\Ai\Tools\SearchProducts;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\CanActAsTool;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

#[MaxSteps(3)]
#[Timeout(120)]
class ProductAnalyzerAgent implements Agent, HasStructuredOutput, HasTools, CanActAsTool
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable | string
    {
        return 'You are a product analysis specialist. '
            . 'When given a product name or category, analyze it based on the available data. '
            . 'Use the SearchProducts tool to look up real product information from the database. '
            . 'Provide honest scores, practical pros and cons, and suggest cheaper alternatives when available. '
            . 'Always base your analysis on actual product data, not assumptions.';
    }

    /**
     * Get the agent's tool name.
     */
    public function name(): string
    {
        return 'product_analyzer';
    }

    /**
     * Get the agent's tool description.
     */
    public function description(): string
    {
        return 'Analyze a product and return a structured evaluation with scrole, '
            . 'pros, cons, verdict, and a cheaper alternative. '
            . 'Use this when the user asks for product analysis, comparison, '
            . 'or recommendations with detailed breakdown.';
    }

    /**
     * Get the tools available to the agent.
     *
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [
            new SearchProducts,
        ];
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'product'      => $schema->string()
                ->description('The name of the analyzed product')
                ->required(),

            'score'        => $schema->integer()
                ->min(1)
                ->max(10)
                ->description('Overall product score from 1 to 10')
                ->required(),

            'verdict'      => $schema->string()
                ->description('One-sentence summary of the product analysis')
                ->required(),

            'pros'         => $schema->array()
                ->items($schema->string())
                ->description('List of product advantages')
                ->required(),

            'cons'         => $schema->array()
                ->items($schema->string())
                ->description('List of product disadvantages')
                ->required(),

            'alternatives' => $schema->object(fn($schema) => [
                'name'   => $schema->string()->description('Name of the cheaper alternative product')->required(),
                'price'  => $schema->number()->description('Price of the alternative in INR')->required(),
                'reason' => $schema->string()->description('Why this alternative is a good option')->required(),
            ])->description('A cheaper alternative product recommendation')->required(),
        ];
    }
}