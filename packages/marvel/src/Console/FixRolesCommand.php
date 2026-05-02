<?php

namespace Marvel\Console;

use Illuminate\Console\Command;
use Marvel\Database\Models\User;
use Marvel\Database\Seeders\RoleSeeder;
use Spatie\Permission\Models\Role;
use Marvel\Enums\Permission as UserPermission;

class FixRolesCommand extends Command
{
    protected $signature = 'marvel:fix-roles {--assign-customer : Назначить роль customer всем пользователям без ролей}';

    protected $description = 'Исправить роли в системе и назначить роль customer обычным пользователям';

    public function handle()
    {
        $this->info('Начинаем исправление ролей...');

        try {
            // Запускаем сидер ролей
            $this->info('Создаем стандартные роли...');
            $seeder = new RoleSeeder();
            $seeder->run();

            // Проверяем, нужно ли назначить роль customer
            if ($this->option('assign-customer')) {
                $this->assignCustomerRoleToUsers();
            }

            $this->info('Роли успешно исправлены!');
            $this->info('Теперь пользователи могут авторизоваться и получать доступ к защищенным маршрутам.');

        } catch (\Exception $e) {
            $this->error('Ошибка при исправлении ролей: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function assignCustomerRoleToUsers()
    {
        $this->info('Назначаем роль customer пользователям без ролей...');

        // Получаем роль customer
        $customerRole = Role::where('name', 'customer')->where('guard_name', 'api')->first();

        if (!$customerRole) {
            $this->error('Роль customer не найдена!');
            return;
        }

        // Находим пользователей без ролей
        $usersWithoutRoles = User::whereDoesntHave('roles')->get();

        if ($usersWithoutRoles->isEmpty()) {
            $this->info('Все пользователи уже имеют роли.');
            return;
        }

        $count = 0;
        foreach ($usersWithoutRoles as $user) {
            // Назначаем роль customer
            $user->assignRole($customerRole);
            
            // Также даем разрешение customer напрямую (для совместимости)
            $user->givePermissionTo(UserPermission::CUSTOMER);
            
            $count++;
        }

        $this->info("Роль customer назначена {$count} пользователям.");
    }
} 