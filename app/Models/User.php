<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'barber_shop_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Controlla se l'utente è un amministratore.
     *
     * @return bool
     */
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    /**
     * Controlla se l'utente è un gestore.
     *
     * @return bool
     */
    public function isManager()
    {
        return $this->role === 'manager';
    }

    /**
     * Controlla se l'utente è un barbiere.
     *
     * @return bool
     */
    public function isBarber()
    {
        return $this->role === 'barber';
    }

    /**
     * Controlla se l'utente è un cliente.
     *
     * @return bool
     */
    public function isCustomer()
    {
        return $this->role === 'customer';
    }

    /**
     * Relazione con il negozio di barbiere.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function barberShop()
    {
        return $this->belongsTo(Barber::class, 'barber_shop_id');
    }

    /**
     * Relazione con i barbieri (se l'utente è un gestore).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function barbers()
    {
        if ($this->isManager()) {
            return $this->hasMany(User::class, 'manager_id');
        }
        
        return null;
    }

    /**
     * Relazione con gli appuntamenti dell'utente (cliente).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'user_id');
    }

    /**
     * Relazione con gli appuntamenti assegnati al barbiere.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function barberAppointments()
    {
        if ($this->isBarber()) {
            return $this->hasMany(Appointment::class, 'barber_id');
        }
        
        return null;
    }

    /**
     * Ottieni tutti gli appuntamenti per un gestore (tutti gli appuntamenti dei suoi barbieri).
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllShopAppointments()
    {
        if (!$this->isManager() || !$this->barber_shop_id) {
            return collect();
        }
        
        return Appointment::where('barber_shop_id', $this->barber_shop_id)->get();
    }
}
