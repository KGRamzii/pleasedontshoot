<?php

use App\Http\Controllers\ChallengeController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TeamMemberController;
use App\Http\Controllers\WebhookController;
use App\Livewire\ChallengeList;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::view('challenges', 'challenges')
    ->middleware(['auth'])
    ->name('challenges');

// Route::view('challenges', 'Challenges');


Route::get('discord', function () {
    return response()->json(['error' => 'Challenge allnn'], 300);
});

//this hould be the final one
Route::get('/discord/webhook', [WebhookController::class, 'sendToDiscord']);


// Route::get('challenges', [ChallengeController::class, 'index']);
// Route::post('challenges', [ChallengeController::class, 'issuechallenge']);
// Route::get('challenges/{id}', [ChallengeController::class, 'show']);
// Route::put('challenges/{id}', [ChallengeController::class, 'update']);
// Route::delete('challenges/{id}', [ChallengeController::class, 'destroy']);


Route::middleware(['auth'])->group(function () {
    Route::resource('teams', TeamController::class);
    Route::post('teams/{team}/members', [TeamMemberController::class, 'store'])
        ->name('team-members.store');
    Route::delete('teams/{team}/members/{user}', [TeamMemberController::class, 'destroy'])
        ->name('team-members.destroy');
});


require __DIR__ . '/auth.php';
