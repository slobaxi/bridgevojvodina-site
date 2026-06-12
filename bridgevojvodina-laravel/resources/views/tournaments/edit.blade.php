<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Edit Tournament') }}
            </h2>
            <div class="flex items-center gap-4">
                @if($tournament->team_results && !empty($tournament->team_results->player_butlers))
                    <a href="{{ route('tournaments.butler', $tournament) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        {{ __('Butler') }}
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-12" x-data="{ 
        uploadModalOpen: false, 
        uploadRoundId: '', 
        uploadRoundName: '',
        renumberModalOpen: false,
        renumberRoundId: '',
        renumberRoundName: '',
        renumberStartingNumber: 1,
        generateRoundsModalOpen: false,
        uploadCsvModalOpen: false
    }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="mb-6 flex justify-end gap-4">
                        @can('delete', $tournament)
                            <form method="POST" action="{{ route('tournaments.destroy', $tournament) }}" id="delete-tournament-form" class="hidden">
                                @csrf
                                @method('DELETE')
                            </form>
                            <x-danger-button type="button" onclick="if(confirm('{{ __('Are you sure?') }}')) document.getElementById('delete-tournament-form').submit();">
                                {{ __('Delete Tournament') }}
                            </x-danger-button>
                        @endcan

                        @if($tournament instanceof \App\Models\TournamentConfiguration)
                            <form method="POST" action="{{ route('tournaments.publish', $tournament) }}" id="publish-form" class="hidden">
                                @csrf
                            </form>
                            <x-secondary-button type="button" onclick="if(confirm('{{ __('Are you sure you want to publish this tournament? This will overwrite any existing published data for this tournament.') }}')) document.getElementById('publish-form').submit();" class="!bg-green-600 !text-white hover:!bg-green-700">
                                {{ __('Publish Tournament') }}
                            </x-secondary-button>
                        @endif
                        
                        <x-primary-button type="submit" form="update-tournament-form">
                            {{ __('Update Tournament') }}
                        </x-primary-button>
                    </div>

                    <form method="POST" action="{{ route('tournaments.update', $tournament) }}" id="update-tournament-form">
                        @csrf
                        @method('PATCH')
                        @include('tournaments.form')
                    </form>

                    @if($tournament->team_results)
                        <!-- Tournament Settings -->
                        <div class="mt-12 pt-8 border-t border-gray-200">
                            <h3 class="text-lg font-bold mb-4">{{ __('Tournament Settings') }}</h3>
                            <form method="POST" action="{{ route('tournaments.settings.update', $tournament) }}">
                                @csrf
                                @method('PATCH')
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-end">
                                    <div class="flex gap-4">
                                        <div class="flex-1">
                                            <x-input-label for="bye_vp" :value="__('Bye VP')" />
                                            <x-text-input id="bye_vp" name="bye_vp" type="number" step="0.5" class="mt-1 block w-full" :value="old('bye_vp', $tournament->team_results->bye_vp ?? 12.0)" required />
                                            <x-input-error class="mt-2" :messages="$errors->get('bye_vp')" />
                                        </div>
                                        <div class="flex-1">
                                            <x-input-label for="boards_per_round" :value="__('Boards per Round')" />
                                            <x-text-input id="boards_per_round" name="boards_per_round" type="number" class="mt-1 block w-full" :value="old('boards_per_round', $tournament->team_results->boards_per_round ?? 16)" required />
                                            <x-input-error class="mt-2" :messages="$errors->get('boards_per_round')" />
                                        </div>
                                    </div>
                                    <div class="flex justify-end">
                                        <x-secondary-button type="submit">
                                            {{ __('Update Settings') }}
                                        </x-secondary-button>
                                    </div>
                                </div>
                                <p class="mt-2 text-xs text-gray-500">
                                    {{ __('Victory Points awarded for a "bye" round, and default number of boards per round.') }}
                                </p>
                            </form>
                        </div>

                        <!-- Teams Section -->
                        <div class="mt-12 pt-8 border-t border-gray-200">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-bold">{{ __('Teams') }}</h3>
                                <div class="flex gap-4">
                                    <form method="POST" action="{{ route('tournaments.teams.add', $tournament) }}" class="flex gap-2">
                                        @csrf
                                        <x-text-input name="name" type="text" class="py-1 px-3 text-sm" placeholder="{{ __('Team Name') }}" required />
                                        <x-primary-button type="submit" class="!py-1 !text-[10px]">
                                            {{ __('Add Team') }}
                                        </x-primary-button>
                                    </form>
                                    @if(count($tournament->team_results->teams) > 0)
                                        <a href="{{ route('tournaments.teams.numbers.edit', $tournament) }}" class="inline-flex items-center px-4 py-1 bg-white border border-gray-300 rounded-md font-semibold text-[10px] text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150">
                                            {{ __('Manage Numbers') }}
                                        </a>
                                    @endif
                                </div>
                            </div>
                            
                            @if(count($tournament->team_results->teams) > 0)
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-16">#</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Name') }}</th>
                                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Actions') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            @foreach(collect($tournament->team_results->teams)->sortBy(fn($t) => $t->number ?? 999999) as $team)
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-500">{{ $team->number ?? '-' }}</td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $team->name }}</td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                        <div class="flex justify-end gap-3">
                                                            <a href="{{ route('tournaments.teams.edit', [$tournament, $team->id]) }}" class="text-indigo-600 hover:text-indigo-900">{{ __('Edit') }}</a>
                                                            <form method="POST" action="{{ route('tournaments.teams.destroy', [$tournament, $team->id]) }}" onsubmit="return confirm('{{ __('Are you sure you want to delete this team?') }}')">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="text-red-600 hover:text-red-900">{{ __('Delete') }}</button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="bg-gray-50 border-2 border-dashed border-gray-200 rounded-lg p-12 text-center text-gray-500 italic">
                                    {{ __('No teams added yet. Add your first team using the form above.') }}
                                </div>
                            @endif
                        </div>

                        <!-- Rounds & Board Sets -->
                        <div class="mt-12 pt-8 border-t border-gray-200">
                            <div class="flex justify-between items-center mb-4">
                                <div class="flex items-center gap-4">
                                    <h3 class="text-lg font-bold">{{ __('Rounds & Board Sets') }}</h3>
                                    @if(collect($tournament->team_results->rounds)->contains('status', 'idle'))
                                        <form method="POST" action="{{ route('tournaments.rounds.idle.destroy', $tournament) }}" onsubmit="return confirm('{{ __('Are you sure you want to delete all idle rounds?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-red-600 hover:text-red-900 font-bold uppercase tracking-wider">
                                                {{ __('Delete All Idle') }}
                                            </button>
                                        </form>
                                    @endif
                                </div>
                                
                                <div class="flex items-center gap-3">
                                    @if(count($tournament->team_results->teams) >= 2)
                                        <x-secondary-button type="button" @click="generateRoundsModalOpen = true" class="!py-1 !text-[10px]">
                                            {{ count($tournament->team_results->rounds) > 0 ? __('Add More Rounds') : __('Generate Rounds') }}
                                        </x-secondary-button>
                                    @else
                                        <span class="text-[10px] text-gray-400 italic">{{ __('Add at least 2 teams to generate rounds') }}</span>
                                    @endif
                                    
                                    <x-secondary-button type="button" @click="uploadCsvModalOpen = true" class="!py-1 !text-[10px]">
                                        {{ __('Upload CSV') }}
                                    </x-secondary-button>
                                </div>
                            </div>
                            
                            @if(count($tournament->team_results->rounds) > 0)
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Round') }}</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Status') }}</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Board Set') }}</th>
                                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Actions') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            @foreach($tournament->team_results->rounds as $round)
                                                @php
                                                    $roundStatus = $round->status ?? 'idle';
                                                @endphp
                                                <tr class="align-top">
                                                    <td class="px-6 py-4">
                                                        <div class="text-sm font-bold text-gray-900 mb-2">{{ $round->name }}</div>
                                                        <!-- Match Pairs -->
                                                        <div class="space-y-3">
                                                            @foreach($round->matches as $match)
                                                                @php
                                                                    $homeName = collect($tournament->team_results->teams)->firstWhere('id', $match->home_team_id)->name ?? __('BYE');
                                                                    $awayName = collect($tournament->team_results->teams)->firstWhere('id', $match->away_team_id)->name ?? __('BYE');
                                                                    $isBye = !$match->home_team_id || !$match->away_team_id || $match->home_team_id === 'bye' || $match->away_team_id === 'bye';

                                                                    $totalBoards = $round->boards_per_round ?? $tournament->team_results->boards_per_round ?? 16;
                                                                    $openCount = count(array_filter($match->boards, fn($b) => ($b->home_score !== null && $b->home_score !== '')));
                                                                    $closedCount = count(array_filter($match->boards, fn($b) => ($b->away_score !== null && $b->away_score !== '')));

                                                                    $canEdit = $roundStatus === 'inProgress' && !$isBye;
                                                                @endphp

                                                                <div class="flex flex-col gap-1">
                                                                    <div class="text-[11px] font-bold text-gray-700 flex items-center gap-2">
                                                                        <span class="{{ $homeName === __('BYE') ? 'text-gray-400 italic' : '' }}">{{ $homeName }}</span>
                                                                        <span class="text-gray-300 font-normal">vs</span>
                                                                        <span class="{{ $awayName === __('BYE') ? 'text-gray-400 italic' : '' }}">{{ $awayName }}</span>
                                                                        
                                                                        <span class="ms-auto text-[9px] font-mono text-gray-400 bg-gray-50 px-1.5 py-0.5 rounded border border-gray-100">
                                                                            {{ number_format($match->home_vp, 1) }} - {{ number_format($match->away_vp, 1) }}
                                                                        </span>
                                                                    </div>

                                                                    @if($canEdit)
                                                                        <div class="ml-4 flex flex-col gap-0.5">
                                                                            <a href="{{ route('tournaments.match.room.edit', ['tournament' => $tournament, 'round' => $round->id, 'match' => ($match->id ?: $match->home_team_id), 'room' => 'open']) }}" class="text-[10px] text-blue-600 hover:text-blue-900 hover:underline flex items-center gap-1">
                                                                                <span class="w-1.5 h-1.5 rounded-full {{ $openCount == $totalBoards ? 'bg-green-500' : 'bg-blue-400' }}"></span>
                                                                                {{ __('Open Room') }}: {{ $openCount }}/{{ $totalBoards }}
                                                                            </a>
                                                                            <a href="{{ route('tournaments.match.room.edit', ['tournament' => $tournament, 'round' => $round->id, 'match' => ($match->id ?: $match->home_team_id), 'room' => 'closed']) }}" class="text-[10px] text-red-600 hover:text-red-900 hover:underline flex items-center gap-1">
                                                                                <span class="w-1.5 h-1.5 rounded-full {{ $openCount == $totalBoards ? 'bg-green-500' : 'bg-red-400' }}"></span>
                                                                                {{ __('Closed Room') }}: {{ $closedCount }}/{{ $totalBoards }}
                                                                            </a>
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        @php
                                                            $statusColors = [
                                                                'idle' => 'bg-gray-100 text-gray-800',
                                                                'inProgress' => 'bg-blue-100 text-blue-800',
                                                                'complete' => 'bg-green-100 text-green-800',
                                                            ];
                                                        @endphp
                                                        <div class="flex flex-col gap-2">
                                                            <span class="inline-flex w-fit items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $statusColors[$roundStatus] ?? 'bg-gray-100 text-gray-800' }}">
                                                                {{ __($roundStatus) }}
                                                            </span>
                                                            <form method="POST" action="{{ route('tournaments.rounds.status.update', [$tournament, $round->id]) }}">
                                                                @csrf
                                                                @method('PATCH')
                                                                <select name="status" onchange="this.form.submit()" class="text-[10px] py-1 px-2 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                                                    <option value="idle" {{ $roundStatus === 'idle' ? 'selected' : '' }}>{{ __('idle') }}</option>
                                                                    <option value="inProgress" {{ $roundStatus === 'inProgress' ? 'selected' : '' }}>{{ __('inProgress') }}</option>
                                                                    <option value="complete" {{ $roundStatus === 'complete' ? 'selected' : '' }}>{{ __('complete') }}</option>
                                                                </select>
                                                            </form>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <div class="flex flex-col gap-2">
                                                            @if($round->board_set_id)
                                                                @php
                                                                    $set = $boardSets->firstWhere('id', $round->board_set_id);
                                                                @endphp
                                                                @if($set)
                                                                    <a href="{{ route('tournaments.board-sets.show', [$tournament, $set]) }}" class="group flex flex-col">
                                                                        <span class="font-bold text-indigo-600 group-hover:text-indigo-900 group-hover:underline">{{ $set->name }}</span>
                                                                        <span class="text-[10px] text-gray-400">{{ $set->created_at->format('d.m.Y H:i') }}</span>
                                                                    </a>
                                                                    <a href="{{ route('tournaments.board-sets.export-pbn', [$tournament, $set]) }}" class="inline-flex w-fit items-center px-3 py-1 bg-white border border-gray-300 rounded-md font-semibold text-[10px] text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                                                        {{ __('Export PBN') }}
                                                                    </a>
                                                                @else
                                                                    <span class="text-red-400 italic text-xs">{{ __('Set not found') }}</span>
                                                                @endif
                                                            @else
                                                                <button 
                                                                    type="button" 
                                                                    @click="uploadModalOpen = true; uploadRoundId = '{{ $round->id }}'; uploadRoundName = '{{ $round->name }}'"
                                                                    class="inline-flex items-center px-3 py-1 bg-white border border-gray-300 rounded-md font-semibold text-[10px] text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                                                >
                                                                    {{ __('Upload Boards') }}
                                                                </button>
                                                            @endif

                                                            <button 
                                                                type="button" 
                                                                @click="renumberModalOpen = true; renumberRoundId = '{{ $round->id }}'; renumberRoundName = '{{ $round->name }}'; renumberStartingNumber = {{ (isset($round->matches[0]) && !empty($round->matches[0]->boards)) ? $round->matches[0]->boards[0]->board_number : 1 }}"
                                                                class="inline-flex items-center px-3 py-1 bg-white border border-gray-300 rounded-md font-semibold text-[10px] text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                                            >
                                                                {{ __('Renumber') }}
                                                            </button>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                        <div class="flex flex-col items-end gap-2">
                                                            @if(($round->status ?? 'idle') === 'idle')
                                                                <div class="flex gap-2">
                                                                    @if(!$loop->first)
                                                                        <form method="POST" action="{{ route('tournaments.rounds.reorder', [$tournament, $round->id]) }}">
                                                                            @csrf
                                                                            @method('PATCH')
                                                                            <input type="hidden" name="direction" value="up">
                                                                            <button type="submit" class="text-gray-400 hover:text-indigo-600" title="{{ __('Move Up') }}">
                                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path></svg>
                                                                            </button>
                                                                        </form>
                                                                    @endif

                                                                    @if(!$loop->last)
                                                                        <form method="POST" action="{{ route('tournaments.rounds.reorder', [$tournament, $round->id]) }}">
                                                                            @csrf
                                                                            @method('PATCH')
                                                                            <input type="hidden" name="direction" value="down">
                                                                            <button type="submit" class="text-gray-400 hover:text-indigo-600" title="{{ __('Move Down') }}">
                                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                                                            </button>
                                                                        </form>
                                                                    @endif
                                                                </div>

                                                                <form method="POST" action="{{ route('tournaments.rounds.destroy', [$tournament, $round->id]) }}" onsubmit="return confirm('{{ __('Are you sure?') }}')">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit" class="text-red-600 hover:text-red-900 text-[10px] uppercase font-bold tracking-wider">
                                                                        {{ __('Delete Round') }}
                                                                    </button>
                                                                </form>
                                                            @endif
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="bg-gray-50 border-2 border-dashed border-gray-200 rounded-lg p-12 text-center text-gray-500 italic">
                                    {{ __('No rounds generated yet. Add teams first, then click Generate Rounds.') }}
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Upload Board Set Modal -->
        <div x-show="uploadModalOpen" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div x-show="uploadModalOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 transition-opacity" @click="uploadModalOpen = false">
                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                </div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                <div x-show="uploadModalOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('Upload Board Set for') }} <span x-text="uploadRoundName"></span></h3>
                        <form method="POST" action="{{ route('tournaments.board-sets.upload', $tournament) }}" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="round_id" :value="uploadRoundId">
                            <div class="space-y-4">
                                <div>
                                    <x-input-label for="board_set_file_modal" :value="__('Board Set PBN File')" />
                                    <input type="file" id="board_set_file_modal" name="board_set_file" accept=".pbn" class="mt-1 block w-full border border-gray-300 rounded-md p-2" required>
                                </div>
                            </div>
                            <div class="mt-6 flex justify-end gap-3">
                                <x-secondary-button type="button" @click="uploadModalOpen = false">{{ __('Cancel') }}</x-secondary-button>
                                <x-primary-button type="submit">{{ __('Upload') }}</x-primary-button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Generate Rounds Modal -->
        <div x-show="generateRoundsModalOpen" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div x-show="generateRoundsModalOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 transition-opacity" @click="generateRoundsModalOpen = false">
                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                </div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                <div x-show="generateRoundsModalOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('Generate Rounds') }}</h3>
                        <form method="POST" action="{{ route('tournaments.rounds.generate', $tournament) }}" onsubmit="return confirm('{{ __('New rounds will be appended to the current schedule. Are you sure?') }}')">
                            @csrf
                            <div class="space-y-4">
                                <div>
                                    <x-input-label for="format_modal" :value="__('Select Format')" />
                                    <select id="format_modal" name="format" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                        <option value="">{{ __('Select Format') }}</option>
                                        <option value="single_round_robin">{{ __('Single Round Robin') }}</option>
                                        <option value="double_round_robin">{{ __('Double Round Robin') }}</option>
                                    </select>
                                </div>
                                <div>
                                    <x-input-label for="boards_per_round_modal" :value="__('Boards per Round')" />
                                    <x-text-input id="boards_per_round_modal" name="boards_per_round" type="number" class="mt-1 block w-full" :value="old('boards_per_round', $tournament->team_results->boards_per_round ?? 16)" required />
                                </div>
                            </div>
                            <div class="mt-6 flex justify-end gap-3">
                                <x-secondary-button type="button" @click="generateRoundsModalOpen = false">{{ __('Cancel') }}</x-secondary-button>
                                <x-primary-button type="submit">{{ __('Generate') }}</x-primary-button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upload CSV Modal -->
        <div x-show="uploadCsvModalOpen" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div x-show="uploadCsvModalOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 transition-opacity" @click="uploadCsvModalOpen = false">
                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                </div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                <div x-show="uploadCsvModalOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('Upload Rounds CSV') }}</h3>
                        <form method="POST" action="{{ route('tournaments.rounds.upload-csv', $tournament) }}" enctype="multipart/form-data" onsubmit="return confirm('{{ __('New rounds will be appended from the CSV. Are you sure?') }}')">
                            @csrf
                            <div class="space-y-4">
                                <div>
                                    <x-input-label for="csv_file_modal" :value="__('CSV File')" />
                                    <input type="file" id="csv_file_modal" name="csv_file" accept=".csv" class="mt-1 block w-full border border-gray-300 rounded-md p-2" required>
                                </div>
                                <div>
                                    <x-input-label for="boards_per_round_csv_modal" :value="__('Boards per Round')" />
                                    <x-text-input id="boards_per_round_csv_modal" name="boards_per_round" type="number" class="mt-1 block w-full" :value="old('boards_per_round', $tournament->team_results->boards_per_round ?? 16)" required />
                                </div>
                            </div>
                            <div class="mt-6 flex justify-end gap-3">
                                <x-secondary-button type="button" @click="uploadCsvModalOpen = false">{{ __('Cancel') }}</x-secondary-button>
                                <x-primary-button type="submit">{{ __('Upload') }}</x-primary-button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <!-- Renumber Boards Modal -->
        <div x-show="renumberModalOpen" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div x-show="renumberModalOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 transition-opacity" @click="renumberModalOpen = false">
                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                </div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                <div x-show="renumberModalOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('Renumber Boards for') }} <span x-text="renumberRoundName"></span></h3>
                        <form method="POST" :action="`{{ route('tournaments.rounds.renumber', ['tournament' => $tournament, 'roundId' => 'ROUND_ID']) }}`.replace('ROUND_ID', renumberRoundId)">
                            @csrf
                            <div class="space-y-4">
                                <div>
                                    <x-input-label for="starting_board_number" :value="__('Starting Board Number')" />
                                    <x-text-input id="starting_board_number" name="starting_board_number" type="number" class="mt-1 block w-full" x-model="renumberStartingNumber" required />
                                    <p class="mt-2 text-xs text-gray-500">
                                        {{ __('This will sequentially renumber all boards in this round starting from this value.') }}
                                    </p>
                                </div>
                            </div>
                            <div class="mt-6 flex justify-end gap-3">
                                <x-secondary-button type="button" @click="renumberModalOpen = false">{{ __('Cancel') }}</x-secondary-button>
                                <x-primary-button type="submit">{{ __('Update') }}</x-primary-button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</x-app-layout>
