<?php
// app/Models/Order.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    protected $fillable = [
        'nota', 'user_id', 'customer_name', 'customer_phone',
        'customer_type', 'service_id', 'weight',
        'total_price', 'status', 'timeline',
        'order_date', 'estimated_date',
    ];

    protected $casts = [
        'timeline'       => 'array',
        'order_date'     => 'date',
        'estimated_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    use SoftDeletes;
}
