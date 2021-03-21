<?php

namespace App\Http\Controllers;

use App\Mail\Passcode;
use App\Mail\PasswordResetCode;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    /**
     * Login
     *
     * Log a user into the system.
     * @bodyParam email string required Email address.
     * @bodyParam password string required Password.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'email' => 'required|email|exists:users',
                'password' => 'required|min:8',
            ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        if (Auth::attempt(['email' => request('email'), 'password' => request('password')])) {
            $user = Auth::user();
            if (env('APP_ENV') == 'production') {
                $token = $this->getToken($request->email, $request->password);
            }
            else {
                //mimic production response
                $token = [
                    "token_type"=>"Bearer",
                    "expires_in"=>604800,
                    "access_token"=>$user->createToken('authToken')->accessToken
                ];
            }
            return response()->json(
                [
                    'app_env'=>env('APP_ENV'),
                    'token' => $token,
                    'email_verified_at' => $user->email_verified_at,
                    'fname' => $user->name
                ],
                Response::HTTP_OK
            );
        }
        return response()->json(['message' => 'Invalid credentials'], Response::HTTP_BAD_REQUEST);
    }

    /**
     * Register User
     *
     * Registers user to the system.
     * @bodyParam name string required User Name.
     * @bodyParam email string required Email address.
     * @bodyParam password string required Password min 8 characters.
     * @bodyParam password_confirm string required Password, must match password.
     */
    public function register(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'name' => 'required',
                'email' => 'required|email|unique:users',
                'password' => 'required|min:8',
                'password_confirm' => 'required|same:password',
            ]);
        if ($validator->fails())
        {
            return response()->json(['message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        $input = $request->only(
            'name', 'email', 'password'
        );

        $input['password'] = Hash::make($input['password']);
        $user = null;
        try {
            $user = User::create($input);
        } catch (\Exception $exception) {
            return response()->json(['message' => $exception], Response::HTTP_BAD_REQUEST);
        }

        if (env('APP_ENV') == 'production') {
            $token = $this->getToken($request->email, $request->password);
        }
        else {
            $token = $user->createToken('authToken');
        }

        return response()->json(
            [
                'token' => $token,
                'email_verified_at' => $user->email_verified_at,
                'fname' => $user->name
            ],
            Response::HTTP_OK
        );
    }

    /**
     * Email Unique Check
     *
     * Check whether the provided email in user registration form is unique.
     * @bodyParam email string required Email address.
     */
    public function emailUnique(Request $request)
    {
        $unique = !User::where('email', $request->email)->exists();
        return response()->json(['message' => $unique], Response::HTTP_OK);
    }

    /**
     * Get Laravel Passport Token
     *
     * @param $username
     * @param $password
     * @return mixed
     */
    protected function getToken($username, $password)
    {
            $http = new Client();
            $form_params = [
                'form_params' => [
                    'grant_type' => 'password',
                    'client_id' => config('app.passport_client_id'),
                    'client_secret' => config('app.passport_client_secret'),
                    'username' => $username,
                    'password' => $password,
                    'scope' => '*',
                ]
            ];
            $response = $http->post(
                config('app.token_url'),
                $form_params
            );

            return json_decode((string)$response->getBody(), true);
    }

    /**
     * Send Password Reset Token
     *
     * Send password reset token via Email to the user with provided email address.
     * @bodyParam email string required Email address.
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(),['email' => 'required|email']);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }

        $exists = User::where('email', $request->email)->exists();
        if (!$exists) {
            return response()->json(['message' => 'User does not exist'], Response::HTTP_BAD_REQUEST);
        }
        $token = $this->token();
        $user = User::where('email', $request->email)->first();
        try {
            DB::table('password_resets')->insert([
                'email' => $user->email,
                'token' => $token
            ]);

            $details = [
                'fname' => $user->name,
                'token' => $token,
                'to' => $user->email,
            ];
            Mail::send(new PasswordResetCode($details));
        } catch (\Exception $e) {
            info($e->getMessage());
            return response()->json(['message' => 'Error sending token'], Response::HTTP_BAD_REQUEST);
        }
        return response()->json(['message' => 'Token sent to '.$request->email], Response::HTTP_OK);
    }

    /**
     * Update Password
     *
     * Update user's password after token verification.
     * @bodyParam email string required Email address.
     * @bodyParam token string required Email token.
     * @bodyParam password string required Password.
     * @bodyParam password_confirm string required Password, must match password.
     */
    public function updatePassword(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'email' => 'required',
                'token' => 'required',
                'password' => 'required',
                'password_confirm' => 'required|same:password',
            ]);
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
        }
        if (!$this->verifyToken($request->email, $request->token)) {
            return response()->json(['message' => 'Invalid token'], Response::HTTP_BAD_REQUEST);
        }
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['message' => 'User not found with that email'], Response::HTTP_BAD_REQUEST);
        }
        $user->update(['password' => Hash::make($request->password)]);
        DB::table('password_resets')->where('email', $request->email)->delete();
        $details = [
            'fname' => $user->name,
            'to' => $user->email,
        ];
        Mail::send(new \App\Mail\PasswordReset($details));
        return response()->json(['message' => 'Password has been reset'], Response::HTTP_OK);
    }

    protected function token($min = 10000, $max = 99999)
    {
        return mt_rand($min, $max);
    }

    protected function verifyToken($email, $token)
    {
        return DB::table('password_resets')
            ->where('token', $token)
            ->where('email', $email)
            ->exists();
    }
}
