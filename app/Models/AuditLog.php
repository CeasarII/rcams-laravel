<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false; // created_at only, set explicitly

    protected $fillable = [
        'user_id', 'module', 'record_id', 'action',
        'old_value', 'new_value', 'ip_address', 'created_at',
    ];

    protected $casts = ['created_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
