<?php
// Abilita il reporting degli errori
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Aggiungi header CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Accept');
header('Content-Type: application/json');

// Gestisci la richiesta OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Verifica che la richiesta sia GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['message' => 'Metodo non consentito']);
    exit;
}

// Verifica che siano stati forniti i parametri necessari
if (!isset($_GET['barber_id']) || !isset($_GET['date']) || !isset($_GET['time'])) {
    http_response_code(400);
    echo json_encode(['message' => 'Parametri mancanti (barber_id, date, time)']);
    exit;
}

$barberId = $_GET['barber_id'];
$date = $_GET['date'];
$time = $_GET['time'];

// Carica l'applicazione Laravel
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

try {
    // Assicuriamoci che il formato dell'orario sia completo (HH:MM)
    if (is_numeric($time) || strlen($time) <= 2) {
        // Se è solo un numero (es. "5" o "05"), aggiungiamo ":00"
        $time = $time . ':00';
    }

    // Crea il datetime dell'appuntamento
    $appointmentDateTime = \Carbon\Carbon::parse($date . ' ' . $time);
    $timeFormatted = $appointmentDateTime->format('H:i');
    
    // Cerca l'appuntamento esistente alla data e ora specificate
    $appointment = App\Models\Appointment::where('barber_id', $barberId)
        ->whereRaw("TIME_FORMAT(appointment_date, '%H:%i') = ?", [$timeFormatted])
        ->whereDate('appointment_date', $appointmentDateTime->format('Y-m-d'))
        ->where('status', '!=', 'cancelled')
        ->first();
    
    if (!$appointment) {
        // Nessun appuntamento trovato
        http_response_code(404);
        echo json_encode([
            'found' => false,
            'message' => 'Nessun appuntamento trovato per questo barbiere in questa data e ora'
        ]);
        exit;
    }
    
    // Restituisci i dettagli dell'appuntamento
    http_response_code(200);
    echo json_encode([
        'found' => true,
        'appointment' => [
            'id' => $appointment->id,
            'client_name' => $appointment->client_name,
            'client_email' => $appointment->client_email,
            'client_phone' => $appointment->client_phone,
            'service_type' => $appointment->service_type,
            'notes' => $appointment->notes,
            'status' => $appointment->status,
            'duration' => $appointment->duration,
            'date' => $appointmentDateTime->format('Y-m-d'),
            'time' => $timeFormatted
        ]
    ]);
    
} catch (Exception $e) {
    // Gestisci errori
    http_response_code(500);
    echo json_encode(['message' => 'Errore del server: ' . $e->getMessage()]);
} 