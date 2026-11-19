<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductStockMovement extends Model
{
    public const TYPE_OUT = 'OUT';

    public const TYPE_REVERSAL = 'REVERSAL';

    public $timestamps = false;

    protected $fillable = [
        'product_id', 'type', 'quantity', 'unit', 'before', 'after',
        'reference_type', 'reference_id', 'actor_id', 'note', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'before' => 'integer',
            'after' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
