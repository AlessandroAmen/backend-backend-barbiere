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
    
    // Elabora e formatta la data e l'ora
    $date = $data['date'];
    $time = $data['time'];
    
    // Assicuriamoci che il formato dell'orario sia corretto (HH:MM)
    if (is_numeric($time)) {
        // Se l'orario è solo un numero (es. "9"), trasformalo in "09:00"
        $time = sprintf("%02d:00", (int)$time);
    } elseif (strlen($time) <= 2) {
        // Se l'orario è breve (es. "9:"), aggiungi ":00"
        $time = sprintf("%02d:00", (int)$time);
    } elseif (strpos($time, ':') === false) {
        // Se non c'è ":", aggiungi ":00"
        $time = $time . ':00';
    }
    
    // Debug information
    $debug_info = [
        'original_time' => $data['time'],
        'normalized_time' => $time,
        'date' => $date
    ];
    
    // Crea un oggetto Carbon per la data e ora dell'appuntamento
    $appointmentDateTime = \Carbon\Carbon::parse($date . ' ' . $time);
    
    // Ottieni il formato dell'orario per il confronto nel database
    $timeFormatted = $appointmentDateTime->format('H:i');
    $debug_info['formatted_time'] = $timeFormatted;
    
    // Verifica che la data non sia nel passato
    if ($appointmentDateTime->isPast()) {
        http_response_code(400);
        echo json_encode([
            'message' => 'Non puoi prenotare un appuntamento nel passato',
            'debug' => $debug_info
        ]);
        exit;
    }
    
    // Determina la durata in base al tipo di servizio
    $duration = 30; // Default 30 minuti
    if (strtolower($data['service_type']) === 'taglio + barba') {
        $duration = 45;
    } elseif (strtolower($data['service_type']) === 'barba') {
        $duration = 15;
    }
    
    // Verifica se esiste già un appuntamento per lo stesso barbiere nello stesso giorno e ora
    $dateYmd = $appointmentDateTime->format('Y-m-d'); // Solo la data in formato Y-m-d
    
    // Query diretta con i parametri corretti
    $existingAppointments = App\Models\Appointment::where('barber_id', $data['barber_id'])
        ->where('status', '!=', 'cancelled')
        ->get();
    
    // Raccogliamo informazioni su tutti gli appuntamenti per il debug
    $all_appointments_debug = [];
    foreach ($existingAppointments as $app) {
        $appDateTime = \Carbon\Carbon::parse($app->appointment_date);
        $all_appointments_debug[] = [
            'id' => $app->id,
            'date' => $appDateTime->format('Y-m-d'),
            'time' => $appDateTime->format('H:i'),
            'raw_date' => $app->appointment_date
        ];
    }
    $debug_info['all_appointments'] = $all_appointments_debug;
    
    // Controllo manuale per evitare problemi di formato nelle query
    $conflict = false;
    $conflictingApp = null;
    
    foreach ($existingAppointments as $existingApp) {
        $existingDateTime = \Carbon\Carbon::parse($existingApp->appointment_date);
        $existingDate = $existingDateTime->format('Y-m-d');
        $existingTime = $existingDateTime->format('H:i');
        
        // Confronto date e ore (stringhe)
        if ($existingDate === $dateYmd && $existingTime === $timeFormatted) {
            $conflict = true;
            $conflictingApp = $existingApp;
            break;
        }
    }
    
    if ($conflict) {
        http_response_code(409); // Conflict
        echo json_encode([
            'message' => 'Questo orario non è più disponibile, è stato prenotato da qualcun altro',
            'conflict_info' => [
                'id' => $conflictingApp->id,
                'date' => $dateYmd,
                'time' => $timeFormatted,
                'existing_time' => \Carbon\Carbon::parse($conflictingApp->appointment_date)->format('H:i')
            ],
            'debug' => $debug_info
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
        'appointment' => [
            'id' => $appointment->id,
            'date' => $date,
            'time' => $time,
            'formatted_time' => $timeFormatted,
            'barber_id' => $appointment->barber_id,
            'service_type' => $appointment->service_type,
            'duration' => $appointment->duration
        ],
        'debug' => $debug_info
    ]);
    
} catch (Exception $e) {
    // Gestisci errori
    http_response_code(500);
    echo json_encode([
        'message' => 'Errore del server: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
} 