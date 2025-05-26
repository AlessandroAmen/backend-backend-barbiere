<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Appointment;
use App\Models\Barber;
use Carbon\Carbon;
use Exception;

class ApiController extends Controller
{
    public function handleApi(Request $request)
    {
        // Get request path and method
        $path = $request->query('path');
        $method = $request->method();
        
        try {
            // Routes
            if ($path === 'available-slots' && $method === 'GET') {
                return $this->getAvailableSlots($request);
            } 
            else if ($path === 'book-appointment' && $method === 'POST') {
                return $this->bookAppointment($request);
            }
            else if ($path === 'appointments' && $method === 'GET') {
                return $this->getAppointments($request);
            }
            else if ($path === 'delete-appointment' && $method === 'POST') {
                return $this->deleteAppointment($request);
            }
            else if ($path === 'reset-system' && $method === 'POST') {
                return $this->resetSystem($request);
            }
            else {
                return response()->json(['message' => 'Endpoint not found'], 404);
            }
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Server error: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
    
    private function getAvailableSlots(Request $request)
    {
        // Get available slots
        if (!$request->has('barber_id') || !$request->has('date')) {
            return response()->json(['message' => 'Missing parameters: barber_id and date are required'], 400);
        }

        $barberId = $request->query('barber_id');
        $date = $request->query('date');
        
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return response()->json(['message' => 'Invalid date format. Use YYYY-MM-DD'], 400);
        }

        // Trova il barbiere - prova prima con Barber, poi con User
        $barber = Barber::find($barberId);
        $isUserBarber = false;
        
        // Se non trovato, prova con User
        if (!$barber) {
            $userBarber = User::find($barberId);
            if ($userBarber && $userBarber->role === 'barber') {
                $barber = $userBarber;
                $isUserBarber = true;
            } else {
                return response()->json(['message' => 'Barber not found'], 404);
            }
        }

        // Get shop hours
        if ($isUserBarber) {
            // Se è un User-Barber, usa gli orari predefiniti o cerca nel negozio
            $openingTime = Carbon::parse('09:00:00');
            $closingTime = Carbon::parse('18:00:00');
            
            if ($barber->barber_shop_id) {
                $barberShop = Barber::find($barber->barber_shop_id);
                if ($barberShop && isset($barberShop->opening_time) && isset($barberShop->closing_time)) {
                    $openingTime = Carbon::parse($barberShop->opening_time);
                    $closingTime = Carbon::parse($barberShop->closing_time);
                }
            }
        } else {
            // Se è un Barber, usa i suoi orari
            $openingTime = Carbon::parse($barber->opening_time ?? '09:00:00');
            $closingTime = Carbon::parse($barber->closing_time ?? '18:00:00');
        }
        
        // Generate time slots
        $slots = [];
        $current = clone $openingTime;
        $appointmentDuration = 30; // Default appointment duration in minutes
        
        while ($current->lt($closingTime)) {
            $endTime = (clone $current)->addMinutes($appointmentDuration);
            
            if ($endTime->lte($closingTime)) {
                $slots[] = [
                    'time' => $current->format('H:i'),
                    'isBooked' => false
                ];
            }
            
            $current->addMinutes(15); // 15-minute intervals
        }

        // Get appointments for this specific date (more efficient filtering)
        $dateStart = Carbon::parse($date . ' 00:00:00');
        $dateEnd = Carbon::parse($date . ' 23:59:59');
        
        $existingAppointments = Appointment::where('barber_id', $barberId)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('appointment_date', [$dateStart, $dateEnd])
            ->get();
        
        // Debug info for booked slots
        $bookedDetails = [];
        
        // Mark booked slots
        foreach ($existingAppointments as $appointment) {
            $appointmentTime = Carbon::parse($appointment->appointment_date)->format('H:i');
            
            // Add to debug info
            $bookedDetails[] = [
                'id' => $appointment->id,
                'time' => $appointmentTime,
                'full_date' => $appointment->appointment_date
            ];
            
            // Find and mark the slot as booked
            foreach ($slots as $key => $slot) {
                if ($slot['time'] === $appointmentTime) {
                    $slots[$key]['isBooked'] = true;
                    $slots[$key]['appointmentId'] = $appointment->id;
                    break;
                }
            }
        }

        // Include debug info in development mode
        $debug = [
            'booked_appointments' => $bookedDetails,
            'total_booked' => count($existingAppointments)
        ];

        return response()->json([
            'slots' => $slots,
            'date' => $date,
            'barber_id' => $barberId,
            'debug' => $debug
        ]);
    }
    
    private function bookAppointment(Request $request) 
    {
        $data = $request->all();
        
        if (!isset($data['barber_id']) || !isset($data['date']) || !isset($data['time'])) {
            return response()->json(['message' => 'Missing required data'], 400);
        }
        
        // Trova il barbiere - prova prima con Barber, poi con User
        $barber = Barber::find($data['barber_id']);
        $isUserBarber = false;
        $barberShopId = null;
        
        // Se non trovato, prova con User
        if (!$barber) {
            $userBarber = User::find($data['barber_id']);
            if ($userBarber && $userBarber->role === 'barber') {
                $barber = $userBarber;
                $isUserBarber = true;
                $barberShopId = $barber->barber_shop_id;
            } else {
                return response()->json(['message' => 'Barber not found'], 404);
            }
        } else {
            $barberShopId = $barber->id; // Il barber_shop_id è l'ID del barbiere
        }
        
        // Process time format
        $time = $data['time'];
        
        if (is_numeric($time)) {
            $time = sprintf("%02d:00", (int)$time);
        } elseif (strlen($time) <= 2) {
            $time = sprintf("%02d:00", (int)$time);
        } elseif (strpos($time, ':') === false) {
            $time = $time . ':00';
        }
        
        $appointmentDateTime = Carbon::parse($data['date'] . ' ' . $time);
        
        // Check if time slot is already booked
        $existingAppointment = Appointment::where('barber_id', $data['barber_id'])
            ->where('status', '!=', 'cancelled')
            ->whereDate('appointment_date', $appointmentDateTime->format('Y-m-d'))
            ->whereRaw("TIME_FORMAT(appointment_date, '%H:%i') = ?", [$appointmentDateTime->format('H:i')])
            ->first();
        
        if ($existingAppointment) {
            return response()->json([
                'message' => 'This time slot is already booked',
                'debug' => [
                    'requested_time' => $appointmentDateTime->format('H:i'),
                    'conflict_with' => Carbon::parse($existingAppointment->appointment_date)->format('H:i'),
                    'appointment_id' => $existingAppointment->id
                ]
            ], 409); // Conflict
        }
        
        // Create new appointment
        $appointment = new Appointment();
        $appointment->barber_id = $data['barber_id'];
        $appointment->barber_shop_id = $barberShopId;
        $appointment->appointment_date = $appointmentDateTime;
        $appointment->duration = $data['duration'] ?? 30;
        $appointment->service_type = $data['service_type'] ?? 'Haircut';
        $appointment->notes = $data['notes'] ?? '';
        $appointment->status = 'pending';
        $appointment->user_id = null;
        $appointment->client_name = $data['client_name'] ?? 'Web Client';
        $appointment->client_email = $data['client_email'] ?? 'client@example.com';
        $appointment->client_phone = $data['client_phone'] ?? '';
        $appointment->save();
        
        return response()->json([
            'message' => 'Appointment created successfully',
            'appointment' => [
                'id' => $appointment->id,
                'date' => $data['date'],
                'time' => $time,
                'barber_id' => $appointment->barber_id,
                'service_type' => $appointment->service_type
            ]
        ], 201); // Created
    }
    
    private function getAppointments(Request $request)
    {
        if (!$request->has('barber_id')) {
            return response()->json(['message' => 'Missing barber_id parameter'], 400);
        }
        
        $barberId = $request->query('barber_id');
        
        $appointments = Appointment::where('barber_id', $barberId)
            ->where('status', '!=', 'cancelled')
            ->orderBy('appointment_date')
            ->get()
            ->map(function($appointment) {
                return [
                    'id' => $appointment->id,
                    'date' => Carbon::parse($appointment->appointment_date)->format('Y-m-d'),
                    'time' => Carbon::parse($appointment->appointment_date)->format('H:i'),
                    'service_type' => $appointment->service_type,
                    'status' => $appointment->status,
                    'client_name' => $appointment->client_name,
                    'client_phone' => $appointment->client_phone
                ];
            });
        
        return response()->json(['appointments' => $appointments]);
    }
    
    private function deleteAppointment(Request $request)
    {
        $data = $request->all();
        
        if (!isset($data['appointment_id'])) {
            return response()->json(['message' => 'Missing appointment_id'], 400);
        }
        
        $appointment = Appointment::find($data['appointment_id']);
        
        if (!$appointment) {
            return response()->json(['message' => 'Appointment not found'], 404);
        }
        
        $appointment->delete();
        
        return response()->json(['message' => 'Appointment deleted successfully']);
    }
    
    private function resetSystem(Request $request)
    {
        $data = $request->all();
        
        if (!isset($data['confirm']) || $data['confirm'] !== true) {
            return response()->json(['message' => 'Confirmation required'], 400);
        }
        
        Appointment::truncate();
        
        if (isset($data['create_test_data']) && $data['create_test_data'] === true) {
            // Create sample test appointment for today
            $today = Carbon::today();
            $appointment = new Appointment();
            $appointment->barber_id = $data['barber_id'] ?? 1;
            $appointment->barber_shop_id = 1;
            $appointment->appointment_date = $today->setHour(10)->setMinute(0);
            $appointment->duration = 30;
            $appointment->service_type = 'Haircut';
            $appointment->notes = 'Test appointment';
            $appointment->status = 'confirmed';
            $appointment->client_name = 'Test Client';
            $appointment->client_email = 'test@example.com';
            $appointment->client_phone = '1234567890';
            $appointment->save();
        }
        
        return response()->json(['message' => 'System reset successfully']);
    }
}
