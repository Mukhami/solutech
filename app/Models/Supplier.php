<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name'
    ];
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
    ];

    //alternative 2 of implementing pivot tables
    public function products()
    {
        return $this->belongsToMany(Product::class, 'supplier_products', 'supply_id')
            ->using(SupplierProduct::class);
    }
}
