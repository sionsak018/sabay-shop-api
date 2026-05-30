<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'from_user_id',
        'to_user_id',
        'product_id',
        'message',
        'type',
        'file_path',
        'is_read',
    ];

    public function reactions()
    {
        return $this->hasMany(MessageReaction::class);
    }

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }
    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
