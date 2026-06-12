<?php

namespace App\Models;

use App\Concerns\HasSnowflakePrimary;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $profile_id
 * @property string $business_name
 * @property string|null $business_email
 * @property string|null $phone
 * @property string|null $description
 * @property string $status
 * @property float $commission_rate
 * @property float $total_sales
 * @property float $balance
 * @property string|null $payout_method
 * @property array|null $payout_details
 * @property \Carbon\Carbon|null $verified_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Vendor extends Model
{
    use HasFactory, HasSnowflakePrimary;

    protected $fillable = [
        'profile_id',
        'business_name',
        'business_email',
        'phone',
        'description',
        'status',
        'commission_rate',
        'total_sales',
        'balance',
        'payout_method',
        'payout_details',
        'verified_at',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'total_sales' => 'decimal:2',
        'balance' => 'decimal:2',
        'payout_details' => 'array',
        'verified_at' => 'datetime',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
