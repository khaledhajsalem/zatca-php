<?php

namespace KhaledHajSalem\ZatcaLaravel\Services;

use Illuminate\Support\Str;
use KhaledHajSalem\ZatcaLaravel\Events\ApiCallFailed;
use KhaledHajSalem\ZatcaLaravel\Models\ZatcaApiLog;

class ApiLogService
{
    public function log(array $data): ZatcaApiLog
    {
        $sanitizedHeaders = $this->sanitizeHeaders($data['request_headers'] ?? []);

        $log = ZatcaApiLog::create([
            'uuid'             => (string) Str::uuid(),
            'invoice_id'       => $data['invoice_id'] ?? null,
            'device_id'        => $data['device_id'] ?? null,
            'endpoint'         => $data['endpoint'] ?? '',
            'method'           => $data['method'] ?? 'POST',
            'environment'      => $data['environment'] ?? config('zatca.environment'),
            'request_headers'  => $sanitizedHeaders,
            'request_body'     => $data['request_body'] ?? null,
            'response_status'  => $data['response_status'] ?? null,
            'response_headers' => $data['response_headers'] ?? null,
            'response_body'    => $data['response_body'] ?? null,
            'duration_ms'      => $data['duration_ms'] ?? null,
            'status'           => $data['status'] ?? 'success',
            'error_message'    => $data['error_message'] ?? null,
            'retry_count'      => $data['retry_count'] ?? 0,
            'created_at'       => now(),
        ]);

        if ($log->isError()) {
            ApiCallFailed::dispatch($log, $log->error_message ?? 'Unknown error');
        }

        return $log;
    }

    private function sanitizeHeaders(array $headers): array
    {
        $sensitiveKeys = ['authorization', 'cookie', 'x-api-key'];

        foreach ($headers as $key => $value) {
            if (in_array(strtolower($key), $sensitiveKeys)) {
                $headers[$key] = '••••••••••••••';
            }
        }

        return $headers;
    }
}
