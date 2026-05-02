<?php

namespace Marvel\Events;

use Marvel\Database\Models\Product;

class ProductCreated
{
    public $product;

    /**
     * Create a new event instance.
     *
     * @param Product $product
     */
    public function __construct(Product $product)
    {
        $this->product = $product;
    }
}
