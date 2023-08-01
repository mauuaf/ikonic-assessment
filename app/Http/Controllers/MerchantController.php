<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Models\Order;
use App\Services\MerchantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class MerchantController extends Controller
{
    public function __construct(
        MerchantService $merchantService
    ) {}

    /**
     * Useful order statistics for the merchant API.
     * 
     * @param Request $request Will include a from and to date
     * @return JsonResponse Should be in the form {count: total number of orders in range, commission_owed: amount of unpaid commissions for orders with an affiliate, revenue: sum order subtotals}
     */
    public function orderStats(Request $request): JsonResponse
    {
        // TODO: Complete this method

        $validator = Validator::make($request->all(), [
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->getMessageBag()->all(), 422);
        }

        $start_date = $request->input('from');
        $end_date = $request->input('to');

        $result = Order::whereBetween('created_at', [$start_date, $end_date])
            ->selectRaw('COUNT(id) AS count')
            ->selectRaw('SUM(CASE WHEN affiliate_id IS NOT NULL AND payout_status = "unpaid" THEN commission_owed ELSE 0 END) AS commission_owed')
            ->selectRaw('SUM(subtotal) AS revenue')
            ->first();

        return response()->json([
            'count' => $result->count,
            'commissions_owed' => $result->commission_owed,
            'revenue' => $result->revenue
        ]);
    }
}
