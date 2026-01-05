<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Property;
use App\Services\GeocodingService;
use Illuminate\Console\Command;

class GeocodePropertiesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'properties:geocode
                            {--limit=0 : Maximum number of properties to geocode (0 = all)}
                            {--force : Re-geocode properties that already have coordinates}
                            {--dry-run : Show what would be geocoded without making changes}';

    /**
     * The console command description.
     */
    protected $description = 'Geocode property addresses to get latitude/longitude coordinates';

    public function __construct(
        private readonly GeocodingService $geocodingService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        $query = Property::query();

        if (! $force) {
            $query->needsGeocoding();
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $properties = $query->get();

        if ($properties->isEmpty()) {
            $this->info('No properties need geocoding.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s %d properties...',
            $dryRun ? 'Would geocode' : 'Geocoding',
            $properties->count()
        ));

        if ($dryRun) {
            $this->table(
                ['ID', 'Name', 'Address', 'Has Coordinates'],
                $properties->map(fn ($p) => [
                    $p->id,
                    \Illuminate\Support\Str::limit($p->name, 30),
                    \Illuminate\Support\Str::limit($p->full_address, 40),
                    $p->hasCoordinates() ? 'Yes' : 'No',
                ])
            );

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($properties->count());
        $bar->start();

        $success = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($properties as $property) {
            $address = $property->full_address;

            if (empty($address)) {
                $skipped++;
                $bar->advance();
                continue;
            }

            $coordinates = $this->geocodingService->geocode($address);

            if ($coordinates) {
                $property->update([
                    'latitude' => $coordinates['latitude'],
                    'longitude' => $coordinates['longitude'],
                ]);
                $success++;
            } else {
                $failed++;
                $this->line('');
                $this->warn("  Failed to geocode: {$property->name}");
            }

            $bar->advance();

            // Small delay to be respectful to the geocoding service
            usleep(200000); // 200ms
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Geocoding complete:");
        $this->line("  - Success: {$success}");
        $this->line("  - Failed: {$failed}");
        $this->line("  - Skipped (no address): {$skipped}");

        // Show final stats
        $total = Property::count();
        $withCoords = Property::whereNotNull('latitude')->whereNotNull('longitude')->count();
        $this->newLine();
        $this->info("Total properties with coordinates: {$withCoords}/{$total}");

        return self::SUCCESS;
    }
}
