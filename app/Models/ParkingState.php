<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParkingState extends Model
{
    use HasFactory;

    protected $fillable = [
        'capacity_total',
        'free_spots',
        'updated_by',
        'version',
        'manual_update_password_hash',
    ];
}
