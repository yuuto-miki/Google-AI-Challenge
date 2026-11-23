<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WalkController extends Controller
{
    private const DEMO_START = ['latitude' => 35.6588, 'longitude' => 139.6998];

    public function start(Request $request)
    {
        $settings = $request->validate([
            'minutes' => ['required', Rule::in([30, 60, 90])],
            'era' => ['required', Rule::in(['showa', 'modern'])],
            'demo' => ['required', 'boolean'],
        ]);
        $origin = $settings['demo'] ? self::DEMO_START : $this->location($request);
        // 70 m/min on foot, allowing 30% extra distance for the street network.
        $radius = $settings['minutes'] / 2 * 70 / 1.3;
        $spots = collect(config('walk_spots'))->where('era', $settings['era'])
            ->filter(fn ($spot) => self::meters($origin, $spot) <= $radius);
        if ($spots->isEmpty()) {
            return response()->json(['message' => 'この条件で歩けるスポットがありません。時間か時代を変えるか、渋谷デモでお試しください。'], 422);
        }
        $spot = $spots->random();
        $comparison = $this->comparison($spot);
        $round = (string) Str::uuid();
        $request->session()->put('walk', ['round' => $round, 'id' => $spot['id'], 'demo' => (bool) $settings['demo']]);
        return response()->json([
            'comparisonCredit' => $comparison['credit'] ?? null,
            'credit' => $spot['credit'], 'round' => $round, 'origin' => array_intersect_key($origin, self::DEMO_START),
            'distance' => round(self::meters($origin, $spot)), 'hints' => $spot['hints'],
        ]);
    }

    private function spot(Request $request, string $round): array
    {
        abort_unless($request->session()->get('walk.round') === $round, 409, '探索が切り替わりました。最初から始めてください。');
        return collect(config('walk_spots'))->firstWhere('id', $request->session()->get('walk.id'));
    }

    private function location(Request $request): array
    {
        return $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);
    }

    private function comparison(array $spot): ?array
    {
        return isset($spot['comparisonId'])
            ? collect(config('walk_spots'))->firstWhere('id', $spot['comparisonId'])
            : null;
    }

    public function photo(Request $request, string $round)
    {
        $spot = $this->spot($request, $round);
        if ($request->boolean('comparison')) {
            $spot = $this->comparison($spot);
            abort_unless($spot, 404);
        }
        return response()->file(storage_path('app/walk-photos/'.$spot['image']), ['Cache-Control' => 'private, no-store']);
    }

    public function distance(Request $request, string $round)
    {
        $spot = $this->spot($request, $round);
        return response()->json(['distance' => round(self::meters($this->location($request), $spot))]);
    }

    public function answer(Request $request, string $round)
    {
        $spot = $this->spot($request, $round);
        if ($result = $request->session()->get('walk.result')) {
            return response()->json($result);
        }
        $location = $this->location($request);
        $pin = $request->validate([
            'pin.latitude' => 'required|numeric|between:-90,90',
            'pin.longitude' => 'required|numeric|between:-180,180',
        ])['pin'];
        if (!$request->session()->get('walk.demo')) {
            $fix = $request->validate(['accuracy' => 'required|numeric|min:0', 'timestamp' => 'required|numeric']);
            if ($fix['accuracy'] > 80 || abs(microtime(true) - $fix['timestamp'] / 1000) > 60) {
                return response()->json(['message' => 'GPSの精度が足りません。空の開けた場所で再取得してください。'], 422);
            }
        }
        if (self::meters($location, $pin) > 60) {
            return response()->json(['message' => '回答ピンから60 m以内に近づいてください。'], 422);
        }
        $error = self::meters($pin, $spot);
        $result = [
            'spot' => array_intersect_key($spot, array_flip(['name', 'latitude', 'longitude', 'credit', 'licenseUrl', 'source', 'description'])),
            'comparisonSource' => $this->comparison($spot)['source'] ?? null,
            'pin' => $pin, 'error' => round($error, 1), 'score' => self::score($error),
        ];
        $request->session()->put('walk.result', $result);
        return response()->json($result);
    }

    public static function meters(array $a, array $b): float
    {
        $dLat = deg2rad($b['latitude'] - $a['latitude']);
        $dLon = deg2rad($b['longitude'] - $a['longitude']);
        $h = sin($dLat / 2) ** 2 + cos(deg2rad($a['latitude'])) * cos(deg2rad($b['latitude'])) * sin($dLon / 2) ** 2;
        return 12742000 * asin(sqrt(min(1, $h)));
    }

    public static function score(float $meters): int
    {
        return match (true) {
            $meters <= 10 => 100, $meters <= 30 => 90, $meters <= 50 => 70,
            $meters <= 100 => 40, default => 0,
        };
    }
}
