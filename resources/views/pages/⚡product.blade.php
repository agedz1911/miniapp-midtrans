<?php

use Livewire\Component;

new class extends Component
{
    public $orders = [];

    public function mount()
    {
        $this->orders = \App\Models\Order::where('user_id', auth()->id())->with('items.product')->get();
    }
};
?>

<div>
    <p>Selamat Datang <span class="text-green-500 font-semibold"> {{ Auth::user()->name }} {{ Auth::user()->last_name
            }}</span></p>

    <a class="hover:underline hover:text-green-600" href="{{route('orders.create')}}">Belanja</a>

    <div class="flex flex-wrap gap-4 items-center justify-start">

        @foreach ($orders as $item)
        <flux:card class="space-y-6 w-full max-w-md mt-10">
            <div>
                <flux:heading size="lg">{{$item->order_number}}</flux:heading>
                <div class="flex justify-between">
                    <div>
                        @foreach ($item->items as $orderItem)
                        <flux:text class="mt-2">{{ $orderItem->product?->name ?? 'Produk tidak tersedia' }}</flux:text>
                        @endforeach
                    </div>
                    <flux:badge color="lime">{{$item->status}}</flux:badge>

                </div>
            </div>

            <div class="space-y-6 flex justify-between">
                <div>
                    <flux:heading size="sm">Price</flux:heading>
                    @foreach ($item->items as $orderItem2)
                    <flux:text class="text-lg font-semibold">Rp {{ number_format($orderItem2->unit_price, 0, ',', '.')
                        }}
                    </flux:text>
                    @endforeach
                </div>
                <div>
                    <flux:heading size="sm">Qty</flux:heading>
                    @foreach ($item->items as $orderItem3)
                    <flux:text class="text-lg font-semibold"> {{ number_format($orderItem3->quantity, 0, ',', '.') }}
                    </flux:text>
                    @endforeach
                </div>
                <div>
                    <flux:heading size="sm">Sub Total</flux:heading>
                    @foreach ($item->items as $orderItem4)
                    <flux:text class="text-lg font-semibold">Rp. {{$orderItem4->subtotal}}</flux:text>
                    @endforeach
                </div>
            </div>
            <div class="flex justify-between items-center">

                <flux:heading size="sm">total</flux:heading>
                <flux:text class="text-lg font-semibold">Rp. {{$item->total_amount}}</flux:text>

            </div>

            <div class="space-y-2">
                <flux:button variant="primary" class="w-full">{{$item->paid_at}} | {{$item->payment_method}}
                </flux:button>

            </div>
        </flux:card>
        @endforeach

    </div>
</div>