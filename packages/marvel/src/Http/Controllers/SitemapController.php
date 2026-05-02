<?php

namespace Marvel\Http\Controllers;

use Illuminate\Http\Response;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\SitemapIndex;
use Spatie\Sitemap\Tags\Url;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\Category;
use Marvel\Database\Models\Tag;
use Marvel\Database\Models\Place;
use Marvel\Database\Models\Hashtag;

class SitemapController extends CoreController
{
    public function index()
    {
        
        $baseUrl = 'https://sancan.ru';
        $sitemapDir = public_path('sitemaps');
        
        // Создаем директорию для sitemap если не существует
        if (!file_exists($sitemapDir)) {
            mkdir($sitemapDir, 0755, true);
        }

        // Получаем все опубликованные товары
        $products = Product::where('status', 'publish')
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->get();
            
        \Log::info('Sitemap: Found ' . $products->count() . ' published products');

        // Получаем категории
        $categories = Category::whereNotNull('slug')
            ->where('slug', '!=', '')
            ->get();
            
        \Log::info('Sitemap: Found ' . $categories->count() . ' categories');

        // Получаем теги
        $tags = Tag::all();
            
        \Log::info('Sitemap: Found ' . $tags->count() . ' tags (total in DB)');
        
        // Фильтруем теги с валидным slug
        $tagsWithSlug = $tags->filter(function($tag) {
            return !empty($tag->slug);
        });
        
        \Log::info('Sitemap: Found ' . $tagsWithSlug->count() . ' tags with valid slug');
        
        $tags = $tagsWithSlug;

        // Получаем все плейсы
        $places = Place::whereNotNull('id')->get();
            
        \Log::info('Sitemap: Places query', [
            'total_places' => $places->count(),
            'sample_ids' => $places->take(5)->pluck('id')->toArray(),
        ]);

        // Получаем все хештеги плейсов
        $hashtags = Hashtag::whereNotNull('slug')
            ->where('slug', '!=', '')
            ->get();
            
        \Log::info('Sitemap: Hashtags query', [
            'total_hashtags' => $hashtags->count(),
            'sample_slugs' => $hashtags->take(5)->pluck('slug')->toArray(),
        ]);

        // Создаем индекс sitemap
        $sitemapIndex = SitemapIndex::create();

        // Создаем sitemap для статических страниц и категорий
        $staticSitemap = Sitemap::create()
            ->add(Url::create($baseUrl . '/')
                ->setLastModificationDate(now())
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                ->setPriority(1.0))
            ->add(Url::create($baseUrl . '/about-us')
                ->setLastModificationDate(now())
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                ->setPriority(0.7))
            ->add(Url::create($baseUrl . '/contact-us')
                ->setLastModificationDate(now())
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                ->setPriority(0.7));

        // Добавляем категории в статический sitemap
        foreach ($categories as $category) {
            if (!empty($category->slug)) {
                $cleanSlug = $category->slug;
                if (strpos($cleanSlug, '/ru/') === 0) {
                    $cleanSlug = substr($cleanSlug, 4);
                }
                
                $staticSitemap->add(Url::create($baseUrl . '/categories/' . $cleanSlug)
                    ->setLastModificationDate($category->updated_at)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                    ->setPriority(0.8));
            }
        }

        // Сохраняем статический sitemap
        $staticSitemap->writeToFile($sitemapDir . '/sitemap-static.xml');
        $sitemapIndex->add($baseUrl . '/sitemaps/sitemap-static.xml');

        // Разбиваем теги по 5000 на файл (в начале, перед товарами)
        $tagsChunks = $tags->chunk(5000);
        $tagChunkIndex = 1;

        foreach ($tagsChunks as $chunk) {
            $tagSitemap = Sitemap::create();
            
            foreach ($chunk as $tag) {
                if (!empty($tag->slug)) {
                    $cleanSlug = $tag->slug;
                    if (strpos($cleanSlug, '/ru/') === 0) {
                        $cleanSlug = substr($cleanSlug, 4);
                    }
                    
                    $tagSitemap->add(Url::create($baseUrl . '/products/tags/' . $cleanSlug)
                        ->setLastModificationDate($tag->updated_at)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                        ->setPriority(0.7));
                }
            }
            
            $filename = "sitemap-tags-{$tagChunkIndex}.xml";
            $tagSitemap->writeToFile($sitemapDir . '/' . $filename);
            $sitemapIndex->add($baseUrl . '/sitemaps/' . $filename);
            
            \Log::info("Sitemap: Created {$filename} with " . $chunk->count() . " tags");
            $tagChunkIndex++;
        }

        // Разбиваем товары по 5000 на файл
        $productsChunks = $products->chunk(5000);
        $chunkIndex = 1;

        foreach ($productsChunks as $chunk) {
            $productSitemap = Sitemap::create();
            
            foreach ($chunk as $product) {
                if (!empty($product->slug)) {
                    $cleanSlug = $product->slug;
                    if (strpos($cleanSlug, '/ru/') === 0) {
                        $cleanSlug = substr($cleanSlug, 4);
                    }
                    
                    // Используем формат /element/{slug}-{id} для товаров
                    $productSitemap->add(Url::create($baseUrl . '/element/' . $cleanSlug . '-' . $product->id)
                        ->setLastModificationDate($product->updated_at)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                        ->setPriority(0.9));
                }
            }
            
            $filename = "sitemap-products-{$chunkIndex}.xml";
            $productSitemap->writeToFile($sitemapDir . '/' . $filename);
            $sitemapIndex->add($baseUrl . '/sitemaps/' . $filename);
            
            \Log::info("Sitemap: Created {$filename} with " . $chunk->count() . " products");
            $chunkIndex++;
        }

        // Разбиваем плейсы по 5000 на файл
        $placeChunkIndex = 1;
        \Log::info('Sitemap: Processing places', ['count' => $places->count()]);
        if ($places->count() > 0) {
            $placesChunks = $places->chunk(5000);
            \Log::info('Sitemap: Places chunks', ['chunks_count' => $placesChunks->count()]);

            foreach ($placesChunks as $chunk) {
                $placeSitemap = Sitemap::create();
                $urlsAdded = 0;
                
                foreach ($chunk as $place) {
                    if (!empty($place->id)) {
                        $placeSitemap->add(Url::create($baseUrl . '/places/' . $place->id)
                            ->setLastModificationDate($place->updated_at ?? now())
                            ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                            ->setPriority(0.8));
                        $urlsAdded++;
                    }
                }
                
                // Создаем файл только если есть URL
                if ($urlsAdded > 0) {
                    $filename = "sitemap-places-{$placeChunkIndex}.xml";
                    $filePath = $sitemapDir . '/' . $filename;
                    $placeSitemap->writeToFile($filePath);
                    $sitemapUrl = $baseUrl . '/sitemaps/' . $filename;
                    $sitemapIndex->add($sitemapUrl);
                    
                    \Log::info("Sitemap: Created {$filename} with {$urlsAdded} places", [
                        'file_path' => $filePath,
                        'file_exists' => file_exists($filePath),
                        'sitemap_url' => $sitemapUrl,
                    ]);
                    $placeChunkIndex++;
                } else {
                    \Log::warning("Sitemap: Skipping places chunk {$placeChunkIndex} - no URLs added");
                }
            }
        } else {
            \Log::info('Sitemap: No places found, skipping places sitemap');
        }

        // Разбиваем хештеги плейсов по 5000 на файл
        $hashtagChunkIndex = 1;
        \Log::info('Sitemap: Processing hashtags', ['count' => $hashtags->count()]);
        if ($hashtags->count() > 0) {
            $hashtagsChunks = $hashtags->chunk(5000);
            \Log::info('Sitemap: Hashtags chunks', ['chunks_count' => $hashtagsChunks->count()]);

            foreach ($hashtagsChunks as $chunk) {
                $hashtagSitemap = Sitemap::create();
                $urlsAdded = 0;
                
                foreach ($chunk as $hashtag) {
                    if (!empty($hashtag->slug)) {
                        $cleanSlug = $hashtag->slug;
                        if (strpos($cleanSlug, '/ru/') === 0) {
                            $cleanSlug = substr($cleanSlug, 4);
                        }
                        
                        $hashtagSitemap->add(Url::create($baseUrl . '/places/element/' . $cleanSlug)
                            ->setLastModificationDate($hashtag->updated_at ?? now())
                            ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                            ->setPriority(0.7));
                        $urlsAdded++;
                    }
                }
                
                // Создаем файл только если есть URL
                if ($urlsAdded > 0) {
                    $filename = "sitemap-hashtags-{$hashtagChunkIndex}.xml";
                    $filePath = $sitemapDir . '/' . $filename;
                    $hashtagSitemap->writeToFile($filePath);
                    $sitemapUrl = $baseUrl . '/sitemaps/' . $filename;
                    $sitemapIndex->add($sitemapUrl);
                    
                    \Log::info("Sitemap: Created {$filename} with {$urlsAdded} hashtags", [
                        'file_path' => $filePath,
                        'file_exists' => file_exists($filePath),
                        'sitemap_url' => $sitemapUrl,
                    ]);
                    $hashtagChunkIndex++;
                } else {
                    \Log::warning("Sitemap: Skipping hashtags chunk {$hashtagChunkIndex} - no URLs added");
                }
            }
        } else {
            \Log::info('Sitemap: No hashtags found, skipping hashtags sitemap');
        }

        // Сохраняем индекс
        $indexPath = public_path('sitemap.xml');
        $totalFiles = 1 + ($tagChunkIndex - 1) + ($chunkIndex - 1) + ($placeChunkIndex - 1) + ($hashtagChunkIndex - 1);
        
        \Log::info('Sitemap: Writing index', [
            'index_path' => $indexPath,
            'place_chunks' => ($placeChunkIndex - 1),
            'hashtag_chunks' => ($hashtagChunkIndex - 1),
            'total_files_expected' => $totalFiles,
        ]);
        
        $sitemapIndex->writeToFile($indexPath);
        
        // Проверяем содержимое индекса после записи
        $indexContent = file_get_contents($indexPath);
        $placesInIndex = substr_count($indexContent, 'sitemap-places-');
        $hashtagsInIndex = substr_count($indexContent, 'sitemap-hashtags-');
        
        \Log::info('Sitemap: Created index with ' . $totalFiles . ' files', [
            'static' => 1,
            'tags' => ($tagChunkIndex - 1),
            'products' => ($chunkIndex - 1),
            'places' => ($placeChunkIndex - 1),
            'hashtags' => ($hashtagChunkIndex - 1),
            'total_places' => $places->count(),
            'total_hashtags' => $hashtags->count(),
            'index_file_exists' => file_exists($indexPath),
            'places_in_index_xml' => $placesInIndex,
            'hashtags_in_index_xml' => $hashtagsInIndex,
        ]);

        // Возвращаем индекс
        return response(file_get_contents(public_path('sitemap.xml')), 200)
            ->header('Content-Type', 'application/xml');
    }
} 