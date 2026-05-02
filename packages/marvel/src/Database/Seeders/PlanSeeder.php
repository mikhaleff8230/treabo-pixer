<?php

namespace Marvel\Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Plan;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'price' => 0,
                'limit_products' => 20,
                'limit_playlists' => 3,
                'extra_product_price' => null,
                'extra_playlist_price' => null,
                'link_ozon_wb' => false,
                'utm_tracking' => null,
                'chat_enabled' => false,
                'featured_collections' => false,
            ],
            [
                'name' => 'Standard',
                'price' => 199,
                'limit_products' => 200,
                'limit_playlists' => 20,
                'extra_product_price' => 0.5,
                'extra_playlist_price' => 2,
                'link_ozon_wb' => false,
                'utm_tracking' => null,
                'chat_enabled' => false,
                'featured_collections' => false,
            ],
            [
                'name' => 'Pro',
                'price' => 299,
                'limit_products' => 300,
                'limit_playlists' => 50,
                'extra_product_price' => 0.5,
                'extra_playlist_price' => 1,
                'link_ozon_wb' => true,
                'utm_tracking' => true,
                'chat_enabled' => true,
                'featured_collections' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['name' => $plan['name']],
                $plan
            );
        }
    }
}

