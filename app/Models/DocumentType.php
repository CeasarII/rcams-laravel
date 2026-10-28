<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    protected $fillable = ['code', 'label', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function attachments()
    {
        return $this->hasMany(LiquidationItemAttachment::class, 'document_type_id');
    }
}
