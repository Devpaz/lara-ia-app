<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Laravel\Ai\Models\ConversationMessage;
use LogicException;

class TokenUsageSummary
{
    /**
     * @return array<string, array{input_tokens: int, output_tokens: int, total_tokens: int}>
     */
    public function forUser(User $user, ?CarbonInterface $now = null): array
    {
        $now = $now === null
            ? CarbonImmutable::now()
            : CarbonImmutable::instance($now);

        /** @var Collection<int, array{created_at: CarbonImmutable, input_tokens: int, output_tokens: int}> $records */
        $records = ConversationMessage::query()
            ->where('participant_type', $user->getMorphClass())
            ->where('participant_id', $user->getKey())
            ->where('role', 'assistant')
            ->whereBetween('created_at', [$now->startOfYear(), $now])
            ->get(['created_at', 'usage'])
            ->map($this->recordFrom(...));

        return [
            'day' => $this->sumFrom($records, $now->startOfDay()),
            'week' => $this->sumFrom($records, $now->startOfWeek()),
            'month' => $this->sumFrom($records, $now->startOfMonth()),
            'year' => $this->sumFrom($records, $now->startOfYear()),
        ];
    }

    /**
     * @param  Collection<int, array{created_at: CarbonImmutable, input_tokens: int, output_tokens: int}>  $records
     * @return array{input_tokens: int, output_tokens: int, total_tokens: int}
     */
    private function sumFrom(Collection $records, CarbonImmutable $start): array
    {
        $periodRecords = $records->where('created_at', '>=', $start);
        $inputTokens = $periodRecords->sum('input_tokens');
        $outputTokens = $periodRecords->sum('output_tokens');

        return [
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'total_tokens' => $inputTokens + $outputTokens,
        ];
    }

    /**
     * @return array{created_at: CarbonImmutable, input_tokens: int, output_tokens: int}
     */
    private function recordFrom(ConversationMessage $message): array
    {
        $createdAt = $message->getAttribute('created_at');
        $usage = $message->getAttribute('usage');

        if (! $createdAt instanceof CarbonInterface) {
            throw new LogicException('AI conversation messages must have a creation timestamp.');
        }

        return [
            'created_at' => CarbonImmutable::instance($createdAt),
            'input_tokens' => (int) data_get($usage, 'input_tokens', 0),
            'output_tokens' => (int) data_get($usage, 'output_tokens', 0),
        ];
    }
}
