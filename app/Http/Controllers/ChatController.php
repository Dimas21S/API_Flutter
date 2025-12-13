<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

use App\Models\MakeUpArtist;
use App\Models\Message;
use App\Models\User;

class ChatController extends Controller
{
    //
    public function getUserToMuaApi($mua_id)
    {
        // DEBUG: Log request
        Log::info('Chat API Request', [
            'method' => request()->method(),
            'mua_id' => $mua_id,
            'bearer_token' => request()->bearerToken(),
            'headers' => request()->headers->all(),
        ]);
        
        // GANTI INI: Gunakan api guard untuk JWT
        $user = Auth::guard('api')->user();
        
        Log::info('Auth check', [
            'user' => $user ? $user->id : 'null',
            'user_object' => $user,
        ]);

        if (!$user) {
            Log::warning('Unauthenticated user', [
                'token' => request()->bearerToken(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'User tidak terautentikasi',
                'token_provided' => !empty(request()->bearerToken()),
                'auth_method' => 'api'
            ], 401);
        }

        // Cari MUA - asumsi model MakeUpArtist extends User
        $mua = MakeUpArtist::find($mua_id);
        
        // Atau jika MakeUpArtist adalah user dengan role tertentu:
        // $mua = User::where('id', $mua_id)->where('role', 'make_up_artist')->first();

        if (!$mua) {
            Log::warning('MUA not found', ['mua_id' => $mua_id]);
            return response()->json([
                'success' => false,
                'message' => 'Make-Up Artist tidak ditemukan'
            ], 404);
        }

        Log::info('Getting messages', [
            'user_id' => $user->id,
            'mua_id' => $mua->id,
            'user_name' => $user->name,
            'mua_name' => $mua->name,
        ]);

        // Ambil semua pesan antara user dan MUA
        $messages = Message::where(function ($query) use ($user, $mua) {
                $query->where('sender_id', $user->id)
                    ->where('sender_type', 'user')
                    ->where('receiver_id', $mua->id)
                    ->where('receiver_type', 'make_up_artist');
            })->orWhere(function ($query) use ($user, $mua) {
                $query->where('sender_id', $mua->id)
                    ->where('sender_type', 'make_up_artist')
                    ->where('receiver_id', $user->id)
                    ->where('receiver_type', 'user');
            })
            ->with(['sender', 'receiver']) // Tambahkan eager loading
            ->orderBy('created_at', 'asc')
            ->get();

        // Debug messages
        Log::info('Messages found', [
            'count' => $messages->count(),
            'messages_sample' => $messages->take(3)->map(function ($msg) {
                return [
                    'id' => $msg->id,
                    'message' => $msg->message,
                    'sender_id' => $msg->sender_id,
                    'sender_type' => $msg->sender_type,
                    'receiver_id' => $msg->receiver_id,
                    'receiver_type' => $msg->receiver_type,
                ];
            })
        ]);

        // Tandai pesan MUA yang diterima user sebagai dibaca
        Message::where('sender_id', $mua->id)
            ->where('sender_type', 'make_up_artist')
            ->where('receiver_id', $user->id)
            ->where('receiver_type', 'user')
            ->where('is_read', false)
            ->update(['is_read' => true]);

        // Format response dengan data lengkap
        $formattedMessages = $messages->map(function ($message) use ($user) {
            return [
                'id' => $message->id,
                'message' => $message->message,
                'sender_id' => $message->sender_id,
                'sender_type' => $message->sender_type,
                'sender_data' => $message->sender ? [
                    'id' => $message->sender->id,
                    'name' => $message->sender->name ?? $message->sender->username,
                    'profile_photo' => $message->sender->profile_photo,
                ] : null,
                'receiver_id' => $message->receiver_id,
                'receiver_type' => $message->receiver_type,
                'receiver_data' => $message->receiver ? [
                    'id' => $message->receiver->id,
                    'name' => $message->receiver->name ?? $message->receiver->username,
                    'profile_photo' => $message->receiver->profile_photo,
                ] : null,
                'is_read' => $message->is_read,
                'created_at' => $message->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $message->updated_at->format('Y-m-d H:i:s'),
                'is_me' => $message->sender_id == $user->id && $message->sender_type == 'user',
            ];
        });

        // Kembalikan JSON
        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'profile_photo' => $user->profile_photo,
            ],
            'mua' => [
                'id' => $mua->id,
                'name' => $mua->name ?? $mua->username,
                'username' => $mua->username,
                'profile_photo' => $mua->profile_photo,
                'category' => $mua->category ?? null,
                'phone' => $mua->phone ?? null,
                'location' => $mua->location ?? null,
            ],
            'messages' => $formattedMessages,
            'meta' => [
                'total_messages' => $messages->count(),
                'unread_count' => $messages->where('is_read', false)
                    ->where('sender_type', 'make_up_artist')
                    ->count(),
            ]
        ], 200);
    }

    public function userSendToMuaApi(Request $request, $mua_id)
    {
        // Validasi input
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        // Cari MUA
        $mua = MakeUpArtist::find($mua_id);

        if (!$mua) {
            return response()->json([
                'success' => false,
                'message' => 'Make-Up Artist tidak ditemukan'
            ], 404);
        }

        // Buat pesan
        $message = Message::create([
            'sender_id' => Auth::id(),
            'sender_type' => 'user',
            'receiver_id' => $mua->id,
            'receiver_type' => 'make_up_artist',
            'message' => $request->message,
            'is_read' => false,
        ]);

        // Kembalikan response JSON
        return response()->json([
            'success' => true,
            'message' => 'Pesan terkirim!',
            'data' => $message
        ], 201);
    }

    public function muaToUserApi($user_id)
    {
        $mua = Auth::guard('makeup_artist')->user();
        $user = User::find($user_id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        // Ambil semua pesan antara MUA dan User
        $messages = Message::where(function ($query) use ($user, $mua) {
            $query->where('sender_id', $user->id)
                ->where('sender_type', 'user')
                ->where('receiver_id', $mua->id)
                ->where('receiver_type', 'make_up_artist');
        })
        ->orWhere(function ($query) use ($user, $mua) {
            $query->where('sender_id', $mua->id)
                ->where('sender_type', 'make_up_artist')
                ->where('receiver_id', $user->id)
                ->where('receiver_type', 'user');
        })
        ->orderBy('created_at', 'asc')
        ->get();

        // Tandai pesan User yang belum dibaca sebagai dibaca
        Message::where('sender_id', $user->id)
            ->where('sender_type', 'user')
            ->where('receiver_id', $mua->id)
            ->where('receiver_type', 'make_up_artist')
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'user' => $user,
            'messages' => $messages
        ], 200);
    }

    public function muaSendToUserApi(Request $request, $user_id)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $user = User::find($user_id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        $message = Message::create([
            'sender_id' => Auth::guard('makeup_artist')->id(),
            'sender_type' => 'make_up_artist',
            'receiver_id' => $user->id,
            'receiver_type' => 'user',
            'message' => $request->message,
            'is_read' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pesan terkirim!',
            'data' => $message
        ], 201);
    }

}
