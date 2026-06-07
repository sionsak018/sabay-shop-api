<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'avatar',
        'cover_photo',
        'role',
        'account_type',
        'post_limit',
        'about_me',
        'province_id',
        'district_id',
        'commune_id',
        'village_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['ads_count', 'followers_count', 'following_count'];

    public function getAdsCountAttribute()
    {
        return $this->products()->where('status', 'active')->count();
    }

    public function getFollowersCountAttribute()
    {
        return $this->followers()->count();
    }

    public function getFollowingCountAttribute()
    {
        return $this->following()->count();
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($user) {
            $cloudinary = app(\App\Services\CloudinaryService::class);
            if ($user->avatar) {
                $cloudinary->delete($user->avatar);
            }
            if ($user->cover_photo) {
                $cloudinary->delete($user->cover_photo);
            }

            // Delete user products (this will trigger product hooks for their images)
            foreach ($user->products as $product) {
                $product->delete();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'seller_id');
    }

    public function cart()
    {
        return $this->hasOne(Cart::class);
    }

    public function favorites()
    {
        return $this->belongsToMany(Product::class, 'favorites');
    }

    public function followers()
    {
        return $this->belongsToMany(User::class, 'follows', 'following_id', 'follower_id');
    }

    public function following()
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'following_id');
    }

    public function province()
    {
        return $this->belongsTo(Province::class);
    }

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function commune()
    {
        return $this->belongsTo(Commune::class);
    }

    public function village()
    {
        return $this->belongsTo(Village::class);
    }
}
