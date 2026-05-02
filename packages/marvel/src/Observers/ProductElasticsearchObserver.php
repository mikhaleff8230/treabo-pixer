<?php

namespace Marvel\Observers;

use Marvel\Services\ElasticsearchService;
use Marvel\Database\Models\Product;
use Illuminate\Support\Facades\Log;

class ProductElasticsearchObserver
{
    protected ElasticsearchService $elasticsearch;
    protected string $indexName;

    public function __construct(ElasticsearchService $elasticsearch)
    {
        $this->elasticsearch = $elasticsearch;
        $this->indexName = config('elasticsearch.indices.products.name');
    }

    /**
     * Handle the Product "created" event.
     */
    public function created(Product $product): void
    {
        if (!config('elasticsearch.enabled')) {
            return;
        }

        $this->indexProduct($product, 'created');
    }

    /**
     * Handle the Product "updated" event.
     */
    public function updated(Product $product): void
    {
        if (!config('elasticsearch.enabled')) {
            return;
        }

        $this->indexProduct($product, 'updated');
    }

    /**
     * Handle the Product "deleted" event.
     */
    public function deleted(Product $product): void
    {
        if (!config('elasticsearch.enabled')) {
            return;
        }

        try {
            $this->elasticsearch->deleteDocument($this->indexName, (string) $product->id);
            
            Log::info('Product deleted from Elasticsearch', [
                'product_id' => $product->id,
                'product_name' => $product->name
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete product from Elasticsearch', [
                'product_id' => $product->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle the Product "restored" event.
     */
    public function restored(Product $product): void
    {
        if (!config('elasticsearch.enabled')) {
            return;
        }

        $this->indexProduct($product, 'restored');
    }

    /**
     * Handle the Product "force deleted" event.
     */
    public function forceDeleted(Product $product): void
    {
        if (!config('elasticsearch.enabled')) {
            return;
        }

        $this->deleted($product);
    }

    /**
     * Index product to Elasticsearch
     */
    protected function indexProduct(Product $product, string $action): void
    {
        try {
            // Load relationships
            $product->load(['shop', 'categories', 'tags', 'author', 'manufacturer']);

            // Transform product to Elasticsearch document
            $document = $this->transformProduct($product);

            // Index document
            $this->elasticsearch->indexDocument(
                $this->indexName,
                (string) $product->id,
                $document
            );

            if (config('elasticsearch.logging.log_queries')) {
                Log::debug("Product {$action} in Elasticsearch", [
                    'product_id' => $product->id,
                    'product_name' => $product->name
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Failed to {$action} product in Elasticsearch", [
                'product_id' => $product->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Transform product to Elasticsearch document
     */
    protected function transformProduct(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name ?? '',
            'slug' => $product->slug ?? '',
            'description' => strip_tags($product->description ?? ''),
            'price' => (float) ($product->price ?? 0),
            'sale_price' => (float) ($product->sale_price ?? 0),
            'quantity' => (int) ($product->quantity ?? 0),
            'in_stock' => (bool) ($product->in_stock ?? false),
            'sku' => $product->sku ?? '',
            'status' => $product->status ?? 'draft',
            'product_type' => $product->product_type ?? 'simple',
            'language' => $product->language ?? 'ru',
            
            'shop' => $product->shop ? [
                'id' => $product->shop->id,
                'name' => $product->shop->name,
                'slug' => $product->shop->slug,
            ] : null,
            
            'categories' => $product->categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                ];
            })->toArray(),
            
            'tags' => $product->tags->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                ];
            })->toArray(),
            
            'author' => $product->author ? [
                'id' => $product->author->id,
                'name' => $product->author->name,
            ] : null,
            
            'manufacturer' => $product->manufacturer ? [
                'id' => $product->manufacturer->id,
                'name' => $product->manufacturer->name,
            ] : null,
            
            'ratings' => (float) ($product->ratings ?? 0),
            'total_reviews' => (int) ($product->total_reviews ?? 0),
            
            'created_at' => $product->created_at?->toIso8601String(),
            'updated_at' => $product->updated_at?->toIso8601String(),
        ];
    }
}

