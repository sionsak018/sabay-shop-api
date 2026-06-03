<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Slider extends Model
{
    protected $fillable = ['title', 'image_url', 'link_url', 'sort_order', 'is_active'];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($slider) {
            if ($slider->image_url) {
                app(\App\Services\CloudinaryService::class)->delete($slider->image_url);
            }
        });
    }
}
