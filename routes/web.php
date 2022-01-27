<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BandarController;
use App\Http\Controllers\BantuanLainController;
use App\Http\Controllers\DataPengundiController;
use App\Http\Controllers\GlobalController;
use App\Http\Controllers\JenisSumbanganController;
use App\Http\Controllers\KadunController;
use App\Http\Controllers\KeahlianPartaiController;
use App\Http\Controllers\KecenderunganPolitikController;
use App\Http\Controllers\MPKKController;
use App\Http\Controllers\MulaCulaanController;
use App\Http\Controllers\NegeriController;
use App\Http\Controllers\PagesController;
use App\Http\Controllers\ParlimenController;
use App\Http\Controllers\ReportController;
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
      Route::group(['prefix' => 'report'], function(){
        Route::get('/mula-culaan', [ReportController::class, 'mulaCulaan'])->name('report-mula-culaan');
        Route::get('/data-pengundi', [ReportController::class, 'dataPengundi'])->name('report-data-pengundi');

        Route::get('/mula-culaan/export-excel', [ReportController::class, 'exportExcelMulaCulaan'])->name('export-excel-mula-culaan');
      });

      Route::group(['prefix' => 'user'], function(){
        Route::get('/admin', [PagesController::class, 'user'])->name('user-admin');
        Route::get('/user', [PagesController::class, 'user'])->name('user-user');
        Route::delete('/destroy/{id}', [UserController::class, 'destroy'])->name('user-destroy');
        Route::post('/store', [UserController::class, 'store'])->name('user-store');
        Route::put('/approved/{id}', [UserController::class, 'approved'])->name('approved');
        Route::get('/edit/{id}', [UserController::class, 'edit'])->name('user.edit');
        Route::put('/edit/{id}', [UserController::class, 'update'])->name('user.update');
        Route::put('/reset-password/{id}', [UserController::class, 'resetPassword'])->name('user.reset-password');
      });

      Route::group(['prefix' => 'data-master'], function(){
        Route::resource('/negeri', NegeriController::class)->except('show', 'create');
        Route::resource('/bandar', BandarController::class)->except('show', 'create');
        Route::resource('/parlimen', ParlimenController::class)->except('show', 'create');
        Route::resource('/kadun', KadunController::class)->except('show', 'create');
        Route::resource('/mpkk', MPKKController::class)->except('show', 'create');
        Route::resource('/tujuan-sumbangan', TujuanSumbanganController::class)->except('show', 'create');
        Route::resource('/jenis-sumbangan', JenisSumbanganController::class)->except('show', 'create');
        Route::resource('/bantuan-lain', BantuanLainController::class)->except('show', 'create');
        Route::resource('/keahlian-parti', KeahlianPartaiController::class)->except('show', 'create');
        Route::resource('/kecenderungan-politik', KecenderunganPolitikController::class)->except('show', 'create');
    });
  });

  Route::group(['middleware' => 'otentikasi:admin,user'], function(){
    Route::get('/mula-culaan', [MulaCulaanController::class, 'index'])->name('mula-culaan.index');
    Route::post('/mula-culaan', [MulaCulaanController::class, 'store'])->name('mula-culaan.store');
    Route::get('/data-pengundi', [DataPengundiController::class, 'index'])->name('data-pengundi.index');
    Route::post('/data-pengundi', [DataPengundiController::class, 'store'])->name('data-pengundi.store');
  });

  Route::get('/profile', [UserController::class, 'profile'])->name('profile');
  Route::post('/profile', [UserController::class, 'profileUpdate'])->name('profile.update');
  Route::put('/profile/update-password', [UserController::class, 'updatePassword'])->name('profile.update-password');

  Route::group(['prefix' => 'global'], function(){
    Route::post('/get-mpkk-specific', [GlobalController::class, 'getMPKKSpecific'])->name('get-mpkk-specific');
    Route::post('/get-bandar-specific', [GlobalController::class, 'getBandarSpecific'])->name('get-bandar-specific');
    Route::post('/get-parlimen-specific', [GlobalController::class, 'getParlimenSpecific'])->name('get-parlimen-specific');
    Route::post('/get-kadun-specific', [GlobalController::class, 'getKadunSpecific'])->name('get-kadun-specific');
  });
  
  Route::get('/logout', [AuthController::class, 'logout'])->name('logout');
});
