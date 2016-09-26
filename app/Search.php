<?php

namespace App;

use Carbon\Carbon;
use DateTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Search extends Model
{
    protected $fillable = ['url'];

    public function resources()
    {
        return $this->hasMany('App\Resource', 'search_id');
    }

    public static function findByUrl($url)
    {
        return self::with('resources')
            ->where('url', '=', $url)
            ->where('created_at', '>=', Carbon::now()->subDay(1))
            ->first();
    }

}
