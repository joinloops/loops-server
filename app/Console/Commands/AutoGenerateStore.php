<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class AutoGenerateStore extends Command
{
    protected $signature = 'commerce:auto-store {profile_id : The vendor profile ID} {--category= : Filter products by category} {--count=5 : Number of products to generate}';

    protected $description = 'Auto-generate products from AI for a vendor';

    public function handle(): int
    {
        $profileId = (int) $this->argument('profile_id');
        $category = $this->option('category');
        $count = (int) $this->option('count');

        if ($count < 1 || $count > 50) {
            $this->error('Count must be between 1 and 50.');

            return 1;
        }

        // Verify the vendor exists
        $vendor = Vendor::where('profile_id', $profileId)->first();
        if (! $vendor) {
            $this->error("Vendor with profile_id {$profileId} not found.");

            return 1;
        }

        $this->info("Found vendor: {$vendor->business_name} (profile_id: {$profileId})");
        $this->info("Generating {$count} product suggestions" . ($category ? " in category '{$category}'" : '') . '...');

        // Determine style hint from existing products
        $existingProducts = Product::where('profile_id', $profileId)
            ->where('status', 'active')
            ->pluck('name')
            ->toArray();

        $styleHint = ! empty($existingProducts)
            ? 'Match the style of existing products: ' . implode(', ', array_slice($existingProducts, 0, 5))
            : 'Create diverse and marketable products';

        // Call loops-ai API
        $payload = [
            'category' => $category ?? 'general',
            'count' => $count,
            'style' => $styleHint,
        ];

        $this->line('Calling AI generation API...');

        try {
            $response = Http::timeout(60)->post('http://loops-ai:8500/ai/generate-products', $payload);

            if (! $response->successful()) {
                $this->error('AI API returned error: ' . $response->body());

                return 1;
            }

            $data = $response->json();
            $products = $data['products'] ?? [];

            if (empty($products)) {
                $this->warn('AI returned no products.');

                return 0;
            }
        } catch (\Exception $e) {
            $this->error('Failed to call AI API: ' . $e->getMessage());

            return 1;
        }

        // Create product records
        $created = 0;
        $bar = $this->output->createProgressBar(count($products));
        $bar->start();

        foreach ($products as $item) {
            try {
                Product::create([
                    'profile_id' => $profileId,
                    'name' => $item['name'] ?? 'Untitled Product',
                    'description' => $item['description'] ?? '',
                    'price' => (float) ($item['price'] ?? 9.99),
                    'currency' => $item['currency'] ?? 'USD',
                    'category' => $item['category'] ?? $category ?? 'general',
                    'tags' => $item['tags'] ?? [],
                    'stock' => (int) ($item['stock'] ?? 100),
                    'status' => 'active',
                ]);
                $created++;
            } catch (\Exception $e) {
                $this->warn("Failed to create product '{$item['name'] ?? 'unknown'}': " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✓ Successfully created {$created} products for {$vendor->business_name}");
        $this->line("  Profile ID: {$profileId}");

        return 0;
    }
}
