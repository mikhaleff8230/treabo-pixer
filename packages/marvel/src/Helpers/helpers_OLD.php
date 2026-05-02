<?php

use Illuminate\Support\Str;
use Marvel\Database\Models\User;
use Marvel\Enums\Permission;

if (!function_exists('gateway_path')) {
    /**
     * Get the path to the base of the install.
     *
     * @param  string  $path
     * @return string
     */
    function gateway_path($path = '')
    {
        return __DIR__ . '/';
    }

    if (!function_exists('globalSlugify')) {

        /**
         * It takes a string, a model,  a key, and a divider, and returns a slugified string with a number
         * appended to it if the slug already exists in the database.
         * 
         * Here's a more detailed explanation:
         * 
         * The function takes three parameters:
         * 
         * - ``: The string to be slugified.
         * - ``: The model to check against. Model must pass as Product::class
         * - ``: The key to check The column name of the slug in the database.
         * - ``: The divider to use between the slug and the number.
         * 
         * The function first slugifies the string and then checks the database to see if the slug
         * already exists. If it doesn't, it returns the slug. If it does, it returns the slug with a
         * number appended to it.
         * 
         * Here's an example of how to use the function:
         * 
         * @param string slugText The text you want to slugify
         * @param string model The model you want to check against.
         * @param string key The column name of the slug in the database.
         * @param string divider The divider to use when appending the slug count to the slug.
         * 
         * @return string slug is being returned.
         */
        function globalSlugify(string $slugText, string $model, string $key = '', string $divider = '-'): string
        {
            try {
                // Транслитерация кириллицы в латиницу
                $transliterated = transliterateToLatin($slugText);
                
                $cleanString = preg_replace("/[~`{}.'\"\!\@\#\$\%\^\&\*\(\)\_\=\+\/\?\>\<\,\[\]\:\;\|\\\]/", "", $transliterated);
                $cleanString = preg_replace("/[\/_|+ -]+/", '-', $cleanString);
                $slug = strtolower($cleanString);
                $slug = trim($slug, '-');
                
                if ($key) {
                    $slugCount = $model::where($key, $slug)->orWhere($key, 'like',  $slug . '%')->count();
                } else {
                    $slugCount = $model::where('slug', $slug)->orWhere('slug', 'like',  $slug . '%')->count();
                }

                if (empty($slugCount)) {
                    return $slug;
                }
                // Генерируем уникальный 12-значный код (каждая цифра случайная, в полный разнобой)
                // Примеры: 012345678901, 987654321098, 000111222333
                $maxAttempts = 10;
                $attempt = 0;
                do {
                    // Генерируем 12 случайных цифр от 0 до 9
                    $randomCode = '';
                    for ($i = 0; $i < 12; $i++) {
                        $randomCode .= random_int(0, 9);
                    }
                    
                    // Проверяем уникальность slug с этим кодом
                    $fullSlug = "{$slug}{$divider}{$randomCode}";
                    $exists = $model::where('slug', $fullSlug)->exists();
                    $attempt++;
                } while ($exists && $attempt < $maxAttempts);
                
                return $fullSlug;
            } catch (\Throwable $th) {
                throw $th;
            }
        }
        
        // Функция транслитерации кириллицы в латиницу
        if (!function_exists('transliterateToLatin')) {
            function transliterateToLatin($string) {
                // Таблица транслитерации кириллицы в латиницу
                $translitTable = [
                    'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
                    'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
                    'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
                    'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
                    'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch',
                    'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
                    'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
                    'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D',
                    'Е' => 'E', 'Ё' => 'Yo', 'Ж' => 'Zh', 'З' => 'Z', 'И' => 'I',
                    'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N',
                    'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T',
                    'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'Ts', 'Ч' => 'Ch',
                    'Ш' => 'Sh', 'Щ' => 'Sch', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '',
                    'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya',
                ];
                
                // Пытаемся использовать PHP Intl если доступен
                if (extension_loaded('intl') && class_exists('Transliterator')) {
                    try {
                        $transliterator = \Transliterator::create('Any-Latin; Latin-ASCII; Lower()');
                        if ($transliterator) {
                            $transliterated = $transliterator->transliterate($string);
                            // Если транслитерация прошла успешно, возвращаем результат
                            if ($transliterated && $transliterated !== $string) {
                                return $transliterated;
                            }
                        }
                    } catch (\Exception $e) {
                        // Если Intl не работает, используем таблицу замены
                    }
                }
                
                // Используем таблицу замены
                return strtr($string, $translitTable);
            }
        }
    }

    if (!function_exists('server_environment_info')) {
        function server_environment_info()
        {
            return [
                "upload_max_filesize" => parseAttachmentUploadSize(ini_get('upload_max_filesize')) / 1024,
                "memory_limit" => ini_get('memory_limit'),
                "max_execution_time" => ini_get('max_execution_time'),
                "max_input_time" => ini_get('max_input_time'),
                "post_max_size" => parseAttachmentUploadSize(ini_get('post_max_size')) / 1024,
            ];
        }
    }

    if (!function_exists('parseAttachmentUploadSize')) {
        function parseAttachmentUploadSize($size)
        {
            $unit = preg_replace('/[^bkmgtpezy]/i', '', $size); // Remove the non-unit characters from the size.
            $size = preg_replace('/[^0-9\.]/', '', $size); // Remove the non-numeric characters from the size.
            if ($unit) {
                // Find the position of the unit in the ordered string which is the power of magnitude to multiply a kilobyte by.
                return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
            } else {
                return round($size);
            }
        }
    }

    if (!function_exists("Role")) {

        function Role(User $user): string
        {
            if ($user->hasPermissionTo(Permission::SUPER_ADMIN)) {
                return Permission::SUPER_ADMIN;
            } else if ($user->hasPermissionTo(Permission::STORE_OWNER) && !$user->hasPermissionTo(Permission::SUPER_ADMIN)) {
                return Permission::STORE_OWNER;
            } else if ($user->hasPermissionTo(Permission::STAFF)) {
                return Permission::STAFF;
            } else {
                return Permission::CUSTOMER;
            }
        }
    }
}
