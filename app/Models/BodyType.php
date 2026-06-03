<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BodyType extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'image_url'];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($bodyType) {
            if ($bodyType->image_url) {
                app(\App\Services\CloudinaryService::class)->delete($bodyType->image_url);
            }
        });
    }
}
