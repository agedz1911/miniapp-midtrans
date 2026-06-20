<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikasi Order Baru</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333;">
    <h1>Order Baru Dibuat</h1>
    <p>Halo {{ $order->user->name }},</p>
    <p>Order Anda telah berhasil dibuat dengan nomor <strong>{{ $order->order_number }}</strong>.</p>
    <p>Total pesanan: <strong>Rp {{ number_format($order->total_amount, 0, ',', '.') }}</strong></p>
    <p>Metode pembayaran: <strong>{{ ucwords(str_replace('_', ' ', $order->payment_method)) }}</strong></p>

    <h2>Detail Order</h2>
    <table cellpadding="6" cellspacing="0" border="1" style="border-collapse: collapse; width: 100%; max-width: 600px;">
        <thead>
            <tr>
                <th align="left">Produk</th>
                <th align="right">Qty</th>
                <th align="right">Harga</th>
                <th align="right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>{{ $item->product->name ?? 'Produk tidak ditemukan' }}</td>
                    <td align="right">{{ $item->quantity }}</td>
                    <td align="right">Rp {{ number_format($item->unit_price, 0, ',', '.') }}</td>
                    <td align="right">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p>Terima kasih telah berbelanja.</p>
</body>
</html>
