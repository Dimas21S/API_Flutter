<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Models\Like;
use App\Models\MakeUpArtist;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function userProfile()
    {
        $user = Auth::user();

        return response()->json([
            'success' => true,
            'message' => 'Data profil ditemukan',
            'data'    => $user
        ], 200);
    }

    public function favouriteUser()
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $user = Auth::user();

        // Semua MUA
        $artists = MakeUpArtist::where('status', 'accepted')->get();

        // History user
        // $history = $user->histories()
        //     ->with('makeupartist')
        //     ->whereHas('makeupartist', function ($query) {
        //         $query->where('status', 'accepted');
        //     })
        //     ->latest()
        //     ->take(5)
        //     ->get();

        // Liked
        $likedArtists = Like::where('user_id', $user->id)
            ->with('makeUpArtist')
            ->get()
            ->pluck('makeUpArtist')
            ->filter()
            ->values(); // reset index untuk Flutter

        return response()->json([
            'success' => true,
            'message' => 'Data favourite user berhasil diambil',
            'artists' => $artists,
            // 'history' => $history,
            'liked_artists' => $likedArtists
        ], 200);
    }

    public function userUpdateProfile(Request $request)
    {
        $user = Auth::user();

        // Rules validasi
        $rules = [
            'name' => 'nullable|string',
            'email' => 'nullable|email|unique:users,email,' . $user->id,
            'foto_profil' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'deskripsi' => 'nullable|max:500'
        ];

        $messages = [
            'name.required' => 'Nama wajib diisi',
            'email.required' => 'Email wajib diisi',
            'email.email' => 'Format email tidak valid',
            'email.unique' => 'Email sudah digunakan',
            'foto_profil.image' => 'File harus berupa gambar',
            'foto_profil.mimes' => 'Format gambar harus jpeg, png, jpg, atau gif',
            'foto_profil.max' => 'Ukuran gambar maksimal 2MB'
        ];

        // Validasi
        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Update data user
        $user->name = $request->name;
        $user->email = $request->email;
        $user->deskripsi = $request->deskripsi;

        // Upload file jika ada
        if ($request->hasFile('foto_profil')) {
            $file = $request->file('foto_profil');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('uploads', $filename, 'public');
            $user->foto_profil = $path;
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui!',
            'user' => $user
        ], 200);
    }

    public function showFormPembayaran($id)
    {
        $mua = MakeUpArtist::with('packages')->find($id);

        if (!$mua) {
            return response()->json([
                'success' => false,
                'message' => 'Artist tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $mua->id,
                'name' => $mua->name,
                'category' => $mua->category,
                'biaya_admin' => 2000,
                'packages' => [
                    'id' => $mua->packages->id,
                    'price' => $mua->packages->price,
                ]
            ]
        ], 200);
    }


}
