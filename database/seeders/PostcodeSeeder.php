<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use App\Models\Postcode;

class PostcodeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $url = 'https://raw.githubusercontent.com/heiswayi/malaysia-postcodes/main/data/json/postcodes.json';
        
        $this->command->info("Fetching data from $url...");
        
        try {
            $response = Http::withoutVerifying()->get($url);
            
            if ($response->failed()) {
                $this->command->error("Failed to fetch data.");
                return;
            }

            $data = $response->json();
            $postcodes = [];

            foreach ($data['states'] as $stateData) {
                $state = $stateData['name'];
                
                foreach ($stateData['cities'] as $cityData) {
                    $city = $cityData['name'];
                    
                    foreach ($cityData['postcodes'] as $code) {
                        $postcodes[] = [
                            'postcode' => $code,
                            'city' => $city,
                            'state' => $state,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
            }

            $this->command->info("Inserting " . count($postcodes) . " postcodes...");
            
            // Insert in chunks to avoid memory issues
            foreach (array_chunk($postcodes, 1000) as $chunk) {
                Postcode::insert($chunk);
            }
            
            $this->command->info("Done!");

        } catch (\Exception $e) {
            $this->command->error("Error: " . $e->getMessage());
        }
    }
}
