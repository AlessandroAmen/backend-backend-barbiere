<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'barber_id',
        'barber_shop_id',
        'appointment_date',
        'duration',
        'service_type',
        'notes',
        'status',
        'client_name',
        'client_email',
        'client_phone'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'appointment_date' => 'datetime',
        'duration' => 'integer',
    ];

    /**
     * Get the user that owns the appointment.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the barber for the appointment.
     */
    public function barber()
    {
        return $this->belongsTo(User::class, 'barber_id');
    }

    /**
     * Get the barber shop for the appointment.
     */
    public function barberShop()
    {
        return $this->belongsTo(Barber::class, 'barber_shop_id');
    }

    /**
     * Scope a query to only include appointments for a specific barber.
     */
    public function scopeOfBarber($query, $barberId)
    {
        return $query->where('barber_id', $barberId);
    }

    /**
     * Scope a query to only include appointments for a specific barber shop.
     */
    public function scopeOfBarberShop($query, $barberShopId)
    {
        return $query->where('barber_shop_id', $barberShopId);
    }

    /**
     * Scope a query to only include future appointments.
     */
    public function scopeFuture($query)
    {
        return $query->where('appointment_date', '>=', now());
    }

    /**
     * Scope a query to only include past appointments.
     */
    public function scopePast($query)
    {
        return $query->where('appointment_date', '<', now());
    }

    /**
     * Scope a query to only include appointments with a specific status.
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope per filtrare gli appuntamenti per negozio.
     */
    public function scopeOfShop($query, $shopId)
    {
        return $query->where('barber_shop_id', $shopId);
    }
}
