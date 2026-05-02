<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Marvel\Database\Models\User;
use Spatie\Permission\Models\Role;

class FixFilamentGuard extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'filament:fix-guard {email?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Исправить проблему с guard для Filament (создать роль для guard web и назначить пользователю)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        
        if (!$email) {
            $email = $this->ask('Введите email пользователя');
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("Пользователь с email {$email} не найден!");
            return 1;
        }

        $this->info("Найден пользователь: {$user->name} (ID: {$user->id})");

        // Создаем роль для guard 'web'
        try {
            $roleWeb = Role::firstOrCreate(
                ['name' => 'super_admin', 'guard_name' => 'web']
            );
            $this->info("✅ Роль super_admin для guard 'web' создана/найдена (ID: {$roleWeb->id})");
        } catch (\Exception $e) {
            $this->error("Ошибка при создании роли для guard 'web': " . $e->getMessage());
            return 1;
        }

        // Создаем роль для guard 'api' (для совместимости)
        try {
            $roleApi = Role::firstOrCreate(
                ['name' => 'super_admin', 'guard_name' => 'api']
            );
            $this->info("✅ Роль super_admin для guard 'api' создана/найдена (ID: {$roleApi->id})");
        } catch (\Exception $e) {
            $this->warn("Предупреждение при создании роли для guard 'api': " . $e->getMessage());
        }

        // Назначаем роли пользователю
        try {
            // Для guard 'web'
            if (!$user->hasRole($roleWeb)) {
                $user->roles()->syncWithoutDetaching([$roleWeb->id]);
                $this->info("✅ Роль super_admin для guard 'web' назначена пользователю");
            } else {
                $this->info("ℹ️  Роль super_admin для guard 'web' уже назначена пользователю");
            }

            // Для guard 'api'
            if (!$user->hasRole($roleApi)) {
                $user->roles()->syncWithoutDetaching([$roleApi->id]);
                $this->info("✅ Роль super_admin для guard 'api' назначена пользователю");
            } else {
                $this->info("ℹ️  Роль super_admin для guard 'api' уже назначена пользователю");
            }

            $this->newLine();
            $this->info("✅ Проблема с guard исправлена!");
            $this->info("Теперь попробуйте войти в Filament:");
            $this->info("   URL: https://api.sancan.ru/admin/login");
            $this->info("   Email: {$user->email}");

            return 0;
        } catch (\Exception $e) {
            $this->error("Ошибка при назначении роли: " . $e->getMessage());
            $this->error("Трассировка: " . $e->getTraceAsString());
            return 1;
        }
    }
}

