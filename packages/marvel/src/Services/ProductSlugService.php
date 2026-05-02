<?php

namespace Marvel\Services;

use Marvel\Database\Models\Product;
use Illuminate\Support\Facades\Log;

/**
 * Сервис для генерации slug и 12-значного кода для товаров
 * Единая точка генерации - используется везде для стабильности
 */
class ProductSlugService
{
    /**
     * Генерирует базовый slug из текста (название товара)
     * 
     * @param string $text Текст для генерации slug
     * @return string Базовый slug (без кода)
     */
    public static function generateBaseSlug(string $text): string
    {
        // Используем глобальную функцию транслитерации
        if (function_exists('transliterateToLatin')) {
            $baseSlug = transliterateToLatin($text);
        } else {
            $baseSlug = $text;
        }
        
        // Очистка от спецсимволов
        $baseSlug = preg_replace("/[~`{}.'\"\!\@\#\$\%\^\&\*\(\)\_\=\+\/\?\>\<\,\[\]\:\;\|\\\]/", "", $baseSlug);
        $baseSlug = preg_replace("/[\/_|+ -]+/", '-', $baseSlug);
        $baseSlug = strtolower($baseSlug);
        $baseSlug = trim($baseSlug, '-');
        
        return $baseSlug;
    }

    /**
     * Генерирует уникальный 12-значный код
     * 
     * @param string $baseSlug Базовый slug
     * @param int|null $excludeProductId ID товара для исключения из проверки (при обновлении)
     * @return string 12-значный код
     */
    public static function generateNumericCode(string $baseSlug, ?int $excludeProductId = null): string
    {
        $maxAttempts = 10;
        $attempt = 0;
        $numericCode = null;
        
        do {
            // Генерируем 12 случайных цифр от 0 до 9
            $randomCode = '';
            for ($i = 0; $i < 12; $i++) {
                $randomCode .= random_int(0, 9);
            }
            
            // Проверяем уникальность полного slug с этим кодом
            $fullSlug = "{$baseSlug}-{$randomCode}";
            $query = Product::where('slug', $fullSlug)
                ->orWhereRaw("CONCAT(slug, '-', slug_numeric_code) = ?", [$fullSlug]);
            
            if ($excludeProductId) {
                $query->where('id', '!=', $excludeProductId);
            }
            
            $exists = $query->exists();
            
            if (!$exists) {
                $numericCode = $randomCode;
                break;
            }
            
            $attempt++;
        } while ($attempt < $maxAttempts);
        
        // Если не смогли сгенерировать уникальный код, используем timestamp
        if (!$numericCode) {
            $timestamp = time();
            $numericCode = substr($timestamp . str_pad('', 12 - strlen($timestamp), '0'), 0, 12);
            
            Log::warning('ProductSlugService::generateNumericCode - Used timestamp fallback', [
                'base_slug' => $baseSlug,
                'code' => $numericCode,
            ]);
        }
        
        return $numericCode;
    }

    /**
     * Генерирует slug и код для нового товара
     * 
     * @param string $name Название товара
     * @param string|null $customSlug Кастомный slug (если передан пользователем)
     * @return array ['slug' => string, 'slug_numeric_code' => string]
     */
    public static function generateForNewProduct(string $name, ?string $customSlug = null): array
    {
        // Генерируем базовый slug
        $slugText = $customSlug ?: $name;
        $baseSlug = self::generateBaseSlug($slugText);
        
        // Всегда генерируем 12-значный код для нового товара
        $numericCode = self::generateNumericCode($baseSlug);
        
        Log::info('ProductSlugService::generateForNewProduct', [
            'name' => $name,
            'custom_slug' => $customSlug,
            'base_slug' => $baseSlug,
            'numeric_code' => $numericCode,
            'full_slug' => "{$baseSlug}-{$numericCode}",
        ]);
        
        return [
            'slug' => $baseSlug,
            'slug_numeric_code' => $numericCode,
        ];
    }

