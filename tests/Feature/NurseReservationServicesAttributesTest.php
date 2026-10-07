<?php

namespace Tests\Feature;

use App\Models\NurseReservation;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The sqlite test driver has no spatial functions, so reservation rows are
 * written through a subclass that drops the `Point` cast. The accessors under
 * test are inherited from NurseReservation unchanged.
 */
class SqliteNurseReservation extends NurseReservation
{
    protected $table = 'nurse_reservations';

    protected $casts = [
        'price' => 'float',
        'reserved_by_admin' => 'boolean',
    ];

    public function getForeignKey(): string
    {
        return 'nurse_reservation_id';
    }
}

class NurseReservationServicesAttributesTest extends TestCase
{
    use RefreshDatabase;

    private function makeReservation(int $serviceCount, ?float $price = null): NurseReservation
    {
        $user = User::factory()->create();

        $serviceIds = [];
        foreach (range(1, max(1, $serviceCount)) as $ignored) {
            $serviceIds[] = DB::table('nurse_services')->insertGetId([
                'nurse_id' => 1,
                'service_id' => Service::factory()->create(['service_type' => 'nurse'])->id,
                'price' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $reservation = new SqliteNurseReservation();
        $reservation->forceFill([
            'user_id' => $user->id,
            'nurse_id' => 1,
            'nurse_service_id' => $serviceIds[0],
            'reservation_type' => 'direct',
            'status' => 'pending',
            'price' => $price ?? 10 * $serviceCount,
            'location' => 'POINT(1 1)',
            'start_at' => now(),
            'end_at' => now()->addHour(),
        ]);
        $reservation->save();

        if ($serviceCount > 0) {
            DB::table('nurse_reservation_services')->insert(
                collect($serviceIds)->map(fn (int $serviceId) => [
                    'nurse_reservation_id' => $reservation->id,
                    'nurse_service_id' => $serviceId,
                    'price' => 10,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all()
            );
        }

        return $reservation;
    }

    public function test_accessors_query_the_database_when_services_are_not_loaded(): void
    {
        $reservation = $this->makeReservation(3);
        $fresh = NurseReservation::findOrFail($reservation->id);

        $this->assertFalse($fresh->relationLoaded('services'));

        $this->assertSame(3, $fresh->services_count);
        $this->assertCount(3, $fresh->service_names);
        $this->assertEquals(30.0, $fresh->services_total_price);
    }

    public function test_accessors_use_the_loaded_relation_without_extra_queries(): void
    {
        $reservation = $this->makeReservation(2);

        $fresh = SqliteNurseReservation::with('services')->findOrFail($reservation->id);

        $this->assertTrue($fresh->relationLoaded('services'));

        $this->assertSame(2, $fresh->services_count);
        $this->assertCount(2, $fresh->service_names);
        $this->assertEquals(20.0, $fresh->services_total_price);
    }

    public function test_total_falls_back_to_the_price_column_when_no_pivot_rows_exist(): void
    {
        $fresh = $this->makeReservation(0, 75.5);

        $this->assertSame(0, $fresh->services_count);
        $this->assertEquals(75.5, $fresh->services_total_price);
    }

    public function test_services_label_lists_every_service_name(): void
    {
        $fresh = SqliteNurseReservation::with('services')
            ->findOrFail($this->makeReservation(2)->id);

        $label = $fresh->servicesLabel();

        $this->assertStringContainsString($fresh->service_names[0], $label);
        $this->assertStringContainsString($fresh->service_names[1], $label);
    }

    public function test_serialising_a_reservation_does_not_throw(): void
    {
        $reservation = $this->makeReservation(1);

        $payload = SqliteNurseReservation::findOrFail($reservation->id)->toArray();

        $this->assertArrayHasKey('services_count', $payload);
        $this->assertArrayHasKey('service_names', $payload);
        $this->assertArrayHasKey('services_total_price', $payload);
        $this->assertSame(1, $payload['services_count']);
    }
}
