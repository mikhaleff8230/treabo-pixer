<?php

namespace App\Console\Commands;

/**
 * Creates a Marvel super_admin for the Next.js admin (seller.treabo.md), not Filament.
 */
class CreateTreaboAdmin extends CreateFilamentAdmin
{
    protected $signature = 'treabo:create-admin
                            {--name= : Admin display name}
                            {--email= : Login email}
                            {--password= : Login password}
                            {--use-existing : Update existing user with this email}';

    protected $description = 'Create super_admin for Treabo Next.js admin (seller.treabo.md/login)';

    public function handle()
    {
        $code = parent::handle();

        if ($code === 0) {
            $dashboard = rtrim(config('shop.dashboard_url', 'https://seller.treabo.md'), '/');
            $this->newLine();
            $this->info('Next.js admin login:');
            $this->info("   {$dashboard}/login");
            $this->info('API auth endpoint (POST only, not a browser page):');
            $this->info('   ' . rtrim(config('app.url', 'https://api.treabo.md'), '/') . '/token');
        }

        return $code;
    }
}
