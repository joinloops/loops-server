<?php

namespace App\Models;

use App\Concerns\HasSnowflakePrimary;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $profile_id
 * @property string $referral_code
 * @property int|null $product_id
 * @property float $commission_rate
 * @property string $commission_type
 * @property float $total_earned
 * @property int $total_clicks
 * @property int $total_conversions
 * @property string $status
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Affiliate extends Model
{
    use HasFactory;

    protected $fillable = [
        'profile_id',
        'referral_code',
        'product_id',
        'commission_rate',
        'commission_type',
        'total_earned',
        'total_clicks',
        'total_conversions',
        'status',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'total_earned' => 'decimal:2',
        'total_clicks' => 'integer',
        'total_conversions' => 'integer',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
