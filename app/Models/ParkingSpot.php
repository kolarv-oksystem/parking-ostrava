<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParkingSpot extends Model
{
    use HasFactory;

    protected $fillable = [
        'spot_number',
        'is_occupied',
        'occupied_by_name',
        'occupied_by_device_id',
        'occupied_at',
        'is_reserved_service',
        'skip_auto_release',
    ];

    protected $casts = [
        'is_occupied' => 'boolean',
        'is_reserved_service' => 'boolean',
        'skip_auto_release' => 'boolean',
        'occupied_at' => 'datetime',
    ];
}
