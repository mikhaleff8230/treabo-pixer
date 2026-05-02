<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Marvel\Enums\Permission as UserPermission;

class EnsureRolesExist extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Очищаем кэш разрешений
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Создаем разрешения, если их нет
        $permissions = [
            UserPermission::SUPER_ADMIN,
            UserPermission::STORE_OWNER,
            UserPermission::STAFF,
            UserPermission::CUSTOMER,
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'api']);
        }

        // Создаем роли, если их нет
        $roles = [
            'customer' => [UserPermission::CUSTOMER],
            'staff' => [UserPermission::STAFF, UserPermission::CUSTOMER],
            'store_owner' => [UserPermission::STORE_OWNER, UserPermission::STAFF, UserPermission::CUSTOMER],
            'admin' => [UserPermission::STORE_OWNER, UserPermission::STAFF, UserPermission::CUSTOMER],
            'super_admin' => [UserPermission::SUPER_ADMIN, UserPermission::STORE_OWNER, UserPermission::STAFF, UserPermission::CUSTOMER],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'api']);
            $role->syncPermissions($rolePermissions);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Удаляем роли
        $roles = ['customer', 'staff', 'store_owner', 'admin', 'super_admin'];
        foreach ($roles as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'api')->first();
            if ($role) {
                $role->delete();
            }
        }

        // Удаляем разрешения
        $permissions = [
            UserPermission::SUPER_ADMIN,
            UserPermission::STORE_OWNER,
            UserPermission::STAFF,
            UserPermission::CUSTOMER,
        ];

        foreach ($permissions as $permission) {
            $perm = Permission::where('name', $permission)->where('guard_name', 'api')->first();
            if ($perm) {
                $perm->delete();
            }
        }
    }
} 