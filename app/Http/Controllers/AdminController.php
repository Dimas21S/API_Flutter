<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Verification;

class AdminController extends Controller
{
    //
    public function updateStatus(Request $request, $verificationId)
    {
        // 1. Validasi Request
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:accepted,rejected',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        // 2. Cek data verifikasi
        $verification = Verification::find($verificationId);

        if (!$verification) {
            return response()->json([
                'success' => false,
                'message' => 'Data verification tidak ditemukan',
            ], 404);
        }

        // 3. Update status verifikasi
        $verification->status = $request->status;
        $verification->save();

        // 4. Jika diterima → update data artist
        if ($request->status === 'accepted') {

            $artist = $verification->makeUpArtist;

            if ($artist) {
                $artist->status           = 'accepted';
                $artist->name             = $verification->name;
                $artist->email            = $verification->email;
                $artist->phone            = $verification->phone;
                $artist->category         = $verification->category;
                $artist->file_certificate = $verification->file_certificate;
                $artist->description      = $verification->description;
                $artist->save();
            }

            // Hapus verifikasi lain yang statusnya rejected
            Verification::where('make_up_artist_id', $artist->id)
                ->where('status', 'rejected')
                ->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Status artist berhasil diperbarui.',
            'data' => [
                'verification' => $verification,
            ],
        ], 200);
    }

    public function getStatus()
    {
        $verifications = Verification::with([
            'makeUpArtist',
            'makeUpArtist.address'
        ])->paginate(10);

        return response()->json([
            'status'  => true,
            'message' => 'Data verifikasi MUA berhasil diambil',
            'data'    => $verifications
        ], 200);
    }

}
