<?php

namespace Marvel\Observers;

use Marvel\Services\ElasticsearchService;
use Marvel\Database\Models\Place;
use Illuminate\Support\Facades\Log;

class PlaceElasticsearchObserver
{
    protected ElasticsearchService $elasticsearch;
    protected string $indexName;

    public function __construct(ElasticsearchService $elasticsearch)
    {
        $this->elasticsearch = $elasticsearch;
        $this->indexName = config('elasticsearch.indices.places.name');
    }

    /**
     * Handle the Place "created" event.
     */
    public function created(Place $place): void
    {
        if (!config('elasticsearch.enabled')) {
            return;
        }

        $this->indexPlace($place, 'created');
    }

    /**
     * Handle the Place "updated" event.
     */
    public function updated(Place $place): void
    {
        if (!config('elasticsearch.enabled')) {
            return;
        }

        $this->indexPlace($place, 'updated');
    }

    /**
     * Handle the Place "deleted" event.
     */
    public function deleted(Place $place): void
    {
        if (!config('elasticsearch.enabled')) {
            return;
        }

        try {
            $this->elasticsearch->deleteDocument($this->indexName, (string) $place->id);
            
            Log::info('Place deleted from Elasticsearch', [
                'place_id' => $place->id,
                'place_title' => $place->title
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete place from Elasticsearch', [
                'place_id' => $place->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle the Place "restored" event.
     */
    public function restored(Place $place): void
    {
        if (!config('elasticsearch.enabled')) {
            return;
        }

        $this->indexPlace($place, 'restored');
    }

    /**
     * Handle the Place "force deleted" event.
     */
    public function forceDeleted(Place $place): void
    {
        if (!config('elasticsearch.enabled')) {
            return;
        }

        $this->deleted($place);
    }

    /**
     * Index place to Elasticsearch
     */
    protected function indexPlace(Place $place, string $action): void
    {
        try {
            // Load relationships
            $place->load(['user', 'hashtags', 'products', 'images', 'videos', 'likes', 'followers']);

            // Transform place to Elasticsearch document
            $document = $this->transformPlace($place);

            // Index document
            $this->elasticsearch->indexDocument(
                $this->indexName,
                (string) $place->id,
                $document
            );

            if (config('elasticsearch.logging.log_queries')) {
                Log::debug("Place {$action} in Elasticsearch", [
                    'place_id' => $place->id,
                    'place_title' => $place->title
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Failed to {$action} place in Elasticsearch", [
                'place_id' => $place->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Transform place to Elasticsearch document
     */
    protected function transformPlace(Place $place): array
    {
        return [
            'id' => $place->id,
            'title' => $place->title ?? '',
            'description' => strip_tags($place->description ?? ''),
            
            'user' => $place->user ? [
                'id' => $place->user->id,
                'name' => $place->user->name,
            ] : null,
            
            'hashtags' => $place->hashtags->map(function ($hashtag) {
                return [
                    'id' => $hashtag->id,
                    'name' => $hashtag->name,
                ];
            })->toArray(),
            
            'products' => $place->products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                ];
            })->toArray(),
            
            'images_count' => $place->images->count(),
            'videos_count' => $place->videos->count(),
            'likes_count' => $place->likes->count(),
            'followers_count' => $place->followers->count(),
            
            'created_at' => $place->created_at?->toIso8601String(),
            'updated_at' => $place->updated_at?->toIso8601String(),
        ];
    }
}

