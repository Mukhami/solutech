<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'order_number','created_at','updated_at'
    ];
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    //alternative 1 of implementing pivot tables
    public function products()
    {
        return $this->belongsToMany(Product::class, 'order_details')
            ->withPivot(['created_at', 'updated_at', 'deleted_at'])
            ->whereNull('order_details.deleted_at') //soft-delete
            ->withTimestamps();
    }
}
