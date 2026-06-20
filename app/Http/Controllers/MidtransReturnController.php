<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Midtrans\Config as MidtransConfig;
use Midtrans\Transaction;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class MidtransReturnController extends Controller
{
    public function finish(Request $request)
    {
        // Configure Midtrans
        MidtransConfig::$serverKey = config('services.midtrans.server_key');
        MidtransConfig::$isProduction = filter_var(config('services.midtrans.is_production', false), FILTER_VALIDATE_BOOLEAN);
        MidtransConfig::$isSanitized = true;
        MidtransConfig::$is3ds = true;

        // Try to obtain order id from query/post parameters
        $orderId = $request->input('order_id') ?? $request->input('order_number') ?? $request->input('orderId');

        $status = null;
        try {
            if ($orderId) {
                $status = Transaction::status($orderId);
            }
        } catch (\Throwable $e) {
            Log::error('Midtrans return status error: ' . $e->getMessage());
            $status = null;
        }

        $order = null;
        if ($status) {
            $orderNumber = $status->order_id ?? $orderId;
            $order = Order::where('order_number', $orderNumber)->first();

            if ($order) {
                $txStatus = $status->transaction_status ?? null;
                $fraudStatus = $status->fraud_status ?? null;

                if ($txStatus === 'settlement' || ($txStatus === 'capture' && $fraudStatus === 'accept')) {
                    $order->payment_status = 'paid';
                    $order->status = 'processing';
                    $order->paid_at = now();
                } elseif ($txStatus === 'pending') {
                    $order->payment_status = 'pending';
                } else {
                    $order->payment_status = 'failed';
                    $order->status = 'cancelled';
                }

                $order->save();
            }
        }

        return view('pages.order.⚡finish', [
            'order' => $order,
            'midtrans' => $status ?? null,
        ]);
    }
}
