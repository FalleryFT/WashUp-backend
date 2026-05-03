<?php
// app/Models/Service.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use SoftDeletes; // ← aktifkan soft delete

    protected $fillable = ['name', 'price', 'type', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    // ── Relasi ────────────────────────────────────────────────────────────────
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    // ── Scope: hanya yang aktif & belum di-soft-delete ────────────────────────
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ── Scope: kiloan saja ────────────────────────────────────────────────────
    public function scopeKiloan($query)
    {
        return $query->where('type', 'kiloan');
    }

    // ── Scope: addon saja ─────────────────────────────────────────────────────
    public function scopeAddon($query)
    {
        return $query->where('type', 'addon');
    }
}