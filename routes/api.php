<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ArtistController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\LikedController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/test', function () {
    return response()->json(['message' => 'API Laravel 12 berjalan!']);
});

// Auth
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/login-mua', [AuthController::class, 'artistLoginApi']);
Route::post('/register-mua', [AuthController::class, 'artistRegisterApi']);

// User
Route::get('/profile', [UserController::class, 'userProfile'])->middleware('auth:api');
Route::get('/favorit', [UserController::class, 'favouriteUser']);
Route::middleware('auth:api')->post('/deskripsi-artis/{id}', [LikedController::class, 'toggleLikeApi']);
Route::get('/beranda-user', [ArtistController::class, 'listMakeUpArtist']);
Route::get('/deskripsi-artist/{id}', [ArtistController::class, 'artistDescription']);
Route::get('/artist-location', [ArtistController::class, 'listAddressMakeUpArtist']);
Route::post('/profile/edit-profile', [UserController::class, 'userUpdateProfile']);
Route::middleware('jwt')->get('/chat-mua/{mua_id}', [ChatController::class, 'getUserToMuaApi']);
Route::post('/chat-mua/{mua_id}', [ChatController::class, 'userSendToMuaAPi']);
Route::get('/booking/{id}', [PaymentController::class, 'getSnapToken']);


// MUA
Route::post('/submit-request', [ArtistController::class, 'formSubmitRequest'])->withoutMiddleware('web');
Route::middleware('auth:makeup_artist')->get('/beranda-mua', [ArtistController::class, 'artistIndex']);
Route::get('/chat', [ArtistController::class, 'receivedMessagesApi']);
Route::post('/profile-mua/edit-profile', [ArtistController::class, 'updateMakeUpArtist']);
Route::get('/chat-user', [ChatController::class, ['muaToUserApi']]);
Route::post('/chat-user', [ChatController::class, ['muaSendToUserApi']]);
Route::post('/update-mua', [ArtistController::class, 'updateMakeUpArtist']);

// Admin
Route::post('/verification/{verificationId}', [AdminController::class, 'updateStatus']);
Route::get('/verification', [AdminController::class, 'getStatus']);

Route::middleware('auth:api')->group(function () {
    // Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});