<?php

namespace Tests\Unit;

use App\Support\SeatMapLayout;
use PHPUnit\Framework\TestCase;

class SeatMapLayoutTest extends TestCase
{
    public function test_normal_and_couple_rows_keep_every_number_and_share_aisles(): void
    {
        $rows = collect(['A' => collect(range(1,12))->map(fn ($number) => ['number' => $number, 'type' => 'normal']), 'J' => collect(range(1,12))->map(fn ($number) => ['number' => $number, 'type' => 'sweetbox'])]);
        $layout = SeatMapLayout::forRows($rows);
        $this->assertCount(12, $layout['columns']);
        $this->assertCount(2, $layout['aisles']);
        $this->assertSame([], array_intersect($layout['columns'], $layout['aisles']));
        $this->assertTrue($layout['rows']['J']['cross_aisle']);
        $this->assertFalse($layout['rows']['A']['cross_aisle']);
        $this->assertCount(12, $rows['J']);
    }

    public function test_existing_missing_columns_stay_an_aisle_without_renumbering(): void
    {
        $numbers = [1,2,3,6,7,8];
        $layout = SeatMapLayout::forRows(collect(['A' => collect($numbers)->map(fn ($number) => ['seat_number' => $number, 'seat_type' => 'normal'])]));
        $this->assertSame($numbers, array_keys($layout['columns']));
        $this->assertCount(1, $layout['aisles']);
        $this->assertGreaterThan($layout['columns'][3] + 1, $layout['columns'][6]);
        $this->assertSame([], array_intersect($layout['columns'], $layout['aisles']));
    }

    public function test_small_and_empty_rooms_do_not_lose_seats_or_create_empty_blocks(): void
    {
        $this->assertSame([], SeatMapLayout::forRows(collect())['columns']);
        foreach ([1,5,7,13] as $count) {
            $layout = SeatMapLayout::forRows(collect(['A' => collect(range(1,$count))->map(fn ($number) => ['number' => $number, 'type' => 'vip'])]));
            $this->assertSame(range(1,$count), array_keys($layout['columns']));
            $this->assertCount($count < 6 ? 0 : ($count < 10 ? 1 : 2), $layout['aisles']);
        }
    }
}
