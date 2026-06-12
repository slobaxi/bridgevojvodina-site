<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Session;

use App\Http\Controllers\ClubController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\EventController;

use App\Http\Controllers\UserController;

use App\Http\Controllers\ContactController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\TournamentController;
use App\Http\Controllers\TournamentConfigurationController;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('contact', [ContactController::class, 'index'])->name('contact');

Route::get('lang/{locale}', function ($locale) {
    if (in_array($locale, ['en', 'sr'])) {
        Session::put('locale', $locale);
    }
    return redirect()->back();
})->name('lang.switch');

Route::resource('clubs', ClubController::class);
Route::resource('players', PlayerController::class);
Route::resource('events', EventController::class);

Route::middleware('auth')->group(function () {
    Route::resource('tournaments', TournamentController::class)->except(['index', 'show']);
    Route::get('tournament-configurations', [TournamentConfigurationController::class, 'index'])->name('tournament-configurations.index');
    Route::post('tournaments/{tournament}/board-sets', [TournamentController::class, 'uploadBoardSet'])->name('tournaments.board-sets.upload');
    Route::get('tournaments/{tournament}/board-sets/{boardSet}/export-pbn', [TournamentController::class, 'exportBoardSetPbn'])->name('tournaments.board-sets.export-pbn');
    Route::delete('tournaments/{tournament}/board-sets/{boardSet}', [TournamentController::class, 'destroyBoardSet'])->name('tournaments.board-sets.destroy');
    Route::patch('tournaments/{tournament}/board-sets/{boardSet}/boards/{board}', [TournamentController::class, 'updateBoard'])->name('tournaments.board-sets.boards.update');
    Route::get('tournaments/{tournament}/teams/numbers', [TournamentController::class, 'editTeamNumbers'])->name('tournaments.teams.numbers.edit');
    Route::patch('tournaments/{tournament}/teams/numbers', [TournamentController::class, 'updateTeamNumbers'])->name('tournaments.teams.numbers.update');
    Route::post('tournaments/{tournament}/teams', [TournamentController::class, 'addTeam'])->name('tournaments.teams.add');
    Route::get('tournaments/{tournament}/teams/{teamId}/edit', [TournamentController::class, 'editTeam'])->name('tournaments.teams.edit');
    Route::patch('tournaments/{tournament}/teams/{teamId}', [TournamentController::class, 'updateTeam'])->name('tournaments.teams.update');
    Route::delete('tournaments/{tournament}/teams/{teamId}', [TournamentController::class, 'destroyTeam'])->name('tournaments.teams.destroy');
    Route::patch('tournaments/{tournament}/rounds/{roundId}/status', [TournamentController::class, 'updateRoundStatus'])->name('tournaments.rounds.status.update');
    Route::patch('tournaments/{tournament}/settings', [TournamentController::class, 'updateSettings'])->name('tournaments.settings.update');
    Route::post('tournaments/{tournament}/rounds/generate', [TournamentController::class, 'generateRounds'])->name('tournaments.rounds.generate');
    Route::post('tournaments/{tournament}/rounds/upload-csv', [TournamentController::class, 'uploadRoundsCsv'])->name('tournaments.rounds.upload-csv');
    Route::post('tournaments/{tournament}/rounds/{roundId}/renumber', [TournamentController::class, 'renumberBoards'])->name('tournaments.rounds.renumber');
    Route::patch('tournaments/{tournament}/rounds/{roundId}/reorder', [TournamentController::class, 'reorderRound'])->name('tournaments.rounds.reorder');
    Route::delete('tournaments/{tournament}/rounds/idle', [TournamentController::class, 'destroyIdleRounds'])->name('tournaments.rounds.idle.destroy');
    Route::delete('tournaments/{tournament}/rounds/{roundId}', [TournamentController::class, 'destroyRound'])->name('tournaments.rounds.destroy');
    Route::post('tournaments/{tournament}/teams/{teamId}/players', [TournamentController::class, 'addPlayerToTeam'])->name('tournaments.teams.players.add');
    Route::delete('tournaments/{tournament}/teams/{teamId}/players/{playerId}', [TournamentController::class, 'removePlayerFromTeam'])->name('tournaments.teams.players.remove');
    Route::post('tournaments/{tournament}/teams/{teamId}/captain/{playerId}', [TournamentController::class, 'setTeamCaptain'])->name('tournaments.teams.captain.set');
    Route::get('tournaments/{tournament}/round/{round}/match/{match}/room/{room}/edit', [TournamentController::class, 'editMatchRoom'])->name('tournaments.match.room.edit');
    Route::patch('tournaments/{tournament}/round/{round}/match/{match}/room/{room}/lineup', [TournamentController::class, 'updateMatchLineup'])->name('tournaments.match.room.lineup.update');
    Route::patch('tournaments/{tournament}/round/{round}/match/{match}/room/{room}/board/{boardNumber}', [TournamentController::class, 'updateMatchBoard'])->name('tournaments.match.room.board.update');
    Route::post('tournaments/{tournament}/round/{round}/match/{match}/room/{room}/boards/csv', [TournamentController::class, 'uploadMatchBoardsCsv'])->name('tournaments.match.room.boards.csv.upload');
    Route::get('tournaments/{tournament}/round/{round}/match/{match}/room/{room}/boards/csv', [TournamentController::class, 'downloadMatchBoardsCsv'])->name('tournaments.match.room.boards.csv.download');
    Route::post('tournaments/{tournament}/publish', [TournamentController::class, 'publish'])->name('tournaments.publish');

    Route::prefix('scoring')->name('scoring.')->group(function () {
        Route::get('/', [\App\Http\Controllers\PlayerScoringController::class, 'index'])->name('index');
        Route::get('/{tournament}/round/{roundId}/match/{matchId}/room/{room}', [\App\Http\Controllers\PlayerScoringController::class, 'showRoom'])->name('room.show');
        Route::patch('/{tournament}/round/{roundId}/match/{matchId}/room/{room}/board/{boardNumber}', [\App\Http\Controllers\PlayerScoringController::class, 'updateBoard'])->name('board.update');
    });
});

Route::resource('tournaments', TournamentController::class)->only(['index', 'show']);
Route::get('tournaments/{tournament}/butler', [TournamentController::class, 'butler'])->name('tournaments.butler');
Route::get('tournaments/{tournament}/details', [TournamentController::class, 'details'])->name('tournaments.details');
Route::get('tournaments/{tournament}/teams/{team}', [TournamentController::class, 'showTeam'])->name('tournaments.teams.show');
Route::get('tournaments/{tournament}/board-sets/{boardSet}', [TournamentController::class, 'showBoardSet'])->name('tournaments.board-sets.show');
Route::get('tournaments/{tournament}/round/{round}/match/{match}', [TournamentController::class, 'match'])->name('tournaments.match');
Route::get('tournaments/{tournament}/round/{round}/board/{board_number}', [TournamentController::class, 'board'])->name('tournaments.board');

Route::middleware(['auth', 'admin'])->group(function () {
    Route::resource('users', UserController::class);
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
