<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'address',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

     // ── Relasi ────────────────────────────────────────────────────────────────
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
 
    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }
 
    public function sentChats()
    {
        return $this->hasMany(Chat::class, 'sender_id');
    }
 
    public function receivedChats()
    {
        return $this->hasMany(Chat::class, 'receiver_id');
    }
    // Kolom untuk soft deletes
    protected $dates = ['deleted_at'];
}