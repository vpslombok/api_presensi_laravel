<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/', [DatauserController::class, 'index'])->middleware('auth:sanctum');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);
Route::get('/datapresensi', [AuthController::class, 'Datapresensi'])->middleware('auth:sanctum');
Route::post('/presensi', [AuthController::class, 'presensi'])->middleware('auth:sanctum');
Route::post('/edit-profile', [AuthController::class, 'edit_profile'])->middleware('auth:sanctum');
// route reset_password
Route::post('/reset-password', [AuthController::class, 'reset_password']);