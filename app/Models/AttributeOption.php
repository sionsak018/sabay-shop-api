<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AttributeOption extends Model
{
    use HasFactory;

    protected $fillable = ['attribute_id', 'value', 'image_url'];

    public function attribute()
    {
        return $this->belongsTo(Attribute::class);
    }
}
