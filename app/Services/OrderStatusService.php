<?php

namespace App\Services;

use App\Events\OrderStatusUpdated;
use App\Models\Order;
use App\Models\OrderCancellation;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderStatusService
{
    public function __construct(
        protected ProductStockService $productStock,
        protected RefundService $refunds,
    ) {}

    public function allowedTransitions(string $from): array
    {
        return Order::$orderFlow[$from] ?? [];
    }

    public function canTransition(Order $order, string $to): bool
    {
        return in_array($to, $this->allowedTransitions($order->order_status), true);
    }

    public function completeImmediately(Order $order, ?int $actorId = null): Order
    {
        return DB::transaction(function () use ($order, $actorId) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            if (! in_array($order->order_status, [
                Order::STATUS_NEW,
                Order::STATUS_ACCEPTED,
                Order::STATUS_COOKING,
                Order::STATUS_READY,
            ], true)) {
                throw new RuntimeException('Order tidak dapat diselesaikan pada status ini.');
            }

            if ($order->payment_status !== Order::PAYMENT_PAID) {
                throw new RuntimeException('Order belum lunas — selesaikan pembayaran terlebih dahulu.');
            }

            $from = $order->order_status;
            $order->update([
                'order_status' => Order::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => $from,
                'to_status' => Order::STATUS_COMPLETED,
                'actor_id' => $actorId,
                'note' => 'Diselesaikan langsung tanpa melalui dapur.',
                'created_at' => now(),
            ]);

            $completedOrder = $order->fresh();
            OrderStatusUpdated::dispatch($completedOrder);

            return $completedOrder;
        });
    }

    /**
     * @param  string|null  $reason  required when cancelling (FR-CAS-003)
     */
    public function transition(Order $order, string $to, ?int $actorId = null, ?string $reason = null, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $to, $actorId, $reason, $note) {
            $order->refresh();

            if (! $this->canTransition($order, $to)) {
                throw new RuntimeException("Transisi tidak diizinkan dari {$order->order_status} ke {$to}");
            }

            if ($to === Order::STATUS_CANCELLED && blank($reason)) {
                throw new RuntimeException('Alasan pembatalan wajib diisi');
            }

            $from = $order->order_status;

            $updates = ['order_status' => $to];
            $updates[$to.'_at'] = now();

            $order->update($updates);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => $from,
                'to_status' => $to,
                'actor_id' => $actorId,
                'note' => $note,
                'created_at' => now(),
            ]);

            if ($to === Order::STATUS_CANCELLED) {
                OrderCancellation::create([
                    'order_id' => $order->id,
                    'reason_code' => $note,
                    'reason' => $reason,
                    'actor_id' => $actorId,
                    'created_at' => now(),
                ]);

                $this->productStock->reverse($order);

                $this->refundSettledPayments($order, $actorId);
            }

            OrderStatusUpdated::dispatch($order->fresh());

            return $order->fresh();
        });
    }

    private function refundSettledPayments(Order $order, ?int $actorId): void
    {
        foreach ($order->payments()->get() as $payment) {
            if (! in_array($payment->status, [Payment::STATUS_PAID, Payment::STATUS_PARTIALLY_REFUNDED], true)) {
                continue;
            }

            $refunded = (float) $payment->refunds()
                ->where('status', Refund::STATUS_SUCCEEDED)
                ->sum('amount');

            $outstanding = (float) $payment->amount - $refunded;

            if ($outstanding > 0) {
                $this->refunds->refund(
                    $order,
                    $payment,
                    $outstanding,
                    'Dibatalkan di kasir',
                    $actorId,
                );
            }
        }
    }
}
