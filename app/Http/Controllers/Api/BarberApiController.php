<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barber;
use Illuminate\Http\Request;

class BarberApiController extends Controller
{
    /**
     * Restituisce la lista di tutte le regioni disponibili
     */
    public function getRegioni()
    {
        $regioni = Barber::select('regione')
            ->distinct()
            ->orderBy('regione')
            ->get()
            ->pluck('regione');
        
        return response()->json($regioni);
    }

    /**
     * Restituisce la lista di tutte le province per una regione
     */
    public function getProvince($regione)
    {
        $province = Barber::where('regione', $regione)
            ->select('provincia')
            ->distinct()
            ->orderBy('provincia')
            ->get()
            ->pluck('provincia');
        
        return response()->json($province);
    }

    /**
     * Restituisce la lista di tutti i barbieri disponibili
     * Opzionalmente filtrati per regione e provincia
     */
    public function index(Request $request)
    {
        $query = Barber::where('is_available', true);
        
        // Filtra per regione se specificata
        if ($request->has('regione')) {
            $query->where('regione', $request->regione);
        }
        
        // Filtra per provincia se specificata
        if ($request->has('provincia')) {
            $query->where('provincia', $request->provincia);
        }
        
        // Filtra per comune se specificato
        if ($request->has('comune')) {
            $query->where('comune', $request->comune);
        }
        
        $barbers = $query->get();
        
        return response()->json($barbers);
    }

    /**
     * Restituisce i dettagli di un barbiere specifico
     */
    public function show($id)
    {
        $barber = Barber::find($id);
        
        if (!$barber) {
            return response()->json(['message' => 'Barbiere non trovato'], 404);
        }
        
        return response()->json($barber);
    }
}
