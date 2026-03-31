<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ReminderController;
use App\Http\Controllers\Api\ContactTransactionController;
use App\Http\Controllers\Api\QuickTransactionController;
use App\Http\Controllers\Api\ContactController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| FILE: routes/api.php
*/

// ✅ Public Routes (No Auth Required)
Route::prefix('auth')->group(function () {
    Route::post('/sign-up',        [AuthController::class, 'signUp']);
    Route::post('/sign-in',        [AuthController::class, 'signIn']);
    Route::post('/forgot-password',[AuthController::class, 'forgotPassword']);
    Route::post('/verify-otp',     [AuthController::class, 'verifyOtp']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/google-login',   [GoogleAuthController::class, 'googleLogin']);
});

// 🔒 Protected Routes (Token Required)
Route::middleware('auth:sanctum')->group(function () {

    // Logout
     Route::post('/auth/update-profile',  [AuthController::class, 'updateProfile']);
    Route::delete('/auth/delete-account', [AuthController::class, 'deleteAccount']);
    Route::post('/auth/logout',           [AuthController::class, 'logout']);

    // ✅ Transaction APIs
    Route::prefix('transactions')->group(function () {
        Route::post('/',       [TransactionController::class, 'store']);    // Add Transaction
        Route::get('/',        [TransactionController::class, 'index']);    // Get All
        Route::get('/{id}',    [TransactionController::class, 'show']);     // Get One
        Route::put('/{id}',    [TransactionController::class, 'update']);   // Update
        Route::delete('/{id}', [TransactionController::class, 'destroy']); // Delete
    });


     // Notifications (FCM)
    Route::prefix('notifications')->group(function () {
        Route::post('/fcm-token', [NotificationController::class, 'saveFcmToken']);  // Token save
        Route::post('/test',      [NotificationController::class, 'testNotification']); // Test
    });

     Route::prefix('reminders')->group(function () {
        Route::post('/',              [ReminderController::class, 'store']);        // Create
        Route::get('/',               [ReminderController::class, 'index']);        // Get All (Home)
        Route::get('/{id}',           [ReminderController::class, 'show']);         // Get One
        Route::put('/{id}',           [ReminderController::class, 'update']);       // Update
        Route::delete('/{id}',        [ReminderController::class, 'destroy']);      // Delete
        Route::post('/{id}/complete', [ReminderController::class, 'markComplete']); // Mark Done
    });

     // ✅ Contact Transactions
    Route::prefix('contacts')->group(function () {
        Route::get('/',                            [ContactTransactionController::class, 'contacts']);           // All contacts list
        Route::get('/balance-summary',             [ContactTransactionController::class, 'balanceSummary']);     // You will receive/pay
        Route::get('/payables',                    [ContactTransactionController::class, 'payables']);           // Payables list
        Route::get('/receivables',                 [ContactTransactionController::class, 'receivables']);        // Receivables list
        Route::get('/{phone}/transactions',        [ContactTransactionController::class, 'contactTransactions']);// Chat style
        Route::get('/{phone}/statement',           [ContactTransactionController::class, 'statement']);          // Statement
    });

       Route::prefix('quick')->group(function () {
        Route::get('/contacts',                     [QuickTransactionController::class, 'contactsList']);    // Step 1: List
        Route::get('/contacts/{phone}/profile',     [QuickTransactionController::class, 'contactProfile']); // Step 2: Profile
        Route::post('/transaction',                 [QuickTransactionController::class, 'quickAdd']);        // Step 3: Add
    });

     // ✅ Contact Profile (Get, Create, Update, Delete by phone)
    Route::prefix('contacts-list')->group(function () {
        Route::get('/',            [ContactController::class, 'index']);         // All contacts
        Route::post('/',           [ContactController::class, 'store']);         // Create
        Route::get('/{phone}',     [ContactController::class, 'show']);          // Get by phone
        Route::post('/{phone}',    [ContactController::class, 'update']);        // Update (POST for file)
        Route::delete('/{phone}',  [ContactController::class, 'destroy']);  // Delete
         });
});
