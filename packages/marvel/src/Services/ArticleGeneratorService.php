<?php

namespace Marvel\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ArticleGeneratorService
{
    /**
     * Минимальное значение для артикула Product (10 знаков)
     */
    const PRODUCT_MIN_VALUE = 1578604838;

    /**
     * Минимальное значение для артикула SKU (10 знаков)
     */
    const SKU_MIN_VALUE = 1000000000;

    /**
     * Максимальное значение для артикула SKU (10 знаков)
     */
    const SKU_MAX_VALUE = 9999999999;

    /**
     * Префикс для артикулов SKU
     */
    const SKU_PREFIX = 'SKU';

    /**
     * Минимальное значение для Seller ID (7 знаков)
     */
    const SELLER_ID_MIN_VALUE = 1486501;

    /**
     * Максимальное значение для Seller ID (7 знаков)
     */
    const SELLER_ID_MAX_VALUE = 9999999;

    /**
     * Генерирует артикул для обычного товара (Product)
     * Формат: уникальный 10-значный числовой код (начиная с 1578604838)
     *
     * @return string
     */
    public static function generateProductArticle(): string
    {
        try {
            // Получаем максимальный существующий артикул для products
            // Ищем только 10-значные числовые артикулы (не меньше минимального значения)
            $maxArticle = DB::table('products')
                ->whereNotNull('internal_article')
                ->where('internal_article', 'REGEXP', '^[0-9]{10}$') // Только 10-значные числовые артикулы
                ->whereRaw('CAST(internal_article AS UNSIGNED) >= ?', [self::PRODUCT_MIN_VALUE])
                ->selectRaw('CAST(internal_article AS UNSIGNED) as article_num')
                ->orderBy('article_num', 'desc')
                ->value('article_num');

            // Начинаем с минимального значения, если артикулов еще нет или все артикулы меньше минимума
            $nextNumber = $maxArticle && $maxArticle >= self::PRODUCT_MIN_VALUE 
                ? $maxArticle + 1 
                : self::PRODUCT_MIN_VALUE;

            // Проверяем, что артикул не превышает максимум для 10-значных чисел
            if ($nextNumber > 9999999999) {
                Log::error('ArticleGeneratorService::generateProductArticle - Maximum value reached', [
                    'max_value' => 9999999999,
                    'next_number' => $nextNumber,
                ]);
                // В случае переполнения используем timestamp-based артикул (последние 10 цифр)
                $nextNumber = (int)substr(time(), -10);
                // Убеждаемся, что не меньше минимума
                if ($nextNumber < self::PRODUCT_MIN_VALUE) {
                    $nextNumber = self::PRODUCT_MIN_VALUE;
                }
            }

            // Проверяем уникальность
            while (DB::table('products')->where('internal_article', (string)$nextNumber)->exists()) {
                $nextNumber++;
                // Если превысили максимум, начинаем с минимума (но это маловероятно)
                if ($nextNumber > 9999999999) {
                    Log::error('ArticleGeneratorService::generateProductArticle - Wrapping around', [
                        'next_number' => $nextNumber,
                    ]);
                    $nextNumber = self::PRODUCT_MIN_VALUE;
                }
            }

            // Убеждаемся, что возвращаем 10-значное число (дополняем нулями слева, если нужно)
            return str_pad((string)$nextNumber, 10, '0', STR_PAD_LEFT);
        } catch (\Exception $e) {
            Log::error('ArticleGeneratorService::generateProductArticle - Error', [
                'error' => $e->getMessage(),
            ]);
            // В случае ошибки возвращаем минимальное значение
            return (string)self::PRODUCT_MIN_VALUE;
        }
    }

