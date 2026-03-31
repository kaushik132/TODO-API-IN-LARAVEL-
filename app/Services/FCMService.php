<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FCMService
{
    // =============================================
    // FCM v1 API se Push Notification Bhejo
    // =============================================

    public function sendNotification(
        string $fcmToken,
        string $title,
        string $body,
        array $data = []
    ): bool {
        try {
            $accessToken = $this->getAccessToken();

            if (!$accessToken) {
                Log::error('FCM: Access token nahi mila');
                return false;
            }

            $projectId = env('FIREBASE_PROJECT_ID');

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type'  => 'application/json',
            ])->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'message' => [
                    'token' => $fcmToken,
                    'notification' => [
                        'title' => $title,
                        'body'  => $body,
                    ],
                    'android' => [
                        'notification' => [
                            'sound'        => 'default',
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        ],
                        'priority' => 'high',
                    ],
                    'apns' => [
                        'payload' => [
                            'aps' => [
                                'sound' => 'default',
                                'badge' => 1,
                            ],
                        ],
                    ],
                    'data' => array_map('strval', $data), // FCM needs string values
                ],
            ]);

            if ($response->successful()) {
                Log::info('FCM: Notification bheji', ['token' => substr($fcmToken, 0, 20) . '...']);
                return true;
            }

            Log::error('FCM Error:', $response->json());
            return false;

        } catch (\Exception $e) {
            Log::error('FCM Exception: ' . $e->getMessage());
            return false;
        }
    }

    // =============================================
    // Firebase Service Account se OAuth2 Token Lo
    // =============================================
    private function getAccessToken(): ?string
    {
        try {
            $credentialsPath = storage_path('app/firebase/service-account.json');

            if (!file_exists($credentialsPath)) {
                Log::error('FCM: service-account.json nahi mila at ' . $credentialsPath);
                return null;
            }

            $credentials = json_decode(file_get_contents($credentialsPath), true);

            // JWT banao
            $now = time();
            $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $payload = base64_encode(json_encode([
                'iss'   => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud'   => 'https://oauth2.googleapis.com/token',
                'iat'   => $now,
                'exp'   => $now + 3600,
            ]));

            $signInput = $header . '.' . $payload;
            openssl_sign($signInput, $signature, $credentials['private_key'], 'SHA256');
            $jwt = $signInput . '.' . base64_encode($signature);

            // Token lo
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]);

            return $response->json('access_token');

        } catch (\Exception $e) {
            Log::error('FCM Token Error: ' . $e->getMessage());
            return null;
        }
    }
}
