<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Dati dell'amministratore
        $adminData = [
            'name' => 'Amministratore',
            'email' => 'admin@admin.com',
            'password' => Hash::make('Admin123!'),
            'role' => 'admin',
            'created_at' => now(),
            'updated_at' => now()
        ];

        // Verifica se l'utente admin esiste già
        $existingAdmin = User::where('email', $adminData['email'])->first();

        if ($existingAdmin) {
            // Aggiorna il ruolo a 'admin' se l'utente esiste già
            $existingAdmin->role = 'admin';
            $existingAdmin->save();
            
            $this->command->info('Utente admin esistente aggiornato: ' . $adminData['email']);
        } else {
            // Crea un nuovo utente admin
            User::create($adminData);
            
            $this->command->info('Nuovo utente admin creato: ' . $adminData['email']);
        }

        // Output delle credenziali a schermo
        $this->command->info('----------------------------------------');
        $this->command->info('CREDENZIALI AMMINISTRATORE:');
        $this->command->info('Email: ' . $adminData['email']);
        $this->command->info('Password: Admin123!');
        $this->command->info('----------------------------------------');
    }
}