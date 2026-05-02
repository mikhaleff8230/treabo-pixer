<?php

namespace Marvel\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Marvel\Enums\Permission as UserPermission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Очищаем кэш разрешений
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Создаем разрешения
        $permissions = [
            UserPermission::SUPER_ADMIN,
            UserPermission::STORE_OWNER,
            UserPermission::STAFF,
            UserPermission::CUSTOMER,
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'api']);
        }

        // Создаем роли
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

        // $this->command->info('Роли и разрешения успешно созданы!');
    }
} 