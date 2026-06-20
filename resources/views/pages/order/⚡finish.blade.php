<?php

use Livewire\Component;

new class extends Component
{
    // Intentionally empty — view shows controller-provided data
};
?>

<div class="space-y-6 p-6">
    @if(isset($order) && $order)
        <flux:heading size="lg">Order Selesai — {{ $order->order_number }}</flux:heading>
        
        <div class="grid gap-2">
            <div><strong>Payment Status:</strong> {{ $order->payment_status }}</div>
            <div><strong>Order Status:</strong> {{ $order->status }}</div>
            <div><strong>Total:</strong> Rp {{ number_format($order->total_amount, 0, ',', '.') }}</div>
            @if($order->paid_at)
            <div><strong>Paid At:</strong> {{ $order->paid_at }}</div>
            @endif
        </div>
    @else
        <flux:heading size="lg">Order tidak ditemukan</flux:heading>
        <flux:text>Order yang terkait transaksi ini tidak dapat ditemukan di sistem kami.</flux:text>
    @endif

    <hr class="my-4" />

    <flux:heading size="md">Midtrans Response</flux:heading>
    @if(isset($midtrans) && $midtrans)
        <pre class="whitespace-pre-wrap bg-gray-50 p-4 rounded text-sm">{{ json_encode($midtrans, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre>
    @else
        <flux:text>Tidak ada data Midtrans yang tersedia pada redirect ini.</flux:text>
    @endif
</div>