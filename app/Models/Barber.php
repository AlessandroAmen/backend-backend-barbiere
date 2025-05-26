<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Barber extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'shop_name',
        'address',
        'regione',
        'provincia',
        'comune',
        'phone',
        'email',
        'description',
        'image_url',
        'is_available',
        'opening_time',
        'closing_time',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_available' => 'boolean',
        'opening_time' => 'datetime:H:i',
        'closing_time' => 'datetime:H:i',
    ];

    /**
     * Get the barbers that belong to this barber shop.
     */
    public function barbers()
    {
        return $this->hasMany(User::class, 'barber_shop_id');
    }

    /**
     * Get the appointments for this barber shop.
     */
    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'barber_shop_id');
    }
}
