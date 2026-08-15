<?php

namespace App\Services\ZoomPhone;

use App\Models\BusinessLead;

class LeadPhoneMatcher
{
    public function match(?string $phoneNumber): ?BusinessLead
    {
        $needle = $this->normalize($phoneNumber);

        if (strlen($needle) < 8) {
            return null;
        }

        return BusinessLead::query()
            ->with('phoneNumbers:id,business_lead_id,phone_number')
            ->get(['id', 'phone', 'mobile_phone'])
            ->first(function (BusinessLead $lead) use ($needle): bool {
                $numbers = collect([$lead->phone, $lead->mobile_phone])
                    ->merge($lead->phoneNumbers->pluck('phone_number'));

                return $numbers->contains(function ($number) use ($needle): bool {
                    $candidate = $this->normalize(is_string($number) ? $number : null);

                    if (strlen($candidate) < 8) {
                        return false;
                    }

                    return $candidate === $needle
                        || str_ends_with($candidate, $needle)
                        || str_ends_with($needle, $candidate)
                        || substr($candidate, -8) === substr($needle, -8);
                });
            });
    }

    protected function normalize(?string $phoneNumber): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phoneNumber) ?? '';

        return str_starts_with($digits, '00') ? substr($digits, 2) : $digits;
    }
}
