<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Mail\OrderCreated;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Midtrans\Config as MidtransConfig;
use Midtrans\Snap;

new class extends Component
{
    protected $listeners = [
        'paymentSuccess' => 'handlePaymentSuccess',
        'paymentPending' => 'handlePaymentPending',
        'paymentError' => 'handlePaymentError',
        'paymentClosed' => 'handlePaymentClosed',
    ];

    public $products = [];
    public $cart = [];
    public $payment_method = 'bank_transfer';
    public $cartTotal = 0;
    public $snapToken = null;
    public $currentOrderNumber = null;

    public function mount(): void
    {
        $this->products = \App\Models\Product::all();
    }

    public function addToCart(int $productId): void
    {
        $product = \App\Models\Product::find($productId);

        if (! $product) {
            return;
        }

        if (isset($this->cart[$productId])) {
            $this->cart[$productId]['quantity']++;
        } else {
            $this->cart[$productId] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'unit_price' => $product->price,
                'quantity' => 1,
                'subtotal' => $product->price,
            ];
        }

        $this->recalculateCartItem($productId);
    }

    public function removeFromCart(int $productId): void
    {
        unset($this->cart[$productId]);
        $this->updateCartTotal();
    }

    public function increaseQuantity(int $productId): void
    {
        if (! isset($this->cart[$productId])) {
            return;
        }

        $this->cart[$productId]['quantity']++;
        $this->recalculateCartItem($productId);
    }

    public function decreaseQuantity(int $productId): void
    {
        if (! isset($this->cart[$productId])) {
            return;
        }

        if ($this->cart[$productId]['quantity'] <= 1) {
            $this->removeFromCart($productId);
            return;
        }

        $this->cart[$productId]['quantity']--;
        $this->recalculateCartItem($productId);
    }

    public function updatedCart(): void
    {
        foreach ($this->cart as $productId => $item) {
            $this->recalculateCartItem((int) $productId);
        }
        $this->updateCartTotal();
    }

    protected function recalculateCartItem(int $productId): void
    {
        if (! isset($this->cart[$productId])) {
            return;
        }

        $this->cart[$productId]['subtotal'] = $this->cart[$productId]['unit_price'] * $this->cart[$productId]['quantity'];
        $this->updateCartTotal();
    }

    protected function updateCartTotal(): void
    {
        $this->cartTotal = array_reduce($this->cart, fn($carry, $item) => $carry + $item['subtotal'], 0);
    }

    public function getCartTotalProperty(): int
    {
        return array_reduce($this->cart, fn($carry, $item) => $carry + $item['subtotal'], 0);
    }

    public function handlePaymentSuccess($result): void
    {
        $orderNumber = $result['order_id'] ?? $this->currentOrderNumber;
        $order = Order::where('order_number', $orderNumber)->first();
        if ($order) {
            $order->payment_status = 'paid';
            $order->status = 'processing';
            $order->paid_at = now();
            $order->save();
        }
        Session::flash('success', 'Pembayaran berhasil! Order sedang diproses.');
    }

    public function handlePaymentPending($result): void
    {
        $orderNumber = $result['order_id'] ?? $this->currentOrderNumber;
        $order = Order::where('order_number', $orderNumber)->first();
        if ($order) {
            $order->payment_status = 'pending';
            $order->save();
        }
        Session::flash('info', 'Pembayaran dalam status pending.');
    }

    public function handlePaymentError($result): void
    {
        Session::flash('error', 'Pembayaran gagal. Silakan coba lagi.');
    }

    public function handlePaymentClosed(): void
    {
        Session::flash('error', 'Pembayaran dibatalkan oleh pengguna.');
    }

    public function placeOrder(?string $paymentMethod = null)
    {
        if ($paymentMethod) {
            $this->payment_method = $paymentMethod;
        }

        if (empty($this->cart)) {
            Session::flash('error', 'Cart kosong. Tambahkan produk terlebih dahulu.');
            return;
        }

        $order = Order::create([
            'order_number' => 'ORD-' . Str::upper(Str::random(8)),
            'user_id' => Auth::id(),
            'status' => 'pending',
            'payment_method' => $this->payment_method,
            'payment_status' => 'pending',
            'total_amount' => $this->cartTotal,
        ]);

        foreach ($this->cart as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'subtotal' => $item['subtotal'],
            ]);
        }
        $order->load('items.product');

        // Send email notification (best-effort)
        try {
            $user = Auth::user();
            if ($user && $user->email) {
                Mail::to($user->email)->send(new OrderCreated($order));
            }
        } catch (\Throwable $e) {
            // swallow email errors; user will still see order
            Session::flash('error', 'Email notifikasi gagal dikirim.');
        }

        // Handle online payment with Midtrans using Snap redirect
        if ($this->payment_method === 'online_payment') {
            $this->currentOrderNumber = $order->order_number;
            try {
                $transaction = $this->createSnapRedirectTransaction($order);
                if ($transaction && isset($transaction->redirect_url)) {
                    $this->snapToken = $transaction->token ?? null;
                    $order->snap_token = $this->snapToken;
                    $order->save();
                    // Log redirect info for debugging (helps diagnose 404 status calls)
                    Log::info('Midtrans redirect created', [
                        'order_number' => $order->order_number,
                        'redirect_url' => $transaction->redirect_url,
                        'transaction_response' => is_object($transaction) ? json_encode($transaction) : (string) $transaction,
                    ]);
                    return redirect()->away($transaction->redirect_url);
                }
            } catch (\Throwable $e) {
                Log::error('Midtrans Snap error: ' . $e->getMessage());
                Session::flash('error', 'Gagal membuat transaksi Midtrans: ' . $e->getMessage());
            }
        }

        Session::flash('success', 'Order berhasil dibuat.');
        $this->cart = [];
    }

    protected function createSnapRedirectTransaction(Order $order): ?object
    {
        // Configure Midtrans
        MidtransConfig::$serverKey = config('services.midtrans.server_key');
        MidtransConfig::$isProduction = filter_var(config('services.midtrans.is_production', false), FILTER_VALIDATE_BOOLEAN);
        MidtransConfig::$isSanitized = true;
        MidtransConfig::$is3ds = true;

        // Build item details from order items
        $item_details = [];
        foreach ($order->items as $item) {
            $item_details[] = [
                'id' => $item->product_id,
                'price' => (int) $item->unit_price,
                'quantity' => $item->quantity,
                'name' => $item->product->name,
            ];
        }

        // Get authenticated user
        $user = Auth::user();

        // Build transaction details
        $transaction = [
            'transaction_details' => [
                'order_id' => $order->order_number,
                'gross_amount' => (int) $order->total_amount,
            ],
            'customer_details' => [
                'first_name' => $user->name ?? 'Customer',
                'email' => $user->email ?? '',
                'phone' => $user->phone ?? '',
            ],
            'item_details' => $item_details,
            'finish_redirect_url' => route('orders.finish'),
        ];

        try {
            $resp = Snap::createTransaction($transaction);
            // Log full response for debugging
            try {
                Log::info('Midtrans createTransaction response', [
                    'order_number' => $order->order_number,
                    'response' => is_object($resp) ? json_encode($resp) : (string) $resp,
                ]);
            } catch (\Throwable $e) {
                // swallow logging errors
            }

            return $resp;
        } catch (\Exception $e) {
            Log::error('Midtrans Snap Redirect Error: ' . $e->getMessage());
            throw $e;
        }
    }
};
?>

