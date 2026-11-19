<?php

use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\DevDataController;
use App\Http\Controllers\DevHandoffController;
use App\Http\Controllers\Journey\ConsentController;
use App\Http\Controllers\Journey\ExitController;
use App\Http\Controllers\Journey\IntakeController;
use App\Http\Controllers\Journey\LivenessController;
use App\Http\Controllers\Journey\OtpController;
use App\Http\Controllers\Journey\PhotoController;
use App\Http\Controllers\Journey\ResultController;
use App\Http\Controllers\Journey\StartController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Volunteer verification journey (session auth + Inertia)
|--------------------------------------------------------------------------
| The `journey.state` middleware enforces the server-side state machine:
| created → consented → intake_done → photo_ok → live_check → terminal.
*/

// The `handoff` middleware verifies the one-time RS256 JWT (signature,
// issuer, expiry, jti replay) before the controller ever sees the request.
Route::get('/start', StartController::class)->middleware('handoff')->name('journey.start');
// The POST consumes the one-time token — rate-limit it per IP so the
// handoff cannot be brute-forced (jti replay already caps reuse).
Route::post('/start', StartController::class)->middleware(['handoff', 'throttle:10,1']);

Route::get('/', [StartController::class, 'welcome'])->name('welcome');

// Local-only OpinionPadi simulator — signs a hand-off JWT and redirects.
Route::get('/dev/handoff', DevHandoffController::class)->name('dev.handoff');
Route::get('/dev/data', DevDataController::class)->name('dev.data');

Route::middleware('journey.state:created')->group(function () {
    Route::get('/consent', [ConsentController::class, 'show'])->name('journey.consent');
    Route::post('/consent', [ConsentController::class, 'store']);
    Route::post('/consent/decline', [ConsentController::class, 'decline']);
});

Route::middleware('journey.state:consented')->group(function () {
    Route::get('/intake', [IntakeController::class, 'show'])->name('journey.intake');
    Route::put('/intake', [IntakeController::class, 'update']);

    // Contact verification OTP — session-scoped endpoints (not the HMAC
    // OpinionPadi API). Rate-limited against code guessing and send abuse.
    Route::post('/api/otp/send', [OtpController::class, 'send'])->middleware('throttle:5,1');
    Route::post('/api/otp/verify', [OtpController::class, 'verify'])->middleware('throttle:10,1');
    Route::post('/api/otp/resend', [OtpController::class, 'resend'])->middleware('throttle:5,1');
    Route::get('/api/otp/status', [OtpController::class, 'status']);
});

Route::middleware('journey.state:intake_done')->group(function () {
    Route::get('/photo', [PhotoController::class, 'show'])->name('journey.photo');
    Route::post('/photo', [PhotoController::class, 'store']);
    // S3 presigned-upload flow — issues a short-lived PUT URL when the
    // media disk supports it; the client falls back to POST /photo multipart.
    Route::post('/photo/upload-url', [PhotoController::class, 'uploadUrl'])->middleware('throttle:20,1');
});

Route::middleware('journey.state:photo_ok,live_check')->group(function () {
    Route::get('/live', [LivenessController::class, 'show'])->name('journey.live');
    Route::post('/live/challenge', [LivenessController::class, 'challenge']);
    Route::post('/live/complete', [LivenessController::class, 'complete']);
});

Route::get('/result', ResultController::class)
    ->middleware('journey.state:verified,failed,manual_review')
    ->name('journey.result');

Route::post('/withdraw', [ConsentController::class, 'withdraw'])
    ->middleware('journey.state:created,consented,intake_done,photo_ok,live_check')
    ->name('journey.withdraw');

Route::get('/exit', [ExitController::class, 'show'])
    ->middleware('journey.state:withdrawn,declined')
    ->name('journey.exit');

// Reviewer screens — HTTP Basic auth (staff SSO in production).
Route::middleware('admin.basic')->group(function () {
    Route::get('/admin/review', [ReviewController::class, 'index'])->name('admin.review');
    Route::post('/admin/review/{id}', [ReviewController::class, 'decide']);
    Route::get('/admin/metrics', [ReviewController::class, 'metrics'])->name('admin.metrics');

    // Short-lived signed URLs let reviewers view private media without
    // exposing it publicly (local analogue of S3 temporary URLs).
    Route::get('/admin/media/{asset}', MediaController::class)
        ->middleware('signed')
        ->name('admin.media');
});
