<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    // =============================================
    // FCM Token Save Karo (Flutter app bhejti hai)
    // POST /api/notifications/fcm-token
    // =============================================
    public function saveFcmToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $request->user()->update([
            'fcm_token' => $request->fcm_token,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'FCM token saved successfully',
        ], 200);
    }

    // =============================================
    // Manual Reminder Test (Development ke liye)
    // POST /api/notifications/test
    // =============================================
    public function testNotification(Request $request)
    {
        $user = $request->user();

        if (!$user->fcm_token) {
            return response()->json([
                'success' => false,
                'message' => 'Pehle FCM token save karo (/api/notifications/fcm-token)',
            ], 400);
        }

        $fcm  = new \App\Services\FCMService();
        $sent = $fcm->sendNotification(
            $user->fcm_token,
            '🔔 Test Notification',
            'Reminder system kaam kar raha hai!',
            ['screen' => 'transaction_list']
        );

        return response()->json([
            'success' => $sent,
            'message' => $sent ? 'Test notification bheji!' : 'Notification fail, FCM token check karo',
        ], $sent ? 200 : 500);
    }
}
