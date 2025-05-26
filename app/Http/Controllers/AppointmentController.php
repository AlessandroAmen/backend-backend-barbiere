<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\User;
use App\Models\Barber;
use App\Models\BarberShop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AppointmentController extends Controller
{
    /**
     * Ottiene la lista degli appuntamenti dell'utente autenticato.
     * Se l'utente è un barbiere, ottiene gli appuntamenti assegnati a lui.
     * Se l'utente è un gestore, ottiene tutti gli appuntamenti del negozio.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $appointments = [];
        
        if ($user->role === 'customer') {
            // Cliente: vede solo i propri appuntamenti
            $appointments = Appointment::where('user_id', $user->id)->with(['barber', 'barberShop'])->get();
        } elseif ($user->role === 'barber') {
            // Barbiere: vede gli appuntamenti assegnati a lui
            $appointments = Appointment::where('barber_id', $user->id)->with(['user', 'barberShop'])->get();
        } elseif ($user->role === 'manager' || $user->role === 'admin') {
            // Gestore/Admin: vede tutti gli appuntamenti del negozio
            if ($request->has('barber_id')) {
                // Filtra per barbiere specifico
                $appointments = Appointment::ofBarber($request->barber_id)
                    ->with(['user', 'barber', 'barberShop'])
                    ->get();
            } else {
                $appointments = Appointment::where('barber_shop_id', $user->barber_shop_id)
                    ->with(['user', 'barber', 'barberShop'])
                    ->get();
            }
        }
        
        return response()->json($appointments);
    }

    /**
     * Ottiene gli slot disponibili per un barbiere in una data specifica.
     */
    public function getAvailableSlots(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'barber_id' => 'required|exists:users,id',
            'date' => 'required|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors(), 'message' => 'Validation failed'], 422);
        }

        $barberId = $request->barber_id;
        $date = $request->date;
        
        // Verifica che l'utente sia un barbiere
        $barber = User::find($barberId);
        if (!$barber) {
            return response()->json(['message' => 'Barber not found'], 404);
        }
        
        if ($barber->role !== 'barber') {
            return response()->json(['message' => 'The specified user is not a barber'], 400);
        }
        
        // Ottieni gli orari del negozio di barbiere
        $barberShop = null;
        
        if ($barber->barber_shop_id) {
            $barberShop = Barber::find($barber->barber_shop_id);
        }
        
        // Orari predefiniti se non troviamo il barber_shop
        $openingTime = Carbon::parse('09:00:00');
        $closingTime = Carbon::parse('18:00:00');
        
        // Se troviamo il barber_shop, usiamo i suoi orari
        if ($barberShop && $barberShop->opening_time && $barberShop->closing_time) {
            $openingTime = Carbon::parse($barberShop->opening_time);
            $closingTime = Carbon::parse($barberShop->closing_time);
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
        $dateStart = Carbon::parse($date . ' 00:00:00');
        $dateEnd = Carbon::parse($date . ' 23:59:59');
        
        $appointments = Appointment::where('barber_id', $barberId)
            ->whereBetween('appointment_date', [$dateStart, $dateEnd])
            ->where('status', '!=', 'cancelled')
            ->select([
                'id',
                'appointment_date',
                'duration',
                'client_name',
                'client_email',
                'client_phone',
                'service_type'
            ])
            ->get();
        
        // Rimuovi gli slot che sono già prenotati
        foreach ($appointments as $appointment) {
            $appointmentTime = Carbon::parse($appointment->appointment_date)->format('H:i');
            
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
        
        return response()->json(['slots' => array_values($slots)]);
    }

    /**
     * Crea un nuovo appuntamento.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'barber_id' => 'required|exists:users,id',
            'date' => 'required|date_format:Y-m-d',
            'time' => 'required|date_format:H:i',
            'service_type' => 'required|string',
            'notes' => 'nullable|string',
            'client_name' => 'required_without:user_id|string',
            'client_email' => 'required_without:user_id|email',
            'client_phone' => 'required_without:user_id|string',
            'user_id' => 'nullable|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $barberId = $request->barber_id;
        
        // Verifica che l'utente selezionato sia un barbiere
        $barber = User::findOrFail($barberId);
        if ($barber->role !== 'barber') {
            return response()->json(['message' => 'L\'utente specificato non è un barbiere'], 400);
        }
        
        // Combina data e ora per ottenere il datetime dell'appuntamento
        $appointmentDateTime = Carbon::parse($request->date . ' ' . $request->time);
        
        // Verifica che la data non sia nel passato
        if ($appointmentDateTime->isPast()) {
            return response()->json(['message' => 'Non puoi prenotare un appuntamento nel passato'], 400);
        }
        
        // Determina la durata in base al tipo di servizio
        $duration = 30; // Default 30 minuti
        if (strtolower($request->service_type) === 'taglio + barba') {
            $duration = 45;
        } elseif (strtolower($request->service_type) === 'barba') {
            $duration = 15;
        }
        
        // Verifica la disponibilità dello slot orario
        $appointmentEndTime = (clone $appointmentDateTime)->addMinutes($duration);
        
        $conflictingAppointments = Appointment::where('barber_id', $barberId)
            ->where('status', '!=', 'cancelled')
            ->whereDate('appointment_date', $appointmentDateTime->format('Y-m-d'))
            ->whereRaw("TIME_FORMAT(appointment_date, '%H:%i') = ?", [$appointmentDateTime->format('H:i')])
            ->count();
        
        if ($conflictingAppointments > 0) {
            return response()->json(['message' => 'Questo slot orario è già prenotato'], 400);
        }
        
        // Crea l'appuntamento
        $appointment = new Appointment();
        
        // Gestione dell'utente (autenticato o ospite)
        if (Auth::check() && !$request->has('user_id')) {
            // Utente autenticato
            $appointment->user_id = Auth::id();
            // Recupera i dati dell'utente autenticato
            $user = Auth::user();
            $appointment->client_name = $user->name;
            $appointment->client_email = $user->email;
            $appointment->client_phone = $user->phone;
        } elseif ($request->has('user_id')) {
            // ID utente fornito esplicitamente
            $appointment->user_id = $request->user_id;
            // Recupera i dati dell'utente specificato
            $user = User::find($request->user_id);
            if ($user) {
                $appointment->client_name = $user->name;
                $appointment->client_email = $user->email;
                $appointment->client_phone = $user->phone;
            }
        } else {
            // Utente ospite - salva i dettagli del cliente
            $appointment->client_name = $request->client_name;
            $appointment->client_email = $request->client_email;
            $appointment->client_phone = $request->client_phone;
            // user_id rimane NULL
        }
        
        $appointment->barber_id = $barberId;
        $appointment->barber_shop_id = $barber->barber_shop_id;
        $appointment->appointment_date = $appointmentDateTime;
        $appointment->duration = $duration;
        $appointment->service_type = $request->service_type;
        $appointment->notes = $request->notes;
        $appointment->status = 'pending';
        $appointment->save();
        
        return response()->json(['message' => 'Appuntamento creato con successo', 'appointment' => $appointment], 201);
    }

    /**
     * Ottiene i dettagli di un appuntamento specifico.
     */
    public function show($id)
    {
        $user = Auth::user();
        $appointment = Appointment::with(['user', 'barber', 'barberShop'])->find($id);
        
        if (!$appointment) {
            return response()->json(['message' => 'Appuntamento non trovato'], 404);
        }
        
        // Verifica i permessi (solo il proprietario, il barbiere assegnato o un gestore/admin può vedere)
        if ($user->id !== $appointment->user_id && 
            $user->id !== $appointment->barber_id && 
            $user->role !== 'manager' && 
            $user->role !== 'admin') {
            return response()->json(['message' => 'Non autorizzato'], 403);
        }
        
        return response()->json($appointment);
    }

    /**
     * Aggiorna un appuntamento esistente.
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $appointment = Appointment::find($id);
        
        if (!$appointment) {
            return response()->json(['message' => 'Appuntamento non trovato'], 404);
        }
        
        // Verifica i permessi (solo il proprietario, un gestore/admin può modificare)
        $canModify = false;
        
        if ($user && $user->id === $appointment->user_id) {
            // Il cliente può modificare solo se l'appuntamento è ancora pending
            $canModify = $appointment->status === 'pending';
        } elseif ($user && ($user->id === $appointment->barber_id || $user->role === 'manager' || $user->role === 'admin')) {
            // Barbieri, gestori e admin possono sempre modificare
            $canModify = true;
        } elseif (!$user && $request->has('client_email') && $appointment->client_email === $request->client_email) {
            // Cliente non autenticato può modificare usando la sua email come identificatore
            $canModify = $appointment->status === 'pending';
        }
        
        if (!$canModify) {
            return response()->json(['message' => 'Non autorizzato a modificare questo appuntamento'], 403);
        }
        
        // Validazione dei dati della richiesta
        $rules = [];
        
        // Le regole dipendono da chi sta facendo la modifica
        if (($user && $user->id === $appointment->user_id) || 
            (!$user && $request->has('client_email') && $appointment->client_email === $request->client_email)) {
            // Il cliente può modificare solo data, ora e note se l'appuntamento è pending
            $rules = [
                'date' => 'sometimes|date_format:Y-m-d',
                'time' => 'sometimes|date_format:H:i',
                'notes' => 'sometimes|nullable|string',
            ];
        } else {
            // Barbieri, gestori e admin possono modificare tutti i campi
            $rules = [
                'barber_id' => 'sometimes|exists:users,id',
                'date' => 'sometimes|date_format:Y-m-d',
                'time' => 'sometimes|date_format:H:i',
                'service_type' => 'sometimes|string',
                'notes' => 'sometimes|nullable|string',
                'status' => 'sometimes|in:pending,confirmed,cancelled,completed',
            ];
        }
        
        $validator = Validator::make($request->all(), $rules);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Aggiorna i campi dell'appuntamento
        if ($request->has('barber_id') && $user && ($user->role === 'manager' || $user->role === 'admin')) {
            $newBarber = User::findOrFail($request->barber_id);
            if ($newBarber->role !== 'barber') {
                return response()->json(['message' => 'L\'utente specificato non è un barbiere'], 400);
            }
            $appointment->barber_id = $newBarber->id;
            // Aggiorna anche barber_shop_id se necessario
            $appointment->barber_shop_id = $newBarber->barber_shop_id;
        }
        
        if ($request->has('date') || $request->has('time')) {
            // Se sono specificati sia data che ora, o solo uno dei due
            $date = $request->has('date') ? $request->date : Carbon::parse($appointment->appointment_date)->format('Y-m-d');
            $time = $request->has('time') ? $request->time : Carbon::parse($appointment->appointment_date)->format('H:i');
            
            $newAppointmentDateTime = Carbon::parse($date . ' ' . $time);
            
            // Verifica che la nuova data non sia nel passato
            if ($newAppointmentDateTime->isPast()) {
                return response()->json(['message' => 'Non puoi spostare un appuntamento nel passato'], 400);
            }
            
            // Verifica la disponibilità dello slot orario (escludendo l'appuntamento corrente)
            $appointmentEndTime = (clone $newAppointmentDateTime)->addMinutes($appointment->duration);
            
            $conflictingAppointments = Appointment::where('barber_id', $appointment->barber_id)
                ->where('id', '!=', $appointment->id)
                ->where('status', '!=', 'cancelled')
                ->where(function ($query) use ($newAppointmentDateTime, $appointmentEndTime) {
                    $query->where(function ($q) use ($newAppointmentDateTime, $appointmentEndTime) {
                        $q->where('appointment_date', '<=', $newAppointmentDateTime)
                          ->whereRaw('DATE_ADD(appointment_date, INTERVAL duration MINUTE) > ?', [$newAppointmentDateTime]);
                    })->orWhere(function ($q) use ($newAppointmentDateTime, $appointmentEndTime) {
                        $q->where('appointment_date', '<', $appointmentEndTime)
                          ->where('appointment_date', '>=', $newAppointmentDateTime);
                    });
                })
                ->count();
            
            if ($conflictingAppointments > 0) {
                return response()->json(['message' => 'Questo slot orario è già prenotato'], 400);
            }
            
            $appointment->appointment_date = $newAppointmentDateTime;
        }
        
        if ($request->has('service_type') && ($user->role === 'manager' || $user->role === 'admin' || $user->id === $appointment->barber_id)) {
            $appointment->service_type = $request->service_type;
            
            // Aggiorna anche la durata in base al tipo di servizio
            if (strtolower($request->service_type) === 'taglio + barba') {
                $appointment->duration = 45;
            } elseif (strtolower($request->service_type) === 'taglio') {
                $appointment->duration = 30;
            } elseif (strtolower($request->service_type) === 'barba') {
                $appointment->duration = 15;
            }
        }
        
        if ($request->has('notes')) {
            $appointment->notes = $request->notes;
        }
        
        if ($request->has('status') && ($user->role === 'manager' || $user->role === 'admin' || $user->id === $appointment->barber_id)) {
            $appointment->status = $request->status;
        }
        
        $appointment->save();
        
        return response()->json(['message' => 'Appuntamento aggiornato con successo', 'appointment' => $appointment]);
    }

    /**
     * Elimina un appuntamento.
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $appointment = Appointment::find($id);
        
        if (!$appointment) {
            return response()->json(['message' => 'Appuntamento non trovato'], 404);
        }
        
        // Verifica i permessi (solo il proprietario, un gestore/admin può eliminare)
        $canDelete = false;
        
        if ($user->id === $appointment->user_id) {
            // Il cliente può eliminare solo se l'appuntamento è ancora pending
            $canDelete = $appointment->status === 'pending';
        } elseif ($user->role === 'manager' || $user->role === 'admin') {
            // Gestori e admin possono sempre eliminare
            $canDelete = true;
        }
        
        if (!$canDelete) {
            return response()->json(['message' => 'Non autorizzato a eliminare questo appuntamento'], 403);
        }
        
        $appointment->delete();
        
        return response()->json(['message' => 'Appuntamento eliminato con successo']);
    }

    /**
     * Ottiene gli appuntamenti per un barbiere specifico.
     */
    public function getBarberAppointments(Request $request, $barberId)
    {
        $user = Auth::user();
        
        // Verifica che l'utente sia autorizzato a vedere gli appuntamenti del barbiere
        $canView = $user->id == $barberId || $user->role === 'manager' || $user->role === 'admin';
        
        if (!$canView) {
            return response()->json(['message' => 'Non autorizzato'], 403);
        }
        
        $appointments = Appointment::where('barber_id', $barberId)
            ->with(['user', 'barberShop']);
        
        // Filtra per data se specificata
        if ($request->has('date')) {
            $date = $request->date;
            $appointments->whereRaw('DATE(appointment_date) = ?', [$date]);
        }
        
        // Filtra per stato se specificato
        if ($request->has('status')) {
            $appointments->where('status', $request->status);
        }
        
        return response()->json($appointments->get());
    }

    /**
     * Ottiene gli appuntamenti per un negozio specifico.
     */
    public function getShopAppointments(Request $request, $shopId)
    {
        $user = Auth::user();
        
        // Verifica che l'utente sia autorizzato a vedere gli appuntamenti del negozio
        $canView = ($user->role === 'barber' && $user->barber_shop_id == $shopId) || 
                   ($user->role === 'manager' && $user->barber_shop_id == $shopId) || 
                   $user->role === 'admin';
        
        if (!$canView) {
            return response()->json(['message' => 'Non autorizzato'], 403);
        }
        
        $appointments = Appointment::where('barber_shop_id', $shopId)
            ->with(['user', 'barber']);
        
        // Filtra per data se specificata
        if ($request->has('date')) {
            $date = $request->date;
            $appointments->whereRaw('DATE(appointment_date) = ?', [$date]);
        }
        
        // Filtra per stato se specificato
        if ($request->has('status')) {
            $appointments->where('status', $request->status);
        }
        
        // Filtra per barbiere se specificato
        if ($request->has('barber_id')) {
            $appointments->where('barber_id', $request->barber_id);
        }
        
        return response()->json($appointments->get());
    }

    public function getAppointmentDetails(Request $request)
    {
        if (!$request->has('barber_id') || !$request->has('date') || !$request->has('time')) {
            return response()->json(['message' => 'Missing parameters: barber_id, date, and time are required'], 400);
        }

        $barberId = $request->query('barber_id');
        $date = $request->query('date');
        $time = $request->query('time');

        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return response()->json(['message' => 'Invalid date format. Use YYYY-MM-DD'], 400);
        }

        // Process time format
        if (is_numeric($time)) {
            $time = sprintf("%02d:00", (int)$time);
        } elseif (strlen($time) <= 2) {
            $time = sprintf("%02d:00", (int)$time);
        } elseif (strpos($time, ':') === false) {
            $time = $time . ':00';
        }

        $appointmentDateTime = Carbon::parse($date . ' ' . $time);
        $timeFormatted = $appointmentDateTime->format('H:i');

        // Cerca l'appuntamento esistente
        $appointment = Appointment::where('barber_id', $barberId)
            ->whereRaw("TIME_FORMAT(appointment_date, '%H:%i') = ?", [$timeFormatted])
            ->whereDate('appointment_date', $appointmentDateTime->format('Y-m-d'))
            ->where('status', '!=', 'cancelled')
            ->first();

        if (!$appointment) {
            return response()->json([
                'found' => false,
                'message' => 'No appointment found for this barber at this date and time'
            ]);
        }

        return response()->json([
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
    }
}
