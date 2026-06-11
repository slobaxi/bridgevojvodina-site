<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Board Set') }}: {{ $boardSet->name }}
            </h2>
            <div class="flex items-center gap-4">
                @if($tournament->team_results && !empty($tournament->team_results->player_butlers))
                    <a href="{{ route('tournaments.butler', $tournament) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        {{ __('Butler') }}
                    </a>
                @endif
                <form method="POST" action="{{ route('tournaments.board-sets.destroy', [$tournament, $boardSet]) }}" onsubmit="return confirm('{{ __('Are you sure?') }}')">
                    @csrf
                    @method('DELETE')
                    <x-danger-button type="submit">
                        {{ __('Delete Board Set') }}
                    </x-danger-button>
                </form>
                <a href="{{ route('tournaments.edit', $tournament) }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    {{ __('Back to Tournament') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg" 
                 x-data="{ 
                    currentIdx: 0, 
                    total: {{ $boards->count() }},
                    editingBoard: false,
                    next() { if (this.currentIdx < this.total - 1) this.currentIdx++; },
                    prev() { if (this.currentIdx > 0) this.currentIdx--; }
                 }">
                <div class="p-6 text-gray-900">
                    @if($errors->has('double_dummy_analysis'))
                        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                            {{ $errors->first('double_dummy_analysis') }}
                        </div>
                    @endif
                    @if($errors->has('board_edit'))
                        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                            {{ $errors->first('board_edit') }}
                        </div>
                    @endif

                    <div class="flex items-center justify-between mb-8 bg-gray-50 p-4 rounded-lg border border-gray-200">
                        <div class="flex items-center gap-6">
                            <h3 class="text-2xl font-bold text-gray-800">{{ __('Board') }} <span x-text="document.querySelectorAll('.board-container')[currentIdx].dataset.number"></span></h3>
                            <div class="flex items-center gap-4">
                                <span class="px-3 py-1 bg-white border border-gray-200 rounded-full text-xs font-semibold text-gray-600">
                                    {{ __('Vuln') }}: <span class="font-black" :class="{
                                        'text-red-600': document.querySelectorAll('.board-container')[currentIdx].dataset.vuln === 'All',
                                        'text-green-700': document.querySelectorAll('.board-container')[currentIdx].dataset.vuln === 'NS',
                                        'text-orange-600': document.querySelectorAll('.board-container')[currentIdx].dataset.vuln === 'EW',
                                        'text-gray-600': document.querySelectorAll('.board-container')[currentIdx].dataset.vuln === 'None'
                                    }" x-text="document.querySelectorAll('.board-container')[currentIdx].dataset.vuln_trans"></span>
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <div class="text-sm text-gray-500 mr-4 font-mono">
                                <span x-text="currentIdx + 1"></span> / <span x-text="total"></span>
                            </div>
                            <x-secondary-button type="button" @click="editingBoard = !editingBoard">
                                {{ __('Edit Board') }}
                            </x-secondary-button>
                            <x-secondary-button @click="prev()" x-bind:disabled="currentIdx === 0">
                                {{ __('Previous') }}
                            </x-secondary-button>
                            <x-secondary-button @click="next()" x-bind:disabled="currentIdx === total - 1">
                                {{ __('Next') }}
                            </x-secondary-button>
                        </div>
                    </div>

                    <div class="relative">
                        @foreach($boards as $index => $board)
                            <div class="board-container transition-opacity duration-300" 
                                 x-show="currentIdx === {{ $index }}" 
                                 x-cloak
                                 data-number="{{ $board->board_number }}"
                                 data-vuln="{{ $board->vulnerability }}"
                                 data-vuln_trans="{{ __($board->vulnerability) }}">
                                @php
                                    $analysis = $board->double_dummy_analysis;
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
                                
                                <div class="flex flex-col items-center justify-center gap-8 py-8">
                                    <!-- North -->
                                    <div class="flex flex-col items-center gap-2">
                                        <div class="text-[10px] uppercase font-bold text-blue-400 text-center">{{ __('North') }}</div>
                                        <div class="p-4 bg-white rounded-xl border border-gray-200 shadow-md transform hover:scale-105 transition-transform duration-200">
                                            <x-bridge-hand :hand="$board->cards_north" />
                                        </div>
                                    </div>

                                    <!-- Middle: West & East -->
                                    <div class="flex items-center gap-8 md:gap-32 lg:gap-48">
                                        <div class="flex flex-col items-center gap-2">
                                            <div class="text-[10px] uppercase font-bold text-red-400 text-center">{{ __('West') }}</div>
                                            <div class="p-4 bg-white rounded-xl border border-gray-200 shadow-md transform hover:scale-105 transition-transform duration-200">
                                                <x-bridge-hand :hand="$board->cards_west" />
                                            </div>
                                        </div>

                                        <div class="flex flex-col items-center gap-2">
                                            <div class="text-[10px] uppercase font-bold text-red-400 text-center">{{ __('East') }}</div>
                                            <div class="p-4 bg-white rounded-xl border border-gray-200 shadow-md transform hover:scale-105 transition-transform duration-200">
                                                <x-bridge-hand :hand="$board->cards_east" />
                                            </div>
                                        </div>
                                    </div>

                                    <!-- South -->
                                    <div class="flex flex-col items-center gap-2">
                                        <div class="text-[10px] uppercase font-bold text-blue-400 text-center">{{ __('South') }}</div>
                                        <div class="p-4 bg-white rounded-xl border border-gray-200 shadow-md transform hover:scale-105 transition-transform duration-200">
                                            <x-bridge-hand :hand="$board->cards_south" />
                                        </div>
                                    </div>
                                </div>

                                <div x-show="editingBoard" x-cloak class="mx-auto mb-8 max-w-4xl rounded-xl border border-gray-200 bg-gray-50 p-5 shadow-sm">
                                    <form method="POST" action="{{ route('tournaments.board-sets.boards.update', [$tournament, $boardSet, $board]) }}">
                                        @csrf
                                        @method('PATCH')

                                        <div class="mb-5 flex flex-col gap-4 sm:flex-row">
                                            <div class="sm:w-40">
                                                <x-input-label for="board_number_{{ $board->id }}" :value="__('Board Number')" />
                                                <x-text-input id="board_number_{{ $board->id }}" name="board_number" type="number" min="1" class="mt-1 block w-full" :value="old('board_number', $board->board_number)" required />
                                            </div>
                                            <div class="sm:w-48">
                                                <x-input-label for="vulnerability_{{ $board->id }}" :value="__('Vulnerability')" />
                                                <select id="vulnerability_{{ $board->id }}" name="vulnerability" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                    @foreach(['None', 'NS', 'EW', 'All'] as $vulnerability)
                                                        <option value="{{ $vulnerability }}" @selected(old('vulnerability', $board->vulnerability) === $vulnerability)>
                                                            {{ __($vulnerability) }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                                            <table class="min-w-full border-collapse text-sm">
                                                <thead class="bg-gray-50 text-xs font-black uppercase tracking-widest text-gray-500">
                                                    <tr>
                                                        <th class="border px-3 py-2 text-left">{{ __('Hand') }}</th>
                                                        <th class="border px-3 py-2 text-left">{{ __('Spades') }}</th>
                                                        <th class="border px-3 py-2 text-left">{{ __('Hearts') }}</th>
                                                        <th class="border px-3 py-2 text-left">{{ __('Diamonds') }}</th>
                                                        <th class="border px-3 py-2 text-left">{{ __('Clubs') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach([
                                                        'N' => ['label' => __('North'), 'cards' => $board->cards_north],
                                                        'S' => ['label' => __('South'), 'cards' => $board->cards_south],
                                                        'E' => ['label' => __('East'), 'cards' => $board->cards_east],
                                                        'W' => ['label' => __('West'), 'cards' => $board->cards_west],
                                                    ] as $seat => $hand)
                                                        <tr>
                                                            <th class="border bg-gray-50 px-3 py-2 text-left text-xs font-black uppercase tracking-widest text-gray-500">
                                                                {{ $hand['label'] }}
                                                            </th>
                                                            @foreach(['S', 'H', 'D', 'C'] as $suit)
                                                                <td class="border px-2 py-2">
                                                                    <input
                                                                        name="cards[{{ $seat }}][{{ $suit }}]"
                                                                        type="text"
                                                                        value="{{ old("cards.$seat.$suit", $hand['cards'][$suit] ?? '') }}"
                                                                        class="block w-full rounded-md border-gray-300 text-sm uppercase shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                                        autocomplete="off"
                                                                    />
                                                                </td>
                                                            @endforeach
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>

                                        <div class="mt-5 flex justify-end gap-3">
                                            <x-secondary-button type="button" @click="editingBoard = false">
                                                {{ __('Cancel') }}
                                            </x-secondary-button>
                                            <x-primary-button type="submit">
                                                {{ __('Save Board') }}
                                            </x-primary-button>
                                        </div>
                                    </form>
                                </div>

                                <div class="mx-auto max-w-xl rounded-xl border border-gray-200 bg-white shadow-sm">
                                    <div class="flex flex-col gap-4 border-b border-gray-200 p-4 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <h4 class="text-sm font-black uppercase tracking-widest text-gray-500">
                                                {{ __('Double Dummy Analysis') }}
                                            </h4>
                                            @if($bestContract)
                                                <p class="mt-1 text-sm font-bold text-gray-800">
                                                    {{ $bestContract['description'] }}
                                                </p>
                                                @if(!empty($analysis['optimum_score']))
                                                    <p class="mt-1 text-xs font-semibold text-gray-500">
                                                        {{ __('Optimum') }}: {{ $analysis['optimum_score'] }}
                                                    </p>
                                                @endif
                                            @else
                                                <p class="mt-1 text-sm font-semibold text-gray-400">
                                                    {{ __('No double dummy analysis yet.') }}
                                                </p>
                                                <p class="mt-1 text-xs font-semibold text-gray-400">
                                                    {{ __('Upload a PBN file with OptimumResultTable to import it.') }}
                                                </p>
                                            @endif
                                        </div>
                                    </div>

                                    @if($analysisTable)
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

                                            @if(!empty($analysis['computed_at']))
                                                <p class="mt-3 text-center text-[10px] font-bold uppercase tracking-widest text-gray-400">
                                                    {{ __('Imported') }} {{ \Illuminate\Support\Carbon::parse($analysis['computed_at'])->diffForHumans() }}
                                                </p>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Board Results Table -->
                    <div class="mt-12 border-t pt-8">
                        @foreach($boards as $index => $board)
                            <div x-show="currentIdx === {{ $index }}" x-cloak>
                                <div class="flex items-center justify-between mb-6">
                                    <h4 class="text-lg font-black uppercase tracking-widest text-gray-500">
                                        {{ __('Match Results for Board') }} {{ $board->board_number }}
                                    </h4>
                                </div>

                                @if(!empty($boardResults[$board->board_number]))
                                    <div class="overflow-x-auto rounded-xl border border-gray-200">
                                        <table class="min-w-full text-xs text-center border-collapse">
                                            <thead class="bg-gray-50 font-black text-gray-700 uppercase tracking-tighter">
                                                <tr>
                                                    <th class="py-4 border px-4">{{ __('Match') }}</th>
                                                    <th class="py-4 border">{{ __('Room') }}</th>
                                                    <th class="py-4 border text-blue-600">{{ __('NS') }}</th>
                                                    <th class="py-4 border text-blue-600">{{ __('EW') }}</th>
                                                    <th class="py-4 border">{{ __('Contract') }}</th>
                                                    <th class="py-4 border text-center">{{ __('Score') }} (NS)</th>
                                                    <th class="py-4 border" colspan="2">{{ __('IMPs') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200">
                                                @foreach($boardResults[$board->board_number] as $res)
                                                    <tr class="hover:bg-gray-50 transition-colors">
                                                        <td class="py-4 px-4 border text-left">
                                                            <div class="font-black text-gray-900 leading-tight">{{ $res['home_team'] }} <span class="text-gray-300 font-normal">vs</span> {{ $res['away_team'] }}</div>
                                                            <div class="text-[9px] text-gray-400 font-bold uppercase mt-0.5">{{ $res['round_name'] }}</div>
                                                        </td>
                                                        <td class="py-4 px-4 border font-bold uppercase tracking-widest text-[10px] {{ $res['room'] === 'Open' ? 'text-blue-600' : 'text-red-600' }}">
                                                            {{ __($res['room']) }}
                                                        </td>
                                                        <td class="py-4 border px-3 text-gray-600 font-medium">{{ $res['ns_names'] }}</td>
                                                        <td class="py-4 border px-3 text-gray-600 font-medium">{{ $res['ew_names'] }}</td>
                                                        <td class="py-4 border font-bold italic"><x-bridge-contract :contract="$res['contract']" /></td>
                                                        <td class="py-4 border font-mono font-black text-sm {{ $res['score'] > 0 ? 'text-green-600' : ($res['score'] < 0 ? 'text-red-600' : '') }}">
                                                            {{ $res['score'] !== null ? ($res['score'] > 0 ? '+' : '') . $res['score'] : '-' }}
                                                        </td>
                                                        <td class="py-4 border font-black text-green-700 text-sm w-12">{{ $res['home_imp'] ?: '' }}</td>
                                                        <td class="py-4 border font-black text-red-700 text-sm w-12">{{ $res['away_imp'] ?: '' }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="bg-gray-50 border-2 border-dashed border-gray-200 rounded-xl p-12 text-center text-gray-400 font-bold italic text-sm">
                                        {{ __('No match results recorded for this board yet.') }}
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        [x-cloak] { display: none !important; }
    </style>
</x-app-layout>
