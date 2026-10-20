<?php

namespace Tests\Feature\Muafa4;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicHospitalTypesTest extends TestCase
{
    use RefreshDatabase;

    public function test_hospital_types_are_public_on_both_urls(): void
    {
        DB::table('hospital_types')->insert(['name_ar' => 'مشفى خاص']);

        foreach (['/api/hospital-types', '/api/hospital/hospital-types'] as $url) {
            $this->getJson($url)                       // no token
                ->assertOk()
                ->assertJsonPath('data.0.name_ar', 'مشفى خاص')
                ->assertHeader('X-RateLimit-Limit', '60');
        }
    }
}
