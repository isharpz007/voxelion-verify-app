<?php

use App\Http\Controllers\Api\CompletionCodeController;
use App\Http\Controllers\Api\RewardSubmissionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| OpinionPadi server-to-server API
|--------------------------------------------------------------------------
| Bearer-token guarded. The completion-code endpoint replaces
| screenshot-only trust during reward review.
*/

Route::prefix('v1')->middleware(['op.token', 'throttle:60,1'])->group(function () {
    Route::get('/completion-codes/{code}', [CompletionCodeController::class, 'show']);

    Route::get('/reward-submissions', [RewardSubmissionController::class, 'index']);
    Route::post('/reward-submissions', [RewardSubmissionController::class, 'store']);
    Route::post('/reward-submissions/{id}/decision', [RewardSubmissionController::class, 'decide']);
});
