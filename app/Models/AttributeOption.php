<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AttributeOption extends Model
{
    use HasFactory;

    protected $fillable = ['attribute_id', 'value', 'image_url'];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($option) {
            if ($option->image_url) {
                app(\App\Services\CloudinaryService::class)->delete($option->image_url);
            }
        });
    }

    public function attribute()
    {
        return $this->belongsTo(Attribute::class);
    }
}
