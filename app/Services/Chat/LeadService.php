<?php

namespace App\Services\Chat;

use App\Models\Lead;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class LeadService
{
    public function findByPhone(string $phone): ?Lead
    {
        return Lead::where('phone', $this->normalizePhone($phone))->first();
    }

    public function createOrUpdate(array $data): Lead
    {
        $phone = isset($data['phone']) && trim($data['phone']) !== ''
            ? $this->normalizePhone($data['phone'])
            : null;

        if (!$phone && empty($data['name'])) {
            throw new InvalidArgumentException(
                'Se necesita al menos un nombre o teléfono.'
            );
        }

        return DB::transaction(function () use ($data, $phone) {
            $lead = $phone
                ? Lead::where('phone', $phone)->lockForUpdate()->first()
                : null;

            if (!$lead) {
                $lead = new Lead();
                $lead->phone = $phone;
                $lead->name = $data['name'] ?? null;
                $lead->email = $data['email'] ?? null;
                $lead->lead_type = 'curious';
            } else {
                // Actualizar solamente los datos proporcionados.
                if (!empty($data['name'])) {
                    $lead->name = $data['name'];
                }

                if (!empty($data['email'])) {
                    $lead->email = $data['email'];
                }
            }

            if ($phone && $lead->name) {
                $lead->lead_type = 'potential';
            }

            $lead->save();

            return $lead;
        });
    }

    public function updateLeadType(Lead $lead): Lead
    {
        $lead->lead_type = ($lead->name && $lead->phone && $lead->email)
            ? 'potential'
            : 'curious';

        $lead->save();

        return $lead;
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if (strlen($digits) === 9) {
            $digits = '51' . $digits;
        }

        if (strlen($digits) < 10 || strlen($digits) > 15) {
            throw new InvalidArgumentException('Número de teléfono inválido.');
        }

        return '+' . $digits;
    }
}
