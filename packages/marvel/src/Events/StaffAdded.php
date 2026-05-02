<?php

namespace Marvel\Events;

use Marvel\Database\Models\Shop;
use Marvel\Database\Models\User;

class StaffAdded
{
    public $shop;
    public $staff;
    public $addedBy;

    /**
     * Create a new event instance.
     *
     * @param Shop $shop
     * @param User $staff
     * @param User $addedBy
     */
    public function __construct(Shop $shop, User $staff, User $addedBy)
    {
        $this->shop = $shop;
        $this->staff = $staff;
        $this->addedBy = $addedBy;
    }
}
