<?php

namespace App\Models;

use App\Concerns\HasSnowflakePrimary;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $profile_id
 * @property string $name
 * @property string|null $description
 * @property float $price
 * @property string $currency
 * @property array|null $images
 * @property string|null $category
 * @property array|null $tags
 * @property int $stock
 * @property string $status
 * @property string|null $external_url
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'profile_id',
        'name',
        'description',
        'price',
        'currency',
        'images',
        'category',
        'tags',
        'stock',
        'status',
        'external_url',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'images' => 'array',
        'tags' => 'array',
        'stock' => 'integer',
    ];

    public function videos(): BelongsToMany
    {
        return $this->belongsToMany(Video::class, 'product_video')
            ->withPivot(['bounding_box_x', 'bounding_box_y', 'bounding_box_w', 'bounding_box_h', 'timestamp_start', 'timestamp_end', 'detection_method', 'confidence'])
            ->withTimestamps();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
