<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'employee_no', 'full_name', 'email', 'password',
        'department_id', 'company_id', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'is_active' => 'boolean',
        'email_verified_at' => 'datetime',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    public function vessels()
    {
        // Vessels this user (typically an OE) is scoped to validate against
        // for Sea Base requests.
        return $this->belongsToMany(Vessel::class, 'user_vessels');
    }

    public function departments()
    {
        // Departments this user (typically an OE) is scoped to validate
        // against for Shore Base requests.
        return $this->belongsToMany(Department::class, 'user_departments');
    }

    public function hasRole(string $code): bool
    {
        return $this->roles()->where('code', $code)->exists();
    }

    public function fundRequests()
    {
        return $this->hasMany(FundRequest::class, 'requestor_id');
    }
}
