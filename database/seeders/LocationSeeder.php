<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Province;
use App\Models\District;
use App\Models\Commune;
use App\Models\Village;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LocationSeeder extends Seeder
{
    /**
     * This seeder fetches official Cambodian administrative data from a reliable open-source repository.
     * It includes all 25 Provinces, 204 Districts, 1,646 Communes, and 14,000+ Villages.
     */
    public function run()
    {
        // Increase memory limit and execution time for this large data set
        ini_set('memory_limit', '512M');
        set_time_limit(0);

        $this->command->info('Starting to fetch Cambodia Location data...');

        $baseUrl = 'https://raw.githubusercontent.com/NorakGithub/cambodia-gazetteer/main';

        try {
            $provincesResponse = Http::get("$baseUrl/provinces.json");

            if (!$provincesResponse->successful()) {
                $this->command->error('Failed to fetch provinces list.');
                return;
            }

            $provincesList = $provincesResponse->json();

            foreach ($provincesList as $pData) {
                $pName = trim(str_replace([' Province', ' Capital'], '', $pData['english']));
                $this->command->info("Processing Province: {$pName}");

                $province = Province::updateOrCreate(
                    ['code' => $pData['code']],
                    ['name' => $pName]
                );

                // Fetch details for this specific province (Districts, Communes, Villages)
                $detailResponse = Http::get("$baseUrl/provinces/{$pData['id']}.json");

                if (!$detailResponse->successful()) {
                    $this->command->warn("Failed to fetch details for {$pData['english']}. Skipping...");
                    continue;
                }

                $provinceDetails = $detailResponse->json();

                if (is_array($provinceDetails)) {
                    foreach ($provinceDetails as $dData) {
                        $district = District::updateOrCreate(
                            ['code' => $dData['code']],
                            [
                                'province_id' => $province->id,
                                'name' => $dData['english']
                            ]
                        );

                        if (isset($dData['communes']) && is_array($dData['communes'])) {
                            foreach ($dData['communes'] as $cData) {
                                $commune = Commune::updateOrCreate(
                                    ['code' => $cData['code']],
                                    [
                                        'district_id' => $district->id,
                                        'name' => $cData['english']
                                    ]
                                );

                                if (isset($cData['villages']) && is_array($cData['villages'])) {
                                    foreach ($cData['villages'] as $vData) {
                                        Village::updateOrCreate(
                                            ['code' => $vData['code']],
                                            [
                                                'commune_id' => $commune->id,
                                                'name' => $vData['english']
                                            ]
                                        );
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $this->command->info('Successfully seeded all Provinces, Districts, Communes, and Villages!');

        } catch (\Exception $e) {
            $this->command->error("An error occurred: " . $e->getMessage());
            Log::error("LocationSeeder Error: " . $e->getMessage());
        }
    }
}
