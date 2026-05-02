<?php


namespace Marvel\GraphQL\Queries;

use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Marvel\Facades\Shop;

class CardQuery
{
    public function fetchCards($rootValue, array $args, GraphQLContext $context)
    {
        // Add payment_gateway to args if not provided, default to 'stripe'
        if (!isset($args['payment_gateway'])) {
            $args['payment_gateway'] = 'stripe';
        }
        return Shop::call('Marvel\Http\Controllers\PaymentMethodController@index', $args);
    }
}
