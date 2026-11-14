<?php

namespace App\Http\Controllers\V1;

use App\Actions\DispatchNotificationAction;
use App\Http\Requests\DispatchNotificationRequest;
use Illuminate\Http\JsonResponse;

class NotificationController
{
    /**
     * Dispatch notifications to specified channels
     *
     * @param DispatchNotificationRequest $request
     * @param DispatchNotificationAction $action
     * @return JsonResponse
     */
    public function dispatch(DispatchNotificationRequest $request, DispatchNotificationAction $action): JsonResponse
    {
        $result = $action->execute($request->validated());

        $statusCode = ($result['status'] === 'duplicate') ? 200 : 202;

        return response()->json($result, $statusCode);
    }
}
