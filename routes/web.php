<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
});

Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('/dashboard/search-ic', [\App\Http\Controllers\DashboardController::class, 'searchIC'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard.search-ic');


// DEBUG: Check pending count
Route::get('/debug-pending-count', function () {
    $user = auth()->user();
    if (!$user) {
        return response()->json(['error' => 'Not authenticated']);
    }
    
    $query = \App\Models\User::pending();
    if ($user->isAdmin()) {
        $query->where('bandar_id', $user->bandar_id);
    }
    $count = $query->count();
    $pendingUsers = $query->get(['id', 'name', 'bandar_id', 'status']);
    
    return response()->json([
        'user_id' => $user->id,
        'user_name' => $user->name,
        'user_role' => $user->role,
        'user_bandar_id' => $user->bandar_id,
        'is_admin' => $user->isAdmin(),
        'is_super_admin' => $user->isSuperAdmin(),
        'pending_count' => $count,
        'pending_users' => $pendingUsers
    ]);
})->middleware('auth');

// Pending approval page (accessible without auth)
Route::get('/pending-approval', function () {
    return Inertia::render('Auth/PendingApproval');
})->name('pending-approval');

// User Approval (Super Admin and Admin only)
Route::middleware(['auth'])->group(function () {
    Route::get('/user-approval', [\App\Http\Controllers\UserApprovalController::class, 'index'])
        ->name('user-approval.index');
    Route::post('/user-approval/{user}/approve', [\App\Http\Controllers\UserApprovalController::class, 'approve'])
        ->name('user-approval.approve');
    Route::post('/user-approval/{user}/reject', [\App\Http\Controllers\UserApprovalController::class, 'reject'])
        ->name('user-approval.reject');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
    // Users management
    Route::resource('users', \App\Http\Controllers\UsersController::class);
    Route::post('/users/bulk-delete', [\App\Http\Controllers\UsersController::class, 'bulkDelete'])->name('users.bulk-delete');
    
    // Call Center (Super Admin only)
    Route::get('/call-center', [\App\Http\Controllers\CallCenterController::class, 'index'])->name('call-center.index');
    Route::get('/call-center/scripts', [\App\Http\Controllers\CallCenterController::class, 'scripts'])->name('call-center.scripts.index');
    Route::get('/call-center/agent', [\App\Http\Controllers\CallCenterController::class, 'agent'])->name('call-center.agent.index');
    Route::get('/call-center/analytics', [\App\Http\Controllers\CallCenterController::class, 'analytics'])->name('call-center.analytics.index');
    Route::get('/call-center/analytics/ai', [\App\Http\Controllers\CallCenterController::class, 'aiAnalytics'])->name('call-center.analytics.ai');
    Route::get('/call-center/history', [\App\Http\Controllers\CallCenterController::class, 'history'])->name('call-center.history.index');
    
    // Master Data
    Route::get('/master-data', [\App\Http\Controllers\MasterDataController::class, 'index'])->name('master-data.index');
    
    // Negeri
    Route::get('/master-data/negeri', [\App\Http\Controllers\MasterDataController::class, 'negeriIndex'])->name('master-data.negeri.index');
    Route::post('/master-data/negeri', [\App\Http\Controllers\MasterDataController::class, 'negeriStore'])->name('master-data.negeri.store');
    Route::put('/master-data/negeri/{negeri}', [\App\Http\Controllers\MasterDataController::class, 'negeriUpdate'])->name('master-data.negeri.update');
    Route::delete('/master-data/negeri/{negeri}', [\App\Http\Controllers\MasterDataController::class, 'negeriDestroy'])->name('master-data.negeri.destroy');
    
    // Bandar
    Route::get('/master-data/bandar', [\App\Http\Controllers\MasterDataController::class, 'bandarIndex'])->name('master-data.bandar.index');
    Route::post('/master-data/bandar', [\App\Http\Controllers\MasterDataController::class, 'bandarStore'])->name('master-data.bandar.store');
    Route::put('/master-data/bandar/{bandar}', [\App\Http\Controllers\MasterDataController::class, 'bandarUpdate'])->name('master-data.bandar.update');
    Route::delete('/master-data/bandar/{bandar}', [\App\Http\Controllers\MasterDataController::class, 'bandarDestroy'])->name('master-data.bandar.destroy');

    // Parlimen
    Route::get('/master-data/parlimen', [\App\Http\Controllers\MasterDataController::class, 'parlimenIndex'])->name('master-data.parlimen.index');
    Route::post('/master-data/parlimen', [\App\Http\Controllers\MasterDataController::class, 'parlimenStore'])->name('master-data.parlimen.store');
    Route::put('/master-data/parlimen/{parlimen}', [\App\Http\Controllers\MasterDataController::class, 'parlimenUpdate'])->name('master-data.parlimen.update');
    Route::delete('/master-data/parlimen/{parlimen}', [\App\Http\Controllers\MasterDataController::class, 'parlimenDestroy'])->name('master-data.parlimen.destroy');

    // KADUN
    Route::get('/master-data/kadun', [\App\Http\Controllers\MasterDataController::class, 'kadunIndex'])->name('master-data.kadun.index'); // For all or filtered
    Route::get('/master-data/kadun/{bandarId}', [\App\Http\Controllers\MasterDataController::class, 'kadunIndex'])->name('master-data.kadun.filter'); // Specific filter
    Route::post('/master-data/kadun', [\App\Http\Controllers\MasterDataController::class, 'kadunStore'])->name('master-data.kadun.store');
    Route::put('/master-data/kadun/{kadun}', [\App\Http\Controllers\MasterDataController::class, 'kadunUpdate'])->name('master-data.kadun.update');
    Route::delete('/master-data/kadun/{kadun}', [\App\Http\Controllers\MasterDataController::class, 'kadunDestroy'])->name('master-data.kadun.destroy');

    // MPKK
    Route::get('/master-data/mpkk', [\App\Http\Controllers\MasterDataController::class, 'mpkkIndex'])->name('master-data.mpkk.index'); // For all or filtered
    Route::get('/master-data/mpkk/{kadunId}', [\App\Http\Controllers\MasterDataController::class, 'mpkkIndex'])->name('master-data.mpkk.filter'); // Specific filter
    Route::post('/master-data/mpkk', [\App\Http\Controllers\MasterDataController::class, 'mpkkStore'])->name('master-data.mpkk.store');
    Route::put('/master-data/mpkk/{mpkk}', [\App\Http\Controllers\MasterDataController::class, 'mpkkUpdate'])->name('master-data.mpkk.update');
    Route::delete('/master-data/mpkk/{mpkk}', [\App\Http\Controllers\MasterDataController::class, 'mpkkDestroy'])->name('master-data.mpkk.destroy');

    // Daerah Mengundi
    Route::get('/master-data/daerah-mengundi', [\App\Http\Controllers\MasterDataController::class, 'daerahMengundiIndex'])->name('master-data.daerah-mengundi.index');
    Route::post('/master-data/daerah-mengundi', [\App\Http\Controllers\MasterDataController::class, 'daerahMengundiStore'])->name('master-data.daerah-mengundi.store');
    Route::put('/master-data/daerah-mengundi/{daerahMengundi}', [\App\Http\Controllers\MasterDataController::class, 'daerahMengundiUpdate'])->name('master-data.daerah-mengundi.update');
    Route::delete('/master-data/daerah-mengundi/{daerahMengundi}', [\App\Http\Controllers\MasterDataController::class, 'daerahMengundiDestroy'])->name('master-data.daerah-mengundi.destroy');

    // Tujuan Sumbangan
    Route::get('/master-data/tujuan-sumbangan', [\App\Http\Controllers\MasterDataController::class, 'tujuanSumbanganIndex'])->name('master-data.tujuan-sumbangan.index');
    Route::post('/master-data/tujuan-sumbangan', [\App\Http\Controllers\MasterDataController::class, 'tujuanSumbanganStore'])->name('master-data.tujuan-sumbangan.store');
    Route::put('/master-data/tujuan-sumbangan/{tujuanSumbangan}', [\App\Http\Controllers\MasterDataController::class, 'tujuanSumbanganUpdate'])->name('master-data.tujuan-sumbangan.update');
    Route::delete('/master-data/tujuan-sumbangan/{tujuanSumbangan}', [\App\Http\Controllers\MasterDataController::class, 'tujuanSumbanganDestroy'])->name('master-data.tujuan-sumbangan.destroy');

    // Jenis Sumbangan
    Route::get('/master-data/jenis-sumbangan', [\App\Http\Controllers\MasterDataController::class, 'jenisSumbanganIndex'])->name('master-data.jenis-sumbangan.index');
    Route::post('/master-data/jenis-sumbangan', [\App\Http\Controllers\MasterDataController::class, 'jenisSumbanganStore'])->name('master-data.jenis-sumbangan.store');
    Route::put('/master-data/jenis-sumbangan/{jenisSumbangan}', [\App\Http\Controllers\MasterDataController::class, 'jenisSumbanganUpdate'])->name('master-data.jenis-sumbangan.update');
    Route::delete('/master-data/jenis-sumbangan/{jenisSumbangan}', [\App\Http\Controllers\MasterDataController::class, 'jenisSumbanganDestroy'])->name('master-data.jenis-sumbangan.destroy');

    // Bantuan Lain
    Route::get('/master-data/bantuan-lain', [\App\Http\Controllers\MasterDataController::class, 'bantuanLainIndex'])->name('master-data.bantuan-lain.index');
    Route::post('/master-data/bantuan-lain', [\App\Http\Controllers\MasterDataController::class, 'bantuanLainStore'])->name('master-data.bantuan-lain.store');
    Route::put('/master-data/bantuan-lain/{bantuanLain}', [\App\Http\Controllers\MasterDataController::class, 'bantuanLainUpdate'])->name('master-data.bantuan-lain.update');
    Route::delete('/master-data/bantuan-lain/{bantuanLain}', [\App\Http\Controllers\MasterDataController::class, 'bantuanLainDestroy'])->name('master-data.bantuan-lain.destroy');

    // Keahlian Parti
    Route::get('/master-data/keahlian-parti', [\App\Http\Controllers\MasterDataController::class, 'keahlianPartiIndex'])->name('master-data.keahlian-parti.index');
    Route::post('/master-data/keahlian-parti', [\App\Http\Controllers\MasterDataController::class, 'keahlianPartiStore'])->name('master-data.keahlian-parti.store');
    Route::put('/master-data/keahlian-parti/{keahlianParti}', [\App\Http\Controllers\MasterDataController::class, 'keahlianPartiUpdate'])->name('master-data.keahlian-parti.update');
    Route::delete('/master-data/keahlian-parti/{keahlianParti}', [\App\Http\Controllers\MasterDataController::class, 'keahlianPartiDestroy'])->name('master-data.keahlian-parti.destroy');

    // Kecenderungan Politik
    Route::get('/master-data/kecenderungan-politik', [\App\Http\Controllers\MasterDataController::class, 'kecenderunganPolitikIndex'])->name('master-data.kecenderungan-politik.index');
    Route::post('/master-data/kecenderungan-politik', [\App\Http\Controllers\MasterDataController::class, 'kecenderunganPolitikStore'])->name('master-data.kecenderungan-politik.store');
    Route::put('/master-data/kecenderungan-politik/{kecenderunganPolitik}', [\App\Http\Controllers\MasterDataController::class, 'kecenderunganPolitikUpdate'])->name('master-data.kecenderungan-politik.update');
    Route::delete('/master-data/kecenderungan-politik/{kecenderunganPolitik}', [\App\Http\Controllers\MasterDataController::class, 'kecenderunganPolitikDestroy'])->name('master-data.kecenderungan-politik.destroy');

    // Hubungan
    Route::get('/master-data/hubungan', [\App\Http\Controllers\MasterDataController::class, 'hubunganIndex'])->name('master-data.hubungan.index');
    Route::post('/master-data/hubungan', [\App\Http\Controllers\MasterDataController::class, 'hubunganStore'])->name('master-data.hubungan.store');
    Route::put('/master-data/hubungan/{hubungan}', [\App\Http\Controllers\MasterDataController::class, 'hubunganUpdate'])->name('master-data.hubungan.update');
    Route::delete('/master-data/hubungan/{hubungan}', [\App\Http\Controllers\MasterDataController::class, 'hubunganDestroy'])->name('master-data.hubungan.destroy');

    // Bangsa
    Route::get('/master-data/bangsa', [\App\Http\Controllers\MasterDataController::class, 'bangsaIndex'])->name('master-data.bangsa.index');
    Route::post('/master-data/bangsa', [\App\Http\Controllers\MasterDataController::class, 'bangsaStore'])->name('master-data.bangsa.store');
    Route::put('/master-data/bangsa/{bangsa}', [\App\Http\Controllers\MasterDataController::class, 'bangsaUpdate'])->name('master-data.bangsa.update');
    Route::delete('/master-data/bangsa/{bangsa}', [\App\Http\Controllers\MasterDataController::class, 'bangsaDestroy'])->name('master-data.bangsa.destroy');
    
    // Reorder Master Data
    Route::post('/master-data/reorder', [\App\Http\Controllers\MasterDataController::class, 'reorder'])->name('master-data.reorder');
        
    // Reports
    Route::get('/reports', [\App\Http\Controllers\ReportsController::class, 'index'])->name('reports.index');
    Route::get('/reports/hasil-culaan', [\App\Http\Controllers\ReportsController::class, 'hasilCulaanIndex'])->name('reports.hasil-culaan.index');
    Route::get('/reports/hasil-culaan/create', [\App\Http\Controllers\ReportsController::class, 'hasilCulaanCreate'])->name('reports.hasil-culaan.create');
    Route::post('/reports/hasil-culaan', [\App\Http\Controllers\ReportsController::class, 'hasilCulaanStore'])->name('reports.hasil-culaan.store');
    Route::get('/reports/hasil-culaan/{hasilCulaan}/edit', [\App\Http\Controllers\ReportsController::class, 'hasilCulaanEdit'])->name('reports.hasil-culaan.edit');
    Route::put('/reports/hasil-culaan/{hasilCulaan}', [\App\Http\Controllers\ReportsController::class, 'hasilCulaanUpdate'])->name('reports.hasil-culaan.update');
    Route::delete('/reports/hasil-culaan/{hasilCulaan}', [\App\Http\Controllers\ReportsController::class, 'hasilCulaanDestroy'])->name('reports.hasil-culaan.destroy');
    Route::post('/reports/hasil-culaan/bulk-delete', [\App\Http\Controllers\ReportsController::class, 'hasilCulaanBulkDelete'])->name('reports.hasil-culaan.bulk-delete');
    Route::get('/reports/hasil-culaan/export', [\App\Http\Controllers\ReportsController::class, 'exportHasilCulaan'])->name('reports.hasil-culaan.export');
    
    // Data Pengundi
    Route::get('/reports/data-pengundi', [\App\Http\Controllers\ReportsController::class, 'dataPengundiIndex'])->name('reports.data-pengundi.index');
    Route::get('/reports/data-pengundi/create', [\App\Http\Controllers\ReportsController::class, 'dataPengundiCreate'])->name('reports.data-pengundi.create');
    Route::post('/reports/data-pengundi', [\App\Http\Controllers\ReportsController::class, 'dataPengundiStore'])->name('reports.data-pengundi.store');
    Route::get('/reports/data-pengundi/{dataPengundi}/edit', [\App\Http\Controllers\ReportsController::class, 'dataPengundiEdit'])->name('reports.data-pengundi.edit');
    Route::put('/reports/data-pengundi/{dataPengundi}', [\App\Http\Controllers\ReportsController::class, 'dataPengundiUpdate'])->name('reports.data-pengundi.update');
    Route::delete('/reports/data-pengundi/{dataPengundi}', [\App\Http\Controllers\ReportsController::class, 'dataPengundiDestroy'])->name('reports.data-pengundi.destroy');
    Route::post('/reports/data-pengundi/bulk-delete', [\App\Http\Controllers\ReportsController::class, 'dataPengundiBulkDelete'])->name('reports.data-pengundi.bulk-delete');
    Route::get('/reports/data-pengundi/export', [\App\Http\Controllers\ReportsController::class, 'exportDataPengundi'])->name('reports.data-pengundi.export');
    
    // Postcode Search
    Route::get('/api/postcodes/search', [\App\Http\Controllers\ReportsController::class, 'searchPostcode'])->name('api.postcodes.search');
    Route::get('/api/postcodes/search-details', [\App\Http\Controllers\ReportsController::class, 'searchPostcodeWithDetails'])->name('api.postcodes.search-details');
    Route::get('/api/kadun/by-bandar', [\App\Http\Controllers\ReportsController::class, 'getKadunByBandar'])->name('api.kadun.by-bandar');
    Route::get('/api/daerah-mengundi/by-bandar', [\App\Http\Controllers\ReportsController::class, 'getDaerahMengundiByBandar'])->name('api.daerah-mengundi.by-bandar');
    Route::get('/api/parlimen/by-negeri', [\App\Http\Controllers\ReportsController::class, 'getParlimenByNegeri'])->name('api.parlimen.by-negeri');
    Route::get('/api/mpkk/by-kadun', [\App\Http\Controllers\ReportsController::class, 'getMpkkByKadun'])->name('api.mpkk.by-kadun');
});

require __DIR__.'/auth.php';
