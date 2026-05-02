<?php

namespace App\Services\DigitalProducts\Handlers;

use App\Services\DigitalProducts\Contracts\DigitalProductHandlerInterface;
use Illuminate\Support\Str;
use Marvel\Database\Models\DownloadToken;
use Marvel\Database\Models\Product;
use Marvel\Exceptions\MarvelException;

class FileProductHandler implements DigitalProductHandlerInterface
{
    public function handle(Product $product, int $userId): array
    {
        if (!(bool) $product->is_digital || !$product->digital_file) {
            throw new MarvelException(NOT_FOUND);
        }

        $token = DownloadToken::create([
            'user_id' => $userId,
            'token' => Str::random(16),
            'digital_file_id' => $product->digital_file->id,
        ]);

        return [
            'type' => 'file',
            'payload' => [
                'download_url' => route('download_url.token', ['token' => $token->token]),
            ],
        ];
    }
}
