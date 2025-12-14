<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

use App\Models\Booking;

class PaymentController extends Controller
{
    //
    public function getSnapToken(Request $request)
    {
        try {
            $user = Auth::user(); // Ambil user dari JWT

            if (!$user) {
                return response()->json([
                    'error' => 'Unauthorized'
                ], 401);
            }

            $request->validate([
                'package_id'   => 'required',
                'mua_id'       => 'required',
                'price'        => 'required|numeric',
                'biaya_admin'  => 'nullable|numeric',
                'total'        => 'nullable|numeric',
            ]);

            $packageId   = $request->package_id;
            $muaId       = $request->mua_id;
            $total       = (int) $request->total;

            // Buat order ID unik
            $orderId = 'ORD-' . time() . '-' . $user->id;

            // Simpan ke DB
            $booking = Booking::create([
                'id_user'        => $user->id,
                'id_mua'         => $muaId,
                'kode_pembayaran'=> $orderId,
                'package_id'     => $packageId,
                'amount'         => $total,
                'status'         => 'pending',
            ]);

            // Log konfigurasi Midtrans
            Log::info('Midtrans Configuration', [
                'server_key_exists' => !empty(config('services.midtrans.serverKey')),
                'server_key_prefix' => substr(config('services.midtrans.serverKey'), 0, 5).'...',
                'is_production'     => config('services.midtrans.isProduction'),
            ]);

            // Konfigurasi Midtrans
            \Midtrans\Config::$serverKey    = config('services.midtrans.serverKey');
            \Midtrans\Config::$isProduction = config('services.midtrans.isProduction', false);
            \Midtrans\Config::$isSanitized  = true;
            \Midtrans\Config::$is3ds        = true;

            $params = [
                'transaction_details' => [
                    'order_id' => $orderId,
                    'gross_amount' => $total,
                ],
                'customer_details' => [
                    'first_name' => $user->name ?? 'Customer',
                    'email'      => $user->email ?? 'email@example.com',
                    'phone'      => $user->phone ?? '08111222333',
                ],
                'item_details' => [
                    [
                        'id'       => $packageId,
                        'price'    => $total,
                        'quantity' => 1,
                        'name'     => 'Paket Konsultasi MUA',
                    ],
                ],
            ];

            // Ambil snap token
            $snapToken = \Midtrans\Snap::getSnapToken($params);

            // Update booking
            $booking->update([
                'snap_token' => $snapToken,
            ]);

            return response()->json([
                'success'    => true,
                'snap_token' => $snapToken,
                'order_id'   => $orderId,
            ], 200);

        } catch (\Exception $e) {

            Log::error("❌ Midtrans Error: ".$e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'success' => false,
                'error'   => 'Gagal membuat snap token',
                'debug'   => env('APP_DEBUG') ? $e->getMessage() : null,
            ], 500);
        }
    }

}
