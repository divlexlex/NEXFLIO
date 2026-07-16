<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Article;

class ArticleController extends Controller
{
    public function index()
    {
        return Article::whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->get();
    }
}
