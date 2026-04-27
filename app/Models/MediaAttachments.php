<?php

namespace App\Models;

use App\Concerns\HasSnowflakePrimary;
use Illuminate\Database\Eloquent\Model;

class MediaAttachments extends Model
{
    use HasSnowflakePrimary;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    protected $guarded = [];

    protected $hidden = ['local_path', 'storage_driver', 'provider', 'external_id', 'has_cached', 'visibility'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visibility' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'order' => 'integer',
            'is_sensitive' => 'boolean',
        ];
    }
}
