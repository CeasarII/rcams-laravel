<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Named AppNotification (not Notification) to avoid clashing with
 * Illuminate\Notifications\Notification / the notifiable trait.
 */
class AppNotification extends Model
{
    protected $table = 'notifications';

    protected $fillable = [
        'user_id', 'title', 'message', 'reference_type', 'reference_id',
        'is_read', 'read_at',
    ];

    protected $casts = ['is_read' => 'boolean', 'read_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
