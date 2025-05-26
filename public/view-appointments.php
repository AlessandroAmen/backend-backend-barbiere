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

// Carica l'applicazione Laravel
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

try {
    // Ottieni tutti gli appuntamenti
    $appointments = App\Models\Appointment::orderBy('id', 'desc')->get();
    
    $formattedAppointments = [];
    foreach ($appointments as $appointment) {
        $formattedAppointments[] = [
            'id' => $appointment->id,
            'barber_id' => $appointment->barber_id,
            'date' => $appointment->appointment_date,
            'duration' => $appointment->duration,
            'service_type' => $appointment->service_type,
            'status' => $appointment->status,
            'created_at' => $appointment->created_at
        ];
    }
    
    // Restituisci la risposta di successo
    http_response_code(200);
    echo json_encode([
        'message' => 'Appuntamenti trovati: ' . count($formattedAppointments),
        'appointments' => $formattedAppointments
    ]);
    
} catch (Exception $e) {
    // Gestisci errori
    http_response_code(500);
    echo json_encode(['message' => 'Errore del server: ' . $e->getMessage()]);
} 