<?php

namespace App\Console\Commands;

use App\Models\Movie;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SyncGalaxyMovies extends Command
{
    protected $signature = 'movies:sync-galaxy
        {--no-images : Chỉ lưu URL ảnh từ CDN, không tải ảnh về storage}
        {--refresh-images : Tải lại cả những ảnh đã có trong storage}
        {--limit=0 : Giới hạn số phim để kiểm thử}';

    protected $description = 'Đồng bộ phim đang chiếu, sắp chiếu và IMAX từ dữ liệu công khai của Galaxy Cinema';

    private const SOURCE = 'galaxycine';
    private const API = 'https://www.galaxycine.vn/api';
    private const CLIENT_ID = '3da4eba4-94dd-4e74-bc25-38e89a01fe07';

    public function handle(): int
    {
        try {
            $movies = $this->collectMovies();
        } catch (Throwable $exception) {
            $this->error('Không thể tải danh sách phim: '.$exception->getMessage());
            return self::FAILURE;
        }

        $limit = max(0, (int) $this->option('limit'));
        if ($limit > 0) {
            $movies = array_slice($movies, 0, $limit, true);
        }

        $this->info('Tìm thấy '.count($movies).' phim không trùng lặp.');
        $progress = $this->output->createProgressBar(count($movies));
        $progress->start();
        $created = 0;
        $updated = 0;
        $failed = [];

        foreach ($movies as $externalId => $summary) {
            try {
                $detail = $this->fetch('/v2/mobile/movies/'.$externalId)['data'] ?? $summary;
                $movie = Movie::query()
                    ->where('source', self::SOURCE)
                    ->where('external_id', $externalId)
                    ->first();

                if (!$movie) {
                    $movie = Movie::query()
                        ->whereNull('source')
                        ->where('title', $detail['name'] ?? $summary['name'])
                        ->first();
                }

                $attributes = $this->attributesFor($detail, $summary);
                $wasExisting = (bool) $movie;
                $movie ??= new Movie();
                $movie->fill($attributes);
                $movie->save();

                $wasExisting ? $updated++ : $created++;
            } catch (Throwable $exception) {
                $failed[] = ($summary['name'] ?? $externalId).': '.$exception->getMessage();
            }

            $progress->advance();
        }

        $progress->finish();
        $this->newLine(2);
        $this->info("Đã thêm {$created} phim và cập nhật {$updated} phim.");

        foreach ($failed as $message) {
            $this->warn($message);
        }

        return $failed === [] ? self::SUCCESS : self::FAILURE;
    }

    private function collectMovies(): array
    {
        $collections = [
            'now_showing' => '/v2/mobile/movies/showing',
            'upcoming' => '/v2/mobile/movies/comming',
            'imax' => '/v2/mobile/movies/movie-imax',
        ];
        $movies = [];

        foreach ($collections as $group => $endpoint) {
            $items = $this->fetch($endpoint)['data']['result'] ?? [];
            foreach ($items as $item) {
                $id = (string) ($item['id'] ?? '');
                if ($id === '') {
                    continue;
                }

                if (!isset($movies[$id])) {
                    $item['_primary_status'] = $group === 'imax'
                        ? $this->statusFromReleaseDate($item['startDate'] ?? null)
                        : $group;
                    $item['_is_imax'] = false;
                    $movies[$id] = $item;
                }

                if ($group === 'imax') {
                    $movies[$id]['_is_imax'] = true;
                }
            }
        }

        return $movies;
    }

    private function attributesFor(array $detail, array $summary): array
    {
        $data = array_replace($summary, $detail);
        $externalId = (string) $summary['id'];
        $posterUrl = trim((string) ($data['imagePortrait'] ?? ''));
        $bannerUrl = trim((string) ($data['imageLandscape'] ?? ''));
        $seoImage = trim((string) Arr::get($data, 'seoData.ogImage', ''));
        $posterUrl = $posterUrl ?: ($seoImage ?: $bannerUrl);
        $bannerUrl = $bannerUrl ?: ($seoImage ?: $posterUrl);

        return [
            'title' => Str::limit(trim((string) ($data['name'] ?? 'Phim chưa đặt tên')), 255, ''),
            'poster_path' => $this->imagePath($posterUrl, $externalId, 'poster'),
            'banner_path' => $this->imagePath($bannerUrl, $externalId, 'banner'),
            'description' => $this->buildDescription($data),
            'genre' => Str::limit($this->names($data['categories'] ?? []), 100, ''),
            'age_rating' => $this->ageRating($data['age'] ?? null),
            'duration' => (int) ($data['duration'] ?? 0) > 0 ? (int) $data['duration'] : 90,
            'director' => Str::limit($this->names($data['directors'] ?? []), 100, ''),
            'actors' => $this->names($data['actors'] ?? []),
            'trailer_url' => $data['trailer'] ?: null,
            'release_date' => $this->date($data['startDate'] ?? null),
            'status' => $summary['_primary_status'],
            'is_imax' => (bool) ($summary['_is_imax'] ?? false),
            'source' => self::SOURCE,
            'external_id' => $externalId,
            'source_url' => $data['link'] ?? null,
            'rating_avg' => min(5, max(0, round(((float) ($data['rate'] ?? 0)) / 2, 1))),
            'review_count' => max(0, (int) ($data['totalVotes'] ?? 0)),
        ];
    }

    private function buildDescription(array $data): string
    {
        $title = trim((string) ($data['name'] ?? 'Bộ phim'));
        $genres = $this->names($data['categories'] ?? []);
        $directors = $this->names($data['directors'] ?? []);
        $actors = $this->names(array_slice($data['actors'] ?? [], 0, 4));
        $country = trim((string) ($data['country'] ?? ''));
        $duration = (int) ($data['duration'] ?? 0);
        $releaseDate = $this->date($data['startDate'] ?? null);

        $sentences = ["{$title} là tác phẩm điện ảnh".($genres ? " thuộc thể loại {$genres}" : '')];
        if ($country !== '') {
            $sentences[0] .= " của {$country}";
        }
        if ($directors !== '') {
            $sentences[] = "Phim do {$directors} đạo diễn";
        }
        if ($actors !== '') {
            $sentences[] = "Dàn diễn viên gồm {$actors}";
        }

        $releaseDetails = [];
        if ($duration > 0) {
            $releaseDetails[] = "thời lượng {$duration} phút";
        }
        if ($releaseDate) {
            $releaseDetails[] = 'khởi chiếu ngày '.Carbon::parse($releaseDate)->format('d/m/Y');
        }
        if ($releaseDetails !== []) {
            $sentences[] = ucfirst(implode(', ', $releaseDetails));
        }

        return implode('. ', $sentences).'.';
    }

    private function imagePath(string $url, string $externalId, string $kind): ?string
    {
        if ($url === '' || $this->option('no-images')) {
            return $url ?: null;
        }

        $extension = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $extension = 'jpg';
        }
        $path = "movies/imported/{$externalId}/{$kind}.{$extension}";

        if (!$this->option('refresh-images') && Storage::disk('public')->exists($path)) {
            return $path;
        }

        $response = $this->request()->get($url)->throw();
        Storage::disk('public')->put($path, $response->body());

        return $path;
    }

    private function fetch(string $endpoint): array
    {
        return $this->request()->get(self::API.$endpoint)->throw()->json();
    }

    private function request(): PendingRequest
    {
        $request = Http::timeout(30)->retry(3, 300);
        if (app()->environment('local', 'testing')) {
            $request = $request->withoutVerifying();
        }

        return $request->withHeaders([
            'Accept' => 'application/json',
            'clientid' => self::CLIENT_ID,
            'platform' => 'website',
            'Referer' => 'https://www.galaxycine.vn/',
            'Origin' => 'https://www.galaxycine.vn',
            'User-Agent' => 'Mozilla/5.0 TD-Cinema movie sync',
        ]);
    }

    private function names(array $items): string
    {
        return collect($items)->pluck('name')->filter()->implode(', ');
    }

    private function ageRating(mixed $age): string
    {
        return match (strtolower((string) $age)) {
            '18' => 'T18',
            '16' => 'T16',
            '13' => 'T13',
            'k' => 'K',
            default => 'P',
        };
    }

    private function statusFromReleaseDate(?string $date): string
    {
        return $date && Carbon::parse($date)->isAfter(today()) ? 'upcoming' : 'now_showing';
    }

    private function date(?string $date): ?string
    {
        return $date ? Carbon::parse($date)->toDateString() : null;
    }
}
