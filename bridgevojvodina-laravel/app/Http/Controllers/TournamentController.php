<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\TournamentConfiguration;
use App\Models\BoardSet;
use App\Models\Board;
use App\Models\Player;
use App\DTOs\Tournament\RoundDTO;
use App\DTOs\Tournament\MatchDTO;
use App\DTOs\Tournament\MatchBoardDTO;
use App\DTOs\Tournament\TeamDTO;
use App\DTOs\Tournament\TournamentResultsDTO;
use App\Services\DoubleDummyAnalysisService;
use App\Services\TournamentHydrationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class TournamentController extends Controller
{
    public function __construct(
        protected TournamentHydrationService $hydrationService,
        protected \App\Services\VpCalculationService $vpService,
        protected \App\Services\BridgeScoringService $scoringService,
        protected DoubleDummyAnalysisService $doubleDummyAnalysisService
    ) {}

    protected function resolveTournament(string $id)
    {
        $draft = TournamentConfiguration::find($id);
        $published = Tournament::find($id);

        if ($draft && auth()->check()) {
            if (auth()->user()->isAdmin() || auth()->user()->isDirector() || auth()->id() === $draft->user_id) {
                return $draft;
            }
        }

        return $published ?? Tournament::findOrFail($id);
    }

    protected function authorizeTournament(\Illuminate\Database\Eloquent\Model $tournament)
    {
        if ($tournament instanceof Tournament) {
            Gate::authorize('update', $tournament);
        } else {
            if (!auth()->check()) {
                abort(401);
            }
            if (!auth()->user()->isAdmin() && !auth()->user()->isDirector() && auth()->id() !== $tournament->user_id) {
                abort(403);
            }
        }
    }

    public function index(): View
    {
        $published = Tournament::latest()->get();
        $publishedIds = $published->pluck('id')->toArray();
        $drafts = collect();
        
        if (auth()->check()) {
            $query = TournamentConfiguration::latest();
            if (!auth()->user()->isAdmin()) {
                $query->where('user_id', auth()->id());
            }
            // Exclude drafts that are already published
            $query->whereNotIn('id', $publishedIds);
            $drafts = $query->get();
        }
        
        $tournaments = $published->concat($drafts)->sortByDesc('created_at');
        
        return view('tournaments.index', compact('tournaments'));
    }

    public function create(): View
    {
        Gate::authorize('create', Tournament::class);
        return view('tournaments.create');
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Tournament::class);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'details' => 'nullable|string',
        ]);

        $tournamentConfiguration = TournamentConfiguration::create([
            'id' => Str::uuid(),
            'title' => $validated['title'],
            'description' => $validated['description'],
            'details' => $validated['details'],
            'user_id' => $request->user()->id,
            'team_results' => [
                'teams' => [],
                'rounds' => [],
                'bye_vp' => 12.0,
                'boards_per_round' => 16
            ],
        ]);

        return redirect()->route('tournaments.edit', $tournamentConfiguration->id)
            ->with('success', __('Tournament created successfully.'));
    }

    public function show(string $id): View|RedirectResponse
    {
        $tournament = $this->resolveTournament($id);

        if ($tournament instanceof TournamentConfiguration && $tournament->team_results) {
            $tournament->team_results->player_butlers = $this->hydrationService->calculatePlayerButlers($tournament->team_results);
        }

        $tournament->load(['boardSets' => function($q) {
            $q->withCount('boards');
        }]);

        return view('tournaments.show', compact('tournament'));
    }

    public function butler(string $id): View
    {
        $tournament = $this->resolveTournament($id);
        $results = $tournament->team_results;

        if ($tournament instanceof TournamentConfiguration && $results) {
            $results->player_butlers = $this->hydrationService->calculatePlayerButlers($results);
        }

        $butlerPlayers = collect();
        if ($results && !empty($results->player_butlers)) {
            $playerIds = collect($results->player_butlers)->pluck('player_id')->unique()->toArray();
            $butlerPlayers = \App\Models\Player::whereIn('id', $playerIds)->get()->keyBy('id');
        }

        return view('tournaments.butler', compact('tournament', 'butlerPlayers'));
    }

    public function details(string $id): View
    {
        $tournament = $this->resolveTournament($id);

        if ($tournament instanceof TournamentConfiguration && $tournament->team_results) {
            $tournament->team_results->player_butlers = $this->hydrationService->calculatePlayerButlers($tournament->team_results);
        }

        return view('tournaments.details', compact('tournament'));
    }

    public function showTeam(string $tournamentId, string $teamId): View
    {
        $tournament = $this->resolveTournament($tournamentId);
        $results = $tournament->team_results;

        if (!$results) {
            abort(404);
        }

        $team = collect($results->teams)->firstWhere('id', $teamId);
        if (!$team) {
            abort(404);
        }

        $players = Player::whereIn('id', $team->player_ids)->orderBy('last_name')->get();

        return view('tournaments.teams.show', compact('tournament', 'team', 'players'));
    }

    public function match(string $tournamentId, string $roundId, string $matchId): View
    {
        $tournament = $this->resolveTournament($tournamentId);
        $results = $tournament->team_results;
        if (!$results) {
            abort(404);
        }

        $round = collect($results->rounds)->firstWhere('id', $roundId);
        if (!$round) {
            abort(404);
        }

        $match = collect($round->matches)->firstWhere('id', $matchId)
            ?? collect($round->matches)->firstWhere('home_team_id', $matchId);
            
        if (!$match) {
            abort(404);
        }

        $this->hydrationService->hydrateMatch($match);

        return view('tournaments.match', compact('tournament', 'round', 'match', 'results'));
    }

    public function board(string $tournamentId, string $roundId, int $boardNumber): View
    {
        $tournament = $this->resolveTournament($tournamentId);
        $results = $tournament->team_results;
        if (!$results) {
            abort(404);
        }

        $round = collect($results->rounds)->firstWhere('id', $roundId);
        if (!$round) {
            abort(404);
        }

        $boardResults = [];
        foreach ($round->matches as $match) {
            $matchBoard = collect($match->boards)->firstWhere('board_number', $boardNumber);
            if ($matchBoard) {
                $boardResults[] = [
                    'match' => $match,
                    'board' => $matchBoard,
                    'home_team' => collect($results->teams)->firstWhere('id', $match->home_team_id),
                    'away_team' => collect($results->teams)->firstWhere('id', $match->away_team_id),
                ];
            }
        }

        $boardData = $this->hydrationService->getBoardData($round, $boardNumber);

        // Calculate all available board numbers in this round
        $allBoardNumbers = collect($round->matches)
            ->flatMap(fn($m) => collect($m->boards)->pluck('board_number'))
            ->unique()
            ->sort()
            ->values();

        $currentIndex = $allBoardNumbers->search($boardNumber);
        $prevBoard = $currentIndex > 0 ? $allBoardNumbers[$currentIndex - 1] : null;
        $nextBoard = $currentIndex !== false && $currentIndex < $allBoardNumbers->count() - 1 
            ? $allBoardNumbers[$currentIndex + 1] 
            : null;

        // Calculate Datum
        $nsScores = [];
        foreach ($boardResults as $res) {
            if ($res['board']->home_score !== null) $nsScores[] = $res['board']->home_score;
            if ($res['board']->away_score !== null) $nsScores[] = $res['board']->away_score;
        }
        
        $datum = null;
        if (count($nsScores) > 0) {
            // Simple average for now. In professional setups, extremes are often trimmed.
            $datum = array_sum($nsScores) / count($nsScores);
        }

        $playerIds = collect($round->matches)->flatMap(fn($m) => array_merge($m->open_ns_ids, $m->open_ew_ids, $m->closed_ns_ids, $m->closed_ew_ids))->unique()->filter();
        $players = \App\Models\Player::whereIn('id', $playerIds)->get()->keyBy('id');

        return view('tournaments.board', compact('tournament', 'round', 'boardNumber', 'boardResults', 'boardData', 'results', 'datum', 'prevBoard', 'nextBoard', 'players'));
    }

    public function formatTricksFromLevel($level, $tricks): string
    {
        if (!$level || $tricks === null || !is_numeric($level)) return '';
        $diff = (int)$tricks - (6 + (int)$level);
        return $diff === 0 ? '=' : ($diff > 0 ? '+' . $diff : (string)$diff);
    }

    public function edit(Request $request, string $id): View
    {
        $tournament = $this->resolveTournament($id);
        $this->authorizeTournament($tournament);

        if ($tournament instanceof TournamentConfiguration && $tournament->team_results) {
            $tournament->team_results->player_butlers = $this->hydrationService->calculatePlayerButlers($tournament->team_results);
        }

        if (!$tournament->team_results) {
            $tournament->team_results = new TournamentResultsDTO(
                teams: [],
                rounds: [],
                bye_vp: 12.0,
                boards_per_round: 16
            );
            $tournament->save();
        }

        $boardSets = $tournament->boardSets()->get();

        return view('tournaments.edit', compact('tournament', 'boardSets'));
    }

    public function publish(Request $request, string $id): RedirectResponse
    {
        $tournamentConfiguration = TournamentConfiguration::findOrFail($id);
        $this->authorizeTournament($tournamentConfiguration);
        
        $tournament = $tournamentConfiguration->publishToTournament();

        return redirect()->route('tournaments.show', $tournament)
            ->with('success', __('Tournament published successfully.'));
    }

    public function showBoardSet(string $tournamentId, BoardSet $boardSet): View
    {
        $tournament = $this->resolveTournament($tournamentId);

        if ($boardSet->tournament_id !== $tournament->id && $boardSet->tournament_configuration_id !== $tournament->id) {
            abort(404);
        }

        $boardSet->load('boards');
        $boards = $boardSet->boards->sortBy('board_number');

        $results = $tournament->team_results;
        $boardResults = [];

        if ($results) {
            // Find ALL rounds that use this board set (could be multiple if set is reused)
            $relevantRounds = collect($results->rounds)->where('board_set_id', $boardSet->id);

            if ($relevantRounds->isNotEmpty()) {
                // Pre-load all players for this tournament for faster lookup
                $playerIds = collect($results->teams)->flatMap->player_ids->unique();
                $players = Player::whereIn('id', $playerIds)->get()->keyBy('id');

                foreach ($relevantRounds as $round) {
                    foreach ($round->matches as $match) {
                        if (!$match->home_team_id || !$match->away_team_id || $match->home_team_id === 'bye' || $match->away_team_id === 'bye') {
                            continue;
                        }

                        $homeTeam = collect($results->teams)->firstWhere('id', $match->home_team_id);
                        $awayTeam = collect($results->teams)->firstWhere('id', $match->away_team_id);

                        // Helper to get player names (last names joined by hyphen)
                        $getNames = function($ids) use ($players) {
                            if (empty($ids)) return '-';
                            return collect($ids)->map(fn($id) => optional($players->get($id))->last_name)->filter()->implode(' - ');
                        };

                        $openNs = $getNames($match->open_ns_ids);
                        $openEw = $getNames($match->open_ew_ids);
                        $closedNs = $getNames($match->closed_ns_ids);
                        $closedEw = $getNames($match->closed_ew_ids);

                        foreach ($match->boards as $boardData) {
                            // Add Open Room result if it exists
                            if ($boardData->home_score !== null) {
                                $boardResults[$boardData->board_number][] = [
                                    'round_name' => $round->name,
                                    'home_team' => $homeTeam->name ?? 'Unknown',
                                    'away_team' => $awayTeam->name ?? 'Unknown',
                                    'room' => 'Open',
                                    'ns_names' => $openNs,
                                    'ew_names' => $openEw,
                                    'contract' => $boardData->home_contract,
                                    'score' => $boardData->home_score,
                                    'home_imp' => $boardData->home_imp,
                                    'away_imp' => $boardData->away_imp,
                                ];
                            }
                            
                            // Add Closed Room result if it exists
                            if ($boardData->away_score !== null) {
                                $boardResults[$boardData->board_number][] = [
                                    'round_name' => $round->name,
                                    'home_team' => $homeTeam->name ?? 'Unknown',
                                    'away_team' => $awayTeam->name ?? 'Unknown',
                                    'room' => 'Closed',
                                    'ns_names' => $closedNs,
                                    'ew_names' => $closedEw,
                                    'contract' => $boardData->away_contract,
                                    'score' => $boardData->away_score,
                                    'home_imp' => $boardData->home_imp,
                                    'away_imp' => $boardData->away_imp,
                                ];
                            }
                        }
                    }
                }
            }
        }

        return view('tournaments.board-sets.show', compact('tournament', 'boardSet', 'boards', 'boardResults'));
    }

    public function destroyBoardSet(string $tournamentId, BoardSet $boardSet): RedirectResponse
    {
        $tournament = $this->resolveTournament($tournamentId);
        $this->authorizeTournament($tournament);

        if ($boardSet->tournament_id !== $tournament->id && $boardSet->tournament_configuration_id !== $tournament->id) {
            abort(404);
        }

        DB::transaction(function () use ($tournament, $boardSet) {
            // Unlink from rounds
            $results = $tournament->team_results;
            if ($results) {
                foreach ($results->rounds as $round) {
                    if ($round->board_set_id == $boardSet->id) {
                        $round->board_set_id = null;
                    }
                }
                $tournament->team_results = $results;
                $tournament->save();
            }

            $boardSet->delete();
        });

        return redirect()->route('tournaments.edit', $tournament)
            ->with('success', __('Board set deleted successfully.'));
    }

    public function updateBoard(Request $request, string $tournamentId, BoardSet $boardSet, Board $board): RedirectResponse
    {
        $tournament = $this->resolveTournament($tournamentId);
        $this->authorizeTournament($tournament);

        if ($boardSet->tournament_id !== $tournament->id && $boardSet->tournament_configuration_id !== $tournament->id) {
            abort(404);
        }

        if ($board->board_set_id !== $boardSet->id) {
            abort(404);
        }

        $validated = $request->validate([
            'board_number' => 'required|integer|min:1|max:256',
            'vulnerability' => 'required|string|in:None,NS,EW,All',
            'cards' => 'required|array',
            'cards.N' => 'required|array',
            'cards.S' => 'required|array',
            'cards.E' => 'required|array',
            'cards.W' => 'required|array',
            'cards.*.S' => 'nullable|string',
            'cards.*.H' => 'nullable|string',
            'cards.*.D' => 'nullable|string',
            'cards.*.C' => 'nullable|string',
        ]);

        if ($boardSet->boards()
            ->where('board_number', (int) $validated['board_number'])
            ->whereKeyNot($board->id)
            ->exists()) {
            return back()->withErrors([
                'board_edit' => __('Board number must be unique within this board set.'),
            ])->withInput();
        }

        try {
            $hands = $this->normalizeAndValidateBoardHands($validated['cards']);
        } catch (\InvalidArgumentException $exception) {
            return back()->withErrors([
                'board_edit' => $exception->getMessage(),
            ])->withInput();
        }

        $board->update([
            'board_number' => (int) $validated['board_number'],
            'vulnerability' => $validated['vulnerability'],
            'cards_north' => $hands['N'],
            'cards_south' => $hands['S'],
            'cards_east' => $hands['E'],
            'cards_west' => $hands['W'],
            'double_dummy_analysis' => null,
        ]);

        return back()->with('success', __('Board updated successfully. Imported double dummy analysis was cleared.'));
    }

    private function normalizeAndValidateBoardHands(array $cards): array
    {
        $seats = ['N' => 'North', 'S' => 'South', 'E' => 'East', 'W' => 'West'];
        $suits = ['S', 'H', 'D', 'C'];
        $rankOrder = 'AKQJT98765432';
        $seen = [];
        $normalizedHands = [];

        foreach ($seats as $seat => $seatName) {
            $normalizedHands[$seat] = [];
            $handCount = 0;

            foreach ($suits as $suit) {
                $holding = strtoupper(str_replace('10', 'T', preg_replace('/\s+/', '', (string) ($cards[$seat][$suit] ?? '')) ?? ''));
                $holdingRanks = str_split($holding);
                $normalizedHolding = '';

                foreach ($holdingRanks as $rank) {
                    if (! str_contains($rankOrder, $rank)) {
                        throw new \InvalidArgumentException(__('Invalid card ":card" in :seat.', [
                            'card' => $suit . $rank,
                            'seat' => $seatName,
                        ]));
                    }

                    $card = $suit . $rank;
                    if (isset($seen[$card])) {
                        throw new \InvalidArgumentException(__('Duplicate card: :card.', ['card' => $card]));
                    }

                    $seen[$card] = true;
                    $normalizedHolding .= $rank;
                    $handCount++;
                }

                $normalizedHands[$seat][$suit] = $normalizedHolding;
            }

            if ($handCount !== 13) {
                throw new \InvalidArgumentException(__('Each hand must contain exactly 13 cards. :seat has :count.', [
                    'seat' => $seatName,
                    'count' => $handCount,
                ]));
            }
        }

        if (count($seen) !== 52) {
            throw new \InvalidArgumentException(__('A board must contain exactly 52 unique cards.'));
        }

        return $normalizedHands;
    }

    private function normalizePbnVulnerability(string $vulnerability): string
    {
        return match (strtoupper(str_replace(['-', ' '], '', $vulnerability))) {
            'NS', 'NORTHSOUTH' => 'NS',
            'EW', 'EASTWEST' => 'EW',
            'ALL', 'BOTH' => 'All',
            default => 'None',
        };
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $tournament = $this->resolveTournament($id);
        $this->authorizeTournament($tournament);

        $rules = [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'details' => 'nullable|string',
            'bye_vp' => 'nullable|numeric|min:0|max:20',
            'boards_per_round' => 'nullable|integer|min:1|max:128',
        ];
        
        if ($tournament instanceof Tournament) {
            $rules['is_completed'] = 'boolean';
        }

        $validated = $request->validate($rules);

        $results = $tournament->team_results;
        if ($results) {
            if (isset($validated['bye_vp'])) {
                $results->bye_vp = (float)$validated['bye_vp'];
            }
            if (isset($validated['boards_per_round'])) {
                $results->boards_per_round = (int)$validated['boards_per_round'];
            }
            $tournament->team_results = $results;
            $this->hydrationService->recalculateStandings($results);
        }

        if ($tournament instanceof Tournament) {
            $validated['is_completed'] = $request->has('is_completed');
        }

        $tournament->update(collect($validated)->only(['title', 'description', 'details', 'is_completed'])->toArray());

        return redirect()->route($tournament instanceof TournamentConfiguration ? 'tournament-configurations.index' : 'tournaments.index')
            ->with('success', __('Tournament updated successfully.'));
    }

    public function uploadBoardSet(Request $request, string $tournamentId): RedirectResponse
    {
        $tournament = $this->resolveTournament($tournamentId);
        $this->authorizeTournament($tournament);

        $request->validate([
            'round_id' => 'required|string',
            'board_set_file' => 'required|file',
        ]);

        $results = $tournament->team_results;
        if (!$results) {
            return back()->withErrors(['round_id' => __('No results found for this tournament.')]);
        }

        $roundIndex = collect($results->rounds)->search(fn($r) => $r->id === $request->round_id);
        if ($roundIndex === false) {
            return back()->withErrors(['round_id' => __('Invalid round selected.')]);
        }

        $file = $request->file('board_set_file');
        $content = file_get_contents($file->getRealPath());

        $boardsData = [];
        $eventName = 'Imported Board Set';
        $currentBoard = null;
        $readingOptimumTable = false;

        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                $readingOptimumTable = false;
                continue;
            }

            if (preg_match('/^\[Event "(.+)"\]$/', $line, $matches)) {
                if ($eventName === 'Imported Board Set' && !empty($matches[1])) {
                    $eventName = $matches[1];
                }
                $readingOptimumTable = false;
            } elseif (preg_match('/^\[Board "(.+)"\]$/', $line, $matches)) {
                if ($currentBoard !== null && isset($currentBoard['deal'])) {
                    $boardsData[] = $currentBoard;
                }
                $currentBoard = ['board_number' => $matches[1]];
                $readingOptimumTable = false;
            } elseif (preg_match('/^\[Vulnerable "(.+)"\]$/', $line, $matches)) {
                if ($currentBoard === null) $currentBoard = [];
                $currentBoard['vulnerability'] = $this->normalizePbnVulnerability($matches[1]);
                $readingOptimumTable = false;
            } elseif (preg_match('/^\[Deal "(.+):(.+)"\]$/', $line, $matches)) {
                if ($currentBoard === null) $currentBoard = [];
                $currentBoard['dealer'] = $matches[1];
                $currentBoard['deal'] = $matches[2];
                $readingOptimumTable = false;
            } elseif (preg_match('/^\[OptimumScore "(.+)"\]$/', $line, $matches)) {
                if ($currentBoard === null) $currentBoard = [];
                $currentBoard['optimum_score'] = $matches[1];
                $readingOptimumTable = false;
            } elseif (preg_match('/^\[OptimumResultTable\b/', $line)) {
                if ($currentBoard === null) $currentBoard = [];
                $currentBoard['double_dummy_table'] = [];
                $readingOptimumTable = true;
            } elseif ($readingOptimumTable && preg_match('/^([NESW])\s+(N|NT|S|H|D|C)\s+(-?\d+)$/i', $line, $matches)) {
                $hand = strtoupper($matches[1]);
                $strain = strtoupper($matches[2]) === 'N' ? 'NT' : strtoupper($matches[2]);
                $currentBoard['double_dummy_table'][$hand][$strain] = (int) $matches[3];
            } elseif (str_starts_with($line, '[')) {
                $readingOptimumTable = false;
            }
        }
        if ($currentBoard !== null && isset($currentBoard['deal'])) {
            $boardsData[] = $currentBoard;
        }

        if (empty($boardsData)) {
            return back()->withErrors(['board_set_file' => __('Invalid PBN format or no boards found.')]);
        }

        $boardSetId = null;

        DB::transaction(function () use ($boardsData, $eventName, $tournament, $results, $roundIndex, &$boardSetId) {
            $boardSet = BoardSet::create([
                'tournament_id' => $tournament instanceof Tournament ? $tournament->id : null,
                'tournament_configuration_id' => $tournament instanceof TournamentConfiguration ? $tournament->id : null,
                'name' => $eventName,
            ]);
            $boardSetId = $boardSet->id;

            $seatOrder = ['N', 'E', 'S', 'W'];
            $fullSeats = ['N' => 'North', 'S' => 'South', 'E' => 'East', 'W' => 'West'];

            foreach ($boardsData as $bData) {
                $handsStr = explode(' ', $bData['deal']);
                $startIndex = array_search($bData['dealer'], $seatOrder);
                
                $mappedHands = ['North' => [], 'South' => [], 'East' => [], 'West' => []];
                
                foreach ($handsStr as $i => $hStr) {
                    if ($hStr === '-') continue;
                    $seat = $seatOrder[($startIndex + $i) % 4];
                    $suits = explode('.', $hStr);
                    $mappedHands[$fullSeats[$seat]] = [
                        'S' => $suits[0] ?? '',
                        'H' => $suits[1] ?? '',
                        'D' => $suits[2] ?? '',
                        'C' => $suits[3] ?? '',
                    ];
                }

                $vulnerability = $bData['vulnerability'] ?? $this->hydrationService->calculateVulnerability((int) $bData['board_number']);
                $doubleDummyAnalysis = null;

                if (! empty($bData['double_dummy_table'])) {
                    $doubleDummyAnalysis = $this->doubleDummyAnalysisService->fromOptimumResultTable(
                        $bData['double_dummy_table'],
                        $vulnerability,
                        $bData['optimum_score'] ?? null
                    );
                }

                Board::create([
                    'board_set_id' => $boardSet->id,
                    'board_number' => (int) $bData['board_number'],
                    'vulnerability' => $vulnerability,
                    'cards_north' => $mappedHands['North'],
                    'cards_south' => $mappedHands['South'],
                    'cards_east' => $mappedHands['East'],
                    'cards_west' => $mappedHands['West'],
                    'double_dummy_analysis' => $doubleDummyAnalysis,
                ]);
            }

            // Update round
            $results->rounds[$roundIndex]->board_set_id = $boardSet->id;
            $tournament->team_results = $results;
            $tournament->save();
        });

        return redirect()->route('tournaments.edit', $tournament)
            ->with('success', __('Board set uploaded successfully.'));
    }

    public function editTeam(string $tournamentId, string $teamId): View
    {
        $tournament = $this->resolveTournament($tournamentId);
        $this->authorizeTournament($tournament);

        $results = $tournament->team_results;
        if (!$results) {
            abort(404);
        }

        $team = collect($results->teams)->firstWhere('id', $teamId);
        if (!$team) {
            abort(404);
        }

        $allTournamentPlayerIds = collect($results->teams)->flatMap(fn($t) => $t->player_ids)->unique()->toArray();
        $teamPlayerIds = $team->player_ids ?? [];
        
        $currentPlayers = Player::whereIn('id', $teamPlayerIds)->orderBy('last_name')->get();
        
        // Players not in any team of this tournament
        $availablePlayers = Player::whereNotIn('id', $allTournamentPlayerIds)->orderBy('last_name')->get();

        return view('tournaments.teams.edit', compact('tournament', 'team', 'currentPlayers', 'availablePlayers'));
    }

    public function addPlayerToTeam(Request $request, string $tournamentId, string $teamId): RedirectResponse
    {
        $tournament = $this->resolveTournament($tournamentId);
        $this->authorizeTournament($tournament);

        $request->validate([
            'player_id' => 'required|integer|exists:players,id',
        ]);

        $results = $tournament->team_results;
        if (!$results) abort(404);

        $allTournamentPlayerIds = collect($results->teams)->flatMap(fn($t) => $t->player_ids)->unique()->toArray();
        if (in_array($request->player_id, $allTournamentPlayerIds)) {
            return back()->withErrors(['player_id' => __('Player is already registered in a team for this tournament.')]);
        }

        $teamIndex = collect($results->rounds)->search(fn($t) => false); // Just finding index
        $teamIndex = collect($results->teams)->search(fn($t) => $t->id === $teamId);
        if ($teamIndex === false) abort(404);

        $playerIds = $results->teams[$teamIndex]->player_ids ?? [];
        $playerIds[] = (int) $request->player_id;
        $results->teams[$teamIndex]->player_ids = array_values(array_unique($playerIds));

        $tournament->team_results = $results;
        $tournament->save();

        return back()->with('success', __('Player added to team.'));
    }

    public function removePlayerFromTeam(string $tournamentId, string $teamId, int $playerId): RedirectResponse
    {
        $tournament = $this->resolveTournament($tournamentId);
        $this->authorizeTournament($tournament);

        $results = $tournament->team_results;
        if (!$results) abort(404);

        $teamIndex = collect($results->teams)->search(fn($t) => $t->id === $teamId);
        if ($teamIndex === false) abort(404);

        $playerIds = $results->teams[$teamIndex]->player_ids ?? [];
        $results->teams[$teamIndex]->player_ids = array_values(array_filter($playerIds, fn($id) => $id != $playerId));

        // If removed player was captain, unset captain
        if ($results->teams[$teamIndex]->captain_id == $playerId) {
            $results->teams[$teamIndex]->captain_id = 0;
        }

        $tournament->team_results = $results;
        $tournament->save();

        return back()->with('success', __('Player removed from team.'));
    }

    public function setTeamCaptain(string $tournamentId, string $teamId, int $playerId): RedirectResponse
    {
        $tournament = $this->resolveTournament($tournamentId);
        $this->authorizeTournament($tournament);

        $results = $tournament->team_results;
        if (!$results) abort(404);

        $teamIndex = collect($results->teams)->search(fn($t) => $t->id === $teamId);
        if ($teamIndex === false) abort(404);

        if (!in_array($playerId, $results->teams[$teamIndex]->player_ids)) {
            return back()->withErrors(['error' => __('Player must be in the team to be captain.')]);
        }

        $results->teams[$teamIndex]->captain_id = $playerId;

        $tournament->team_results = $results;
        $tournament->save();

        return back()->with('success', __('Team captain updated.'));
    }

    public function updateRoundStatus(Request $request, string $tournamentId, string $roundId): RedirectResponse
    {
        $tournament = $this->resolveTournament($tournamentId);
        $this->authorizeTournament($tournament);

        $request->validate([
            'status' => 'required|string|in:idle,inProgress,complete',
        ]);

        $results = $tournament->team_results;
        if (!$results) {
            abort(404);
        }

        $roundIndex = collect($results->rounds)->search(fn($r) => $r->id === $roundId);
        if ($roundIndex === false) {
            abort(404);
        }

        $results->rounds[$roundIndex]->status = $request->status;

        $this->hydrationService->recalculateStandings($results);

        $tournament->team_results = $results;
        $tournament->save();

        return back()->with('success', __('Round status updated.'));
    }

    public function editTeamNumbers(string $tournamentId): View
    {
        $tournament = $this->resolveTournament($tournamentId);
        $this->authorizeTournament($tournament);

        $results = $tournament->team_results;
        if (!$results) {
            abort(404);
        }

        $teams = collect($results->teams)->sortBy(fn($t) => $t->number ?? 999999);

        return view('tournaments.teams.numbers', compact('tournament', 'teams'));
    }

    public function updateTeamNumbers(Request $request, string $tournamentId): RedirectResponse
    {
        $tournament = $this->resolveTournament($tournamentId);
        $this->authorizeTournament($tournament);

        $results = $tournament->team_results;
        if (!$results) {
            abort(404);
        }

        $teamCount = count($results->teams);

        $request->validate([
            'numbers' => 'required|array',
            'numbers.*' => "nullable|integer|min:1|max:{$teamCount}",
        ]);

        $newNumbers = $request->input('numbers');
        $filteredNumbers = array_filter($newNumbers, fn($v) => !is_null($v) && $v !== '');
        
        if (count($filteredNumbers) !== count(array_unique($filteredNumbers))) {
            return back()->withErrors(['error' => __('Team numbers must be unique.')]);
        }

        foreach ($results->teams as $team) {
            if (isset($newNumbers[$team->id])) {
                $team->number = $newNumbers[$team->id] !== '' ? (int) $newNumbers[$team->id] : null;
            }
        }

        $tournament->team_results = $results;
        $tournament->save();

        return redirect()->route('tournaments.edit', $tournament)
            ->with('success', __('Team numbers updated successfully.'));
    }

    public function updateTeam(Request $request, string $tournamentId, string $teamId): RedirectResponse
    {
        $tournament = $this->resolveTournament($tournamentId);
        $this->authorizeTournament($tournament);

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $results = $tournament->team_results;
        if (!$results) {
            abort(404);
        }

        $teamIndex = collect($results->teams)->search(fn($t) => $t->id === $teamId);
        if ($teamIndex === false) {
            abort(404);
        }

        $results->teams[$teamIndex]->name = $request->name;

        $tournament->team_results = $results;
        $tournament->save();

        return back()->with('success', __('Team name updated successfully.'));
    }

    public function reorderRound(Request $request, string $tournamentId, string $roundId): RedirectResponse
    {
        $tournament = $this->resolveTournament($tournamentId);
        $this->authorizeTournament($tournament);

        $request->validate([
            'direction' => 'required|string|in:up,down',
        ]);

        $results = $tournament->team_results;
        if (!$results) {
            abort(404);
        }

        $rounds = $results->rounds;
        $index = collect($rounds)->search(fn($r) => $r->id === $roundId);

        if ($index === false) {
            abort(404);
        }

        if ($rounds[$index]->status !== 'idle') {
            return back()->withErrors(['error' => __('Only idle rounds can be reordered.')]);
        }

        $direction = $request->direction;
        $newIndex = ($direction === 'up') ? $index - 1 : $index + 1;

        if ($newIndex < 0 || $newIndex >= count($rounds)) {
            return back();
        }

        // Swap
        $temp = $rounds[$index];
        $rounds[$index] = $rounds[$newIndex];
        $rounds[$newIndex] = $temp;

        $results->rounds = $rounds;
        $tournament->team_results = $results;
        $tournament->save();

        return back()->with('success', __('Round reordered successfully.'));
    }

    public function updateSettings(Request $request, string $tournamentId): RedirectResponse
    {
        $tournament = $this->resolveTournament($tournamentId);
        $this->authorizeTournament($tournament);

        $request->validate([
            'bye_vp' => 'required|numeric|min:0|max:20',
            'boards_per_round' => 'required|integer|min:1|max:64',
        ]);

        $results = $tournament->team_results;
        if (!$results) abort(404);

        $results->bye_vp = (float) $request->bye_vp;
        $results->boards_per_round = (int) $request->boards_per_round;
        
        $this->hydrationService->recalculateStandings($results);
        
        $tournament->team_results = $results;
        $tournament->save();

        return back()->with('success', __('Settings updated and standings recalculated.'));
    }

    public function generateRounds(Request $request, string $tournamentId): RedirectResponse
    {
        $tournament = $this->resolveTournament($tournamentId);
        $this->authorizeTournament($tournament);

        $request->validate([
            'format' => 'required|string|in:single_round_robin,double_round_robin',
            'boards_per_round' => 'required|integer|min:1|max:64',
        ]);

        $results = $tournament->team_results;
        if (!$results || count($results->teams) < 2) {
            return back()->withErrors(['format' => __('At least 2 teams are required to generate rounds.')]);
        }

        // Sort teams by number, fallback to index
        $teams = collect($results->teams)->sortBy(fn($t) => $t->number ?? 999999)->values()->toArray();
        $n = count($teams);
        $isOdd = $n % 2 !== 0;
        
        if ($isOdd) {
            $n++;
            // Dummy "bye" team with id null
            $teams[] = (object) ['id' => null, 'name' => __('bye')];
        }

        $rounds = [];
        $numRounds = $n - 1;
        $existingRoundCount = count($results->rounds);

        for ($r = 1; $r <= $numRounds; $r++) {
            $matches = [];
            
            // Fixed team is at index n-1
            $fixedTeam = $teams[$n - 1];
            $rotatingTeamIdx = ($r - 1) % ($n - 1);
            $opponent = $teams[$rotatingTeamIdx];

            if ($fixedTeam->id || $opponent->id) {
                // Alternating home/away for the fixed team
                // Fixed team is Home in odd rounds, Away in even rounds
                $isFixedHome = ($r % 2 !== 0);
                $homeId = $isFixedHome ? $fixedTeam->id : $opponent->id;
                $awayId = $isFixedHome ? $opponent->id : $fixedTeam->id;
                
                $matches[] = new MatchDTO(
                    id: Str::uuid()->toString(),
                    home_team_id: $homeId,
                    away_team_id: $awayId,
                    home_imp: 0, away_imp: 0, 
                    home_vp: (!$awayId ? $results->bye_vp : 0), 
                    away_vp: (!$homeId ? $results->bye_vp : 0)
                );
            }

            // Other pairings
            for ($k = 1; $k < $n / 2; $k++) {
                $idx1 = ($r - 1 - $k + ($n - 1)) % ($n - 1);
                $idx2 = ($r - 1 + $k) % ($n - 1);
                
                $t1 = $teams[$idx1];
                $t2 = $teams[$idx2];

                if ($t1->id || $t2->id) {
                    $matches[] = new MatchDTO(
                        id: Str::uuid()->toString(),
                        home_team_id: $t1->id,
                        away_team_id: $t2->id,
                        home_imp: 0, away_imp: 0, 
                        home_vp: (!$t2->id ? $results->bye_vp : 0), 
                        away_vp: (!$t1->id ? $results->bye_vp : 0)
                    );
                }
            }

            $rounds[] = new RoundDTO(
                id: Str::uuid()->toString(),
                name: __('Round') . ' ' . ($existingRoundCount + $r),
                status: 'idle',
                matches: $matches,
                boards_per_round: (int) $request->boards_per_round
            );
        }

        if ($request->format === 'double_round_robin') {
            $secondHalf = [];
            foreach ($rounds as $round) {
                $newMatches = [];
                foreach ($round->matches as $match) {
                    $newMatches[] = new MatchDTO(
                        id: Str::uuid()->toString(),
                        home_team_id: $match->away_team_id,
                        away_team_id: $match->home_team_id,
                        home_imp: 0, away_imp: 0, home_vp: 0, away_vp: 0
                    );
                }
                $secondHalf[] = new RoundDTO(
                    id: Str::uuid()->toString(),
                    name: __('Round') . ' ' . ($existingRoundCount + count($rounds) + count($secondHalf) + 1),
                    status: 'idle',
                    matches: $newMatches,
                    boards_per_round: (int) $request->boards_per_round
                );
            }
            $rounds = array_merge($rounds, $secondHalf);
        }

        $results->rounds = array_merge($results->rounds, $rounds);
        $this->hydrationService->recalculateStandings($results);
        $tournament->team_results = $results;
        $tournament->save();

        return back()->with('success', __('Rounds generated successfully.'));
    }

    public function uploadRoundsCsv(Request $request, string $tournamentId): RedirectResponse
    {
        $tournament = $this->resolveTournament($tournamentId);
        $this->authorizeTournament($tournament);

        $request->validate([
            'csv_file' => 'required|file',
            'boards_per_round' => 'required|integer|min:1|max:64',
        ]);

        $results = $tournament->team_results;
        if (!$results) {
            abort(404);
        }

        $file = $request->file('csv_file');
        $handle = fopen($file->getRealPath(), 'r');
        
        // Skip header
        fgetcsv($handle);

        $roundsData = [];
        $teamMap = collect($results->teams)->keyBy('number');

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 3) continue;

            $roundName = trim($row[0]);
            $homeNum = trim($row[1]);
            $awayNum = trim($row[2]);

            if (empty($roundName) || empty($homeNum) || empty($awayNum)) continue;
            if (strtolower($homeNum) === 'bye' || strtolower($awayNum) === 'bye') continue;

            $homeTeam = $teamMap->get($homeNum);
            $awayTeam = $teamMap->get($awayNum);

            // One can be null (bye), but not both
            if (!$homeTeam && !$awayTeam) continue;

            if (!isset($roundsData[$roundName])) {
                $roundsData[$roundName] = [];
            }

            $roundsData[$roundName][] = new MatchDTO(
                id: Str::uuid()->toString(),
                home_team_id: $homeTeam?->id,
                away_team_id: $awayTeam?->id,
                home_imp: 0, away_imp: 0, 
                home_vp: (!$awayTeam ? $results->bye_vp : 0), 
                away_vp: (!$homeTeam ? $results->bye_vp : 0)
            );
        }
        fclose($handle);

        if (empty($roundsData)) {
            return back()->withErrors(['csv_file' => __('No valid matches found in CSV.')]);
        }

        $newRounds = [];
        foreach ($roundsData as $name => $matches) {
            $newRounds[] = new RoundDTO(
                id: Str::uuid()->toString(),
                name: $name,
                status: 'idle',
                matches: $matches,
                boards_per_round: (int) $request->boards_per_round
            );
        }

        $results->rounds = array_merge($results->rounds, $newRounds);
        $this->hydrationService->recalculateStandings($results);
        $tournament->team_results = $results;
        $tournament->save();

        return back()->with('success', __('Rounds uploaded successfully.'));
    }

    public function renumberBoards(Request $request, string $id, string $roundId): RedirectResponse
    {
        $tournament = $this->resolveTournament($id);
        $this->authorizeTournament($tournament);

        $request->validate([
            'starting_board_number' => 'required|integer|min:1|max:1000',
        ]);

        $results = $tournament->team_results;
        if (!$results) abort(404);

        $roundIndex = collect($results->rounds)->search(fn($r) => $r->id === $roundId);
        if ($roundIndex === false) abort(404);

        $round = $results->rounds[$roundIndex];
        $startFrom = (int) $request->starting_board_number;

        DB::transaction(function () use ($round, $startFrom, $tournament, $results) {
            foreach ($round->matches as $match) {
                if (empty($match->boards)) continue;
                
                $boards = collect($match->boards)->sortBy('board_number')->values();
                foreach ($boards as $i => $board) {
                    $board->board_number = $startFrom + $i;
                }
                $match->boards = $boards->toArray();
            }

            // Also update physical board set if it exists
            if ($round->board_set_id) {
                $boards = Board::where('board_set_id', $round->board_set_id)
                    ->orderBy('board_number')
                    ->get();
                    
                foreach ($boards as $i => $board) {
                    $board->board_number = $startFrom + $i;
                    $board->vulnerability = $this->hydrationService->calculateVulnerability($board->board_number);
                    $board->save();
                }
            }

            $tournament->team_results = $results;
            $tournament->save();
        });

        return back()->with('success', __('Boards renumbered successfully.'));
    }

    public function editMatchRoom(string $id, string $roundId, string $matchId, string $room): View
    {
        $tournament = $this->resolveTournament($id);
        $this->authorizeTournament($tournament);

        $results = $tournament->team_results;
        if (!$results) abort(404);

        $round = collect($results->rounds)->firstWhere('id', $roundId);
        if (!$round) abort(404);

        if ($round->status !== 'inProgress') {
            return abort(403, __('Results can only be entered for rounds in progress.'));
        }

        $match = collect($round->matches)->firstWhere('id', $matchId)
            ?? collect($round->matches)->firstWhere('home_team_id', $matchId);
            
        if (!$match) abort(404);

        $homeTeam = collect($results->teams)->firstWhere('id', $match->home_team_id);
        $awayTeam = collect($results->teams)->firstWhere('id', $match->away_team_id);

        if (!$homeTeam || !$awayTeam) {
            abort(404, __('Cannot enter results for a bye match.'));
        }

        $numBoards = $round->boards_per_round ?? $results->boards_per_round ?? 16;
        if (empty($match->boards)) {
            $boards = [];
            for ($i = 1; $i <= $numBoards; $i++) {
                $boards[] = new MatchBoardDTO(board_number: $i);
            }
            $match->boards = $boards;
        }

        // Determine which teams are NS and EW for this room
        // Open Room: NS = Home, EW = Away
        // Closed Room: NS = Away, EW = Home
        $nsTeam = $room === 'open' ? $homeTeam : $awayTeam;
        $ewTeam = $room === 'open' ? $awayTeam : $homeTeam;

        $nsPlayers = Player::whereIn('id', $nsTeam->player_ids)->get();
        $ewPlayers = Player::whereIn('id', $ewTeam->player_ids)->get();

        return view('tournaments.matches.room_edit', compact(
            'tournament', 'round', 'match', 'room',
            'homeTeam', 'awayTeam', 'nsTeam', 'ewTeam',
            'nsPlayers', 'ewPlayers', 'results'
        ));
    }

    public function updateMatchLineup(Request $request, string $id, string $roundId, string $matchId, string $room): RedirectResponse|array
    {
        $tournament = $this->resolveTournament($id);
        $this->authorizeTournament($tournament);

        $results = $tournament->team_results;
        if (!$results) abort(404);

        $roundIndex = collect($results->rounds)->search(fn($r) => $r->id === $roundId);
        if ($roundIndex === false) abort(404);
        
        $matchIndex = collect($results->rounds[$roundIndex]->matches)->search(fn($m) => ($m->id === $matchId || $m->home_team_id === $matchId));
        if ($matchIndex === false) abort(404);

        $request->validate([
            'n_id' => 'nullable|integer|exists:players,id',
            's_id' => 'nullable|integer|exists:players,id',
            'e_id' => 'nullable|integer|exists:players,id',
            'w_id' => 'nullable|integer|exists:players,id',
        ]);

        $match = $results->rounds[$roundIndex]->matches[$matchIndex];
        
        if ($room === 'open') {
            $match->open_ns_ids = array_map('intval', array_values(array_filter([$request->n_id, $request->s_id])));
            $match->open_ew_ids = array_map('intval', array_values(array_filter([$request->e_id, $request->w_id])));
        } else {
            $match->closed_ns_ids = array_map('intval', array_values(array_filter([$request->n_id, $request->s_id])));
            $match->closed_ew_ids = array_map('intval', array_values(array_filter([$request->e_id, $request->w_id])));
        }

        $tournament->team_results = $results;
        $tournament->save();

        if ($request->wantsJson()) {
            return ['success' => true];
        }
        return back()->with('success', __('Lineup updated.'));
    }

    public function updateMatchBoard(Request $request, string $id, string $roundId, string $matchId, string $room, int $boardNumber): RedirectResponse|array
    {
        $tournament = $this->resolveTournament($id);
        $this->authorizeTournament($tournament);

        $results = $tournament->team_results;
        if (!$results) abort(404);

        $roundIndex = collect($results->rounds)->search(fn($r) => $r->id === $roundId);
        if ($roundIndex === false) abort(404);
        $round = $results->rounds[$roundIndex];
        
        $matchIndex = collect($round->matches)->search(fn($m) => ($m->id === $matchId || $m->home_team_id === $matchId));
        if ($matchIndex === false) abort(404);

        $match = $round->matches[$matchIndex];

        // Ensure boards are initialized
        if (empty($match->boards)) {
            $numBoards = $round->boards_per_round ?? $results->boards_per_round ?? 16;
            $boards = [];
            for ($i = 1; $i <= $numBoards; $i++) {
                $boards[] = new MatchBoardDTO(board_number: $i);
            }
            $match->boards = $boards;
        }

        $boardIndex = collect($match->boards)->search(fn($b) => $b->board_number === $boardNumber);
        if ($boardIndex === false) abort(404);

        $data = $request->all();
        $isVul = $this->hydrationService->calculateVulnerability($boardNumber);
        
        // Contract Components
        $level = (int) ($data['contract_level'] ?? 0);
        if ($level === 0) {
            $score = 0;
            $contract = 'Pass';
            $decl = null;
            $tricks = null;
        } else {
            $suit = $data['contract_suit'];
            $risk = (int) $data['contract_risk'];
            $tricks = (int) $data['tricks'];
            $decl = $data['declarer'];

            $isRoomVul = ($isVul === 'All' || $isVul === ($decl === 'N' || $decl === 'S' ? 'NS' : 'EW'));
            $absScore = $this->scoringService->calculateScore($level, $suit, $risk, $tricks, $isRoomVul);
            $score = ($decl === 'N' || $decl === 'S') ? $absScore : -$absScore;
            
            $suffix = $risk === 2 ? 'X' : ($risk === 4 ? 'XX' : '');
            $contract = $level . $suit . $suffix;
        }

        $lead = $data['lead'] ?? null;

        $board = $match->boards[$boardIndex];
        if ($room === 'open') {
            $board->home_score = $score;
            $board->home_contract = $contract;
            $board->home_declarer = $decl;
            $board->home_tricks = $tricks;
            $board->home_lead = $lead;
            $board->home_updated_by = auth()->id();
        } else {
            $board->away_score = $score;
            $board->away_contract = $contract;
            $board->away_declarer = $decl;
            $board->away_tricks = $tricks;
            $board->away_lead = $lead;
            $board->away_updated_by = auth()->id();
        }

        // Recalculate board IMPs
        if ($board->home_score !== null && $board->away_score !== null) {
            $diff = $board->home_score - $board->away_score;
            $imp = $this->hydrationService->scoreToImp($diff);
            $board->home_imp = $imp > 0 ? $imp : 0;
            $board->away_imp = $imp < 0 ? abs($imp) : 0;
        } else {
            $board->home_imp = 0;
            $board->away_imp = 0;
        }

        // Recalculate match totals
        $totalHomeImp = 0;
        $totalAwayImp = 0;
        foreach ($match->boards as $b) {
            $totalHomeImp += $b->home_imp;
            $totalAwayImp += $b->away_imp;
        }
        $match->home_imp = $totalHomeImp;
        $match->away_imp = $totalAwayImp;

        $boardsCount = $round->boards_per_round ?? $results->boards_per_round ?? 16;
        list($hVp, $aVp) = $this->vpService->calculateVp($totalHomeImp, $totalAwayImp, $boardsCount);
        $match->home_vp = $hVp;
        $match->away_vp = $aVp;

        $this->hydrationService->recalculateStandings($results);
        $tournament->team_results = $results;
        $tournament->save();

        if ($request->wantsJson()) {
            return [
                'success' => true,
                'board' => $board->toArray(),
                'match_home_imp' => $match->home_imp,
                'match_away_imp' => $match->away_imp,
                'match_home_vp' => $match->home_vp,
                'match_away_vp' => $match->away_vp
            ];
        }

        return back()->with('success', __('Board updated.'));
    }

    public function uploadMatchBoardsCsv(Request $request, string $id, string $roundId, string $matchId, string $room): RedirectResponse
    {
        $tournament = $this->resolveTournament($id);
        $this->authorizeTournament($tournament);

        $request->validate([
            'csv_file' => 'required|file',
        ]);

        $results = $tournament->team_results;
        if (!$results) abort(404);

        $roundIndex = collect($results->rounds)->search(fn($r) => $r->id === $roundId);
        if ($roundIndex === false) abort(404);
        $round = $results->rounds[$roundIndex];
        
        $matchIndex = collect($round->matches)->search(fn($m) => ($m->id === $matchId || $m->home_team_id === $matchId));
        if ($matchIndex === false) abort(404);

        $match = $round->matches[$matchIndex];

        // Ensure boards are initialized
        if (empty($match->boards)) {
            $numBoards = $round->boards_per_round ?? $results->boards_per_round ?? 16;
            $boards = [];
            for ($i = 1; $i <= $numBoards; $i++) {
                $boards[] = new MatchBoardDTO(board_number: $i);
            }
            $match->boards = $boards;
        }

        $file = $request->file('csv_file');
        $handle = fopen($file->getRealPath(), 'r');
        $header = fgetcsv($handle); // bd,contract,by,lead,result
        
        $importedCount = 0;
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 5) continue;
            
            $boardNum = (int) $row[0];
            $contractStr = $row[1];
            $by = strtoupper($row[2]);
            $lead = strtoupper($row[3]);
            $result = $row[4];

            $boardIndex = collect($match->boards)->search(fn($b) => $b->board_number === $boardNum);
            if ($boardIndex === false) continue;

            $board = $match->boards[$boardIndex];
            $parsed = $this->scoringService->parseContract($contractStr);
            $level = $parsed[0];
            
            if ($level === 0) {
                $score = 0;
                $contract = 'Pass';
                $decl = null;
                $tricks = null;
            } else {
                $suit = $parsed[1];
                $risk = $parsed[2];
                $decl = $by;

                // Parse result (e.g. =, +1, -2)
                $tricks = 6 + $level;
                if ($result !== '=') {
                    $tricks += (int) $result;
                }

                // Standardize declarer to one of N, E, S, W
                if (!in_array($decl, ['N', 'E', 'S', 'W'])) {
                    // Try to normalize or skip
                    continue;
                }

                $isVul = $this->hydrationService->calculateVulnerability($boardNum);
                $isRoomVul = ($isVul === 'All' || $isVul === ($decl === 'N' || $decl === 'S' ? 'NS' : 'EW'));
                $absScore = $this->scoringService->calculateScore($level, $suit, $risk, $tricks, $isRoomVul);
                $score = ($decl === 'N' || $decl === 'S') ? $absScore : -$absScore;
                
                $suffix = $risk === 2 ? 'X' : ($risk === 4 ? 'XX' : '');
                $contract = $level . $suit . $suffix;
            }

            if ($room === 'open') {
                $board->home_score = $score;
                $board->home_contract = $contract;
                $board->home_declarer = $decl;
                $board->home_tricks = $tricks;
                $board->home_lead = $lead;
                $board->home_updated_by = auth()->id();
            } else {
                $board->away_score = $score;
                $board->away_contract = $contract;
                $board->away_declarer = $decl;
                $board->away_tricks = $tricks;
                $board->away_lead = $lead;
                $board->away_updated_by = auth()->id();
            }

            // Recalculate board IMPs
            if ($board->home_score !== null && $board->away_score !== null) {
                $diff = $board->home_score - $board->away_score;
                $imp = $this->hydrationService->scoreToImp($diff);
                $board->home_imp = $imp > 0 ? $imp : 0;
                $board->away_imp = $imp < 0 ? abs($imp) : 0;
            }
            $importedCount++;
        }
        fclose($handle);

        // Recalculate match totals
        $totalHomeImp = 0;
        $totalAwayImp = 0;
        foreach ($match->boards as $b) {
            $totalHomeImp += $b->home_imp;
            $totalAwayImp += $b->away_imp;
        }
        $match->home_imp = $totalHomeImp;
        $match->away_imp = $totalAwayImp;

        $boardsCount = $round->boards_per_round ?? $results->boards_per_round ?? 16;
        list($hVp, $aVp) = $this->vpService->calculateVp($totalHomeImp, $totalAwayImp, $boardsCount);
        $match->home_vp = $hVp;
        $match->away_vp = $aVp;

        $this->hydrationService->recalculateStandings($results);
        $tournament->team_results = $results;
        $tournament->save();

        return back()->with('success', __(':count board results imported successfully.', ['count' => $importedCount]));
    }

    public function downloadMatchBoardsCsv(string $id, string $roundId, string $matchId, string $room): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $tournament = $this->resolveTournament($id);
        $results = $tournament->team_results;
        if (!$results) abort(404);

        $roundIndex = collect($results->rounds)->search(fn($r) => $r->id === $roundId);
        if ($roundIndex === false) abort(404);
        $round = $results->rounds[$roundIndex];
        
        $matchIndex = collect($round->matches)->search(fn($m) => ($m->id === $matchId || $m->home_team_id === $matchId));
        if ($matchIndex === false) abort(404);
        $match = $round->matches[$matchIndex];

        $filename = sprintf('match_%s_%s_%s.csv', $matchId, $room, now()->format('Y-m-d_H-i'));

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        return response()->stream(function () use ($match, $room) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['bd', 'contract', 'by', 'lead', 'result']);

            foreach ($match->boards as $board) {
                $contract = $room === 'open' ? $board->home_contract : $board->away_contract;
                $decl = $room === 'open' ? $board->home_declarer : $board->away_declarer;
                $lead = $room === 'open' ? $board->home_lead : $board->away_lead;
                $tricks = $room === 'open' ? $board->home_tricks : $board->away_tricks;
                
                if (!$contract || $contract === 'Pass') {
                    if ($contract === 'Pass') {
                        fputcsv($handle, [$board->board_number, 'Pass', '', '', '=']);
                    } else {
                        // Skip unplayed boards or keep empty? Let's export empty ones too.
                        fputcsv($handle, [$board->board_number, '', '', '', '']);
                    }
                    continue;
                }

                // Parse contract level to calculate relative result
                preg_match('/^(\d)/', $contract, $m);
                $level = isset($m[1]) ? (int)$m[1] : 0;
                $result = '';
                if ($level > 0 && $tricks !== null) {
                    $diff = $tricks - (6 + $level);
                    $result = $diff === 0 ? '=' : ($diff > 0 ? '+' . $diff : $diff);
                }

                fputcsv($handle, [
                    $board->board_number,
                    $contract,
                    $decl,
                    $lead,
                    $result
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }

    public function destroyRound(string $tournamentId, string $roundId): RedirectResponse
    {
        $tournament = $this->resolveTournament($tournamentId);
        $this->authorizeTournament($tournament);

        $results = $tournament->team_results;
        if (!$results) {
            abort(404);
        }

        $roundIndex = collect($results->rounds)->search(fn($r) => $r->id === $roundId);
        if ($roundIndex === false) {
            abort(404);
        }

        if ($results->rounds[$roundIndex]->status !== 'idle') {
            return back()->withErrors(['error' => __('Only idle rounds can be deleted.')]);
        }

        $newRounds = array_values(array_filter($results->rounds, fn($r) => $r->id !== $roundId));
        $results->rounds = $newRounds;
        
        $tournament->team_results = $results;
        $tournament->save();

        return back()->with('success', __('Round deleted successfully.'));
    }

    public function destroyIdleRounds(string $tournamentId): RedirectResponse
    {
        $tournament = $this->resolveTournament($tournamentId);
        $this->authorizeTournament($tournament);

        $results = $tournament->team_results;
        if (!$results) {
            abort(404);
        }

        $newRounds = array_values(array_filter($results->rounds, fn($r) => $r->status !== 'idle'));
        
        if (count($newRounds) === count($results->rounds)) {
            return back()->with('info', __('No idle rounds found to delete.'));
        }

        $results->rounds = $newRounds;
        $tournament->team_results = $results;
        $tournament->save();

        return back()->with('success', __('All idle rounds deleted successfully.'));
    }

    public function addTeam(Request $request, string $tournamentId): RedirectResponse
    {
        $tournament = $this->resolveTournament($tournamentId);
        $this->authorizeTournament($tournament);

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $results = $tournament->team_results;
        if (!$results) abort(404);

        $teamCount = count($results->teams);
        $newTeam = new TeamDTO(
            id: (string) Str::uuid(),
            name: $request->input('name'),
            captain_id: 0,
            player_ids: [],
            total_vp: 0,
            number: $teamCount + 1
        );

        $results->teams[] = $newTeam;
        $tournament->team_results = $results;
        $tournament->save();

        return back()->with('success', __('Team added successfully.'));
    }

    public function destroyTeam(string $tournamentId, string $teamId): RedirectResponse
    {
        $tournament = $this->resolveTournament($tournamentId);
        $this->authorizeTournament($tournament);

        $results = $tournament->team_results;
        if (!$results) abort(404);

        // Check if team is in any match
        foreach ($results->rounds as $round) {
            foreach ($round->matches as $match) {
                if ($match->home_team_id === $teamId || $match->away_team_id === $teamId) {
                    return back()->withErrors(['error' => __('Cannot delete team that is already in a schedule. Delete rounds first.')]);
                }
            }
        }

        $results->teams = array_values(array_filter($results->teams, fn($t) => $t->id !== $teamId));
        
        // Re-number teams
        foreach ($results->teams as $index => $team) {
            $team->number = $index + 1;
        }

        $tournament->team_results = $results;
        $tournament->save();

        return back()->with('success', __('Team deleted successfully.'));
    }

    public function destroy(string $id): RedirectResponse
    {
        $draft = TournamentConfiguration::find($id);
        $published = Tournament::find($id);
        
        // Authorization check
        if ($draft) {
            if (!auth()->user()->isAdmin() && auth()->id() !== $draft->user_id) {
                abort(403);
            }
        }
        
        if ($published) {
            Gate::authorize('delete', $published);
        }

        DB::transaction(function () use ($draft, $published) {
            if ($draft) {
                $draft->delete();
            }
            if ($published) {
                $published->delete();
            }
        });

        return redirect()->route('tournaments.index')
            ->with('success', __('Tournament deleted successfully.'));
    }
}
