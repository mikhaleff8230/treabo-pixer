<?php

namespace App\Services;

use App\Services\DigitalProducts\Contracts\DigitalProductHandlerInterface;
use App\Services\DigitalProducts\Handlers\AccountProductHandler;
use App\Services\DigitalProducts\Handlers\FileProductHandler;
use App\Services\DigitalProducts\Handlers\KeyProductHandler;
use App\Services\DigitalProducts\Handlers\LinkProductHandler;
use App\Services\DigitalProducts\Handlers\PromptProductHandler;
use App\Services\DigitalProducts\Handlers\SubscriptionProductHandler;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\User;

class DigitalProductAccessService
{
    /** @var array<string, class-string<DigitalProductHandlerInterface>> */
    protected array $handlers = [
        'file' => FileProductHandler::class,
        'prompt' => PromptProductHandler::class,
        'link' => LinkProductHandler::class,
        'account' => AccountProductHandler::class,
        'key' => KeyProductHandler::class,
        'subscription' => SubscriptionProductHandler::class,
    ];

    public function __construct(
        protected DigitalAccessGrantService $grantService
    ) {
    }

    public function getAccess(User $user, Product $product, ?string $trackingNumber, bool $verifyGrant = true): JsonResponse
    {
        if ($verifyGrant) {
            $grant = $this->grantService->grant($user->id, $product->id, $trackingNumber);
            if (!$grant['granted']) {
                throw new AuthorizationException(NOT_AUTHORIZED);
            }
        }

        return $this->renderAccess($user, $product);
    }

    public function renderAccess(User $user, Product $product): JsonResponse
    {
        $type = $product->digital_product_type ?? 'file';
        if (!isset($this->handlers[$type])) {
            $type = 'file';
        }

        $handler = app($this->handlers[$type]);
        $result = $handler->handle($product, $user->id);

        return response()->json([
            'product_id' => $product->id,
            'type' => $result['type'],
            'payload' => $result['payload'],
        ]);
    }
}
