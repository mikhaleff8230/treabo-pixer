<?php

namespace Marvel\Events;

use Marvel\Database\Models\User;

class UserRegistered
{
    public $user;
    public $permission;

    /**
     * Create a new event instance.
     *
     * @param User $user
     * @param string $permission - customer, store_owner, etc.
     */
    public function __construct(User $user, string $permission)
    {
        $this->user = $user;
        $this->permission = $permission;
    }
}
