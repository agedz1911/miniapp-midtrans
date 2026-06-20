<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('products')->insert([
            [
                'name' => 'Legion 5 Pro',
                'description' => 'Description for LLegion 5 Pro',
                'price' => 1000000,
                'stock' => 10,
                'images' => 'product1.jpg',
                'category' => 'Laptop',
            ],
            [
                'name' => 'Apple Macbook Pro M1',
                'description' => 'Description for Apple Macbook Pro M1',
                'price' => 2000000,
                'stock' => 20,
                'images' => 'product2.jpg',
                'category' => 'Laptop',
            ],
            [
                'name' => 'Iphone 14 Pro Max',
                'description' => 'Description for Iphone 14 Pro Max',
                'price' => 1500000,
                'stock' => 10,
                'images' => 'product3.jpg',
                'category' => 'Handphone',
            ],
        ]);
    }
}
