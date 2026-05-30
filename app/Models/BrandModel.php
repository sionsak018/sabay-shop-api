<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BrandModel extends Model
{
    use HasFactory;

    protected $table = 'brand_models';
    protected $fillable = ['brand_id', 'name', 'slug'];

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }
}
