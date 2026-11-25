<?php
namespace App\Actions\Pharmacy;

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterPharmacyAction
{
    public function execute(User $user, array $data): Pharmacy
    {
        return DB::transaction(function () use ($user, $data) {

            $pharmacy = Pharmacy::create($data);

            $membership = new Membership();

            $membership->user_id = $user->id;
            $membership->pharmacy_id = $pharmacy->id;
            $membership->role = MembershipRole::Owner;
            $membership->is_active = true;

            $membership->save();

            return $pharmacy->refresh();

        });
    }
}