<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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
        'role',
        'language',
        'nickname',
        'avatar',
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

}
