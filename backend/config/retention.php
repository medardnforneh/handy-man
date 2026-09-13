<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| The retention schedule (doc 04, "keep a documented retention schedule")
|--------------------------------------------------------------------------
| What `data:retain` (nightly, routes/console.php) destroys, and when. Personal data held longer
| than its purpose needs is a liability under Law 2024/017, and this file is the register's
| answer to "for how long". Days, counted from the moment the purpose ended — a rejection, a
| session's end, an expiry — never from creation.
*/
return [
    // A one-time code is spent or expired within minutes; the row is kept a day so the per-phone
    // rate limit (3/hour, counted from the table) can still see it.
    'otp_challenges_days' => (int) env('RETENTION_OTP_DAYS', 1),

    // Idempotency records replay a response for their TTL and are dead weight after it.
    'idempotency_keys_days' => (int) env('RETENTION_IDEMPOTENCY_DAYS', 0),

    // A revoked or expired refresh token is an audit trail of a session, not a credential.
    'refresh_tokens_days' => (int) env('RETENTION_REFRESH_TOKENS_DAYS', 30),

    // Where a worker checked in and out is safety evidence for the dispute window and location
    // tracking after it (doc 04: "work-session geo aggregated after 90 days"). The session — that
    // it happened, when, how long — stays; the coordinates go.
    'work_session_geo_days' => (int) env('RETENTION_WORK_SESSION_GEO_DAYS', 90),

    // A rejected identity document has no purpose once the person has had time to re-submit
    // (doc 04: "purged N days after rejection"). The bytes go; the row stays for the audit trail.
    'rejected_documents_days' => (int) env('RETENTION_REJECTED_DOCUMENTS_DAYS', 30),

    // Same for a document that expired (its own validity date) and was never replaced.
    'expired_documents_days' => (int) env('RETENTION_EXPIRED_DOCUMENTS_DAYS', 30),
];
