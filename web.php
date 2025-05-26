// API pubblica per prenotazioni (senza CSRF)
Route::post('/public-appointment', function (Illuminate\Http\Request $request) {
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
        return response()->json(['errors' => $validator->errors()], 422);
    }

    try {
        // Verifica che l'utente selezionato sia un barbiere
        $barber = App\Models\User::findOrFail($request->barber_id);
        if ($barber->role !== 'barber') {
            return response()->json(['message' => 'L\'utente specificato non è un barbiere'], 400);
        }
        
        // Combina data e ora per ottenere il datetime dell'appuntamento
        $appointmentDateTime = \Carbon\Carbon::parse($request->date . ' ' . $request->time);
        
        // Verifica che la data non sia nel passato
        if ($appointmentDateTime->isPast()) {
            return response()->json(['message' => 'Non puoi prenotare un appuntamento nel passato'], 400);
        }
    } catch (\Exception $e) {
        return response()->json(['message' => 'Errore durante la validazione'], 500);
    }
}); 