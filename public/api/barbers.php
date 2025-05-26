<?php

header('Content-Type: application/json');

// Simula la risposta dell'API dei barbieri
$barbers = [
    [
        'id' => 1,
        'name' => 'Marco Rossi',
        'shop_name' => 'Barbiere Elegante',
        'address' => 'Via Montenapoleone 12, Milano',
        'regione' => 'Lombardia',
        'provincia' => 'Milano',
        'comune' => 'Milano',
        'phone' => '02 1234567',
        'email' => 'marco@barbiereelegante.it',
        'description' => 'Specializzato in tagli classici e barba',
        'image_url' => 'https://images.unsplash.com/photo-1521590832167-7bcbfaa6381f',
        'is_available' => true,
        'opening_time' => '09:00',
        'closing_time' => '18:00',
        'created_at' => '2023-05-18T10:00:00.000000Z',
        'updated_at' => '2023-05-18T10:00:00.000000Z'
    ],
    [
        'id' => 2,
        'name' => 'Luca Bianchi',
        'shop_name' => 'Taglio Perfetto',
        'address' => 'Corso Buenos Aires 45, Milano',
        'regione' => 'Lombardia',
        'provincia' => 'Milano',
        'comune' => 'Milano',
        'phone' => '02 9876543',
        'email' => 'luca@taglioperfetto.it',
        'description' => 'Barbiere moderno con servizi premium',
        'image_url' => 'https://images.unsplash.com/photo-1622286342621-4bd786c2447c',
        'is_available' => true,
        'opening_time' => '10:00',
        'closing_time' => '19:00',
        'created_at' => '2023-05-18T10:00:00.000000Z',
        'updated_at' => '2023-05-18T10:00:00.000000Z'
    ]
];

echo json_encode($barbers); 