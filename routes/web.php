<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PagesController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

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

$otentikasi = ['admin', 'master'];

Route::get('/', [AuthController::class, 'index']);
Route::post('/', [AuthController::class, 'login'])->name('login');

Route::middleware('auth')->group(function(){
  Route::get('/dashboard', [PagesController::class, 'dashboard'])->name('dashboard');

  
  Route::get('/user/master', [PagesController::class, 'user'])->middleware('otentikasi:master')->name('user-master');
  Route::group(['middleware' => 'otentikasi:master,admin'], function(){
      Route::get('/user/admin', [PagesController::class, 'user'])->name('user-admin');
      Route::get('/user/user', [PagesController::class, 'user'])->name('user-user');
  });
  
  Route::get('/logout', [AuthController::class, 'logout'])->name('logout');
});
