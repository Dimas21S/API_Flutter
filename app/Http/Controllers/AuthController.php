<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;

use App\Models\User;
use App\Models\MakeUpArtist;

class AuthController extends Controller
{
    // ====================== LOGIN ======================
    public function login(Request $request)
    {
        $data = $request->json()->all();

        $validator = Validator::make($data, [
            'name'     => 'required|string',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $credentials = [
            'name' => $data['name'],
            'password' => $data['password']
        ];

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Name atau password salah.'
            ], 401);
        }

        $user = Auth::user();

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'user' => $user,
            'role' => $user->role ?? null,
            'token' => $token,
        ], 200);
    }


    // ====================== REGISTER ======================
    public function register(Request $request)
    {        
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = User::create([
            'name'     => $request->username,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Registrasi berhasil',
            'data'    => $user
        ], 201);
    }

    // ====================== LOGOUT JWT ======================
    public function logout(Request $request)
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken()); // hancurkan token

            return response()->json([
                'status'  => true,
                'message' => 'Logout berhasil'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Logout gagal atau token tidak valid.'
            ], 500);
        }
    }

    // ====================== MUA LOGIN ======================
    public function artistLoginApi(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|min:8',
        ]);

        // Coba login dengan guard `makeup_artist`
        if (!$token = Auth::guard('makeup_artist')->attempt($request->only('username', 'password'))) {
            return response()->json([
                'status'  => false,
                'message' => 'Login gagal, username atau password salah.'
            ], 401);
        }

        $mua = Auth::guard('makeup_artist')->user();

        // Jika account belum diterima admin
        // if ($mua->status !== 'accepted') {
        //     return response()->json([
        //         'status'  => false,
        //         'message' => 'Akun Anda belum diterima, silakan lengkapi pendaftaran.'
        //     ], 403);
        // }

        return response()->json([
            'success'  => true,
            'message' => 'Login berhasil sebagai MUA',
            'user'    => $mua,
            'status'    => $mua->status,
            'token'   => $token // penting untuk autentikasi Flutter
        ], 200);
    }

    // ====================== MUA REGISTER ======================
    public function artistRegisterApi(Request $request)
    {
        Log::info('REQ BODY', request()->all());

        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255',
            'email'    => 'required|email|unique:make_up_artists,email',
            'address'  => 'required',
            'password' => 'required|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        $artist = MakeUpArtist::create([
            'username' => $request->username,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'status'   => 'pending', // belum bisa login sebelum verified admin
        ]);

        $artist->address()->create([
            'kecamatan' => $request->address
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Registrasi berhasil, menunggu verifikasi admin',
            'data'    => $artist
        ], 201);
    }

}
