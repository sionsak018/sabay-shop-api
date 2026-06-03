<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    protected $fillable = ['product_id', 'image_url', 'sort_order'];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($image) {
            app(\App\Services\CloudinaryService::class)->delete($image->image_url);
        });
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
