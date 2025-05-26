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

// Test connection route (available at both /api/test-connection and /test-connection)
Route::get('/test-connection', function() {
    return response()->json(['message' => 'Connection test successful']);
})->withoutMiddleware(['auth:sanctum', 'api', 'csrf', 'web']);
Route::get('test-connection', function() {
    return response()->json(['message' => 'Connection test successful']);
})->withoutMiddleware(['auth:sanctum', 'api', 'csrf', 'web']);

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/appointments', [AppointmentController::class, 'store'])->withoutMiddleware(['api', 'csrf', 'web']);
Route::get('/test-connection', function() {
    return response()->json(['message' => 'Connection test successful']);
})->withoutMiddleware(['api', 'csrf', 'web']);

// Rotte pubbliche per barbieri
Route::get('/barbers-test', [BarberTestController::class, 'index']);
Route::get('/barbers/{id}', [BarberApiController::class, 'show']);
Route::get('/regioni', [BarberApiController::class, 'getRegioni']);
Route::get('/province/{regione}', [BarberApiController::class, 'getProvince']);
Route::get('/users/role/barber', [UserController::class, 'getUsersByRole']);
Route::get('/available-slots', [AppointmentController::class, 'getAvailableSlots'])->withoutMiddleware(['auth:sanctum', 'api']);
Route::get('/get-appointment-details', [AppointmentController::class, 'getAppointmentDetails'])->withoutMiddleware(['auth:sanctum', 'api', 'web', 'cors']);
Route::get('/test-after-appointment-details', function() { return response()->json(['message' => 'Test after appointment details']); });

// Test endpoint
Route::get('/test', function() {
    return response()->json(['message' => 'API funzionante correttamente']);
});

// Test route per i barbieri
Route::get('/test-barbers', function() {
    return response()->json(['message' => 'Test barbers route works!']);
});

// Test route per il controller di test
Route::get('/test-controller', [TestController::class, 'index']);

// Test route per verificare connessione Android
Route::get('/android-test', function() {
    return response()->json([
        'message' => 'Android connection test successful',
        'timestamp' => now()->toDateTimeString()
    ]);
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
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

