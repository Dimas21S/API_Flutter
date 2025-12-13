<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\MakeUpArtist;
use App\Models\Like;

class LikedController extends Controller
{
    public function toggleLikeApi($artistId)
    {
        $user = Auth::user(); // JWT Auth
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $artist = MakeUpArtist::find($artistId);
        if (!$artist) {
            return response()->json([
                'success' => false,
                'message' => 'Artist tidak ditemukan.'
            ], 404);
        }

        $existingLike = Like::where('user_id', $user->id)
            ->where('make_up_artist_id', $artistId)
            ->first();

        if ($existingLike) {
            $existingLike->delete();

            return response()->json([
                'success' => true,
                'liked' => false,
                'message' => 'Like dihapus.'
            ], 200);
        }

        Like::create([
            'user_id' => $user->id,
            'make_up_artist_id' => $artistId,
        ]);

        return response()->json([
            'success' => true,
            'liked' => true,
            'message' => 'Berhasil menyukai.'
        ], 200);
    }

}
