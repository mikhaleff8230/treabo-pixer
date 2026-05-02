<?php

namespace Marvel\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Marvel\Database\Models\Hashtag;

class HashtagController extends CoreController
{
    /**
     * Получить список хештегов с поиском
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $limit = $request->get('limit', 20);
        $name = $request->get('name', '');
        
        $query = Hashtag::query();
        
        // Поиск по имени (если передан параметр name)
        if (!empty($name)) {
            $query->where('name', 'LIKE', '%' . $name . '%');
        }
        
        // Сортировка по имени
        $query->orderBy('name', 'asc');
        
        $hashtags = $query->paginate($limit);
        
        Log::info('HashtagController::index - получение хештегов', [
            'name' => $name,
            'limit' => $limit,
            'count' => $hashtags->count(),
        ]);
        
        return response()->json($hashtags);
    }

    /**
     * Получить хештег по slug
     * 
     * @param Request $request
     * @param string $slug
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $slug)
    {
        $hashtag = Hashtag::where('slug', $slug)->firstOrFail();
        
        return response()->json($hashtag);
    }
}

