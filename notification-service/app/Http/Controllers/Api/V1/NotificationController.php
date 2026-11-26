<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\NotificationLogRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\NotificationIndexRequest;
use App\Http\Resources\NotificationLogResource;
use App\Services\Notifications\NotificationDispatcher;
use Ecommerce\PlatformKit\Laravel\CurrentAccess;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationLogRepository $logs,
        private readonly NotificationDispatcher $notifications,
    ) {}

    public function index(NotificationIndexRequest $request): AnonymousResourceCollection
    {
        return NotificationLogResource::collection(
            $this->logs->paginate($request->filters()),
        );
    }

    public function show(string $id): NotificationLogResource
    {
        return new NotificationLogResource($this->logs->findOrFail($id));
    }

    public function replay(string $id, CurrentAccess $access): NotificationLogResource
    {
        $log = $this->logs->findOrFail($id);
        $actorId = $access->claims()?->userId;

        return new NotificationLogResource(
            $this->notifications->replay($log, $actorId),
        );
    }
}
