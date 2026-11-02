<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OfficeLocation extends Model
{
    use HasFactory;

    protected $table = 'office_locations';

    protected $guarded = ['id'];

    protected $casts = [
        'polygon_coordinates' => 'array',
        'latitude'            => 'float',
        'longitude'           => 'float',
        'radius'              => 'integer',
        'is_active'           => 'boolean',
    ];
}

