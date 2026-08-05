<?php

use App\Http\Controllers\ApprovedVersionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\GlobalSettingsController;
use App\Http\Controllers\OperationController;
use App\Http\Controllers\PublicQuizController;
use App\Http\Controllers\QuizManagementController;
use App\Http\Controllers\SubAdminController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'success' => true,
    'message' => 'Islamic Content Platform API',
]));

Route::prefix('v1')->group(function () {
    // Public routes (Authentication, Public Quiz Access)
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/quizzes/{token}', [PublicQuizController::class, 'show']);
    Route::post('/quizzes/{token}/submit', [PublicQuizController::class, 'submit']);

    // Protected Admin routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::apiResource('documents', DocumentController::class);
        Route::post('/operations', [OperationController::class, 'store']);
        Route::post('/approved-versions', [ApprovedVersionController::class, 'store']);
        Route::post('/quizzes', [QuizManagementController::class, 'store']);
        Route::get('/quizzes/{quiz}/results', [QuizManagementController::class, 'results']);

        // Super Admin only routes
        Route::middleware('role:super_admin')->group(function () {
            Route::apiResource('sub-admins', SubAdminController::class);
            Route::put('/settings', [GlobalSettingsController::class, 'update']);
        });
    });
});
