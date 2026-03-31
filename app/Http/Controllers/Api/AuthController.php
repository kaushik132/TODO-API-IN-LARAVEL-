<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\OtpCode;
use App\Mail\OtpMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // =============================================
    // 1. SIGN UP
    // =============================================
    public function signUp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully',
            'data'    => [
                'user'  => $user,
                'token' => $token,
            ],
        ], 201);
    }

    // =============================================
    // 2. SIGN IN
    // =============================================
    public function signIn(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Check email
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password',
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data'    => [
                'user'  => $user,
                'token' => $token,
            ],
        ], 200);
    }

    // =============================================
    // 3. FORGOT PASSWORD - Send OTP to Email
    // =============================================
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Delete previous OTPs for this email
        OtpCode::where('email', $request->email)->delete();

        // Generate 6-digit OTP
        $otp = rand(100000, 999999);

        // Save OTP in DB (expires in 10 minutes)
        OtpCode::create([
            'email'      => $request->email,
            'otp'        => $otp,
            'expires_at' => now()->addMinutes(10),
        ]);

        // Send OTP via Email
        Mail::to($request->email)->send(new OtpMail($otp));

        return response()->json([
            'success' => true,
            'message' => 'OTP sent to your email. Valid for 10 minutes.',
        ], 200);
    }

    // =============================================
    // 4. VERIFY OTP
    // =============================================
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp'   => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $otpRecord = OtpCode::where('email', $request->email)
                            ->where('otp', $request->otp)
                            ->first();

        if (!$otpRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP',
            ], 400);
        }

        if ($otpRecord->expires_at < now()) {
            return response()->json([
                'success' => false,
                'message' => 'OTP has expired. Please request a new one.',
            ], 400);
        }

        // Generate a reset token to use in next step
        $resetToken = Str::random(60);

        $otpRecord->update([
            'is_verified'  => true,
            'reset_token'  => $resetToken,
        ]);

        return response()->json([
            'success'     => true,
            'message'     => 'OTP verified successfully',
            'reset_token' => $resetToken,
        ], 200);
    }

    // =============================================
    // 5. RESET PASSWORD (after OTP verify)
    // =============================================
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'            => 'required|email',
            'reset_token'      => 'required|string',
            'password'         => 'required|string|min:6',
            'confirm_password' => 'required|same:password',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $otpRecord = OtpCode::where('email', $request->email)
                            ->where('reset_token', $request->reset_token)
                            ->where('is_verified', true)
                            ->first();

        if (!$otpRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset token',
            ], 400);
        }

        // Update password
        User::where('email', $request->email)->update([
            'password' => Hash::make($request->password),
        ]);

        // Delete OTP record
        $otpRecord->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully. You can now login.',
        ], 200);
    }

    // =============================================
    // 6. UPDATE PROFILE (with Avatar Upload) ✅ NEW
    // =============================================
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name'     => 'nullable|string|max:255',
            'email'    => 'nullable|email|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:6',
            'avatar'   => 'nullable|image|mimes:jpg,jpeg,png|max:2048', // Max 2MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Update name
        if ($request->has('name')) {
            $user->name = $request->name;
        }

        // Update email
        if ($request->has('email')) {
            $user->email = $request->email;
        }

        // Update password
        if ($request->has('password')) {
            $user->password = Hash::make($request->password);
        }

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            // Upload new avatar
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = $avatarPath;
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data'    => [
                'user'       => $user,
                'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
            ],
        ], 200);
    }

    // =============================================
    // 7. DELETE ACCOUNT ✅ NEW
    // =============================================
    public function deleteAccount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        // Verify password before deletion
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid password. Account deletion failed.',
            ], 401);
        }

        // Delete avatar if exists
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        // Delete all user's tokens
        $user->tokens()->delete();

        // Delete all related data (add more as per your needs)
        // Example: Delete user's contacts, transactions, reminders, etc.
        // $user->contacts()->delete();
        // $user->transactions()->delete();
        // $user->reminders()->delete();

        // Delete OTP records
        OtpCode::where('email', $user->email)->delete();

        // Finally, delete the user
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Account deleted successfully',
        ], 200);
    }

    // =============================================
    // 8. LOGOUT
    // =============================================
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ], 200);
    }
}
