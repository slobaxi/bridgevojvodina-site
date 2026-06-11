<?php

namespace App\Services;

use Illuminate\Support\Carbon;

class DoubleDummyAnalysisService
{
    private const DISPLAY_HANDS = [
        'N' => 'North',
        'S' => 'South',
        'E' => 'East',
        'W' => 'West',
    ];

    private const DISPLAY_STRAINS = ['NT', 'S', 'H', 'D', 'C'];

    private const STRAIN_LABELS = [
        'NT' => 'No Trump',
        'S' => 'Spades',
        'H' => 'Hearts',
        'D' => 'Diamonds',
        'C' => 'Clubs',
    ];

    public function __construct(
        protected BridgeScoringService $scoringService
    ) {}

    public function fromOptimumResultTable(array $rawTable, string $vulnerability, ?string $optimumScore = null): array
    {
        $table = $this->normalizePbnTable($rawTable);

        return [
            'engine' => 'pbn',
            'optimum_score' => $optimumScore,
            'table' => $table,
            'best_contract' => $this->bestContract($table, $vulnerability),
            'computed_at' => Carbon::now()->toIso8601String(),
        ];
    }

    private function normalizePbnTable(array $rawTable): array
    {
        $table = [];

        foreach (self::DISPLAY_HANDS as $hand => $handName) {
            $table[$hand] = [
                'label' => $handName,
                'strains' => [],
            ];

            foreach (self::DISPLAY_STRAINS as $strain) {
                $table[$hand]['strains'][$strain] = (int) ($rawTable[$hand][$strain] ?? 0);
            }
        }

        return $table;
    }

    private function bestContract(array $table, string $vulnerability): ?array
    {
        $best = null;

        foreach (self::DISPLAY_HANDS as $hand => $handName) {
            foreach (self::DISPLAY_STRAINS as $strain) {
                $tricks = (int) ($table[$hand]['strains'][$strain] ?? 0);
                $maxLevel = min(7, $tricks - 6);

                for ($level = 1; $level <= $maxLevel; $level++) {
                    $score = $this->scoringService->calculateScore(
                        $level,
                        $strain,
                        1,
                        $tricks,
                        $this->isVulnerable($hand, $vulnerability)
                    );

                    if ($best === null || $score > $best['score']) {
                        $best = [
                            'contract' => $level . $strain,
                            'level' => $level,
                            'strain' => $strain,
                            'strain_label' => self::STRAIN_LABELS[$strain],
                            'declarer' => $hand,
                            'declarer_label' => $handName,
                            'tricks' => $tricks,
                            'score' => $score,
                            'description' => "{$level} " . self::STRAIN_LABELS[$strain] . " by {$handName}. {$score} points.",
                        ];
                    }
                }
            }
        }

        return $best;
    }

    private function isVulnerable(string $hand, string $vulnerability): bool
    {
        return match ($vulnerability) {
            'All' => true,
            'NS' => in_array($hand, ['N', 'S'], true),
            'EW' => in_array($hand, ['E', 'W'], true),
            default => false,
        };
    }
}
