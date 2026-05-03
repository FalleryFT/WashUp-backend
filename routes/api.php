<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Admin\CustomerController; // Import Controller Customer
use App\Http\Controllers\Admin\OrderController;    // Import Controller Order
use App\Http\Controllers\Admin\DashboardController; // Import Controller Dashboard
use App\Http\Controllers\Admin\FinancialReportController; // Import Controller FinancialReport
use App\Http\Controllers\Admin\PriceSettingController; // Import Controller PriceSetting
use App\Http\Controllers\Admin\NewTransactionController; // Import Controller NewTransaction    

// ─── PUBLIC ROUTES ───────────────────────────────────────────────────────────
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/send-otp', [AuthController::class, 'sendOtp']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// ─── PROTECTED ROUTES (Harus Login) ──────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // CRUD Customer dengan Soft Delete
    Route::get('/customers', [CustomerController::class, 'index']);
    Route::post('/customers', [CustomerController::class, 'store']);
    Route::put('/customers/{id}', [CustomerController::class, 'update']);
    Route::delete('/customers/{id}', [CustomerController::class, 'destroy']);

    // CRUD Order
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders/{id}/next-status', [OrderController::class, 'nextStatus']);
    Route::delete('/orders/{id}', [OrderController::class, 'destroy']);

    // Dashboard Admin
    Route::get('/admin/dashboard', [DashboardController::class, 'index']);

    // Laporan Keuangan
    Route::get('/admin/reports', [FinancialReportController::class, 'index']);
    Route::get('/admin/reports/export', [FinancialReportController::class, 'export']);

    // Pengaturan Harga
    Route::get('/admin/prices', [PriceSettingController::class, 'index']);
    Route::post('/admin/prices', [PriceSettingController::class, 'store']);
    Route::get('/admin/prices/trash', [PriceSettingController::class, 'trash']);
    Route::put('/admin/prices/settings/max-berat', [PriceSettingController::class, 'updateMaxBerat']);
    Route::put('/admin/prices/{id}', [PriceSettingController::class, 'update']);
    Route::delete('/admin/prices/{id}', [PriceSettingController::class, 'destroy']);
    Route::post('/admin/prices/{id}/restore', [PriceSettingController::class, 'restore']);
    Route::delete('/admin/prices/{id}/force', [PriceSettingController::class, 'forceDelete']);

    // Form Data untuk Transaksi Baru
    Route::get('/admin/transactions/form-data', [NewTransactionController::class, 'formData']);
    Route::get('/admin/transactions/search-member', [NewTransactionController::class, 'searchMember']);
    Route::post('/admin/transactions', [NewTransactionController::class, 'store']);

});