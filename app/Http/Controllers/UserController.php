<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Barber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Ottieni tutti gli utenti (solo per admin)
     */
    public function index()
    {
        // Verifica che l'utente sia admin
        if (!Auth::user()->isAdmin()) {
            return response()->json(['message' => 'Non autorizzato'], 403);
        }

        $users = User::all();
        return response()->json($users);
    }

    /**
     * Ottieni utenti per ruolo
     * Se il ruolo è 'barber', consenti accesso pubblico
     */
    public function getUsersByRole($role)
    {
        // Se stiamo cercando barbieri, consenti l'accesso pubblico
        if ($role === 'barber') {
            $users = User::where('role', $role)->get();
            return response()->json($users);
        }
        
        // Altrimenti, verifica che l'utente sia admin
        if (!Auth::check() || !Auth::user()->isAdmin()) {
            return response()->json(['message' => 'Non autorizzato'], 403);
        }

        $users = User::where('role', $role)->get();
        return response()->json($users);
    }

    /**
     * Promuovi un utente a gestore (solo per admin)
     */
    public function promoteToManager(Request $request, $id)
    {
        // Verifica che l'utente sia admin
        if (!Auth::user()->isAdmin()) {
            return response()->json(['message' => 'Non autorizzato'], 403);
        }

        $validator = Validator::make($request->all(), [
            'barber_shop_id' => 'required|exists:barbers,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::findOrFail($id);
        $user->role = 'manager';
        $user->barber_shop_id = $request->barber_shop_id;
        $user->save();

        return response()->json([
            'message' => 'Utente promosso a gestore con successo',
            'user' => $user
        ]);
    }

    /**
     * Promuovi un utente a barbiere (solo per admin o gestore)
     */
    public function promoteToBarber(Request $request, $id)
    {
        // Verifica che l'utente sia admin o gestore
        if (!Auth::user()->isAdmin() && !Auth::user()->isManager()) {
            return response()->json(['message' => 'Non autorizzato'], 403);
        }

        $validator = Validator::make($request->all(), [
            'barber_shop_id' => 'required|exists:barbers,id',
            'manager_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Se è un gestore, può promuovere solo per il suo negozio
        if (Auth::user()->isManager() && Auth::user()->barber_shop_id != $request->barber_shop_id) {
            return response()->json(['message' => 'Non puoi promuovere barbieri per altri negozi'], 403);
        }

        $user = User::findOrFail($id);
        $user->role = 'barber';
        $user->barber_shop_id = $request->barber_shop_id;
        $user->manager_id = $request->manager_id;
        $user->save();

        return response()->json([
            'message' => 'Utente promosso a barbiere con successo',
            'user' => $user
        ]);
    }

    /**
     * Ottieni informazioni sull'utente corrente con eventuali dati aggiuntivi
     * in base al ruolo.
     */
    public function me()
    {
        $user = Auth::user();
        $response = $user->toArray();

        // Aggiungi dati specifici in base al ruolo
        if ($user->isManager() || $user->isBarber()) {
            $barberShop = $user->barberShop;
            $response['barber_shop'] = $barberShop;
        }

        // Se è un gestore, aggiungi i barbieri associati
        if ($user->isManager()) {
            $barbers = $user->barbers;
            $response['barbers'] = $barbers;
        }

        return response()->json($response);
    }

    /**
     * Trasforma un utente in barbiere (solo per admin)
     * Versione semplificata che non richiede barber_shop_id e manager_id
     */
    public function makeBarber($id)
    {
        // Verifica che l'utente sia admin
        if (!Auth::user()->isAdmin()) {
            return response()->json(['message' => 'Non autorizzato'], 403);
        }

        $user = User::findOrFail($id);
        
        // Verifica che l'utente non sia già un barbiere
        if ($user->isBarber()) {
            return response()->json(['message' => 'L\'utente è già un barbiere'], 400);
        }

        // Verifica che l'utente non sia un admin
        if ($user->isAdmin()) {
            return response()->json(['message' => 'Non puoi modificare il ruolo di un amministratore'], 403);
        }

        $user->role = 'barber';
        $user->save();

        return response()->json([
            'message' => 'Utente trasformato in barbiere con successo',
            'user' => $user
        ]);
    }
} 