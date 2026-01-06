<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Item;

class TestItemsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('📦 Creating test items for Week 5-6 testing...');

        $this->createTestItems();
    }

    /**
     * Create test items for testing order and donation workflows
     */
    private function createTestItems(): void
    {
        // Get test users
        $user1 = User::where('email', 'user1@rewear.com')->first();
        $admin = User::where('email', 'admin@rewear.com')->first();

        if (!$user1 || !$admin) {
            $this->command->error('❌ Test users not found. Run Week56TestUsersSeeder first.');
            return;
        }

        $items = [
            // Regular items for sale
            [
                'seller_id' => $user1->id,
                'title' => 'Winter Jacket - North Face',
                'description' => 'Warm winter jacket from The North Face in excellent condition. Size M, black color. Perfect for cold weather, barely used. Original price was $180.',
                'category' => 'outerwear',
                'size' => 'M',
                'condition' => 'like_new',
                'gender' => 'unisex',
                'brand' => 'The North Face',
                'color' => 'Black',
                'price' => 45.00,
                'is_donation' => false,
                'status' => 'available',
                'views_count' => 0,
            ],
            [
                'seller_id' => $user1->id,
                'title' => 'Designer Handbag - Coach Leather',
                'description' => 'Authentic Coach leather handbag in brown. Good condition with minor wear on corners. Comes with authenticity card and dust bag.',
                'category' => 'accessories',
                'size' => 'One Size',
                'condition' => 'good',
                'gender' => 'female',
                'brand' => 'Coach',
                'color' => 'Brown',
                'price' => 120.00,
                'is_donation' => false,
                'status' => 'available',
                'views_count' => 0,
            ],
            [
                'seller_id' => $admin->id,
                'title' => 'Running Shoes - Nike Air Max',
                'description' => 'Nike Air Max running shoes in white/blue colorway. Size 42 (EU). Light usage, very clean condition. Great for running or casual wear.',
                'category' => 'shoes',
                'size' => 'XL', // Using enum values from schema
                'condition' => 'like_new',
                'gender' => 'male',
                'brand' => 'Nike',
                'color' => 'White',
                'price' => 65.00,
                'is_donation' => false,
                'status' => 'available',
                'views_count' => 0,
            ],

            // Donation items (using exact schema fields)
            [
                'seller_id' => $user1->id,
                'title' => 'Winter Clothing Bundle',
                'description' => 'Bundle of warm winter clothes including sweaters, pants, and warm socks. Various sizes available (S, M, L). All items are clean and in good condition.',
                'category' => 'tops', // Using enum value
                'size' => 'L', // Mixed sizes but using enum value
                'condition' => 'good',
                'gender' => 'unisex',
                'brand' => 'Various',
                'color' => 'Mixed',
                'price' => null, // NULL for donations
                'is_donation' => true,
                'status' => 'available',
                'views_count' => 0,
            ],
            [
                'seller_id' => $admin->id,
                'title' => 'Children\'s School Clothes',
                'description' => 'School appropriate clothing for children including uniforms, casual wear, and shoes. Sizes range from 6-12 years old. All clean and ready to wear.',
                'category' => 'tops',
                'size' => 'S', // Child sizes as S
                'condition' => 'good',
                'gender' => 'unisex',
                'brand' => 'Various',
                'color' => 'Mixed',
                'price' => null,
                'is_donation' => true,
                'status' => 'available',
                'views_count' => 0,
            ],
            [
                'seller_id' => $user1->id,
                'title' => 'Baby Clothes Collection',
                'description' => 'Gently used baby clothes from 0-12 months. Includes onesies, sleepers, bibs, and small blankets. All items washed and sanitized.',
                'category' => 'tops',
                'size' => 'XS', // Baby sizes as XS
                'condition' => 'good',
                'gender' => 'unisex',
                'brand' => 'Various',
                'color' => 'Mixed',
                'price' => null,
                'is_donation' => true,
                'status' => 'available',
                'views_count' => 0,
            ],
            [
                'seller_id' => $admin->id,
                'title' => 'Women\'s Professional Attire',
                'description' => 'Professional clothing for women including blazers, dress shirts, and dress pants. Perfect for job interviews or workplace. Sizes M-L.',
                'category' => 'tops',
                'size' => 'M',
                'condition' => 'like_new',
                'gender' => 'female',
                'brand' => 'Various',
                'color' => 'Dark',
                'price' => null,
                'is_donation' => true,
                'status' => 'available',
                'views_count' => 0,
            ],
            [
                'seller_id' => $user1->id,
                'title' => 'Men\'s Casual Wear Bundle',
                'description' => 'Casual men\'s clothing including jeans, t-shirts, and casual shoes. Size L-XL. All items in good condition, perfect for everyday wear.',
                'category' => 'bottoms',
                'size' => 'L',
                'condition' => 'good',
                'gender' => 'male',
                'brand' => 'Various',
                'color' => 'Mixed',
                'price' => null,
                'is_donation' => true,
                'status' => 'available',
                'views_count' => 0,
            ],
        ];

        foreach ($items as $itemData) {
            // Check if item already exists
            $existingItem = Item::where('seller_id', $itemData['seller_id'])
                ->where('title', $itemData['title'])
                ->first();

            if ($existingItem) {
                continue;
            }

            Item::create($itemData);
        }

        $regularCount = count(array_filter($items, fn($item) => !$item['is_donation']));
        $donationCount = count(array_filter($items, fn($item) => $item['is_donation']));

        $this->command->info('✅ Test items created successfully!');
        $this->command->info("📦 Created {$regularCount} regular items for sale");
        $this->command->info("❤️  Created {$donationCount} donation items");
        $this->command->info('🛒 Items include:');
        $this->command->info('   SALE: Winter Jacket ($45), Coach Handbag ($120), Nike Shoes ($65)');
        $this->command->info('   DONATIONS: Winter clothes, School clothes, Baby clothes, Professional wear, Casual wear');
        $this->command->info('🚀 Ready for order and donation testing!');
    }
}
