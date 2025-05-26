<?php
// Abilita il reporting degli errori
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Aggiungi header CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Accept');
header('Content-Type: application/json');

// Gestisci la richiesta OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Verifica che la richiesta sia POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['message' => 'Metodo non consentito']);
    exit;
}

// Ottieni i dati JSON inviati
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Verifica che i dati siano validi
if (!$data || !isset($data['barber_id']) || !isset($data['date']) || !isset($data['time']) || !isset($data['service_type'])) {
    http_response_code(400);
    echo json_encode(['message' => 'Dati mancanti o non validi']);
    exit;
}

// Carica l'applicazione Laravel
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

try {
    // Utilizza i modelli Laravel
    $barber = App\Models\User::where('id', $data['barber_id'])->first();
    
    if (!$barber) {
        http_response_code(404);
        echo json_encode(['message' => 'Barbiere non trovato']);
        exit;
    }
    
    if ($barber->role !== 'barber') {
        http_response_code(400);
        echo json_encode(['message' => 'L\'utente specificato non è un barbiere']);
        exit;
    }
    
    // Crea il datetime dell'appuntamento
    // Assicuriamoci che il formato dell'orario sia completo (HH:MM)
    $time = $data['time'];
    if (is_numeric($time) || strlen($time) <= 2) {
        // Se è solo un numero (es. "5" o "05"), aggiungiamo ":00"
        $time = $time . ':00';
    }
    $appointmentDateTime = \Carbon\Carbon::parse($data['date'] . ' ' . $time);
    
    // Debug: stampa l'orario esatto che stiamo cercando
    $timeFormatted = $appointmentDateTime->format('H:i');
    
    // Verifica che la data non sia nel passato
    if ($appointmentDateTime->isPast()) {
        http_response_code(400);
        echo json_encode(['message' => 'Non puoi prenotare un appuntamento nel passato']);
        exit;
    }
    
    // Determina la durata in base al tipo di servizio
    $duration = 30; // Default 30 minuti
    if (strtolower($data['service_type']) === 'taglio + barba') {
        $duration = 45;
    } elseif (strtolower($data['service_type']) === 'barba') {
        $duration = 15;
    }
    
    // Verifica se esiste già un appuntamento per lo stesso barbiere alla stessa ora esatta
    $existingAppointment = App\Models\Appointment::where('barber_id', $data['barber_id'])
        ->where('status', '!=', 'cancelled')
        ->whereRaw("TIME_FORMAT(appointment_date, '%H:%i') = ?", [$timeFormatted])
        ->whereDate('appointment_date', $appointmentDateTime->format('Y-m-d'))
        ->first();
    
    if ($existingAppointment) {
        http_response_code(409); // Conflict
        echo json_encode([
            'message' => 'Questo orario non è più disponibile, è stato prenotato da qualcun altro',
            'overlap_with' => [
                'id' => $existingAppointment->id,
                'time' => $existingAppointment->appointment_date,
                'formatted_time' => \Carbon\Carbon::parse($existingAppointment->appointment_date)->format('H:i'),
                'duration' => $existingAppointment->duration
            ],
            'requested_time' => [
                'formatted' => $timeFormatted,
                'raw' => $time,
                'date' => $data['date']
            ]
        ]);
        exit;
    }
    
    // Crea l'appuntamento usando il modello Eloquent
    $appointment = new App\Models\Appointment();
    $appointment->barber_id = $data['barber_id'];
    $appointment->barber_shop_id = $barber->barber_shop_id;
    $appointment->appointment_date = $appointmentDateTime;
    $appointment->duration = $duration;
    $appointment->service_type = $data['service_type'];
    $appointment->notes = $data['notes'] ?? '';
    $appointment->status = 'pending';
    $appointment->user_id = null;
    $appointment->client_name = $data['client_name'] ?? 'Cliente Web';
    $appointment->client_email = $data['client_email'] ?? 'cliente@example.com';
    $appointment->client_phone = $data['client_phone'] ?? '3334445566';
    $appointment->save();
    
    // Restituisci la risposta di successo
    http_response_code(201);
    echo json_encode([
        'message' => 'Appuntamento creato con successo',
        'appointment' => $appointment
    ]);
    
} catch (Exception $e) {
    // Gestisci errori
    http_response_code(500);
    echo json_encode(['message' => 'Errore del server: ' . $e->getMessage()]);
} 