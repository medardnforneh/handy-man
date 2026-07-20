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
        --hm-shadow: 0 1px 2px color-mix(in srgb, var(--hm-text) 6%, transparent),
                     0 4px 16px color-mix(in srgb, var(--hm-text) 7%, transparent);
        color: var(--hm-text);
        font-variant-numeric: tabular-nums;
    }
    .hm-dash *{ box-sizing:border-box; }
    .hm-dash .hm-grid{ display:flex; flex-direction:column; gap:24px; }
    .hm-dash .hm-kpis{ display:grid; grid-template-columns:repeat(3,1fr); gap:16px; }
    .hm-dash .hm-card{ background:var(--hm-surface); border:1px solid var(--hm-border); border-radius:16px; box-shadow:var(--hm-shadow); }
    .hm-dash .hm-kpi{ padding:16px; display:grid; grid-template-columns:1fr auto; gap:4px 12px; align-items:start; }
    .hm-dash .hm-kpi .hm-label{ grid-column:1; font-size:12px; color:var(--hm-muted); font-weight:600; }
    .hm-dash .hm-kpi .hm-value{ grid-column:1; font-size:25px; font-weight:700; letter-spacing:-.02em; }
    .hm-dash .hm-kpi .hm-value .hm-unit{ font-size:13px; color:var(--hm-muted); font-weight:600; margin-left:3px; }
    .hm-dash .hm-kpi .hm-spark{ grid-column:2; grid-row:1 / span 3; align-self:center; }
    .hm-dash .hm-delta{ grid-column:1; font-size:12px; font-weight:600; }
    .hm-dash .hm-delta.up{ color:var(--hm-success);} .hm-dash .hm-delta.down{ color:var(--hm-danger);} .hm-dash .hm-delta.flat{ color:var(--hm-muted);}
    .hm-dash .hm-kpi.hm-attention{ outline:1px solid var(--hm-danger-w); }
    .hm-dash .hm-cols{ display:grid; grid-template-columns:1.6fr 1fr; gap:24px; align-items:start; }
    .hm-dash .hm-phead{ display:flex; align-items:center; justify-content:space-between; padding:14px 16px; border-bottom:1px solid var(--hm-border); }
    .hm-dash .hm-phead h2{ margin:0; font-size:14px; letter-spacing:-.01em; font-weight:700; }
    .hm-dash .hm-phead a{ font-size:12.5px; color:var(--hm-brand); font-weight:600; text-decoration:none; }
    .hm-dash table{ width:100%; min-width:520px; border-collapse:collapse; font-size:13px; }
    .hm-dash thead th{ text-align:left; font-size:10.5px; letter-spacing:.07em; text-transform:uppercase; color:var(--hm-muted); font-weight:700; padding:10px 16px; border-bottom:1px solid var(--hm-border); }
    .hm-dash tbody td{ padding:12px 16px; border-bottom:1px solid var(--hm-border); vertical-align:middle; }
    .hm-dash tbody tr:last-child td{ border-bottom:0; }
    .hm-dash .hm-num{ text-align:right; font-variant-numeric:tabular-nums; }
    .hm-dash .hm-ref{ font-weight:600; }
    .hm-dash .hm-sub{ color:var(--hm-muted); font-size:12px; }
    .hm-dash .hm-prov{ display:flex; align-items:center; gap:9px; }
    .hm-dash .hm-pa{ width:26px; height:26px; border-radius:7px; flex:none; display:grid; place-items:center; font-weight:700; font-size:11px; color:var(--hm-on-brand); background:var(--hm-brand); }
    .hm-dash .hm-pa.accent-info{ background:var(--hm-info); }
    .hm-dash .hm-pa.accent-warning{ background:var(--hm-warning); }
    .hm-dash .hm-pa.accent-muted{ background:var(--hm-muted); }
    .hm-dash .hm-pill{ display:inline-flex; align-items:center; gap:6px; padding:3px 9px; border-radius:9999px; font-size:11.5px; font-weight:600; white-space:nowrap; }
    .hm-dash .hm-pill::before{ content:""; width:6px; height:6px; border-radius:50%; background:currentColor; }
    .hm-dash .hm-engaged{ color:var(--hm-info); background:var(--hm-info-w); }
    .hm-dash .hm-progress{ color:var(--hm-warning); background:var(--hm-warning-w); }
    .hm-dash .hm-completed{ color:var(--hm-success); background:var(--hm-success-w); }
    .hm-dash .hm-danger{ color:var(--hm-danger); background:var(--hm-danger-w); }
    .hm-dash .hm-neutral{ color:var(--hm-muted); background:var(--hm-sunken); }
    .hm-dash .hm-mstones{ display:flex; gap:3px; }
    .hm-dash .hm-mstones i{ width:22px; height:5px; border-radius:3px; background:var(--hm-border-strong); }
    .hm-dash .hm-mstones i.hm-done{ background:var(--hm-brand); }
    .hm-dash .hm-stack{ display:flex; flex-direction:column; gap:24px; }
    .hm-dash .hm-exc{ display:flex; gap:12px; padding:14px 16px; border-bottom:1px solid var(--hm-border); }
    .hm-dash .hm-exc:last-child{ border-bottom:0; }
    .hm-dash .hm-exc .hm-sev{ width:4px; border-radius:3px; flex:none; }
    .hm-dash .hm-exc.hm-crit .hm-sev{ background:var(--hm-danger);} .hm-dash .hm-exc.hm-warn .hm-sev{ background:var(--hm-warning);}
    .hm-dash .hm-exc .hm-body{ flex:1; min-width:0; }
    .hm-dash .hm-exc .hm-body b{ font-size:13px; }
    .hm-dash .hm-exc .hm-body p{ margin:2px 0 0; color:var(--hm-muted); font-size:12px; }
    .hm-dash .hm-exc .hm-amt{ font-weight:700; font-size:13px; }
    .hm-dash .hm-exc.hm-crit .hm-amt{ color:var(--hm-danger); }
    .hm-dash .hm-chip{ display:inline-block; margin-top:7px; font-size:10.5px; font-weight:700; letter-spacing:.05em; text-transform:uppercase; padding:2px 7px; border-radius:6px; }
    .hm-dash .hm-chip.hm-crit{ color:var(--hm-danger); background:var(--hm-danger-w); }
    .hm-dash .hm-chip.hm-warn{ color:var(--hm-warning); background:var(--hm-warning-w); }
    .hm-dash .hm-chip.hm-ok{ color:var(--hm-success); background:var(--hm-success-w); }
    .hm-dash .hm-empty{ padding:26px 16px; text-align:center; color:var(--hm-muted); font-size:13px; }
    .hm-dash .hm-ledger{ padding:16px; display:flex; flex-direction:column; gap:10px; }
    .hm-dash .hm-lbar{ height:8px; border-radius:9999px; overflow:hidden; display:flex; background:var(--hm-sunken); }
    .hm-dash .hm-lbar span{ height:100%; }
    .hm-dash .hm-lrow{ display:flex; align-items:center; justify-content:space-between; gap:10px; }
    .hm-dash .hm-lrow .hm-k{ display:flex; align-items:center; gap:9px; color:var(--hm-muted); font-size:12.5px; font-weight:500; }
    .hm-dash .hm-lrow .hm-k i{ width:9px; height:9px; border-radius:3px; }
    .hm-dash .hm-lrow .hm-v{ font-weight:700; letter-spacing:-.01em; }
    .hm-dash .hm-tot{ border-top:1px dashed var(--hm-border); padding-top:10px; }
    .hm-dash .hm-foot{ color:var(--hm-muted); font-size:12px; text-align:center; padding-top:2px; }

    /* Detail-view specifics */
    .hm-dash .hm-head{ display:flex; align-items:center; gap:14px; padding:18px 16px; flex-wrap:wrap; }
    .hm-dash .hm-head .hm-title{ font-size:19px; font-weight:700; letter-spacing:-.02em; text-wrap:balance; }
    .hm-dash .hm-head .hm-title small{ color:var(--hm-muted); font-weight:500; font-size:13px; margin-left:8px; }
    .hm-dash .hm-two{ display:grid; grid-template-columns:1fr 1fr; gap:16px; }
    .hm-dash .hm-metric{ padding:14px 16px; }
    .hm-dash .hm-metric .hm-mk{ font-size:11px; color:var(--hm-muted); font-weight:600; text-transform:uppercase; letter-spacing:.05em; }
    .hm-dash .hm-metric .hm-mv{ font-size:22px; font-weight:700; letter-spacing:-.02em; margin-top:3px; }
    .hm-dash .hm-metric .hm-mv small{ font-size:12px; color:var(--hm-muted); font-weight:600; }
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
        .hm-dash .hm-mile, .hm-dash .hm-exc, .hm-dash .hm-metric, .hm-dash .hm-ledger, .hm-dash .hm-kv{ padding-left:14px; padding-right:14px; }
    }
    @media (max-width:380px){
        .hm-dash .hm-grid{ gap:16px; }
        .hm-dash .hm-two, .hm-dash .hm-stack{ gap:16px; }
        .hm-dash .hm-kpi .hm-value{ font-size:20px; }
    }
</style>
