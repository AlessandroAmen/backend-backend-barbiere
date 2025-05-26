<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BarberController;
use App\Http\Controllers\Api\BarberApiController;
use App\Http\Controllers\Api\BarberTestController;
use App\Http\Controllers\Api\TestController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AppointmentController;

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

// CSRF cookie for frontend SPA applications
Route::get('/csrf-cookie', function () {
    return response()->json(['csrf_token' => csrf_token()]);
});

// Test connection route
Route::get('test-connection', function() {
    return response()->json([
        'message' => 'Connection test successful',
        'timestamp' => now()->toISOString()
    ])
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');

// Public routes
Route::post('/register', [AuthController::class, 'register'])->middleware('cors');
Route::post('/login', [AuthController::class, 'login'])->middleware('cors');
Route::post('/appointments', [AppointmentController::class, 'store'])->middleware('cors');

// Rotte pubbliche per barbieri
Route::get('/barbers-test', [BarberTestController::class, 'index'])->middleware('cors');
Route::get('/barbers/{id}', [BarberApiController::class, 'show'])->middleware('cors');
Route::get('/regioni', [BarberApiController::class, 'getRegioni'])->middleware('cors');
Route::get('/province/{regione}', [BarberApiController::class, 'getProvince'])->middleware('cors');
Route::get('/users/role/barber', [UserController::class, 'getUsersByRole'])->middleware('cors');
Route::get('/available-slots', [AppointmentController::class, 'getAvailableSlots'])->middleware('cors');
Route::get('/get-appointment-details', [AppointmentController::class, 'getAppointmentDetails'])->middleware('cors');
Route::get('/test-after-appointment-details', function() { return response()->json(['message' => 'Test after appointment details']); });

// Test endpoint
Route::get('/test', function() {
    return response()->json(['message' => 'API funzionante correttamente']);
})->middleware('cors');

// Test route per i barbieri
Route::get('/test-barbers', function() {
    return response()->json(['message' => 'Test barbers route works!']);
})->middleware('cors');

// Test route per il controller di test
Route::get('/test-controller', [TestController::class, 'index'])->withoutMiddleware(['api', 'csrf', 'web'])->middleware('cors');

// Test route per verificare connessione Android
Route::get('/android-test', function() {
    return response()->json([
        'message' => 'Android connection test successful',
        'timestamp' => now()->toDateTimeString()
    ])
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
    ->header('Access-Control-Allow-Headers', '*');
})->withoutMiddleware(['api', 'csrf', 'web'])->middleware('cors');

// Endpoint specifico per test mobile
Route::get('/mobile-test', function() {
    return response()->json([
        'status' => 'success',
        'message' => 'Mobile connection working',
        'server_info' => [
            'timestamp' => now()->toISOString(),
            'server_ip' => request()->server('SERVER_ADDR'),
            'client_ip' => request()->ip(),
            'user_agent' => request()->header('User-Agent')
        ]
    ])
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
    ->header('Access-Control-Allow-Headers', '*');
})->withoutMiddleware(['auth:sanctum', 'api', 'csrf', 'web'])->middleware('cors');

// Simple test route
Route::get('/simple-test', function() {
    return response()->json(['status' => 'ok']);
})->middleware('cors');

// Protected routes
Route::middleware(['auth:sanctum', 'cors'])->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // User management
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users/{id}/promote/manager', [UserController::class, 'promoteToManager']);
    Route::post('/users/{id}/promote/barber', [UserController::class, 'promoteToBarber']);
    
    // Appointments
    Route::get('/appointments', [AppointmentController::class, 'index']);
    Route::get('/appointments/{id}', [AppointmentController::class, 'show']);
    Route::put('/appointments/{id}', [AppointmentController::class, 'update'])->withoutMiddleware('csrf');
    Route::delete('/appointments/{id}', [AppointmentController::class, 'destroy'])->withoutMiddleware('csrf');
    
    // Specialized appointment routes
    Route::get('/barber/{id}/appointments', [AppointmentController::class, 'getBarberAppointments']);
    Route::get('/shop/{id}/appointments', [AppointmentController::class, 'getShopAppointments']);
    
    // Rotte protette per barbieri (solo admin)
    Route::post('/barbers', [BarberController::class, 'store']);
    Route::put('/barbers/{barber}', [BarberController::class, 'update']);
    Route::delete('/barbers/{barber}', [BarberController::class, 'destroy']);
});

