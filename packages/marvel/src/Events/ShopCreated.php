<?php

namespace Marvel\Events;

use Marvel\Database\Models\Shop;

class ShopCreated
{
    public $shop;

    /**
     * Create a new event instance.
     *
     * @param Shop $shop
     */
    public function __construct(Shop $shop)
    {
        $this->shop = $shop;
    }
}
