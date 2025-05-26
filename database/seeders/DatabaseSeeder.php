<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Barber;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create a sample barber shop
        $barberShop = Barber::create([
            'shop_name' => 'Mario\'s Barbershop',
            'name' => 'Mario Rossi',
            'address' => 'Via Roma 123',
            'regione' => 'Lombardia',
            'provincia' => 'MI',
            'comune' => 'Milano',
            'phone' => '02 1234567',
            'email' => 'mario@barber.com',
            'description' => 'Un barbiere di classe nel centro di Milano',
            'image_url' => 'https://picsum.photos/200',
            'opening_time' => '09:00:00',
            'closing_time' => '19:00:00',
            'is_available' => true
        ]);
        
        // Create admin user
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin'
        ]);
        
        // Create manager user
        $manager = User::create([
            'name' => 'Manager User',
            'email' => 'manager@example.com',
            'password' => Hash::make('password'),
            'role' => 'manager',
            'barber_shop_id' => $barberShop->id
        ]);
        
        // Create barber users
        $barber1 = User::create([
            'name' => 'Barber One',
            'email' => 'barber1@example.com',
            'password' => Hash::make('password'),
            'role' => 'barber',
            'barber_shop_id' => $barberShop->id,
            'manager_id' => $manager->id
        ]);
        
        $barber2 = User::create([
            'name' => 'Barber Two',
            'email' => 'barber2@example.com',
            'password' => Hash::make('password'),
            'role' => 'barber',
            'barber_shop_id' => $barberShop->id,
            'manager_id' => $manager->id
        ]);
        
        // Create customer users
        $customer = User::create([
            'name' => 'Customer User',
            'email' => 'customer@example.com',
            'password' => Hash::make('password'),
            'role' => 'customer'
        ]);
        
        // You can add more users if needed
        
        // Aggiungi il seeder dell'admin
        $this->call(AdminUserSeeder::class);
    }
}
