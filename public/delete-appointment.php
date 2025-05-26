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

// Verifica che sia stato fornito un ID
if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['message' => 'ID appuntamento mancante']);
    exit;
}

$id = $_GET['id'];

// Carica l'applicazione Laravel
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

try {
    // Trova e elimina l'appuntamento
    $appointment = App\Models\Appointment::find($id);
    
    if (!$appointment) {
        http_response_code(404);
        echo json_encode(['message' => 'Appuntamento non trovato']);
        exit;
    }
    
    $appointment->delete();
    
    // Restituisci la risposta di successo
    http_response_code(200);
    echo json_encode([
        'message' => 'Appuntamento eliminato con successo',
        'status' => 'success'
    ]);
    
} catch (Exception $e) {
    // Gestisci errori
    http_response_code(500);
    echo json_encode(['message' => 'Errore del server: ' . $e->getMessage()]);
} 