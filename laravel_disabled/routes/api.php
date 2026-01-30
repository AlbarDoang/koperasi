<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CairkanController;
use App\Http\Controllers\Admin\PencairanController;

Route::post('/cairkan', [CairkanController::class, 'store']);

// Admin protected routes (place behind auth middleware in production)
Route::get('/admin/pencairan', [PencairanController::class, 'index']);
Route::post('/admin/pencairan/approve', [PencairanController::class, 'approve']);
