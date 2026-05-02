<?php

namespace Marvel\Http\Queries;

use Illuminate\Database\Eloquent\Builder;
use Marvel\Database\Models\Place;

class PlaceSimilarQuery
{
    public static function query($placeId, array $params = []): Builder
    {
        $place = Place::with('hashtags')->findOrFail($placeId);
        $hashtagIds = $place->hashtags->pluck('id')->toArray();

        \Log::info('PlaceSimilarQuery::query', [
            'placeId' => $placeId,
            'hashtagIds' => $hashtagIds,
            'hashtagCount' => count($hashtagIds)
        ]);

        $query = Place::query()
            ->select(['id', 'title', 'slug', 'user_id', 'created_at'])
            ->where('id', '!=', $placeId);

        // ВРЕМЕННО: отключаем фильтрацию по хэштегам для тестирования
        \Log::info('PlaceSimilarQuery::query - temporarily disabling hashtag filtering for testing');
        // $query->whereHas('hashtags', function ($q) use ($hashtagIds) {
        //     $q->whereIn('id', $hashtagIds);
        // });

        $query->orderBy('created_at', 'desc')
              ->orderBy('id', 'desc');

        if (isset($params['cursor'])) {
            $cursor = self::parseCursor($params['cursor']);
            if ($cursor) {
                $query->where(function ($q) use ($cursor) {
                    $q->where('created_at', '<', $cursor['created_at'])
                      ->orWhere(function ($subQ) use ($cursor) {
                          $subQ->where('created_at', '=', $cursor['created_at'])
                               ->where('id', '<', $cursor['id']);
                      });
                });
            }
        }

        $limit = $params['limit'] ?? 24;
        $query->limit(min($limit, 100));

        $query->with([
            'images' => function ($q) {
                $q->select(['id', 'place_id', 'url'])->orderBy('id', 'asc');
            },
            'user' => function ($q) {
                $q->select(['id', 'name'])->with('profile:id,customer_id,avatar');
            },
            'hashtags' => function ($q) {
                $q->select(['hashtags.id', 'hashtags.name', 'hashtags.slug']);
            },
        ])->withCount(['likes', 'comments']);

        return $query;
    }

    private static function parseCursor(string $cursor): ?array
    {
        $parts = explode('|', $cursor);
        if (count($parts) !== 2) return null;

        $createdAt = $parts[0];
        $id = $parts[1];

        if (!strtotime($createdAt) || !is_numeric($id)) return null;

        return ['created_at' => $createdAt, 'id' => (int) $id];
    }

    public static function createCursor(Place $place): string
    {
        return $place->created_at->format('Y-m-d\TH:i:s.u\Z') . '|' . $place->id;
    }
}