    /**
     * Генерирует артикул для SKU (вариативного товара)
     * Формат: "SKU 1578604838" (10 значный номер, начиная с 1000000000)
     *
     * @return string
     */
    public static function generateSkuArticle(): string
    {
        try {
            // Получаем максимальный существующий артикул для product_skus
            $maxArticle = DB::table('product_skus')
                ->whereNotNull('internal_article')
                ->where('internal_article', 'LIKE', self::SKU_PREFIX . ' %')
                ->selectRaw('CAST(SUBSTRING(internal_article, 5) AS UNSIGNED) as article_num')
                ->orderBy('article_num', 'desc')
                ->value('article_num');

            // Начинаем с минимального значения, если артикулов еще нет
            $nextNumber = $maxArticle ? $maxArticle + 1 : self::SKU_MIN_VALUE;

            // Проверяем, что не превысили максимум
            if ($nextNumber > self::SKU_MAX_VALUE) {
                Log::error('ArticleGeneratorService::generateSkuArticle - Maximum value reached', [
                    'max_value' => self::SKU_MAX_VALUE,
                ]);
                // В случае переполнения используем timestamp-based артикул
                $nextNumber = (int)substr(time(), -10);
            }

            // Проверяем уникальность
            $article = self::SKU_PREFIX . ' ' . $nextNumber;
            while (DB::table('product_skus')->where('internal_article', $article)->exists()) {
                $nextNumber++;
                if ($nextNumber > self::SKU_MAX_VALUE) {
                    $nextNumber = (int)substr(time(), -10);
                }
                $article = self::SKU_PREFIX . ' ' . $nextNumber;
            }

            return $article;
        } catch (\Exception $e) {
            Log::error('ArticleGeneratorService::generateSkuArticle - Error', [
                'error' => $e->getMessage(),
            ]);
            // В случае ошибки возвращаем timestamp-based артикул
            return self::SKU_PREFIX . ' ' . substr(time(), -10);
        }
    }

    /**
     * Проверяет, является ли артикул артикулом SKU
     *
     * @param string $article
     * @return bool
     */
    public static function isSkuArticle(string $article): bool
    {
        return strpos($article, self::SKU_PREFIX) === 0;
    }

    /**
     * Извлекает числовую часть из артикула SKU
     *
     * @param string $article
     * @return int|null
     */
    public static function extractSkuNumber(string $article): ?int
    {
        if (!self::isSkuArticle($article)) {
            return null;
        }

        $number = (int)substr($article, strlen(self::SKU_PREFIX) + 1);
        return $number > 0 ? $number : null;
    }

    /**
     * Генерирует уникальный Seller ID для пользователя
     * Формат: уникальный 7-значный числовой код (начиная с 1486501)
     *
     * @return string
     */
    public static function generateSellerId(): string
    {
        try {
            // Получаем максимальный существующий seller_id для user_profiles
            // Ищем только 7-значные числовые seller_id (не меньше минимального значения)
            $maxSellerId = DB::table('user_profiles')
                ->whereNotNull('seller_id')
                ->where('seller_id', 'REGEXP', '^[0-9]{7}$') // Только 7-значные числовые коды
                ->whereRaw('CAST(seller_id AS UNSIGNED) >= ?', [self::SELLER_ID_MIN_VALUE])
                ->selectRaw('CAST(seller_id AS UNSIGNED) as seller_id_num')
                ->orderBy('seller_id_num', 'desc')
                ->value('seller_id_num');

            // Начинаем с минимального значения, если seller_id еще нет или все seller_id меньше минимума
            $nextNumber = $maxSellerId && $maxSellerId >= self::SELLER_ID_MIN_VALUE 
                ? $maxSellerId + 1 
                : self::SELLER_ID_MIN_VALUE;

            // Проверяем, что seller_id не превышает максимум для 7-значных чисел
            if ($nextNumber > self::SELLER_ID_MAX_VALUE) {
                Log::error('ArticleGeneratorService::generateSellerId - Maximum value reached', [
                    'max_value' => self::SELLER_ID_MAX_VALUE,
                    'next_number' => $nextNumber,
                ]);
                // В случае переполнения используем timestamp-based код (последние 7 цифр)
                $nextNumber = (int)substr(time(), -7);
                // Убеждаемся, что не меньше минимума
                if ($nextNumber < self::SELLER_ID_MIN_VALUE) {
                    $nextNumber = self::SELLER_ID_MIN_VALUE;
                }
            }

            // Проверяем уникальность
            while (DB::table('user_profiles')->where('seller_id', (string)$nextNumber)->exists()) {
                $nextNumber++;
                // Если превысили максимум, начинаем с минимума (но это маловероятно)
                if ($nextNumber > self::SELLER_ID_MAX_VALUE) {
                    Log::error('ArticleGeneratorService::generateSellerId - Wrapping around', [
                        'next_number' => $nextNumber,
                    ]);
                    $nextNumber = self::SELLER_ID_MIN_VALUE;
                }
            }

            // Убеждаемся, что возвращаем 7-значное число (дополняем нулями слева, если нужно)
            return str_pad((string)$nextNumber, 7, '0', STR_PAD_LEFT);
        } catch (\Exception $e) {
            Log::error('ArticleGeneratorService::generateSellerId - Error', [
                'error' => $e->getMessage(),
            ]);
            // В случае ошибки возвращаем минимальное значение
            return (string)self::SELLER_ID_MIN_VALUE;
        }
    }
}

