<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Song extends Model
{
    protected $fillable = ['time', 'cover', 'path', 'name', 'artist', 'album'];
}
