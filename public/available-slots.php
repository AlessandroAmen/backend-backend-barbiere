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

// Verifica i parametri richiesti
if (!isset($_GET['barber_id']) || !isset($_GET['date'])) {
    http_response_code(400);
    echo json_encode(['message' => 'Parametri mancanti: barber_id e date sono obbligatori']);
    exit;
}

$barberId = $_GET['barber_id'];
$date = $_GET['date'];

// Valida il formato della data
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['message' => 'Formato data non valido. Usa il formato YYYY-MM-DD']);
    exit;
}

// Carica l'applicazione Laravel
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

try {
    // Verifica che l'utente sia un barbiere
    $barber = App\Models\User::find($barberId);
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
    
    // Ottieni gli orari del negozio di barbiere
    $barberShop = null;
    
    if ($barber->barber_shop_id) {
        $barberShop = App\Models\Barber::find($barber->barber_shop_id);
    }
    
    // Orari predefiniti se non troviamo il barber_shop
    $openingTime = \Carbon\Carbon::parse('09:00:00');
    $closingTime = \Carbon\Carbon::parse('18:00:00');
    
    // Se troviamo il barber_shop, usiamo i suoi orari
    if ($barberShop && isset($barberShop->opening_time) && isset($barberShop->closing_time)) {
        $openingTime = \Carbon\Carbon::parse($barberShop->opening_time);
        $closingTime = \Carbon\Carbon::parse($barberShop->closing_time);
    }
    
    // Durata standard per un appuntamento
    $appointmentDuration = 15; // 15 minuti
    
    // Genera tutti gli slot possibili
    $slots = [];
    $current = clone $openingTime;
    
    while ($current->lt($closingTime)) {
        $endTime = (clone $current)->addMinutes($appointmentDuration);
        
        // Verifica che lo slot termini entro l'orario di chiusura
        if ($endTime->lte($closingTime)) {
            $slots[] = [
                'time' => $current->format('H:i'),
                'isBooked' => false
            ];
        }
        
        $current->addMinutes($appointmentDuration); // Incremento di 15 minuti
    }
    
    // Ottieni gli appuntamenti esistenti per questa data
    $dateStart = \Carbon\Carbon::parse($date . ' 00:00:00');
    $dateEnd = \Carbon\Carbon::parse($date . ' 23:59:59');
    
    $appointments = App\Models\Appointment::where('barber_id', $barberId)
        ->whereBetween('appointment_date', [$dateStart, $dateEnd])
        ->where('status', '!=', 'cancelled')
        ->get();
    
    // Marca gli slot come occupati
    foreach ($appointments as $appointment) {
        $appointmentTime = \Carbon\Carbon::parse($appointment->appointment_date)->format('H:i');
        
        // Trova lo slot corrispondente e marcalo come occupato
        foreach ($slots as $index => $slot) {
            if ($slot['time'] === $appointmentTime) {
                $slots[$index]['isBooked'] = true;
                $slots[$index]['appointmentId'] = $appointment->id;
                $slots[$index]['client_name'] = $appointment->client_name;
                $slots[$index]['client_email'] = $appointment->client_email;
                $slots[$index]['client_phone'] = $appointment->client_phone;
                $slots[$index]['service_type'] = $appointment->service_type;
                break;
            }
        }
    }
    
    // Restituisci tutti gli slot e le info di debug
    http_response_code(200);
    echo json_encode($slots);
    
} catch (Exception $e) {
    // Gestisci errori
    http_response_code(500);
    echo json_encode(['message' => 'Errore del server: ' . $e->getMessage()]);
} 