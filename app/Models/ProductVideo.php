<?php

namespace App\Models;

use App\Concerns\HasSnowflakePrimary;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $product_id
 * @property int|null $video_id
 * @property int|null $comment_id
 * @property float|null $bounding_box_x
 * @property float|null $bounding_box_y
 * @property float|null $bounding_box_w
 * @property float|null $bounding_box_h
 * @property float|null $timestamp_start
 * @property float|null $timestamp_end
 * @property string $detection_method
 * @property float|null $confidence
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class ProductVideo extends Model
{
    use HasFactory;

    protected $table = 'product_video';

    protected $fillable = [
        'product_id',
        'video_id',
        'comment_id',
        'bounding_box_x',
        'bounding_box_y',
        'bounding_box_w',
        'bounding_box_h',
        'timestamp_start',
        'timestamp_end',
        'detection_method',
        'confidence',
    ];

    protected $casts = [
        'bounding_box_x' => 'float',
        'bounding_box_y' => 'float',
        'bounding_box_w' => 'float',
        'bounding_box_h' => 'float',
        'timestamp_start' => 'float',
        'timestamp_end' => 'float',
        'confidence' => 'float',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }
}
