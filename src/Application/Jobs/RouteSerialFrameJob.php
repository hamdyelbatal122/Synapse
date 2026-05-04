<?php

declare(strict_types=1);

namespace Hamzi\PortFlow\Application\Jobs;

use Hamzi\PortFlow\Application\Services\MessageRouter;
use Hamzi\PortFlow\Domain\DTO\SerialFrame;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class RouteSerialFrameJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int Max attempts before the job is marked as failed. */
    public int $tries = 3;

    public function __construct(public readonly SerialFrame $frame) {}

    public function handle(MessageRouter $router): void
    {
        $router->routeSync($this->frame);
    }
}
