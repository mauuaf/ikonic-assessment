<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class OrderService
{
    public function __construct(
        protected AffiliateService $affiliateService
    ) {}

    /**
     * Process an order and log any commissions.
     * This should create a new affiliate if the customer_email is not already associated with one.
     * This method should also ignore duplicates based on order_id.
     *
     * @param array{order_id: string, subtotal_price: float, merchant_domain: string, discount_code: string, customer_email: string, customer_name: string} $data
     * @return void
     */
    public function processOrder(array $data)
    {
        // TODO: Complete this method

        $validator = Validator::make($data, [
            'order_id' => ['required'],
            "merchant_domain" => ["required"],
            'subtotal_price' => ["required", "numeric"],
            "discount_code" => ['required'],
            "customer_name" => ["required", "string"],
            "customer_email" => ["required", "email", "unique:users"]
        ]);

        if ($validator->fails()) {
            return response()->json($validator->getMessageBag()->all(), 422);
        }


        $merchant = Merchant::whereDomain($data['merchant_domain'])->first();

        if (!$merchant) {
            throw new \Exception('Merchant not found with the given domain.');
        }

        $user = User::whereEmail($data['customer_email'])->first();

        $affiliate = null;
        if (empty($user) || empty($user->affiliate)) {
            $affiliate = $this->affiliateService->register($merchant, $data["customer_email"], $data['customer_name'], 1);
        }

        Order::updateOrCreate(
            ['id' => $data['order_id']],
            [
                "merchant_id" => $merchant->id,
                "affiliate_id" => $affiliate->id,
                "subtotal" => $data["subtotal_price"],
                "discount_code" => $data["discount_code"],
                "status_code" => Order::STATUS_PAID
            ]
        );
    }
}
