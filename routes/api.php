<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\FaqController;
use App\Http\Controllers\Api\ProjectInterestController;

Route::prefix('chat')->group(function () {

    Route::post('/start', [ChatController::class, 'start']);

    Route::get('/history', [ChatController::class, 'history']);

    Route::post('/message', [ChatController::class, 'message']);

    Route::post('/close', [ChatController::class, 'close']);

    Route::post('/lead', [LeadController::class, 'store']);

    Route::get('/projects', [ProjectController::class, 'index']);

    Route::get('/projects/{id}', [ProjectController::class, 'show']);

    Route::get('/locations', [ProjectController::class, 'locations']);

    Route::get('/faqs', [FaqController::class, 'index']);

    Route::post('/project-interest', [ProjectInterestController::class, 'store']);

    Route::post('/select-project', [ChatController::class, 'selectProject']);

    Route::post('/contact-details', [ChatController::class, 'captureContactDetails']);
});