<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Education extends Model
{
    protected $table = 'educations';

    protected $guarded = ['id'];

    public function employees()
    {
        return $this->hasMany(Employee::class, 'education_id');
    }
}
