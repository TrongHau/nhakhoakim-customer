<?php

namespace App\Http\Controllers\InsuranceHub;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InsuranceNotificationController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    // GET /customer/insurance-hub/notifications
    public function index(Request $request): JsonResponse
    {
        // Placeholder — tích hợp notification system thực tế
        return response()->json([
            'success' => true,
            'data'    => ['items' => [], 'total' => 0, 'unread' => 0],
        ]);
    }

    // PUT /customer/insurance-hub/notifications/:id
    public function markRead(Request $request, int $id): JsonResponse
    {
        return response()->json(['success' => true, 'data' => ['marked' => true]]);
    }
}
