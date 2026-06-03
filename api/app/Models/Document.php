<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'documentable_type',
        'documentable_id',
        'type',
        'file_path',
        'uploaded_by',
    ];

    /** Disk holding the (private) uploaded files. */
    public const DISK = 'local';

    protected static function booted(): void
    {
        // Remove the backing file whenever a document row is deleted directly.
        static::deleted(function (Document $document) {
            Storage::disk(self::DISK)->delete($document->file_path);
        });
    }

    /**
     * The owning model — a Project or IncentiveApplication.
     */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
