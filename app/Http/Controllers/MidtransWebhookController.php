<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Midtrans\Config as MidtransConfig;
use Midtrans\Notification as MidtransNotification;

class MidtransWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // Configure Midtrans
        MidtransConfig::$serverKey = config('services.midtrans.server_key');
        MidtransConfig::$isProduction = config('services.midtrans.is_production', false);

        try {
            // Get notification from request
            $notification = new MidtransNotification();
        } catch (\Exception $e) {
            Log::error('Midtrans Webhook Error: Invalid notification', ['error' => $e->getMessage()]);
            return response('Invalid notification', 400);
        }

        $orderId = $notification->order_id ?? null;
        $transactionStatus = $notification->transaction_status ?? null;

        Log::info('Midtrans Webhook Received', [
            'order_id' => $orderId,
            'transaction_status' => $transactionStatus,
        ]);

        if (!$orderId) {
            Log::warning('Midtrans Webhook: Order ID missing');
            return response('Order ID missing', 400);
        }

        // Find order by order_number (which is our order_id in transaction)
        $order = Order::where('order_number', $orderId)->first();

        if (!$order) {
            Log::warning('Midtrans Webhook: Order not found', ['order_number' => $orderId]);
            return response('Order not found', 404);
        }

        // Update order status based on transaction status
        switch ($transactionStatus) {
            case 'capture':
            case 'settlement':
                $order->payment_status = 'paid';
                $order->status = 'processing';
                $order->paid_at = now();
                Log::info('Order marked as paid', ['order_id' => $orderId]);
                break;

            case 'pending':
                $order->payment_status = 'pending';
                Log::info('Order payment pending', ['order_id' => $orderId]);
                break;

            case 'deny':
            case 'cancel':
            case 'expire':
                $order->payment_status = 'failed';
                $order->status = 'cancelled';
                Log::info('Order payment failed', ['order_id' => $orderId, 'reason' => $transactionStatus]);
                break;

            default:
                Log::warning('Midtrans Webhook: Unknown transaction status', [
                    'order_id' => $orderId,
                    'status' => $transactionStatus,
                ]);
                break;
        }

        $order->save();

        return response('OK', 200);
    }
}
