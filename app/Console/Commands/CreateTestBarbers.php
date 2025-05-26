<?php

namespace App\Console\Commands;

use App\Models\Barber;
use Illuminate\Console\Command;

class CreateTestBarbers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:test-barbers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create test barbers for development';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Elimina i barbieri esistenti
        Barber::truncate();
        $this->info('Tabella barbieri svuotata.');
        
        $barbers = [
            // Lombardia - Milano
            [
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
                'opening_time' => '09:00',
                'closing_time' => '18:00',
            ],
            [
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
                'opening_time' => '10:00',
                'closing_time' => '19:00',
            ],
            // Lombardia - Brescia
            [
                'name' => 'Paolo Verdi',
                'shop_name' => 'Barba e Capelli',
                'address' => 'Corso Martiri della Libertà 78, Brescia',
                'regione' => 'Lombardia',
                'provincia' => 'Brescia',
                'comune' => 'Brescia',
                'phone' => '030 5555555',
                'email' => 'paolo@barbaecapelli.it',
                'description' => 'Tradizione bresciana dal 1980',
                'image_url' => 'https://images.unsplash.com/photo-1503951914875-452162b0f3f1',
                'opening_time' => '08:30',
                'closing_time' => '17:30',
            ],
            // Lazio - Roma
            [
                'name' => 'Giovanni Neri',
                'shop_name' => 'Barber Shop Roma',
                'address' => 'Via del Corso 112, Roma',
                'regione' => 'Lazio',
                'provincia' => 'Roma',
                'comune' => 'Roma',
                'phone' => '06 1122334',
                'email' => 'giovanni@barbershoproma.it',
                'description' => 'Stile e tradizione nel cuore di Roma',
                'image_url' => 'https://images.unsplash.com/photo-1585747860715-2ba37e788b70',
                'opening_time' => '09:00',
                'closing_time' => '19:00',
            ],
            // Campania - Napoli
            [
                'name' => 'Antonio Esposito',
                'shop_name' => 'Barbieria Napoletana',
                'address' => 'Via Toledo 45, Napoli',
                'regione' => 'Campania',
                'provincia' => 'Napoli',
                'comune' => 'Napoli',
                'phone' => '081 4455667',
                'email' => 'antonio@barbierianapoletana.it',
                'description' => 'Tradizione partenopea dal 1950',
                'image_url' => 'https://images.unsplash.com/photo-1512864084360-7c0c4d0a0845',
                'opening_time' => '08:00',
                'closing_time' => '20:00',
            ],
        ];

        $count = 0;
        foreach ($barbers as $barberData) {
            Barber::create($barberData);
            $count++;
        }

        $this->info("Creati {$count} barbieri di test con successo!");
    }
}
