<?php

namespace Marvel\Database\Seeders;

use Illuminate\Database\Seeder;
use Marvel\Database\Models\Comment;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\User;

class CommentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Получаем несколько пользователей
        $users = User::take(10)->get();
        
        if ($users->isEmpty()) {
            $this->command->warn('Нет пользователей в базе. Создайте пользователей перед запуском сидера.');
            return;
        }

        // Получаем несколько продуктов
        $products = Product::take(20)->get();
        
        if ($products->isEmpty()) {
            $this->command->warn('Нет продуктов в базе. Создайте продукты перед запуском сидера.');
            return;
        }

        $this->command->info('Создание комментариев к продуктам...');

        // Создаем родительские комментарии
        $parentComments = [];
        foreach ($products->take(10) as $product) {
            $comment = Comment::factory()
                ->approved()
                ->create([
                    'user_id' => $users->random()->id,
                    'commentable_type' => 'product',
                    'commentable_id' => $product->id,
                    'parent_id' => null,
                ]);
            
            $parentComments[] = $comment;
            
            // Создаем ответы на некоторые комментарии (максимум 1 уровень вложенности)
            if (rand(0, 1)) {
                Comment::factory()
                    ->approved()
                    ->reply($comment->id)
                    ->create([
                        'user_id' => $users->random()->id,
                        'commentable_type' => 'product',
                        'commentable_id' => $product->id,
                    ]);
            }
        }

        // Создаем несколько комментариев со статусом pending
        foreach ($products->skip(10)->take(5) as $product) {
            Comment::factory()
                ->pending()
                ->create([
                    'user_id' => $users->random()->id,
                    'commentable_type' => 'product',
                    'commentable_id' => $product->id,
                    'parent_id' => null,
                ]);
        }

        // Создаем несколько комментариев со статусом rejected
        foreach ($products->skip(15)->take(3) as $product) {
            Comment::factory()
                ->rejected()
                ->create([
                    'user_id' => $users->random()->id,
                    'commentable_type' => 'product',
                    'commentable_id' => $product->id,
                    'parent_id' => null,
                ]);
        }

        $totalComments = Comment::count();
        $this->command->info("Создано комментариев: {$totalComments}");
    }
}

