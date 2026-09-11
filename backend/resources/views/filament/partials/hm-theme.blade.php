{{-- Shared admin styles. SEMANTIC TOKENS ONLY — every colour resolves to a --hm-color-* variable
     from the generated stylesheet (tokens/tokens.json → public/css/tokens.css, linked by
     AdminPanelProvider). Light/dark follow `data-theme`, which is mirrored from Filament's `.dark`
     class, so both themes are designed rather than inverted. Enforced by `npm run lint:colors`. --}}
<style>
    .hm-dash {
        --hm-surface: var(--hm-color-surface-raised);
        --hm-base: var(--hm-color-surface-base);
        --hm-sunken: var(--hm-color-surface-sunken);
        --hm-text: var(--hm-color-text-primary);
        --hm-muted: var(--hm-color-text-muted);
        --hm-border: var(--hm-color-border-subtle);
        --hm-border-strong: var(--hm-color-border-strong);
        --hm-brand: var(--hm-color-brand-primary);
        --hm-on-brand: var(--hm-color-brand-onPrimary);
        --hm-success: var(--hm-color-status-success);
        --hm-warning: var(--hm-color-status-warning);
        --hm-danger: var(--hm-color-status-danger);
        --hm-info: var(--hm-color-status-info);
        --hm-brand-weak: color-mix(in srgb, var(--hm-brand) 12%, transparent);
        --hm-success-w: color-mix(in srgb, var(--hm-success) 14%, transparent);
        --hm-warning-w: color-mix(in srgb, var(--hm-warning) 14%, transparent);
        --hm-danger-w: color-mix(in srgb, var(--hm-danger) 14%, transparent);
        --hm-info-w: color-mix(in srgb, var(--hm-info) 14%, transparent);
        --hm-rule: var(--hm-rule-strong) solid var(--hm-border-strong);
        color: var(--hm-text);
        font-variant-numeric: tabular-nums;
    }
    .hm-dash *{ box-sizing:border-box; }
    .hm-dash .hm-grid{ display:flex; flex-direction:column; gap:24px; }
    .hm-dash .hm-kpis{ display:grid; grid-template-columns:repeat(3,1fr); gap:16px; }
    /* A card is a fill. No hairline, no radius, no shadow — the Modernist system organises with
       alignment and rules, and the regions inside a card are separated by the 2px rule. */
    .hm-dash .hm-card{ background:var(--hm-surface); }
    .hm-dash .hm-kpi{ padding:16px; display:grid; grid-template-columns:1fr auto; gap:4px 12px; align-items:start; }
    .hm-dash .hm-kpi .hm-label{ grid-column:1; font-size:12px; color:var(--hm-muted); font-weight:600; }
    .hm-dash .hm-kpi .hm-value{ grid-column:1; font-size:25px; font-weight:800; letter-spacing:-.02em; }
    .hm-dash .hm-kpi .hm-value .hm-unit{ font-size:13px; color:var(--hm-muted); font-weight:600; margin-left:3px; }
    .hm-dash .hm-kpi .hm-spark{ grid-column:2; grid-row:1 / span 3; align-self:center; }
    .hm-dash .hm-delta{ grid-column:1; font-size:12px; font-weight:600; }
    .hm-dash .hm-delta.up{ color:var(--hm-success);} .hm-dash .hm-delta.down{ color:var(--hm-danger);} .hm-dash .hm-delta.flat{ color:var(--hm-muted);}
    .hm-dash .hm-kpi.hm-attention{ box-shadow:inset 0 0 0 2px var(--hm-danger); }
    /* `min-width:0` so the 1.6fr / 1fr ratio actually holds. A grid track defaults to min-content,
       so the engagements table's own min-width pushed the left column wider and starved the aside
       until "Settlement mismatch" was setting two words to a line. The table already scrolls inside
       its card; that is the right thing to give way, not the panel beside it. */
    .hm-dash .hm-cols{ display:grid; grid-template-columns:1.6fr 1fr; gap:24px; align-items:start; }
    .hm-dash .hm-cols > *{ min-width:0; }
    .hm-dash .hm-phead{ display:flex; align-items:center; justify-content:space-between; padding:14px 16px; border-bottom:var(--hm-rule); }
    .hm-dash .hm-phead h2{ margin:0; font-size:14px; letter-spacing:-.01em; font-weight:800; }
    .hm-dash .hm-phead a{ font-size:12.5px; color:var(--hm-brand); font-weight:600; text-decoration:none; }
    .hm-dash table{ width:100%; min-width:640px; border-collapse:collapse; font-size:13px; }
    .hm-dash thead th{ text-align:left; font-size:10.5px; letter-spacing:.08em; text-transform:uppercase; color:var(--hm-muted); font-weight:700; padding:10px 16px; border-bottom:var(--hm-rule); }
    .hm-dash tbody td{ padding:12px 16px; border-bottom:1px solid var(--hm-border); vertical-align:middle; }
    .hm-dash tbody tr:last-child td{ border-bottom:0; }
    /* A money figure must never wrap: "800 000" broken across two lines reads as two numbers. The
       thousands separator here is a space, so nowrap is the only thing holding the figure together. */
    .hm-dash .hm-num{ text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap; }
    /* Same reasoning as the money figure, one column over: a reference is ONE token, and a hyphen
       is a licence to break that has to be revoked explicitly. "JOB-GWRLP" was arriving as "JOB-"
       above "GWRLP" in every single row, which reads as a broken table rather than as a code. */
    .hm-dash .hm-ref{ font-weight:600; white-space:nowrap; }
    .hm-dash .hm-sub{ color:var(--hm-muted); font-size:12px; }
    /* Five columns inside 520px left the first two so narrow that "Entretien de climatiseur" and
       "Douala Cool Services" each set one word per line. The table already scrolls horizontally;
       these give the text columns enough room to read before that happens. */
    .hm-dash tbody td:first-child{ min-width:170px; }
    .hm-dash tbody td:nth-child(2){ min-width:150px; }
    .hm-dash .hm-prov{ display:flex; align-items:center; gap:9px; }
    .hm-dash .hm-prov > div{ min-width:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .hm-dash .hm-pa{ width:26px; height:26px; border-radius:0; flex:none; display:grid; place-items:center; font-weight:700; font-size:11px; color:var(--hm-on-brand); background:var(--hm-brand); }
    .hm-dash .hm-pa-lg{ width:42px; height:42px; font-size:15px; }
    .hm-dash .hm-pa.accent-info{ background:var(--hm-info); }
    .hm-dash .hm-pa.accent-warning{ background:var(--hm-warning); }
    .hm-dash .hm-pa.accent-muted{ background:var(--hm-muted); }
    .hm-dash .hm-pill{ display:inline-flex; align-items:center; gap:6px; padding:3px 9px; font-size:11.5px; font-weight:600; white-space:nowrap; }
    .hm-dash .hm-pill::before{ content:""; width:6px; height:6px; border-radius:50%; background:currentColor; }
    .hm-dash .hm-engaged{ color:var(--hm-info); background:var(--hm-info-w); }
    .hm-dash .hm-progress{ color:var(--hm-warning); background:var(--hm-warning-w); }
    .hm-dash .hm-completed{ color:var(--hm-success); background:var(--hm-success-w); }
    .hm-dash .hm-danger{ color:var(--hm-danger); background:var(--hm-danger-w); }
    .hm-dash .hm-neutral{ color:var(--hm-muted); background:var(--hm-sunken); }
    .hm-dash .hm-mstones{ display:flex; gap:3px; }
    .hm-dash .hm-mstones i{ width:22px; height:5px; background:var(--hm-border-strong); }
    .hm-dash .hm-mstones i.hm-done{ background:var(--hm-brand); }
    .hm-dash .hm-stack{ display:flex; flex-direction:column; gap:24px; }
    /* Long-form prose (a complaint, a report, a resolution note) — the one place in the admin where
       a full paragraph is read rather than scanned, so it gets a reading measure and line height. */
    /* `.hm-card` carries no padding of its own — every child inside it provides its own, which is
       why `.hm-phead` sets 14px/16px. `.hm-body` set none, so on all five detail pages (the
       dispute complaint, the exception's finding, the report, the referral's flag reason, the
       safety-alert note) the heading was inset and the text under it ran flush to the card's edge.
       It is the same 16px as the heading above it; `max-width` keeps the measure readable. */
    .hm-dash .hm-body{ margin:0; padding:14px 16px; font-size:13.5px; line-height:1.65; color:var(--hm-text); max-width:64ch; white-space:pre-wrap; overflow-wrap:anywhere; }

    /* A short explanatory line standing in for a section's content — "no provider profile", "not
       flagged", "no consents recorded". The SAME omission `.hm-body` was added for, one card down:
       these were written as `<p class="hm-sub" style="margin:0">`, which kills the browser's margin
       and supplies no padding, so the sentence sat flush against the card's left edge under a
       heading that was properly inset. Five of them, in three files.

       It takes the same 14px/16px as `.hm-phead` above it, so the text lines up with the heading it
       belongs to rather than with the card. */
    .hm-dash .hm-note{ margin:0; padding:14px 16px; font-size:12.5px; line-height:1.6; color:var(--hm-muted); }
    .hm-dash .hm-linkcard{ display:flex; align-items:center; justify-content:space-between; gap:12px;
        padding:14px 16px; background:var(--hm-surface); color:var(--hm-brand); font-size:13px; font-weight:700; text-decoration:none; }
    .hm-dash .hm-linkcard:hover{ background:var(--hm-sunken); }
    .hm-dash .hm-exc{ display:flex; gap:12px; padding:14px 16px; border-bottom:1px solid var(--hm-border); }
    .hm-dash .hm-exc:last-child{ border-bottom:0; }
    .hm-dash .hm-exc .hm-sev{ width:4px; flex:none; }
    .hm-dash .hm-exc.hm-crit .hm-sev{ background:var(--hm-danger);} .hm-dash .hm-exc.hm-warn .hm-sev{ background:var(--hm-warning);}
    /* `.hm-body` means two different things in this file: the long free-text body of a report,
       where `white-space:pre-wrap` and `overflow-wrap:anywhere` are exactly right, and this card's
       little text block, where they are exactly wrong — they were breaking "mismatch" mid-word and
       holding the line at 64ch inside a 300px aside. The narrower rule wins them back. */
    .hm-dash .hm-exc .hm-body{ flex:1; min-width:0; max-width:none; padding:0; white-space:normal; overflow-wrap:break-word; font-size:inherit; line-height:1.45; }
    .hm-dash .hm-exc .hm-body b{ font-size:13px; }
    .hm-dash .hm-exc .hm-body p{ margin:2px 0 0; color:var(--hm-muted); font-size:12px; }
    /* A figure separated by spaces must not wrap — the same rule the table's `.hm-num` carries. */
    .hm-dash .hm-exc .hm-amt{ font-weight:700; font-size:13px; flex:none; white-space:nowrap; }
    .hm-dash .hm-exc.hm-crit .hm-amt{ color:var(--hm-danger); }
    /* A two-word status is a label, not a sentence. Uppercased and letter-spaced it is wide enough
       to wrap in a narrow aside, and "CRITICAL · UNRESOLV / ED" is not a state anyone should have
       to read. Same rule the table pills already carry. */
    .hm-dash .hm-chip{ display:inline-block; margin-top:7px; font-size:10.5px; font-weight:700; letter-spacing:.05em; text-transform:uppercase; padding:2px 7px; white-space:nowrap; }
    .hm-dash .hm-chip.hm-crit{ color:var(--hm-danger); background:var(--hm-danger-w); }
    .hm-dash .hm-chip.hm-warn{ color:var(--hm-warning); background:var(--hm-warning-w); }
    .hm-dash .hm-chip.hm-ok{ color:var(--hm-success); background:var(--hm-success-w); }
    .hm-dash .hm-empty{ padding:26px 16px; text-align:center; color:var(--hm-muted); font-size:13px; }
    .hm-dash .hm-ledger{ padding:16px; display:flex; flex-direction:column; gap:10px; }
    .hm-dash .hm-lbar{ height:8px; overflow:hidden; display:flex; background:var(--hm-sunken); }
    .hm-dash .hm-lbar span{ height:100%; }
    .hm-dash .hm-lrow{ display:flex; align-items:center; justify-content:space-between; gap:10px; }
    .hm-dash .hm-lrow .hm-k{ display:flex; align-items:center; gap:9px; color:var(--hm-muted); font-size:12.5px; font-weight:500; }
    .hm-dash .hm-lrow .hm-k i{ width:9px; height:9px; }
    .hm-dash .hm-lrow .hm-v{ font-weight:700; letter-spacing:-.01em; }
    .hm-dash .hm-tot{ border-top:var(--hm-rule); padding-top:10px; }
    .hm-dash .hm-foot{ color:var(--hm-muted); font-size:12px; text-align:center; padding-top:2px; }

    /* Detail-view specifics */
    .hm-dash .hm-head{ display:flex; align-items:center; gap:14px; padding:18px 16px; flex-wrap:wrap; }
    .hm-dash .hm-head .hm-title{ font-size:19px; font-weight:800; letter-spacing:-.02em; text-wrap:balance; }
    .hm-dash .hm-head .hm-title small{ color:var(--hm-muted); font-weight:500; font-size:13px; margin-left:8px; }
    /* align-items:start so a short card sizes to its content instead of stretching to match a taller
       neighbour and trailing a block of dead space. */
    .hm-dash .hm-two{ display:grid; grid-template-columns:1fr 1fr; gap:16px; align-items:start; }
    .hm-dash .hm-metric{ padding:14px 16px; }
    .hm-dash .hm-metric .hm-mk{ font-size:11px; color:var(--hm-muted); font-weight:600; text-transform:uppercase; letter-spacing:.05em; }
    .hm-dash .hm-metric .hm-mv{ font-size:22px; font-weight:800; letter-spacing:-.02em; margin-top:3px; }
    .hm-dash .hm-metric .hm-mv small{ font-size:12px; color:var(--hm-muted); font-weight:600; }
    /* A second hint on one metric. Inline it would wrap mid-phrase against the first one and leave
       that card taller than its neighbours, so it takes the line below instead. */
    .hm-dash .hm-metric .hm-mv small.hm-under{ display:block; margin-top:2px; }
    .hm-dash .hm-mgrid{ display:grid; grid-template-columns:repeat(4,1fr); gap:16px; }
    .hm-dash .hm-tl{ display:flex; flex-direction:column; }
    .hm-dash .hm-mile{ display:flex; align-items:center; gap:14px; padding:12px 16px; border-bottom:1px solid var(--hm-border); }
    .hm-dash .hm-mile:last-child{ border-bottom:0; }
    .hm-dash .hm-dot{ width:26px; height:26px; border-radius:50%; flex:none; display:grid; place-items:center; font-size:12px; font-weight:700; border:2px solid var(--hm-border-strong); color:var(--hm-muted); }
    .hm-dash .hm-dot.hm-paid{ background:var(--hm-brand); border-color:var(--hm-brand); color:var(--hm-on-brand); }
    .hm-dash .hm-mile .hm-mt{ flex:1; }
    .hm-dash .hm-mile .hm-mt b{ font-size:13px; }
    .hm-dash .hm-kv{ display:flex; flex-direction:column; gap:12px; padding:16px; }
    .hm-dash .hm-kv .r{ display:flex; align-items:center; justify-content:space-between; gap:12px; }
    .hm-dash .hm-kv .r .l{ color:var(--hm-muted); font-size:12.5px; }
    .hm-dash .hm-kv .r .val{ font-weight:600; font-size:13px; }

    /* Extreme responsiveness: fluid phone → desktop; nothing overflows the viewport. */
    @media (max-width:1180px){ .hm-dash .hm-cols{ grid-template-columns:1fr; } }
    @media (max-width:1024px){ .hm-dash .hm-kpis{ grid-template-columns:repeat(2,1fr);} .hm-dash .hm-mgrid{ grid-template-columns:repeat(2,1fr);} }
    @media (max-width:820px){ .hm-dash .hm-two{ grid-template-columns:1fr; } }
    @media (max-width:560px){
        .hm-dash .hm-kpis{ grid-template-columns:1fr; }
        .hm-dash .hm-mgrid{ grid-template-columns:1fr; }
        .hm-dash .hm-kpi .hm-value{ font-size:22px; }
        .hm-dash .hm-metric .hm-mv{ font-size:19px; }
        .hm-dash .hm-head{ gap:10px; padding:14px; }
        .hm-dash .hm-head .hm-title{ font-size:17px; }
        .hm-dash .hm-phead{ padding:12px 14px; }
        .hm-dash tbody td, .hm-dash thead th{ padding:10px 12px; }
        /* `.hm-note` and `.hm-body` belong in this list for the same reason as the rest: the heading
           above them narrows to 14px here, and anything left at 16px stops lining up with it. */
        .hm-dash .hm-mile, .hm-dash .hm-exc, .hm-dash .hm-metric, .hm-dash .hm-ledger, .hm-dash .hm-kv,
        .hm-dash .hm-note, .hm-dash .hm-body{ padding-left:14px; padding-right:14px; }
    }
    @media (max-width:380px){
        .hm-dash .hm-grid{ gap:16px; }
        .hm-dash .hm-two, .hm-dash .hm-stack{ gap:16px; }
        .hm-dash .hm-kpi .hm-value{ font-size:20px; }
    }
</style>
