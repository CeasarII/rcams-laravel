<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiquidationItemAttachment extends Model
{
    protected $fillable = [
        'liquidation_item_id', 'document_type_id', 'file_name', 'file_path',
        'mime_type', 'file_size', 'uploaded_by', 'uploaded_at',
    ];

    protected $casts = ['uploaded_at' => 'datetime'];

    public function item()
    {
        return $this->belongsTo(LiquidationItem::class, 'liquidation_item_id');
    }

    public function documentType()
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function humanSize(): string
    {
        $bytes = (int) $this->file_size;

        return match (true) {
            $bytes >= 1048576 => number_format($bytes / 1048576, 1).' MB',
            $bytes >= 1024 => number_format($bytes / 1024, 0).' KB',
            default => $bytes.' B',
        };
    }
}
