<?php

namespace Marvel\Policies;

use Marvel\Database\Models\User;
use Marvel\Database\Models\Place;

class PlacePolicy
{
    public function update(User $user, Place $place)
    {
        return $user->id === $place->user_id;
    }

    public function delete(User $user, Place $place)
    {
        // Разрешить удаление владельцу плейса и super_admin
        return $user->id === $place->user_id || $user->hasPermissionTo('super_admin');
    }
} 