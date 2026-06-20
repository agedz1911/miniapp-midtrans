<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Mail\OrderCreated;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Component;

new class extends Component
{
    public $products = [];
    public $cart = [];
    public $payment_method = 'cash';
    public $cartTotal = 0;

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
        $this->cartTotal = array_reduce($this->cart, fn ($carry, $item) => $carry + $item['subtotal'], 0);
    }

    public function getCartTotalProperty(): int
    {
        return array_reduce($this->cart, fn ($carry, $item) => $carry + $item['subtotal'], 0);
    }

    public function placeOrder(): void
    {
        if (empty($this->cart)) {
            Session::flash('error', 'Cart kosong. Tambahkan produk terlebih dahulu.');
            return;
        }

        $order = Order::create([
            'order_number' => 'ORD-'.Str::upper(Str::random(8)),
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

        // No external payment provider: finish order and notify via email only

        Session::flash('success', 'Order berhasil dibuat.');
        $this->cart = [];
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
                            <flux:heading size="sm">Metode Pembayaran</flux:heading>
                            <flux:select wire:model="payment_method">
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                            </flux:select>
                                     <flux:button variant="primary" class="w-full" wire:click="placeOrder">Buat Order</flux:button>
                        </flux:card>
                    </div>
                @endif
            </flux:card>
        </div>
    </div>
</div>
