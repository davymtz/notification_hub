<?php

namespace App\Actions;

use App\DTO\NotificationDTO;
use App\Services\NotificationRouterService;

class DispatchNotificationAction
{
    public function __construct(
        private NotificationRouterService $router
    ) {}

    /**
     * Execute the notification dispatch action
     *
     * @param array $validatedData
     * @return array
     */
    public function execute(array $validatedData): array
    {
        $dto = NotificationDTO::fromArray($validatedData);
        
        return $this->router->dispatch($dto);
    }
}
