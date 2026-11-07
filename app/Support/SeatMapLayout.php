<?php

namespace App\Support;

use Illuminate\Support\Collection;

class SeatMapLayout
{
    /**
     * Use the same numbered positions in every row, including filtered rows.
     * Aisles are display tracks; they never remove or renumber stored seats.
     */
    public static function forRows(Collection $rows): array
    {
        $numbers = $rows->flatten(1)
            ->map(fn ($seat) => (int) (data_get($seat, 'number') ?? data_get($seat, 'seat_number')))
            ->filter(fn ($number) => $number > 0)->unique()->sort()->values();

        if ($numbers->isEmpty()) {
            return ['template' => '', 'columns' => [], 'aisles' => [], 'rows' => [], 'width' => 0, 'min_width' => 0];
        }

        $maxNumber = $numbers->last();
        $occupied = array_fill_keys($numbers->all(), true);
        $hasExistingAisle = $numbers->count() < $maxNumber - $numbers->first() + 1;
        $breaks = [];

        if (! $hasExistingAisle) {
            $count = $numbers->count();
            if ($count >= 10) {
                $sideBlock = (int) round($count / 4);
                $breaks = [$numbers[$sideBlock - 1], $numbers[$count - $sideBlock - 1]];
            } elseif ($count >= 6) {
                $breaks = [$numbers[(int) ceil($count / 2) - 1]];
            }
        }

        $tracks = [];
        $columns = [];
        $aisles = [];
        $aisleWidth = 0;
        $seatTracks = 0;

        for ($number = 1; $number <= $maxNumber; $number++) {
            if (! isset($occupied[$number]) && $number > $numbers->first()) {
                $start = $number;
                while ($number < $maxNumber && ! isset($occupied[$number + 1])) {
                    $number++;
                }
                $width = min(64, max(32, ($number - $start + 1) * 24));
                $tracks[] = $width . 'px';
                $aisles[] = count($tracks);
                $aisleWidth += $width;
                continue;
            }

            $tracks[] = 'minmax(42px, 1fr)';
            $columns[$number] = count($tracks);
            $seatTracks++;

            if (in_array($number, $breaks, true)) {
                $tracks[] = '32px';
                $aisles[] = count($tracks);
                $aisleWidth += 32;
            }
        }

        $rowLayouts = [];
        $previousWasCouple = true;
        foreach ($rows as $label => $seats) {
            $coupleRow = $seats->isNotEmpty() && $seats->every(
                fn ($seat) => (data_get($seat, 'type') ?? data_get($seat, 'seat_type')) === 'sweetbox'
            );
            $rowLayouts[$label] = ['cross_aisle' => $coupleRow && ! $previousWasCouple];
            $previousWasCouple = $coupleRow;
        }

        $fixedWidth = $aisleWidth + max(0, count($tracks) - 1) * 6 + 72;

        return [
            'template' => implode(' ', $tracks),
            'columns' => $columns,
            'aisles' => $aisles,
            'rows' => $rowLayouts,
            'width' => $seatTracks * 56 + $fixedWidth,
            'min_width' => $seatTracks * 42 + $fixedWidth,
        ];
    }
}
