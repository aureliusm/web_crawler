<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Resource extends Model
{
    protected $fillable = ['search_id', 'resource_id', 'url', 'type', 'title', 'description', 'og_title', 'og_description', 'language', 'file_type', 'file_size', 'file_dimensions'];

    public function search()
    {
        return $this->belongsTo('App\Search', 'search_id');
    }

    public function resources()
    {
        return $this->hasMany('App\Resource', 'resource_id');
    }

    public static function findAllByKeywordsAndSearchId($keywords, $search_id)
    {
        return self::with('search')
            ->where('search_id', '=', $search_id)
            ->where('title', 'LIKE', '%' . $keywords . '%')
            ->orWhere('url', 'LIKE', '%' . $keywords . '%')
            ->orWhere('description', 'LIKE', '%' . $keywords . '%')
            ->orWhere('og_title', 'LIKE', '%' . $keywords . '%')
            ->orWhere('og_description', 'LIKE', '%' . $keywords . '%')
            ->orWhereHas('resources', function ($q) use ($keywords) {
                $q->where('title', 'LIKE', '%' . $keywords . '%')
                    ->orWhere('url', 'LIKE', '%' . $keywords . '%')
                    ->orWhere('description', 'LIKE', '%' . $keywords . '%')
                    ->orWhere('og_title', 'LIKE', '%' . $keywords . '%')
                    ->orWhere('og_description', 'LIKE', '%' . $keywords . '%')
                ;
            })
            ->get();
    }

}
