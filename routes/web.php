<?php

use App\Http\Controllers\ChallengeController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TeamMemberController;
use App\Http\Controllers\WebhookController;
use App\Livewire\ChallengeList;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;


Route::view('/', 'welcome')->name('welcome');

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
    //notification
    Route::get('/teams/invitations', fn() => view('teams.invitations'))
        ->middleware(['auth'])
        ->name('teams.invitations');
    Route::resource('teams', TeamController::class);
    Route::get('teams', [TeamController::class, 'index'])->name('teams.index');
    Route::get('/teams/create', [TeamController::class, 'create'])->name('teams.create');
    Route::get('/teams/{team}', [TeamController::class, 'show'])->name('teams.show');

    //Route::get('teams/{team}', [TeamController::class, 'show'])->name('teams.show');

    Route::post('teams/{team}/members', [TeamMemberController::class, 'store'])
        ->name('team-members.store');
    Route::delete('teams/{team}/members/{user}', [TeamMemberController::class, 'destroy'])
        ->name('team-members.destroy');
});

// Memebership
// Route::group(['middleware' => ['auth']], function () {
//     Route::get('/teams/invite', [TeamController::class, 'invite'])->name('team.invite');
//     Route::post('/teams/join/{teamId}', [TeamController::class, 'requestToJoin'])->name('team.join');
//     Route::get('/teams/invitations', [TeamController::class, 'invitations'])->name('team.invitations');
//     Route::get('/teams/membership', [TeamController::class, 'membershipStatus'])->name('team.membership');
// });


// Might delete Later

// routes/web.php
Route::get('/send-discord', function () {
    return view('send-discord');
});

Route::post('/send-discord', function (Request $request) {
    // Simple HTTP request to your Python bot
    $response = Http::post('https://pdsapi.fly.dev/send-channel-message', [
        'channel_id' => $request->channel_id,
        'message' => $request->message
    ]);

    if ($response->successful()) {
        return back()->with('success', 'Message sent to Discord!');
    } else {
        return back()->with('error', 'Failed to send message: ' . $response->body());
    }
});



require __DIR__ . '/auth.php';
