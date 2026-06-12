<?php

namespace App\Models;

use App\Concerns\HasSnowflakePrimary;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $buyer_profile_id
 * @property int|null $seller_profile_id
 * @property int $product_id
 * @property int|null $video_id
 * @property int $quantity
 * @property float $unit_price
 * @property float $total
 * @property string $currency
 * @property string $status
 * @property string|null $payment_method
 * @property string|null $payment_id
 * @property array|null $shipping_address
 * @property string|null $notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'buyer_profile_id',
        'seller_profile_id',
        'product_id',
        'video_id',
        'quantity',
        'unit_price',
        'total',
        'currency',
        'status',
        'payment_method',
        'payment_id',
        'shipping_address',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total' => 'decimal:2',
        'shipping_address' => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'buyer_profile_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'seller_profile_id');
    }
}
