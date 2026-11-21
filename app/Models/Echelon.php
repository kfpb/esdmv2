<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Echelon extends Model
{
    protected $table = 'echelons';

    protected $guarded = ['id'];

    public function employees()
    {
        return $this->hasMany(Employee::class, 'echelon_id');
    }
}
