<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Slider extends Model
{
    protected $fillable = ['title', 'image_url', 'link_url', 'sort_order', 'is_active'];
}
