<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReverseOrderPaymentRequest;
use App\Http\Requests\StoreOrderPaymentRequest;
use App\Models\CustomerOrder;
use App\Models\OrderPayment;
use App\Services\OrderActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FinanceController extends Controller
{
    public function __construct(
        private OrderActivityLogger $activityLogger,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('view finance'), 403);

        $openReceivables = (float) CustomerOrder::query()
            ->whereRaw('order_total > amount_paid')
            ->selectRaw('COALESCE(SUM(order_total - amount_paid), 0) as total')
            ->value('total');

        $awaitingDpCount = CustomerOrder::query()
            ->where('amount_paid', 0)
            ->where('order_total', '>', 0)
            ->count();

        $clearedThisWeek = (float) OrderPayment::query()
            ->whereNull('reversed_at')
            ->whereBetween('posted_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->sum('amount');

        $openOrders = CustomerOrder::query()
            ->with('customer')
            ->whereRaw('order_total > amount_paid')
            ->orderByRaw('(order_total - amount_paid) DESC')
            ->limit(50)
            ->get();

        return view('finance.index', [
            'openReceivables' => $openReceivables,
            'awaitingDpCount' => $awaitingDpCount,
            'clearedThisWeek' => $clearedThisWeek,
            'openOrders' => $openOrders,
            'canManage' => $request->user()->can('manage finance'),
        ]);
    }

    public function storePayment(StoreOrderPaymentRequest $request, CustomerOrder $order): RedirectResponse|JsonResponse
    {
        $payment = DB::transaction(function () use ($request, $order) {
            $amount = round((float) $request->input('amount'), 2);

            $payment = $order->payments()->create([
                'amount' => $amount,
                'method' => $request->string('method'),
                'reference' => $request->input('reference'),
                'notes' => $request->input('notes'),
                'recorded_by' => $request->user()->id,
                'posted_at' => now(),
            ]);

            $order->increment('amount_paid', $amount);
            $order->refresh();

            $this->activityLogger->log(
                $order,
                'payment_recorded',
                'Payment recorded',
                sprintf('₱%s via %s', number_format($amount, 2), $payment->method),
                [
                    'payment_id' => $payment->id,
                    'amount' => $amount,
                    'method' => $payment->method,
                ],
                $request->user(),
            );

            return $payment;
        });

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'payment_id' => $payment->id,
                'amount_paid' => (float) $order->fresh()->amount_paid,
                'balance_due' => $order->fresh()->balanceDue(),
                'payment_status' => $order->fresh()->paymentStatus()->value,
            ]);
        }

        return redirect()
            ->route('orders.show', $order)
            ->with('success', 'Payment recorded successfully.');
    }

    public function reversePayment(ReverseOrderPaymentRequest $request, CustomerOrder $order, OrderPayment $payment): RedirectResponse|JsonResponse
    {
        abort_unless($payment->customer_order_id === $order->id, 404);

        if ($payment->isReversed()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment is already reversed.',
                ], 422);
            }

            return redirect()
                ->route('orders.show', $order)
                ->with('error', 'Payment is already reversed.');
        }

        DB::transaction(function () use ($request, $order, $payment): void {
            $payment->update([
                'reversed_at' => now(),
                'reversal_reason' => $request->string('reversal_reason'),
            ]);

            $order->decrement('amount_paid', (float) $payment->amount);
            $order->refresh();

            $this->activityLogger->log(
                $order,
                'payment_reversed',
                'Payment reversed',
                sprintf('₱%s reversed: %s', number_format((float) $payment->amount, 2), $payment->reversal_reason),
                [
                    'payment_id' => $payment->id,
                    'amount' => (float) $payment->amount,
                    'reason' => $payment->reversal_reason,
                ],
                $request->user(),
            );
        });

        if ($request->expectsJson()) {
            $order->refresh();

            return response()->json([
                'success' => true,
                'amount_paid' => (float) $order->amount_paid,
                'balance_due' => $order->balanceDue(),
                'payment_status' => $order->paymentStatus()->value,
            ]);
        }

        return redirect()
            ->route('orders.show', $order)
            ->with('success', 'Payment reversed successfully.');
    }
}
