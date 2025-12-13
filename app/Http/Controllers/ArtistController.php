<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\Jadwal;
use App\Models\MakeUpArtist;
use App\Models\Message;
use App\Models\UserHistory;
use App\Models\Package;

use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ArtistController extends Controller
{
    //
    public function listMakeUpArtist(Request $request)
    {
        // Ambil query dasar: hanya artist yang sudah diterima
        $query = MakeUpArtist::where('status', 'accepted');

        // Filter kategori jika ada di query params
        if ($request->has('category') && $request->category != '') {
            $query->where('category', $request->category);
        }

        // Pagination API (10 per halaman)
        $artists = $query->with('packages')->paginate(10);        

        return response()->json([
            'success' => true,
            'message' => 'Daftar Make Up Artist berhasil diambil.',
            'data' => $artists,
        ], 200);
    }

    public function artistDescription(Request $request, $id)
    {
        // Ambil artist atau gagal 404
        $artist = MakeUpArtist::findOrFail($id);

        $user = $request->user(); // dari Sanctum / token Flutter

        $likedArtistIds = [];
        $alreadyLiked = false;

        if ($user) {
            // Ambil daftar ID MUA yang disukai user
            $likedArtistIds = $user->likes()->pluck('make_up_artist_id')->toArray();
            $alreadyLiked = in_array($artist->id, $likedArtistIds);

            // Cek keberadaan history
            $exists = UserHistory::where('user_id', $user->id)
                ->where('make_up_artist_id', $artist->id)
                ->first();

            // Simpan jika belum ada
            if (!$exists) {
                UserHistory::create([
                    'user_id' => $user->id,
                    'make_up_artist_id' => $artist->id,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail artist berhasil diambil',
            'data' => [
                'artist' => $artist,
                'liked' => $alreadyLiked,
                'likedArtistIds' => $likedArtistIds
            ]
        ], 200);
    }

    public function getSettingPrice()
    {
        $mua = auth('makeup_artist')->user();

        if (!$mua) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);
        }

        return response()->json([
            'message' => 'Data berhasil diambil',
            'data' => [
                'id' => $mua->id,
                'name' => $mua->name,
                'description' => $mua->description,
                'package' => $mua->package, // jika hasOne
            ]
        ], 200);
    }

    public function updateSettingPrice(Request $request)
    {
        // Validasi
        $request->validate([
            'category' => 'nullable|in:Pesta dan Acara,Pengantin,Editorial,Artistik',
            'price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string|max:1000',
            'add_description' => 'nullable|string|max:1000',
        ]);

        // Ambil MUA login (JWT)
        $mua = auth('makeup_artist')->user();

        if (!$mua) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 401);
        }

        // Update / Create Package
        $package = Package::updateOrCreate(
            ['make_up_artist_id' => $mua->id],
            ['price' => $request->price]
        );

        // Update kategori (opsional)
        if ($request->filled('category')) {
            $mua->update(['category' => $request->category]);
        }

        // Deskripsi detail
        $desc = $mua->detailDescription;

        if ($desc) {
            // Update
            $desc->update([
                'description' => $request->description,
                'description_tambahan' => $request->add_description,
            ]);
        } else {
            // Create
            $mua->detailDescription()->create([
                'description' => $request->description,
                'description_tambahan' => $request->add_description,
            ]);
        }

        return response()->json([
            'message' => 'Data berhasil diperbarui!',
            'data' => [
                'category' => $mua->category,
                'package' => $package,
                'description' => $mua->detailDescription,
            ]
        ], 200);
    }

    public function logout()
    {
        try {
            // Ambil token dari header
            $token = JWTAuth::getToken();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token tidak ditemukan.'
                ], 400);
            }

            // Blacklist token
            JWTAuth::invalidate($token);

            return response()->json([
                'success' => true,
                'message' => 'Logout berhasil.'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal logout: ' . $e->getMessage()
            ], 500);
        }
    }

    public function listAddressMakeUpArtist(Request $request)
    {
        $artistStatus = MakeUpArtist::with('address')
            ->where('status', 'accepted');

        // 🔍 Search
        if ($request->filled('search')) {
            $search = $request->search;

            $artistStatus->where(function ($query) use ($search) {
                $query->where('name', 'like', "%$search%")
                    ->orWhere('category', 'like', "%$search%")
                    ->orWhereHas('address', function ($q) use ($search) {
                        $q->where('kota', 'like', "%$search%");
                    });
            });
        }

        // 📍 Filter lokasi slug
        if ($request->filled('location') && $request->location !== 'all') {
            $location = strtolower($request->location);

            $artistStatus->whereHas('address', function ($q) use ($location) {
                $q->whereRaw('LOWER(REPLACE(kecamatan, " ", "-")) = ?', [$location]);
            });
        }

        // 📍 Filter kecamatan exact
        if ($request->filled('kecamatan') && $request->kecamatan !== 'all') {
            $artistStatus->whereHas('address', function ($q) use ($request) {
                $q->where('kecamatan', $request->kecamatan);
            });
        }

        // Pagination
        $artist = $artistStatus->paginate(10);

        return response()->json([
            'success' => true,
            'message' => 'Data MUA berhasil diambil.',
            'data' => $artist->items(),
            'pagination' => [
                'current_page' => $artist->currentPage(),
                'last_page' => $artist->lastPage(),
                'per_page' => $artist->perPage(),
                'total' => $artist->total(),
            ]
        ], 200);
    }

    public function artistIndex()
    {
        $artist = Auth::guard('makeup_artist')->user();

        if (!$artist) {
            return response()->json([
                'status'  => false,
                'message' => 'Artist belum login'
            ], 401);
        }

        // Load relasi yang dibutuhkan supaya tidak null
        $artist->load(['jadwal', 'packages', 'photos', 'address']);

        return response()->json([
            'status'  => true,
            'message' => 'Data artist berhasil diambil',
            'data'    => [
                'id'            => $artist->id,
                'name'          => $artist->name,
                'email'         => $artist->email,
                'phone'         => $artist->phone,
                'gender'        => $artist->gender,
                'description'   => $artist->description,
                'category'      => $artist->category,
                'profile_photo' => $artist->profile_photo,
                'jadwal' => $artist->jadwal ? $artist->jadwal->map(function ($jadwal) {
                        return [
                            'id'        => $jadwal->id,
                            'hari'      => $jadwal->hari,
                            'jam_buka'  => $jadwal->jam_buka,
                            'jam_tutup' => $jadwal->jam_tutup,
                        ];
                    }) : [],

                'packages' => $artist->packages ? [
                    'id'    => $artist->packages->id,
                    'price' => $artist->packages->price,
                ] : null,
                'photos' => $artist->photos ? $artist->photos->map(function ($photo) {
                    return [
                        'id'             => $photo->id,
                        'image_path'     => $photo->image_path,
                        'thumbnail_path' => $photo->thumbnail_path,
                    ];
                })->toArray() : [],
                'address' => $artist->address ? [
                    'id'       => $artist->address->id,
                    'city'     => $artist->address->city,
                    'province' => $artist->address->province,
                    'detail'   => $artist->address->detail,
                    'link_map' => $artist->address->link_map ?? null,
                ] : null,
                'created_at' => $artist->created_at,
            ]
        ], 200);
    }


    public function receivedMessagesApi()
    {
        $mua = Auth::guard('makeup_artist')->user();

        if (!$mua) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        // Ambil pesan terbaru dari setiap pengirim
        $latestMessages = Message::where('receiver_id', $mua->id)
            ->where('receiver_type', 'make_up_artist')
            ->select('sender_id', 'sender_type', DB::raw('MAX(created_at) as latest_time'))
            ->groupBy('sender_id', 'sender_type')
            ->orderBy('latest_time', 'desc')
            ->get();

        // Ambil detail lengkap dari setiap pesan terbaru
        $messages = collect();
        foreach ($latestMessages as $latest) {
            $message = Message::where('receiver_id', $mua->id)
                ->where('receiver_type', 'make_up_artist')
                ->where('sender_id', $latest->sender_id)
                ->where('sender_type', $latest->sender_type)
                ->where('created_at', $latest->latest_time)
                ->first();

            if ($message) {
                $messages->push($message);
            }
        }

        // Total semua pesan masuk
        $totalMessages = Message::where('receiver_id', $mua->id)
            ->where('receiver_type', 'make_up_artist')
            ->count();

        // Mapping jumlah pesan per sender
        $messageCounts = Message::where('receiver_id', $mua->id)
            ->where('receiver_type', 'make_up_artist')
            ->groupBy('sender_id')
            ->select('sender_id', DB::raw('count(*) as total'))
            ->pluck('total', 'sender_id');

        return response()->json([
            'status' => true,
            'message' => 'Data pesan berhasil diambil',
            'data' => [
                'latest_messages' => $messages,
                'total_messages'  => $totalMessages,
                'message_counts'  => $messageCounts
            ]
        ], 200);
    }

    public function formSubmitRequest(Request $request)
    {
        // Validator ringan
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|string',
            'phone' => 'required|string',
            'link_map' => 'nullable|string',
            'phone' => 'required|string|max:15',
            'category' => 'required|in:Pesta dan Acara,Pengantin,Artistik',
            'portfolio' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5048',
            'deskripsi' => 'nullable|string|max:1000'
            // lainnya opsional
        ]);

        $artist = auth()->guard('makeup_artist')->user();

        if (!$artist) {
            return response()->json([
                "success" => false,
                "message" => "Unauthenticated makeup artist."
            ], 401);
        }

        // Upload file jika ada
        $path = null;
        if ($request->hasFile('portfolio')) {
            $file = $request->file('portfolio');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('uploads', $filename, 'public');
        }

        // Update atau buat alamat
        $artist->address()->updateOrCreate([], [
            'link_map' => $request->link_map,
        ]);

        // Simpan data verifikasi
        $verification = $artist->verification()->create([
            'make_up_artist_id' => $artist->id,
            'username' => $artist->username,
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'category' => $request->category,
            'file_certificate' => $path,
            'description' => $request->deskripsi,
            'password' => $artist->password,
            'status' => 'pending',
        ]);

        return response()->json([
            "success" => true,
            "message" => "Form pendaftaran berhasil dikirim.",
            "data" => $verification
        ], 201);
    }

    public function updateMakeUpArtist(Request $request)
    {
        $mua = Auth::guard('makeup_artist')->user();

        // Validasi input
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:15',
            'link_map' => 'nullable|url',
            'category' => 'required|in:Pesta dan Acara,Pengantin,Editorial,Artistik',
            'description' => 'nullable|string|max:1000',
            'photos' => 'nullable|array',
            'photos.*' => 'file|mimes:jpg,jpeg,png|max:2048',
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'jadwal' => 'nullable|array',
            'harga' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Upload profile photo
        if ($request->hasFile('profile_photo')) {
            $file = $request->file('profile_photo');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('uploads', $filename, 'public');
            $mua->profile_photo = asset('storage/' . $path);
        }

        // Upload multiple photos
        if ($request->hasFile('photos')) {
            $photos = $request->file('photos');
            foreach ($photos as $photo) {
                $filename = time() . '_' . $photo->getClientOriginalName();
                $path = $photo->storeAs('uploads', $filename, 'public');

                $mua->photos()->create([
                    'image_path' => asset('storage/' . $path),
                ]);
            }
        }

        // Update jadwal
        if ($request->has('jadwal')) {
            Jadwal::where('make_up_artist_id', $mua->id)->delete();

            foreach ($request->jadwal as $data) {
                Jadwal::create([
                    'make_up_artist_id' => $mua->id,
                    'hari' => $data['hari'],
                    'jam_buka' => $data['jam_buka'],
                    'jam_tutup' => $data['jam_tutup'],
                ]);
            }
        }

        // Update harga paket
        if ($request->has('harga')) {
            Package::updateOrCreate(
                ['make_up_artist_id' => $mua->id],
                ['price' => $request->harga]
            );
        }

        // Update data MUA
        $mua->update([
            'name' => $request->name,
            'phone' => $request->phone,
            'category' => $request->category,
            'description' => $request->description,
        ]);

        // Update address
        $mua->address()->updateOrCreate([], [
            'link_map' => $request->link_map,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui',
            'user' => $mua->load('photos', 'address', 'jadwal') // include relasi jika perlu
        ], 200);
    }



}
