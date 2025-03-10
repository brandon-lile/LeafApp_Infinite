<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Adapters\FileUtilInterface;
use App\Jobs\PullAppearance;
use App\Jobs\PullXuid;
use App\Models\Player;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Symfony\Component\HttpFoundation\Response;
use Tests\Mocks\Appearance\MockAppearanceService;
use Tests\Mocks\Image\MockImageService;
use Tests\TestCase;

class PullAppearanceTest extends TestCase
{
    use WithFaker;

    public function test_pull_appearance_as_bot(): void
    {
        // Arrange
        Http::fake()->preventStrayRequests();

        $player = Player::factory()->createOne([
            'is_bot' => true,
        ]);

        // Act
        PullAppearance::dispatchSync($player);

        // Assert
        $this->assertDatabaseHas('players', [
            'id' => $player->id,
            'is_bot' => true,
        ]);
    }

    public function test_pulling_assets_down_from_web(): void
    {
        // Arrange
        Bus::fake([
            PullXuid::class,
        ]);
        Storage::fake();
        $mockAppearanceResponse = (new MockAppearanceService)->success('gamertag');
        $mockOptimizedResponse = (new MockImageService)->success();

        $this->instance(
            FileUtilInterface::class,
            Mockery::mock(FileUtilInterface::class, function (Mockery\MockInterface $mock) {
                $mock
                    ->shouldReceive('getFileContents')
                    ->andReturn('example-binary-contents');
            })
        );

        $headers = ['Location' => 'domain.com'];
        Http::fakeSequence()
            ->push($mockAppearanceResponse, Response::HTTP_OK)
            ->push(null, Response::HTTP_OK)
            ->push($mockOptimizedResponse, Response::HTTP_OK, $headers)
            ->push(null, Response::HTTP_OK)
            ->push($mockOptimizedResponse, Response::HTTP_OK, $headers);

        $player = Player::factory()->createOne();

        // Act
        PullAppearance::dispatchSync($player);

        // Assert
        $this->assertDatabaseHas('players', [
            'id' => $player->id,
        ]);
        Bus::assertDispatched(PullXuid::class);
    }

    public function test_invalid_pulling_assets_down_from_web_if_missing_image(): void
    {
        // Arrange
        Bus::fake([
            PullXuid::class,
        ]);
        Storage::fake();
        $mockAppearanceResponse = (new MockAppearanceService)->success('gamertag');

        Http::fakeSequence()
            ->push($mockAppearanceResponse, Response::HTTP_OK)
            ->push(null, Response::HTTP_NOT_FOUND)
            ->push(null, Response::HTTP_NOT_FOUND);

        $player = Player::factory()->createOne();

        // Act
        PullAppearance::dispatchSync($player);

        // Assert
        $this->assertDatabaseHas('players', [
            'id' => $player->id,
        ]);
        Bus::assertDispatched(PullXuid::class);
    }
}
