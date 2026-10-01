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

    /*
    |--------------------------------------------------------------------------
    | Workspace content, counted from the engagement ending
    |--------------------------------------------------------------------------
    | The schedule said nothing about the workspace, so a thread, its voice notes and a job's
    | photos were kept for ever — for everyone, not only for people who asked to be erased. These
    | are the two rules that close that, and they are deliberately asymmetric because the two
    | things are not alike:
    |
    |   MEDIA is heavy and is the most personal thing here: a voice note is a recording of someone
    |   speaking, a report photo is the inside of someone's home. Its purpose ends when the dispute
    |   and warranty windows do.
    |
    |   MESSAGE TEXT is small, and it is the record — of what was agreed, of who said they would
    |   come on Tuesday. It is what a dispute is argued from, so it outlives the media by a year.
    |   The row always survives either way; only the body is emptied, so a thread stays whole.
    |
    | THE NUMBERS BELOW ARE A STARTING POINT, NOT A FINDING. Two years and three are long enough
    | to be safe and short enough to be a real schedule, and they are what the CNDP processing
    | register needs an answer for — the founder's answer, with the lawyer. Run
    | `php artisan data:retain --dry-run` to see exactly what a number would destroy before it
    | destroys it. Set either to 0 to keep that class for ever and say so in the register.
    */
    'engagement_media_days' => (int) env('RETENTION_ENGAGEMENT_MEDIA_DAYS', 730),
    'message_bodies_days' => (int) env('RETENTION_MESSAGE_BODIES_DAYS', 1095),
];
