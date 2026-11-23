<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WalkController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('walk');
});

Route::prefix('walk')->controller(WalkController::class)->group(function () {
    Route::post('/start', 'start')->block();
    Route::get('/{round}/photo', 'photo')->block();
    Route::post('/{round}/distance', 'distance')->block();
    Route::post('/{round}/answer', 'answer')->block();
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
