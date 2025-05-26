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
    $appointmentDuration = 30; // 30 minuti
    
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
        
        $current->addMinutes(15); // Incremento di 15 minuti
    }
    
    // Ottieni gli appuntamenti esistenti per questa data
    $existingAppointments = App\Models\Appointment::where('barber_id', $barberId)
        ->where('status', '!=', 'cancelled')
        ->get();
    
    // Debug info
    $debug = [];
    
    // Filtra gli appuntamenti per la data specificata e marca gli slot come prenotati
    foreach ($existingAppointments as $appointment) {
        $appointmentDate = \Carbon\Carbon::parse($appointment->appointment_date)->format('Y-m-d');
        
        // Salta se non è per la data richiesta
        if ($appointmentDate !== $date) {
            continue;
        }
        
        $appointmentTime = \Carbon\Carbon::parse($appointment->appointment_date)->format('H:i');
        $debug[] = [
            'id' => $appointment->id,
            'date' => $appointmentDate,
            'time' => $appointmentTime,
            'raw_date' => $appointment->appointment_date
        ];
        
        // Cerca lo slot con l'orario corrispondente
        $found = false;
        foreach ($slots as $key => $slot) {
            if ($slot['time'] === $appointmentTime) {
                $slots[$key]['isBooked'] = true;
                $slots[$key]['appointmentId'] = $appointment->id;
                $found = true;
                break;
            }
        }
        
        // If exact match was not found, try to find by hour only (for legacy data)
        if (!$found) {
            $appointmentHour = \Carbon\Carbon::parse($appointment->appointment_date)->format('H');
            foreach ($slots as $key => $slot) {
                $slotHour = substr($slot['time'], 0, 2);
                if ($slotHour === $appointmentHour) {
                    $slots[$key]['isBooked'] = true;
                    $slots[$key]['appointmentId'] = $appointment->id;
                    break;
                }
            }
        }
    }
    
    // Restituisci gli slot con debug info
    echo json_encode([
        'slots' => $slots,
        'debug' => $debug
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'message' => 'Errore del server: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
} 