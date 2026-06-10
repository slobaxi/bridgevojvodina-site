<?php

namespace App\Services;

use App\Models\Board;
use Illuminate\Support\Carbon;
use RuntimeException;
use Symfony\Component\Process\Process;

class DoubleDummyAnalysisService
{
    private const DISPLAY_HANDS = [
        'N' => 'North',
        'S' => 'South',
        'E' => 'East',
        'W' => 'West',
    ];

    private const DDS_HAND_INDEX = [
        'N' => 0,
        'E' => 1,
        'S' => 2,
        'W' => 3,
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

    public function analyze(Board $board): array
    {
        $pbn = $this->boardToPbn($board);
        $engineResult = $this->runAnalyzer($pbn);
        $table = $this->normalizeTable($engineResult['table'] ?? []);

        return [
            'engine' => $engineResult['engine'] ?? 'dds',
            'pbn' => $pbn,
            'table' => $table,
            'best_contract' => $this->bestContract($table, $board->vulnerability),
            'computed_at' => Carbon::now()->toIso8601String(),
        ];
    }

    public function boardToPbn(Board $board): string
    {
        $hands = [
            'N' => $board->cards_north,
            'E' => $board->cards_east,
            'S' => $board->cards_south,
            'W' => $board->cards_west,
        ];

        foreach ($hands as $seat => $hand) {
            $cardCount = collect(['S', 'H', 'D', 'C'])
                ->sum(fn(string $suit): int => strlen($this->normalizeCards((string) ($hand[$suit] ?? ''))));

            if ($cardCount !== 13) {
                throw new RuntimeException("DDS analysis requires 13 cards in {$this->seatName($seat)}.");
            }
        }

        return 'N:' . collect(['N', 'E', 'S', 'W'])
            ->map(fn(string $seat): string => $this->handToPbn($hands[$seat]))
            ->implode(' ');
    }

    private function runAnalyzer(string $pbn): array
    {
        $command = config('services.dds_analyzer.command', storage_path('app/bin/dds_analyze'));
        $command = is_array($command) ? array_values($command) : [$command];
        $command[] = $pbn;

        if (empty($command[0]) || ! is_file($command[0])) {
            throw new RuntimeException('DDS analyzer binary is missing. Build storage/app/bin/dds_analyze first.');
        }

        $output = $this->canUsePhpFunction('proc_open')
            ? $this->runAnalyzerWithProcess($command)
            : $this->runAnalyzerWithExec($command);

        $decoded = json_decode($output, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('DDS analyzer returned invalid JSON.');
        }

        return $decoded;
    }

    private function runAnalyzerWithProcess(array $command): string
    {
        $process = new Process($command, base_path());
        $process->setTimeout((float) config('services.dds_analyzer.timeout', 30));
        $process->run();

        $output = trim($process->getOutput());
        $errorOutput = trim($process->getErrorOutput());

        if (! $process->isSuccessful()) {
            $message = $errorOutput !== '' ? $errorOutput : ($output !== '' ? $output : 'DDS analyzer failed.');
            throw new RuntimeException($message);
        }

        return $output;
    }

    private function runAnalyzerWithExec(array $command): string
    {
        if (! $this->canUsePhpFunction('exec')) {
            throw new RuntimeException('DDS analysis requires proc_open or exec, but both are disabled on this PHP installation.');
        }

        $previousDirectory = getcwd();

        if ($previousDirectory === false || ! chdir(base_path())) {
            throw new RuntimeException('DDS analyzer could not enter the application directory.');
        }

        try {
            $lines = [];
            $exitCode = 0;
            exec($this->toShellCommand($command) . ' 2>&1', $lines, $exitCode);
            $output = trim(implode("\n", $lines));
        } finally {
            chdir($previousDirectory);
        }

        if ($exitCode !== 0) {
            throw new RuntimeException($output !== '' ? $output : 'DDS analyzer failed.');
        }

        return $output;
    }

    private function toShellCommand(array $command): string
    {
        return collect($command)
            ->map(fn(string $part): string => escapeshellarg($part))
            ->implode(' ');
    }

    private function canUsePhpFunction(string $function): bool
    {
        if (! function_exists($function)) {
            return false;
        }

        $disabled = array_map(
            'trim',
            explode(',', (string) ini_get('disable_functions'))
        );

        return ! in_array($function, $disabled, true);
    }

    private function normalizeTable(array $engineTable): array
    {
        $table = [];

        foreach (self::DISPLAY_HANDS as $hand => $handName) {
            $table[$hand] = [
                'label' => $handName,
                'strains' => [],
            ];

            foreach (self::DISPLAY_STRAINS as $strain) {
                $handIndex = self::DDS_HAND_INDEX[$hand];
                $table[$hand]['strains'][$strain] = (int) ($engineTable[$strain][$handIndex] ?? 0);
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

    private function handToPbn(array $hand): string
    {
        return collect(['S', 'H', 'D', 'C'])
            ->map(fn(string $suit): string => $this->normalizeCards((string) ($hand[$suit] ?? '')))
            ->implode('.');
    }

    private function normalizeCards(string $cards): string
    {
        return strtoupper(str_replace('10', 'T', preg_replace('/\s+/', '', $cards) ?? ''));
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

    private function seatName(string $seat): string
    {
        return self::DISPLAY_HANDS[$seat] ?? $seat;
    }
}
