<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'address', 'description', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function inventoryItems()
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function ingredients()
    {
        return $this->belongsToMany(Ingredient::class, 'inventory_items')
            ->withPivot(['quantity', 'unit', 'min_threshold', 'max_capacity'])
            ->withTimestamps();
    }

    public function movements()
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
