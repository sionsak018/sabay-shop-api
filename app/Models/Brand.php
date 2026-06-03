<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Brand extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'image_url', 'category_id'];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($brand) {
            if ($brand->image_url) {
                app(\App\Services\CloudinaryService::class)->delete($brand->image_url);
            }
        });
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function models()
    {
        return $this->hasMany(BrandModel::class);
    }
}