    /**
     * Обновляет slug для существующего товара с сохранением кода
     * 
     * @param Product $product Существующий товар
     * @param string $newSlugText Новый slug (базовая часть, без кода)
     * @return array ['slug' => string, 'slug_numeric_code' => string]
     */
    public static function updateSlugForProduct(Product $product, string $newSlugText): array
    {
        // Генерируем базовый slug из нового текста
        $baseSlug = self::generateBaseSlug($newSlugText);
        
        // Получаем существующий код из БД
        $existingCode = $product->slug_numeric_code;
        
        // Если код не сохранен в БД, но есть в slug - извлекаем и сохраняем
        if (empty($existingCode) && !empty($product->slug)) {
            $slugParsed = Product::parseSlugId($product->slug);
            if (isset($slugParsed['code']) && preg_match('/^\d{12}$/', $slugParsed['code'])) {
                $existingCode = $slugParsed['code'];
                // Сохраняем код в БД
                $product->slug_numeric_code = $existingCode;
                // Убираем код из slug, оставляем только базовую часть
                $product->slug = preg_replace('/-\d{12}$/', '', $product->slug);
                $product->save();
            }
        }
        
        // Если кода нет - генерируем новый (для старых товаров)
        if (empty($existingCode) || !preg_match('/^\d{12}$/', $existingCode)) {
            $existingCode = self::generateNumericCode($baseSlug, $product->id);
            
            Log::info('ProductSlugService::updateSlugForProduct - Generated new code for old product', [
                'product_id' => $product->id,
                'new_code' => $existingCode,
            ]);
        }
        
        // Проверяем уникальность полного slug с существующим кодом
        $fullSlug = "{$baseSlug}-{$existingCode}";
        $exists = Product::where('slug', $fullSlug)
            ->orWhereRaw("CONCAT(slug, '-', slug_numeric_code) = ?", [$fullSlug])
            ->where('id', '!=', $product->id)
            ->exists();
        
        // Если slug с этим кодом уже существует, генерируем новый код
        if ($exists) {
            $existingCode = self::generateNumericCode($baseSlug, $product->id);
            
            Log::warning('ProductSlugService::updateSlugForProduct - Code conflict, generated new code', [
                'product_id' => $product->id,
                'base_slug' => $baseSlug,
                'old_code' => $product->slug_numeric_code,
                'new_code' => $existingCode,
            ]);
        }
        
        Log::info('ProductSlugService::updateSlugForProduct', [
            'product_id' => $product->id,
            'new_slug_text' => $newSlugText,
            'base_slug' => $baseSlug,
            'numeric_code' => $existingCode,
            'full_slug' => "{$baseSlug}-{$existingCode}",
        ]);
        
        return [
            'slug' => $baseSlug,
            'slug_numeric_code' => $existingCode,
        ];
    }

    /**
     * Генерирует slug из названия для существующего товара (когда slug пустой)
     * Сохраняет существующий код из БД
     * 
     * @param Product $product Существующий товар
     * @param string $name Новое название товара
     * @return array ['slug' => string, 'slug_numeric_code' => string]
     */
    public static function generateSlugFromName(Product $product, string $name): array
    {
        // Генерируем базовый slug из названия
        $baseSlug = self::generateBaseSlug($name);
        
        // Получаем существующий код из БД
        $existingCode = $product->slug_numeric_code;
        
        // Если код не сохранен в БД, но есть в slug - извлекаем и сохраняем
        if (empty($existingCode) && !empty($product->slug)) {
            $slugParsed = Product::parseSlugId($product->slug);
            if (isset($slugParsed['code']) && preg_match('/^\d{12}$/', $slugParsed['code'])) {
                $existingCode = $slugParsed['code'];
            }
        }
        
        // Если кода нет - генерируем новый
        if (empty($existingCode) || !preg_match('/^\d{12}$/', $existingCode)) {
            $existingCode = self::generateNumericCode($baseSlug, $product->id);
        }
        
        // Проверяем уникальность
        $fullSlug = "{$baseSlug}-{$existingCode}";
        $exists = Product::where('slug', $fullSlug)
            ->orWhereRaw("CONCAT(slug, '-', slug_numeric_code) = ?", [$fullSlug])
            ->where('id', '!=', $product->id)
            ->exists();
        
        if ($exists) {
            $existingCode = self::generateNumericCode($baseSlug, $product->id);
        }
        
        Log::info('ProductSlugService::generateSlugFromName', [
            'product_id' => $product->id,
            'name' => $name,
            'base_slug' => $baseSlug,
            'numeric_code' => $existingCode,
            'full_slug' => "{$baseSlug}-{$existingCode}",
        ]);
        
        return [
            'slug' => $baseSlug,
            'slug_numeric_code' => $existingCode,
        ];
    }
}

