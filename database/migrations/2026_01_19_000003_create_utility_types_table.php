<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Default utility types to seed.
     */
    private const DEFAULT_TYPES = [
        ['key' => 'water', 'label' => 'Water', 'icon' => 'BeakerIcon', 'color_scheme' => 'blue', 'sort_order' => 1, 'is_system' => true],
        ['key' => 'electric', 'label' => 'Electric', 'icon' => 'BoltIcon', 'color_scheme' => 'yellow', 'sort_order' => 2, 'is_system' => true],
        ['key' => 'gas', 'label' => 'Gas', 'icon' => 'FireIcon', 'color_scheme' => 'orange', 'sort_order' => 3, 'is_system' => true],
        ['key' => 'garbage', 'label' => 'Garbage', 'icon' => 'TrashIcon', 'color_scheme' => 'gray', 'sort_order' => 4, 'is_system' => true],
        ['key' => 'sewer', 'label' => 'Sewer', 'icon' => 'SparklesIcon', 'color_scheme' => 'green', 'sort_order' => 5, 'is_system' => true],
        ['key' => 'other', 'label' => 'Other', 'icon' => 'CubeIcon', 'color_scheme' => 'purple', 'sort_order' => 100, 'is_system' => true],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('utility_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key', 50)->unique();
            $table->string('label', 100);
            $table->string('icon', 50)->nullable();
            $table->string('color_scheme', 20)->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->index('sort_order');
        });

        // Seed default utility types
        $this->seedDefaultTypes();

        // Migrate any custom types from Settings
        $this->migrateCustomTypes();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('utility_types');
    }

    /**
     * Seed the default utility types.
     */
    private function seedDefaultTypes(): void
    {
        $now = now();

        foreach (self::DEFAULT_TYPES as $type) {
            DB::table('utility_types')->insert([
                'id' => Str::uuid()->toString(),
                'key' => $type['key'],
                'label' => $type['label'],
                'icon' => $type['icon'],
                'color_scheme' => $type['color_scheme'],
                'sort_order' => $type['sort_order'],
                'is_system' => $type['is_system'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Migrate any custom utility types from Settings.
     */
    private function migrateCustomTypes(): void
    {
        // Get existing types from settings (if any)
        $existingTypes = Setting::get('utilities', 'types', []);

        if (empty($existingTypes)) {
            return;
        }

        $defaultKeys = array_column(self::DEFAULT_TYPES, 'key');
        $now = now();
        $sortOrder = 50; // Start custom types between system types and "other"

        foreach ($existingTypes as $key => $label) {
            // Skip if it's a default type (already seeded)
            if (in_array($key, $defaultKeys, true)) {
                continue;
            }

            // Insert custom type with default icon/color
            DB::table('utility_types')->insert([
                'id' => Str::uuid()->toString(),
                'key' => $key,
                'label' => $label,
                'icon' => 'CubeIcon', // Default icon for custom types
                'color_scheme' => 'slate', // Default color for custom types
                'sort_order' => $sortOrder++,
                'is_system' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
