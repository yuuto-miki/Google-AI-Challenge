<?php

namespace Tests\Feature;

use App\Http\Controllers\WalkController;
use Tests\TestCase;

class WalkQuestTest extends TestCase
{
    private const ORIGIN = ['latitude' => 35.6588, 'longitude' => 139.6998];

    public function test_catalog_is_playable_in_both_eras_and_answers_stay_hidden(): void
    {
        foreach (['modern', 'showa'] as $era) {
            $start = $this->postJson('/walk/start', ['demo' => true, 'era' => $era, 'minutes' => 30])->assertOk();
            $base = '/walk/'.$start->json('round');
            $spot = collect(config('walk_spots'))->firstWhere('id', session('walk.id'));
            $this->assertSame($era, $spot['era']);
            $start->assertJsonMissingPath('spot')->assertDontSee($spot['name']);
            $this->get($base.'/photo')->assertOk();
            $this->postJson($base.'/distance', $spot)->assertOk()->assertExactJson(['distance' => 0]);
            $this->postJson($base.'/answer', array_merge($spot, ['pin' => ['latitude' => 0, 'longitude' => 0]]))->assertUnprocessable();
            $this->postJson($base.'/answer', array_merge($spot, ['pin' => $spot]))->assertOk()
                ->assertJsonPath('score', 100)->assertJsonPath('spot.name', $spot['name']);
        }
    }

    public function test_live_walk_requires_a_nearby_candidate_and_a_recent_accurate_fix(): void
    {
        $settings = ['demo' => false, 'era' => 'modern', 'minutes' => 30];
        $this->postJson('/walk/start', $settings + ['latitude' => 0, 'longitude' => 0])->assertUnprocessable();
        $start = $this->postJson('/walk/start', $settings + self::ORIGIN)->assertOk();
        $url = '/walk/'.$start->json('round').'/answer';
        $input = self::ORIGIN + ['pin' => self::ORIGIN];
        $this->postJson($url, $input + ['accuracy' => 5, 'timestamp' => 0])->assertUnprocessable();
        $this->postJson($url, $input + ['accuracy' => 90, 'timestamp' => microtime(true) * 1000])->assertUnprocessable();
        $this->postJson($url, $input + ['accuracy' => 5, 'timestamp' => microtime(true) * 1000])->assertOk();
    }

    public function test_score_boundaries(): void
    {
        foreach ([[0,100],[10,100],[10.01,90],[30,90],[30.01,70],[50,70],[50.01,40],[100,40],[100.01,0]] as [$distance,$score]) {
            $this->assertSame($score, WalkController::score($distance));
        }
    }
}
