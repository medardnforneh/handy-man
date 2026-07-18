<?php

declare(strict_types=1);

return [

    // 6-digit numeric code, valid for a short window.
    'code_length' => 6,
    'ttl_minutes' => (int) env('OTP_TTL_MINUTES', 5),

    // SMS pumping is a real fraud vector (doc 04 §"OTP abuse"). Rate limit by phone, IP AND device.
    'max_per_phone_per_hour' => (int) env('OTP_MAX_PER_PHONE_PER_HOUR', 3),
    'max_per_ip_per_hour' => (int) env('OTP_MAX_PER_IP_PER_HOUR', 10),
    'max_per_device_per_hour' => (int) env('OTP_MAX_PER_DEVICE_PER_HOUR', 5),

    // Hard-lock a challenge after this many wrong verify attempts.
    'max_verify_attempts' => (int) env('OTP_MAX_VERIFY_ATTEMPTS', 5),

    'purposes' => ['signup', 'login', 'phone_change'],

];
