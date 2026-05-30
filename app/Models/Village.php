<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Village extends Model
{
    use HasFactory;

    protected $fillable = ['commune_id', 'name', 'code'];

    public function commune()
    {
        return $this->belongsTo(Commune::class);
    }
}
