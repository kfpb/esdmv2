<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Division extends Model
{
    use HasFactory;

    protected $table = 'divisions';

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function subDivisions(): HasMany
    {
        return $this->hasMany(SubDivision::class, 'division_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'division_id');
    }
}
