<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BarberTestController extends Controller
{
    public function index()
    {
        return response()->json([
            'message' => 'Barber test controller works!',
            'barbers' => [
                [
                    'id' => 1,
                    'name' => 'Mario Rossi',
                    'shop_name' => 'Barbiere di Mario',
                    'address' => 'Via Roma 123',
                    'regione' => 'Lombardia',
                    'provincia' => 'Milano',
                    'comune' => 'Milano',
                    'phone' => '123456789',
                    'email' => 'mario@example.com',
                    'description' => 'Barbiere esperto con 10 anni di esperienza',
                    'image_url' => 'https://example.com/mario.jpg',
                    'is_available' => true,
                    'opening_time' => '09:00',
                    'closing_time' => '18:00'
                ],
                [
                    'id' => 2,
                    'name' => 'Luigi Verdi',
                    'shop_name' => 'Barbiere di Luigi',
                    'address' => 'Via Milano 456',
                    'regione' => 'Lazio',
                    'provincia' => 'Roma',
                    'comune' => 'Roma',
                    'phone' => '987654321',
                    'email' => 'luigi@example.com',
                    'description' => 'Barbiere specializzato in tagli moderni',
                    'image_url' => 'https://example.com/luigi.jpg',
                    'is_available' => true,
                    'opening_time' => '10:00',
                    'closing_time' => '19:00'
                ]
            ]
        ]);
    }
} 