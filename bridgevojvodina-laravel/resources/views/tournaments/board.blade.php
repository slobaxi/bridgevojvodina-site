<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div class="flex items-center gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Board') }} {{ $boardNumber }} - {{ $round->name }}
                </h2>
                
                <div class="flex items-center bg-gray-100 rounded-lg p-1 ml-4 shadow-inner">
                    @if ($prevBoard)
                        <a href="{{ route('tournaments.board', [$tournament, $round->id, $prevBoard]) }}" class="px-3 py-1 bg-white rounded-md shadow-sm text-sm font-bold text-gray-600 hover:text-blue-600 hover:bg-blue-50 transition-all" title="{{ __('Previous Board') }}">
                            &larr; {{ $prevBoard }}
                        </a>
                    @else
                        <span class="px-3 py-1 text-gray-300 text-sm font-bold cursor-not-allowed">
                            &larr;
                        </span>
                    @endif
                    
                    <span class="px-4 text-xs font-black text-gray-400 uppercase tracking-widest">{{ __('Board') }}</span>
                    
                    @if ($nextBoard)
                        <a href="{{ route('tournaments.board', [$tournament, $round->id, $nextBoard]) }}" class="px-3 py-1 bg-white rounded-md shadow-sm text-sm font-bold text-gray-600 hover:text-blue-600 hover:bg-blue-50 transition-all" title="{{ __('Next Board') }}">
                            {{ $nextBoard }} &rarr;
                        </a>
                    @else
                        <span class="px-3 py-1 text-gray-300 text-sm font-bold cursor-not-allowed">
                            &rarr;
                        </span>
                    @endif
                </div>
            </div>
            <div class="flex items-center gap-4">
                @if($results->player_butlers && !empty($results->player_butlers))
                    <a href="{{ route('tournaments.butler', $tournament) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        {{ __('Butler') }}
                    </a>
                @endif
                <a href="{{ route('tournaments.show', $tournament) }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    {{ __('Back to Tournament') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900">
                    <div class="flex flex-col items-center justify-center gap-12 mb-12">
                        <!-- Board Info Center -->
                        <div class="w-32 h-32 shrink-0 flex flex-col items-center justify-center bg-white rounded-lg shadow-sm border-2 border-gray-100 p-2">
                            <div class="text-[10px] uppercase font-bold text-gray-400">{{ __('Board') }}</div>
                            <div class="text-3xl font-black text-blue-900 mb-2 leading-none">{{ $boardNumber }}</div>
                            
                            <div class="w-full border-t pt-2 space-y-1">
                                <div class="flex justify-between items-center text-[10px]">
                                    <span class="font-bold text-gray-400 uppercase">{{ __('Dealer') }}</span>
                                    <span class="font-black text-blue-700">{{ $boardData['dealer'] }}</span>
                                </div>
                                <div class="flex justify-between items-center text-[10px]">
                                    <span class="font-bold text-gray-400 uppercase">{{ __('Vuln') }}</span>
                                    <span class="font-black @if($boardData['vulnerability'] == 'All') text-red-600 @elseif($boardData['vulnerability'] == 'NS') text-green-700 @elseif($boardData['vulnerability'] == 'EW') text-orange-600 @else text-gray-600 @endif">
                                        {{ __($boardData['vulnerability']) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Hands Layout -->
                        @if ($boardData['physical_board'])
                            <div class="flex flex-col items-center gap-6">
                                <!-- North Hand -->
                                <div class="p-3 bg-blue-50 rounded-lg border border-blue-100 shadow-sm">
                                    <div class="text-[10px] uppercase font-bold text-blue-400 mb-1 text-center">{{ __('North') }}</div>
                                    <x-bridge-hand :hand="$boardData['physical_board']->cards_north" />
                                </div>

                                <!-- Middle Row: West & East -->
                                <div class="flex items-center gap-8 md:gap-32 lg:gap-48">
                                    <div class="p-3 bg-red-50 rounded-lg border border-red-100 shadow-sm">
                                        <div class="text-[10px] uppercase font-bold text-red-400 mb-1 text-center">{{ __('West') }}</div>
                                        <x-bridge-hand :hand="$boardData['physical_board']->cards_west" />
                                    </div>

                                    <div class="p-3 bg-red-50 rounded-lg border border-red-100 shadow-sm">
                                        <div class="text-[10px] uppercase font-bold text-red-400 mb-1 text-center">{{ __('East') }}</div>
                                        <x-bridge-hand :hand="$boardData['physical_board']->cards_east" />
                                    </div>
                                </div>

                                <!-- South Hand -->
                                <div class="p-3 bg-blue-50 rounded-lg border border-blue-100 shadow-sm">
                                    <div class="text-[10px] uppercase font-bold text-blue-400 mb-1 text-center">{{ __('South') }}</div>
                                    <x-bridge-hand :hand="$boardData['physical_board']->cards_south" />
                                </div>
                            </div>
                        @else
                            <div class="text-gray-400 italic bg-gray-50 p-6 rounded-lg border-2 border-dashed border-gray-200">
                                {{ __('Card data not available for this board.') }}
                            </div>
                        @endif
                    </div>

                    @php
                        $physicalBoard = $boardData['physical_board'] ?? null;
                        $analysis = $physicalBoard?->double_dummy_analysis;
                        $analysisTable = $analysis['table'] ?? null;
                        $bestContract = $analysis['best_contract'] ?? null;
                        $displayHands = ['N' => __('N'), 'S' => __('S'), 'E' => __('E'), 'W' => __('W')];
                        $displayStrains = [
                            'NT' => __('NT'),
                            'S' => '&spades;',
                            'H' => '&hearts;',
                            'D' => '&diams;',
                            'C' => '&clubs;',
                        ];
                    @endphp

                    @if($analysisTable)
                        <div class="mx-auto mb-10 max-w-xl rounded-xl border border-gray-200 bg-white shadow-sm">
                            <div class="border-b border-gray-200 p-4">
                                <h3 class="text-sm font-black uppercase tracking-widest text-gray-500">
                                    {{ __('Double Dummy Analysis') }}
                                </h3>
                                @if($bestContract)
                                    <p class="mt-1 text-sm font-bold text-gray-800">
                                        {{ $bestContract['description'] }}
                                    </p>
                                @endif
                                @if(!empty($analysis['optimum_score']))
                                    <p class="mt-1 text-xs font-semibold text-gray-500">
                                        {{ __('Optimum') }}: {{ $analysis['optimum_score'] }}
                                    </p>
                                @endif
                            </div>

                            <div class="overflow-x-auto p-4">
                                <table class="mx-auto border-collapse text-center text-lg">
                                    <thead>
                                        <tr class="text-gray-500">
                                            <th class="w-12 border-b border-gray-300 px-3 py-1"></th>
                                            @foreach($displayStrains as $strain => $label)
                                                <th class="w-16 border-b border-gray-300 px-3 py-1 font-semibold {{ in_array($strain, ['H', 'D'], true) ? 'text-red-600' : 'text-gray-700' }}">
                                                    {!! $label !!}
                                                </th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($displayHands as $hand => $label)
                                            <tr>
                                                <th class="border-r border-gray-300 px-3 py-1 font-semibold text-amber-900">
                                                    {{ $label }}
                                                </th>
                                                @foreach(array_keys($displayStrains) as $strain)
                                                    @php
                                                        $isBestCell = $bestContract
                                                            && ($bestContract['declarer'] ?? null) === $hand
                                                            && ($bestContract['strain'] ?? null) === $strain;
                                                    @endphp
                                                    <td class="border border-gray-200 px-3 py-1 font-semibold {{ $isBestCell ? 'bg-emerald-300 text-emerald-950' : 'text-gray-700' }}">
                                                        {{ $analysisTable[$hand]['strains'][$strain] ?? '-' }}
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm text-center border-collapse">
                            <thead class="bg-gray-50 font-bold text-gray-700">
                                <tr>
                                    <th class="py-3 border px-2">{{ __('Room') }}</th>
                                    <th class="py-3 border px-2">{{ __('NS Team') }}</th>
                                    <th class="py-3 border px-2">{{ __('NS Players') }}</th>
                                    <th class="py-3 border px-2">{{ __('EW Team') }}</th>
                                    <th class="py-3 border px-2">{{ __('EW Players') }}</th>
                                    <th class="py-3 border">{{ __('Contr.') }}</th>
                                    <th class="py-3 border">{{ __('Decl.') }}</th>
                                    <th class="py-3 border">{{ __('Lead') }}</th>
                                    <th class="py-3 border">{{ __('Tr.') }}</th>
                                    <th class="py-3 border">{{ __('Score') }}</th>
                                    <th class="py-3 border">{{ __('IMP') }}</th>
                                    <th class="py-3 border bg-green-50 text-green-800">{{ __('Butler') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($boardResults as $res)
                                    @php
                                        $match = $res['match'];
                                        $board = $res['board'];
                                        $homeTeam = $res['home_team'];
                                        $awayTeam = $res['away_team'];
                                        
                                        $hydration = app(\App\Services\TournamentHydrationService::class);
                                        
                                        // Open Room Data
                                        $openData = [
                                            'room' => __('Open'),
                                            'ns_team' => $homeTeam,
                                            'ew_team' => $awayTeam,
                                            'n' => $players[$match->open_ns_ids[0] ?? null] ?? null,
                                            's' => $players[$match->open_ns_ids[1] ?? null] ?? null,
                                            'e' => $players[$match->open_ew_ids[0] ?? null] ?? null,
                                            'w' => $players[$match->open_ew_ids[1] ?? null] ?? null,
                                            'contract' => $board->home_contract,
                                            'declarer' => $board->home_declarer,
                                            'lead' => $board->home_lead,
                                            'tricks' => $board->home_tricks,
                                            'score' => $board->home_score,
                                            'imp' => $board->home_imp,
                                            'butler' => ($datum !== null && $board->home_score !== null) ? $hydration->scoreToImp($board->home_score - $datum) : null,
                                            'bg' => 'bg-blue-50/20'
                                        ];

                                        // Closed Room Data
                                        $closedData = [
                                            'room' => __('Closed'),
                                            'ns_team' => $awayTeam,
                                            'ew_team' => $homeTeam,
                                            'n' => $players[$match->closed_ns_ids[0] ?? null] ?? null,
                                            's' => $players[$match->closed_ns_ids[1] ?? null] ?? null,
                                            'e' => $players[$match->closed_ew_ids[0] ?? null] ?? null,
                                            'w' => $players[$match->closed_ew_ids[1] ?? null] ?? null,
                                            'contract' => $board->away_contract,
                                            'declarer' => $board->away_declarer,
                                            'lead' => $board->away_lead,
                                            'tricks' => $board->away_tricks,
                                            'score' => $board->away_score,
                                            'imp' => $board->away_imp,
                                            'butler' => ($datum !== null && $board->away_score !== null) ? $hydration->scoreToImp($board->away_score - $datum) : null,
                                            'bg' => 'bg-red-50/20'
                                        ];
                                    @endphp

                                    @foreach([$openData, $closedData] as $roomData)
                                        <tr class="hover:bg-gray-50 transition-colors border-b {{ $roomData['bg'] }}">
                                            <td class="py-2 border font-bold text-[10px] uppercase tracking-tighter text-gray-400">{{ $roomData['room'] }}</td>
                                            <td class="py-2 border font-semibold px-2 text-left text-xs">{{ $roomData['ns_team']->name ?? __('bye') }}</td>
                                            <td class="py-2 border px-2 text-left text-[10px] leading-tight">
                                                @if($roomData['n']) <div>{{ $roomData['n']->first_name }} {{ $roomData['n']->last_name }}</div> @endif
                                                @if($roomData['s']) <div>{{ $roomData['s']->first_name }} {{ $roomData['s']->last_name }}</div> @endif
                                            </td>
                                            <td class="py-2 border font-semibold px-2 text-left text-xs">{{ $roomData['ew_team']->name ?? __('bye') }}</td>
                                            <td class="py-2 border px-2 text-left text-[10px] leading-tight">
                                                @if($roomData['e']) <div>{{ $roomData['e']->first_name }} {{ $roomData['e']->last_name }}</div> @endif
                                                @if($roomData['w']) <div>{{ $roomData['w']->first_name }} {{ $roomData['w']->last_name }}</div> @endif
                                            </td>
                                            
                                            <td class="py-2 border italic text-gray-600"><x-bridge-contract :contract="$roomData['contract']" /></td>
                                            <td class="py-2 border">{{ $roomData['declarer'] }}</td>
                                            <td class="py-2 border"><x-bridge-contract :contract="$roomData['lead']" /></td>
                                            <td class="py-2 border text-[10px]">{{ app(\App\Http\Controllers\TournamentController::class)->formatTricksFromLevel(substr($roomData['contract'], 0, 1), $roomData['tricks']) }}</td>
                                            <td class="py-2 border font-mono">{{ $roomData['score'] !== null ? ($roomData['score'] > 0 ? '+' . $roomData['score'] : $roomData['score']) : '' }}</td>
                                            
                                            <td class="py-2 border font-bold {{ $roomData['imp'] > 0 ? 'text-green-700' : '' }}">{{ $roomData['imp'] ?: '' }}</td>

                                            <td class="py-2 border bg-green-50/30 font-bold @if($roomData['butler'] > 0) text-green-600 @elseif($roomData['butler'] < 0) text-red-600 @endif">
                                                {{ $roomData['butler'] > 0 ? '+' : '' }}{{ $roomData['butler'] }}
                                            </td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                            @if($datum !== null)
                                <tfoot class="bg-gray-100 font-bold border-t-2 border-gray-300 text-center">
                                    <tr>
                                        <td colspan="9" class="py-3 px-4 text-right uppercase tracking-widest text-gray-500 text-xs">{{ __('Datum') }}</td>
                                        <td class="py-3 text-lg font-mono text-blue-900">{{ round($datum) }}</td>
                                        <td colspan="2" class="py-3"></td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
