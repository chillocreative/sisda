<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BantuanLainController;
use App\Http\Controllers\GlobalController;
use App\Http\Controllers\JenisSumbanganController;
use App\Http\Controllers\KadunController;
use App\Http\Controllers\KeahlianPartaiController;
use App\Http\Controllers\KecenderunganPolitikController;
use App\Http\Controllers\MPKKController;
use App\Http\Controllers\MulaCulaanController;
use App\Http\Controllers\PagesController;
use App\Http\Controllers\TujuanSumbanganController;
use App\Http\Controllers\UserController;
use App\Models\User;
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

Route::get('/', [AuthController::class, 'index']);
Route::post('/', [AuthController::class, 'login'])->name('login');
Route::get('/register', [AuthController::class, 'register'])->name('register');
Route::post('/register', [AuthController::class, 'registerStore'])->name('register.store');

Route::middleware('auth')->group(function(){
  Route::get('/dashboard', [PagesController::class, 'dashboard'])->name('dashboard');

  Route::group(['middleware' => 'otentikasi:superadmin'], function(){
    Route::get('/user/superadmin', [PagesController::class, 'user'])->name('user-superadmin');
  });
  
  Route::group(['middleware' => 'otentikasi:superadmin,admin'], function(){
      Route::group(['prefix' => 'user'], function(){
        Route::get('/admin', [PagesController::class, 'user'])->name('user-admin');
        Route::get('/user', [PagesController::class, 'user'])->name('user-user');
        Route::delete('/destroy/{id}', [UserController::class, 'destroy'])->name('user-destroy');
        Route::post('/store', [UserController::class, 'store'])->name('user-store');
        Route::put('/approved/{id}', [UserController::class, 'approved'])->name('approved');
        Route::get('/edit/{id}', [UserController::class, 'edit'])->name('user.edit');
        Route::put('/edit/{id}', [UserController::class, 'update'])->name('user.update');
      });

      Route::group(['prefix' => 'data-culaan-master'], function(){
        Route::resource('/kadun', KadunController::class)->only('index', 'store', 'destroy');
        Route::resource('/mpkk', MPKKController::class)->only('index', 'store', 'destroy');
        Route::resource('/tujuan-sumbangan', TujuanSumbanganController::class)->only('index', 'store', 'destroy');
        Route::resource('/jenis-sumbangan', JenisSumbanganController::class)->only('index', 'store', 'destroy');
        Route::resource('/bantuan-lain', BantuanLainController::class)->only('index', 'store', 'destroy');
        Route::resource('/keahlian-parti', KeahlianPartaiController::class)->only('index', 'store', 'destroy');
        Route::resource('/kecenderungan-politik', KecenderunganPolitikController::class)->only('index', 'store', 'destroy');
    });
  });

  Route::group(['middleware' => 'otentikasi:admin,user'], function(){
    Route::get('/mula-culaan', [MulaCulaanController::class, 'index'])->name('mula-culaan.index');
    Route::post('/mula-culaan', [MulaCulaanController::class, 'store'])->name('mula-culaan.store');
  });

  Route::get('/profile', [UserController::class, 'profile'])->name('profile');
  Route::post('/profile', [UserController::class, 'profileUpdate'])->name('profile.update');
  Route::post('/global/get-mpkk-specific', [GlobalController::class, 'getMPKKSpecific'])->name('get-mpkk-specific');
  
  Route::get('/logout', [AuthController::class, 'logout'])->name('logout');
});
