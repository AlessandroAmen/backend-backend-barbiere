<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AppointmentController;
use App\Models\Barber;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

Route::get('/', function () {
    return view('welcome');
});

// Rotta di test per i barbieri
Route::get('/api/barbers-test', function () {
    $barbers = Barber::all();
    return response()->json($barbers);
});

// Rotta di test per le regioni
Route::get('/api/regioni-test', function () {
    $regioni = Barber::select('regione')
        ->distinct()
        ->orderBy('regione')
        ->get()
        ->pluck('regione');
    
    return response()->json($regioni);
});

// Rotta di test per le province
Route::get('/api/province-test/{regione}', function ($regione) {
    $province = Barber::where('regione', $regione)
        ->select('provincia')
        ->distinct()
        ->orderBy('provincia')
        ->get()
        ->pluck('provincia');
    
    return response()->json($province);
});

// Rotta di test per i barbieri filtrati
Route::get('/api/barbers-filtered-test', function (Illuminate\Http\Request $request) {
    $query = Barber::query();
    
    if ($request->has('regione')) {
        $query->where('regione', $request->regione);
    }
    
    if ($request->has('provincia')) {
        $query->where('provincia', $request->provincia);
    }
    
    return response()->json($query->get());
});

// Test route for debugging
Route::get('/test-connection', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'Laravel server is running correctly',
        'timestamp' => now()->toDateTimeString()
    ])
    ->header('Access-Control-Allow-Origin', '*')
    ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
});

// Rotte API
Route::prefix('api')->middleware(['api'])->group(function () {
    // Rotte pubbliche per autenticazione
    Route::post('/register', [AuthController::class, 'register'])->withoutMiddleware(['web', 'csrf']);
    Route::post('/login', [AuthController::class, 'login'])->withoutMiddleware(['web', 'csrf']);

    // Nuova rotta pubblica per prenotazione appuntamento
    Route::post('/book-appointment', [AppointmentController::class, 'store'])->withoutMiddleware(['web', 'csrf', 'auth']);

    // Rota pubblica per ottenere gli slot disponibili
    Route::get('/available-slots', [AppointmentController::class, 'getAvailableSlots'])->withoutMiddleware(['web', 'csrf', 'auth']);

    // Rota pubblica per ottenere i dettagli di un appuntamento
    Route::get('/get-appointment-details', [AppointmentController::class, 'getAppointmentDetails'])->withoutMiddleware(['web', 'csrf', 'auth']);

    // Per test - verifica se le rotte API sono accessibili
    Route::get('/test', function() {
        return response()->json(['message' => 'API funzionante correttamente']);
    });

    // Rotte protette (richiedono autenticazione)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout'])->withoutMiddleware(['csrf']);
        
        // Gestione utenti e ruoli
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/role/{role}', [UserController::class, 'getUsersByRole']);
        Route::post('/users/{id}/promote/manager', [UserController::class, 'promoteToManager']);
        Route::post('/users/{id}/promote/barber', [UserController::class, 'promoteToBarber']);
        Route::post('/users/{id}/make-barber', [UserController::class, 'makeBarber'])->withoutMiddleware(['web', 'csrf']);
        
        // Gestione appuntamenti
        Route::get('/appointments', [AppointmentController::class, 'index']);
        Route::post('/appointments', [AppointmentController::class, 'store']);
        Route::get('/appointments/{id}', [AppointmentController::class, 'show']);
        Route::put('/appointments/{id}', [AppointmentController::class, 'update']);
        Route::delete('/appointments/{id}', [AppointmentController::class, 'destroy']);
        
        // Rotte specializzate per gli appuntamenti
        Route::get('/barber/{id}/appointments', [AppointmentController::class, 'getBarberAppointments']);
        Route::get('/shop/{id}/appointments', [AppointmentController::class, 'getShopAppointments']);
    });

    // API pubblica per prenotazioni (senza CSRF)
    Route::match(['OPTIONS', 'POST'], '/public-appointment', function (Illuminate\Http\Request $request) {
        // Aggiungi gli header CORS a tutte le risposte
        $headers = [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Accept, X-Requested-With'
        ];
        
        // Se è una richiesta OPTIONS (preflight), restituisci solo gli header
        if ($request->isMethod('OPTIONS')) {
            return response()->json(['status' => 'success'], 200, $headers);
        }
        
        // Validazione
        $validator = Validator::make($request->all(), [
            'barber_id' => 'required|exists:users,id',
            'date' => 'required|date_format:Y-m-d',
            'time' => 'required|date_format:H:i',
            'service_type' => 'required|string',
            'notes' => 'nullable|string',
            'client_name' => 'required|string',
            'client_email' => 'required|email',
            'client_phone' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422, $headers);
        }

        try {
            // Verifica che l'utente selezionato sia un barbiere
            $barber = App\Models\User::findOrFail($request->barber_id);
            if ($barber->role !== 'barber') {
                return response()->json(['message' => 'L\'utente specificato non è un barbiere'], 400, $headers);
            }
            
            // Combina data e ora per ottenere il datetime dell'appuntamento
            $appointmentDateTime = \Carbon\Carbon::parse($request->date . ' ' . $request->time);
            
            // Verifica che la data non sia nel passato
            if ($appointmentDateTime->isPast()) {
                return response()->json(['message' => 'Non puoi prenotare un appuntamento nel passato'], 400, $headers);
            }
            
            // Determina la durata in base al tipo di servizio
            $duration = 30; // Default 30 minuti
            if (strtolower($request->service_type) === 'taglio + barba') {
                $duration = 45;
            } elseif (strtolower($request->service_type) === 'barba') {
                $duration = 15;
            }
            
            // Crea l'appuntamento
            $appointment = new App\Models\Appointment();
            $appointment->barber_id = $request->barber_id;
            $appointment->barber_shop_id = $barber->barber_shop_id;
            $appointment->appointment_date = $appointmentDateTime;
            $appointment->duration = $duration;
            $appointment->service_type = $request->service_type;
            $appointment->notes = $request->notes;
            $appointment->status = 'pending';
            $appointment->client_name = $request->client_name;
            $appointment->client_email = $request->client_email;
            $appointment->client_phone = $request->client_phone;
            $appointment->save();
            
            return response()->json(['message' => 'Appuntamento creato con successo', 'appointment' => $appointment], 201, $headers);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Errore durante la creazione dell\'appuntamento: ' . $e->getMessage()], 500, $headers);
        }
    })->withoutMiddleware(['web', 'csrf', 'api']);

    // Rotte di test API
    Route::get('/test-connection', function() {
        return response()->json([
            'message' => 'Connection test successful',
            'timestamp' => now()->toISOString()
        ]);
    });

    Route::get('/simple-test', function() {
        return response()->json([
            'status' => 'ok'
        ]);
    });
});

// Test route per verificare il funzionamento delle rotte web
Route::get('/test', function () {
    return response()->json(['message' => 'Web route test successful']);
});
