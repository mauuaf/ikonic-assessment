<?php

namespace App\Services;

use App\Jobs\PayoutOrderJob;
use App\Models\Affiliate;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MerchantService
{
    /**
     * Register a new user and associated merchant.
     * Hint: Use the password field to store the API key.
     * Hint: Be sure to set the correct user type according to the constants in the User model.
     *
     * @param array{domain: string, name: string, email: string, api_key: string} $data
     * @return Merchant
     */
    public function register(array $data): Merchant
    {
        // TODO: Complete this method
        $validator = Validator::make($data, [
            "domain" => ['required', Rule::unique('merchants', 'domain'), "string"],
            "name" => ["required", "string"],
            "email" => ["required", "email:rfc:dns", Rule::unique("users", "email")],
            "api_key" => ["required"]
        ]);

        if ($validator->fails()) {
            return $validator->getMessageBag()->all();
        }

        try {
            DB::beginTransaction();

            $user = User::firstOrCreate(
                ["email" => $data['email']],
                [
                    "name" => $data['name'],
                    "password" => $data["api_key"],
                    "type" => User::TYPE_MERCHANT
                ]
            );

            $merchant = $user->merchant()->create([
                "domain" => $data['domain'],
                "display_name" => $user->name
            ]);

            DB::commit();
            return $merchant;
        } catch (\Exception $exception) {
            DB::rollBack();
            throw new \Exception($exception->getMessage());
        }


    }

    /**
     * Update the user
     *
     * @param array{domain: string, name: string, email: string, api_key: string} $data
     * @return void
     */
    public function updateMerchant(User $user, array $data)
    {
        $validator = Validator::make($data, [
            "domain" => ['required', Rule::unique('merchants', 'domain')->ignore($data['domain'], 'domain'), "string"],
            "name" => ["required", "string"],
            "email" => ["required", "email", Rule::unique("users", "email")->ignore($user->id)],
            "api_key" => ["required"]
        ]);

        if ($validator->fails()) {
            return $validator->getMessageBag()->all();
        }

        User::updateOrCreate(
            ["email" => $data['email']],
            [
                "name" => $data['name'],
                "password" => $data["api_key"],
                "type" => User::TYPE_MERCHANT
            ]
        );

        $merchant = Merchant::whereUserId($user->id)->first();

        if ($merchant) {
            $merchant->user_id = $user->id;
            $merchant->domain = $data['domain'];
            $merchant->display_name = $data['name'];
            $merchant->save();
        }

        return $merchant;
    }

    /**
     * Find a merchant by their email.
     * Hint: You'll need to look up the user first.
     *
     * @param string $email
     * @return Merchant|null
     */
    public function findMerchantByEmail(string $email): ?Merchant
    {
        // TODO: Complete this method

        return optional(User::whereEmail($email)->first())->merchant;
    }

    /**
     * Pay out all of an affiliate's orders.
     * Hint: You'll need to dispatch the job for each unpaid order.
     *
     * @param Affiliate $affiliate
     * @return void
     */
    public function payout(Affiliate $affiliate)
    {
        // TODO: Complete this method
        $orders = Order::where(['affiliate_id' => $affiliate->id])->get();
        foreach ($orders as $order) {
            if($order->payout_status == Order::STATUS_UNPAID) {
                PayoutOrderJob::dispatch($order);
            }
        }
    }
}
