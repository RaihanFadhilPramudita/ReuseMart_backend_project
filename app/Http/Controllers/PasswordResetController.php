<?php

namespace App\Http\Controllers;

use App\Models\Pembeli;
use App\Models\Penitip;
use App\Models\Organisasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\ResetPasswordMail;
use Carbon\Carbon;

class PasswordResetController extends Controller
{
    /**
     * Send a reset link to the given user's email.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendResetLinkEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'type' => 'required|in:pembeli,penitip,organisasi'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $userType = $request->type;
        $email = $request->email;
        
        // Find the user
        $user = $this->findUserByEmail($email, $userType);
        
        if (!$user) {
            return response()->json(['message' => 'User with this email not found'], 404);
        }
        
        // Generate token
        $token = Str::random(64);
        
        // Store token in database
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'email' => $email,
                'token' => Hash::make($token),
                'created_at' => now()
            ]
        );
        
        // Create reset URL
        $resetUrl = url(route('password.reset', [
            'token' => $token,
            'email' => $email,
            'type' => $userType
        ], false));
        
        try {
            // Send email with reset link
            Mail::to($email)->send(new ResetPasswordMail($resetUrl, $userType, $user));
            
            return response()->json([
                'message' => 'Reset link sent to your email',
                'email' => $email
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to send reset link: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Reset the given user's password.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function reset(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|confirmed|min:6',
            'type' => 'required|in:pembeli,penitip,organisasi'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $userType = $request->type;
        $email = $request->email;
        
        // Find token in database
        $tokenRecord = DB::table('password_reset_tokens')
                       ->where('email', $email)
                       ->first();
                       
        if (!$tokenRecord) {
            return response()->json(['message' => 'Invalid token or email'], 400);
        }
        
        // Check if token is valid and not expired
        if (!Hash::check($request->token, $tokenRecord->token)) {
            return response()->json(['message' => 'Invalid token'], 400);
        }
        
        if (Carbon::parse($tokenRecord->created_at)->addMinutes(60)->isPast()) {
            return response()->json(['message' => 'Token has expired'], 400);
        }
        
        // Find the user
        $user = $this->findUserByEmail($email, $userType);
        
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        
        // Update password
        $user->PASSWORD = Hash::make($request->password);
        $user->save();
        
        // Delete token
        DB::table('password_reset_tokens')
          ->where('email', $email)
          ->delete();
        
        return response()->json(['message' => 'Password reset successfully']);
    }
    
    /**
     * Find a user by type and email.
     *
     * @param  string  $email
     * @param  string  $userType
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    private function findUserByEmail($email, $userType)
    {
        switch ($userType) {
            case 'pembeli':
                return Pembeli::where('EMAIL', $email)->first();
            case 'penitip':
                return Penitip::where('EMAIL', $email)->first();
            case 'organisasi':
                return Organisasi::where('EMAIL', $email)->first();
            default:
                return null;
        }
    }
}