<div class="space-y-8">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="lg">Buat Order Baru</flux:heading>
            <flux:text>Tambah item ke cart, ubah jumlah, lalu buat order.</flux:text>
        </div>
        <div class="flex gap-2">
            <flux:badge color="lime">Total Cart: {{ count($cart) }} item</flux:badge>
            <flux:badge color="blue">Rp {{ number_format($cartTotal, 0, ',', '.') }}</flux:badge>
        </div>

    </div>

    @if (session('success'))
    <flux:badge variant="primary" color="green">{{ session('success') }}</flux:badge>
    @endif
    @if (session('error'))
    <flux:badge variant="danger">{{ session('error') }}</flux:badge>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-[1.4fr_1fr] gap-6">
        <div class="space-y-6">
            <flux:card class="space-y-6">
                <flux:heading size="md">Produk</flux:heading>
                <div class="grid sm:grid-cols-2 gap-4">
                    @foreach ($products as $product)
                    <flux:card class="space-y-4">
                        <div>
                            <flux:heading size="sm">{{ $product->name }}</flux:heading>
                            <flux:text class="text-sm text-slate-500">{{ $product->description }}</flux:text>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <flux:text class="font-semibold">Rp {{ number_format($product->price, 0, ',', '.') }}</flux:text>
                            <flux:badge color="lime">Stok {{ $product->stock }}</flux:badge>
                        </div>
                        <flux:button variant="primary" class="w-full" wire:click="addToCart({{ $product->id }})">Tambah ke Cart</flux:button>
                    </flux:card>
                    @endforeach
                </div>
            </flux:card>
        </div>

        <div class="space-y-6">
            <flux:card class="space-y-6">
                <flux:heading size="md">Cart</flux:heading>

                @if (empty($cart))
                <flux:text>Belum ada item di cart.</flux:text>
                @else
                <div class="space-y-4">
                    @foreach ($cart as $item)
                    <flux:card class="space-y-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <flux:heading size="sm">{{ $item['name'] }}</flux:heading>
                                <flux:text class="text-sm">Rp {{ number_format($item['unit_price'], 0, ',', '.') }} / pcs</flux:text>
                            </div>
                            <flux:button variant="danger" size="sm" wire:click="removeFromCart({{ $item['product_id'] }})">Hapus</flux:button>
                        </div>

                        <div class="grid grid-cols-[auto_1fr_auto] items-center gap-2">
                            <flux:button variant="primary" size="sm" wire:click="decreaseQuantity({{ $item['product_id'] }})">-</flux:button>
                            <flux:input type="number" min="1" wire:model.lazy="cart.{{ $item['product_id'] }}.quantity" class="text-center" />
                            <flux:button variant="primary" size="sm" wire:click="increaseQuantity({{ $item['product_id'] }})">+</flux:button>
                        </div>
                        <div class="flex items-center justify-between">
                            <flux:text>Subtotal</flux:text>
                            <flux:text class="font-semibold">Rp {{ number_format($item['subtotal'], 0, ',', '.') }}</flux:text>
                        </div>
                    </flux:card>
                    @endforeach
                </div>

                <div class="space-y-4">
                    <flux:card class="space-y-4">
                        <div class="flex items-center justify-between">
                            <flux:text>Total</flux:text>
                            <flux:heading size="lg">Rp {{ number_format($cartTotal, 0, ',', '.') }}</flux:heading>
                        </div>
                        <flux:heading size="sm">Pilih Metode Pembayaran</flux:heading>
                        <div class="grid grid-cols-2 gap-3">
                            <flux:button variant="primary" class="w-full" wire:click="placeOrder('bank_transfer')">
                                Bank Transfer
                            </flux:button>
                            <flux:button variant="primary" color="blue" class="w-full" wire:click="placeOrder('online_payment')">
                                Online Payment
                            </flux:button>
                        </div>
                    </flux:card>
                </div>
                @endif
            </flux:card>
        </div>
    </div>
</div>