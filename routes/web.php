<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PagesController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', [AuthController::class, 'index']);

Route::get('/dashboard', [PagesController::class, 'dashboard'])->name('dashboard');

Route::get('/user/master', [PagesController::class, 'master'])->name('user-master');
Route::get('/user/admin', [PagesController::class, 'admin'])->name('user-admin');
Route::get('/user/user', [PagesController::class, 'user'])->name('user-user');

Route::get('/logout', [AuthController::class, 'logout'])->name('logout');
