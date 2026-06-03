<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['name', 'slug', 'image_url', 'parent_id'];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($category) {
            if ($category->image_url) {
                app(\App\Services\CloudinaryService::class)->delete($category->image_url);
            }
        });
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function attributes()
    {
        return $this->belongsToMany(Attribute::class, 'category_attribute')
                    ->withPivot('is_required', 'sort_order');
    }
}
