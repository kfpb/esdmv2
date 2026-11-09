<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeStatus extends Model
{
    use HasFactory;

    protected $table = 'employee_statuses';

    protected $guarded = ['id'];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'employee_status_id');
    }
}
