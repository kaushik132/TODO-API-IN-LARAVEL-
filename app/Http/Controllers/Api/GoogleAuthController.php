<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleAuthController extends Controller
{
    // =============================================
    // GOOGLE LOGIN - Flutter app se id_token aata hai
    // POST /api/auth/google-login
    // =============================================
    public function googleLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // ✅ Google se token verify karo
        $googleUser = $this->verifyGoogleToken($request->id_token);

        if (!$googleUser) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Google token. Please try again.',
            ], 401);
        }

        $googleId   = $googleUser['sub'];
        $email      = $googleUser['email'];
        $name       = $googleUser['name'];
        $avatar     = $googleUser['picture'] ?? null;
        $isVerified = $googleUser['email_verified'] ?? false;

        if (!$isVerified) {
            return response()->json([
                'success' => false,
                'message' => 'Google email is not verified.',
            ], 401);
        }

        // ✅ User check karo
        $user = User::where('google_id', $googleId)
                    ->orWhere('email', $email)
                    ->first();

        if ($user) {
            if (!$user->google_id) {
                $user->update([
                    'google_id' => $googleId,
                    'avatar'    => $user->avatar ?? $avatar,
                ]);
            }
            $isNewUser = false;
        } else {
            $user = User::create([
                'name'      => $name,
                'email'     => $email,
                'google_id' => $googleId,
                'avatar'    => $avatar,
                'password'  => bcrypt(Str::random(24)),
            ]);
            $isNewUser = true;
        }

        $token = $user->createToken('google_auth_token')->plainTextToken;

        return response()->json([
            'success'     => true,
            'message'     => $isNewUser
                ? 'Account created successfully via Google'
                : 'Google login successful',
            'is_new_user' => $isNewUser,
            'data'        => [
                'user'  => [
                    'id'         => $user->id,
                    'name'       => $user->name,
                    'email'      => $user->email,
                    'avatar_url' => $user->avatar
                        ? (filter_var($user->avatar, FILTER_VALIDATE_URL)
                            ? $user->avatar
                            : asset('storage/' . $user->avatar))
                        : null,
                ],
                'token' => $token,
            ],
        ], 200);
    }

    // =============================================
    // PRIVATE: Google Token Verify
    // =============================================
    private function verifyGoogleToken(string $idToken): ?array
    {
        try {
            $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || !$response) {
                Log::error('Google token verify failed. HTTP: ' . $httpCode);
                return null;
            }

            $data = json_decode($response, true);

            if (isset($data['error_description'])) {
                Log::error('Google token error: ' . $data['error_description']);
                return null;
            }

            // Client ID verify karo
            $validClientId = env('GOOGLE_CLIENT_ID');
            if ($validClientId && $data['aud'] !== $validClientId) {
                Log::error('Google token aud mismatch.');
                return null;
            }

            return $data;

        } catch (\Exception $e) {
            Log::error('Google verify exception: ' . $e->getMessage());
            return null;
        }
    }
}
