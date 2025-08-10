<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Casts\Attribute;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'telegram_username',
        'telegram_id',
        'telegram_channel',
        'role',
        'language',
        'nickname',
        'avatar',
        'banner',
        'bio',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }


    public function songs()
    {
        return $this->hasMany(Song::class);
    }

    public function favorites()
    {
        return $this->belongsToMany(Song::class, 'favorites');
    }

    public function playlists()
    {
        return $this->hasMany(Playlist::class);
    }

    public function followedPlaylists()
    {
        return $this->belongsToMany(Playlist::class, 'follows');
    }

    public function totalUploadedSize(string $in = 'bytes')
    {
        $size = $this->songs()->sum('size'); // bite

        if ($in === 'kb') {
            return round($size / 1024, 2);
        }

        if ($in === 'mb') {
            return round($size / (1024 ** 2), 2);
        }

        if ($in === 'gb') {
            return round($size / (1024 ** 3), 2);
        }

        return $size;
    }

    public function canUpload($fileSize)
    {
        $maxUploadSize = config('uploads.max_upload_size_per_user'); // bite
        $currentSize = $this->totalUploadedSize(); // bite

        return ($currentSize + $fileSize) <= $maxUploadSize;
    }

    public function avatar(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value ? url(\Illuminate\Support\Facades\Storage::url($value)) : null
        );
    }

    public function banner(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value ? url(\Illuminate\Support\Facades\Storage::url($value)) : null
        );
    }

    public function is_premium(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => in_array($attributes['role'], ['artist', 'premium', 'admin']),
        );
    }

    public function subscriptions()
    {
        return $this->belongsToMany(User::class, 'subscription_user', 'subscriber_id', 'subscribed_id')
            ->withTimestamps();
    }

    public function subscribers()
    {
        return $this->belongsToMany(User::class, 'subscription_user', 'subscribed_id', 'subscriber_id')
            ->withTimestamps();
    }

    public function telegram()
    {
        return $this->hasOne(TelegramUser::class, 'telegram_id', 'telegram_id');
    }
}
