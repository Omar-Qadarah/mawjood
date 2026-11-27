<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\ReservationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewReservationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ReservationStatus::class)->only([
                ReservationStatus::Confirmed, ReservationStatus::Rejected,
            ])],
            'rejection_reason' => ['prohibited_unless:status,rejected', 'nullable', 'string', 'max:255'],
        ];
    }
}
