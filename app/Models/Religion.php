<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Religion extends Model
{
    use HasFactory;

    protected $table = 'religions';
    protected $guarded = ['id'];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'religion_id');
    }
}
