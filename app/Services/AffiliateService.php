<?php

namespace App\Services;

use App\Exceptions\AffiliateCreateException;
use App\Mail\AffiliateCreated;
use App\Models\Affiliate;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class AffiliateService
{
    public function __construct(
        protected ApiService $apiService
    ) {}

    /**
     * Create a new affiliate for the merchant with the given commission rate.
     *
     * @param  Merchant $merchant
     * @param  string $email
     * @param  string $name
     * @param  float $commissionRate
     * @return Affiliate
     */
    public function register(Merchant $merchant, string $email, string $name, float $commissionRate): Affiliate
    {
        $data = [
            "email" => $email,
            "name" => $name,
            "commission_rate" => $commissionRate
        ];
        $validator = Validator::make($data, [
            "email" => "required|unique:users|email|max:255",
            "name" => "required|string|max:255",
            "commission_rate" => "required|numeric"
        ]);

        if ($validator->fails()) {
            throw new AffiliateCreateException("Failed to validate data");
        }

        // TODO: Complete this method
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                "name" => $name,
                "type" => User::TYPE_AFFILIATE
            ]
        );

        $affiliate = $user->affiliate;

        if ($user) {
            $affiliate = new Affiliate();
            $affiliate->user_id = $user->id;
            $affiliate->merchant_id = $merchant->id;
            $affiliate->commission_rate = $commissionRate;
            $affiliate->discount_code = $this->apiService->createDiscountCode($merchant)['code'];
            $affiliate->save();

            Mail::to($email)->send(new AffiliateCreated($affiliate));
            return $affiliate;
        }

        return $affiliate;

    }
}
