{{-- Shared admin styles (handoff: "HandyMan Admin"). SEMANTIC TOKENS ONLY — every colour resolves
     to a --hm-color-* variable from the generated stylesheet (tokens/tokens.json →
     public/css/tokens.css, linked by AdminPanelProvider). Enforced by `npm run lint:colors`. --}}
<style>
    .hm-dash {
        --hm-surface: var(--hm-color-surface-raised);
        --hm-base: var(--hm-color-surface-base);
        --hm-sunken: var(--hm-color-surface-sunken);
        --hm-step: var(--hm-color-surface-step);
        --hm-rail: var(--hm-color-surface-rail);
        --hm-text: var(--hm-color-text-primary);
        --hm-muted: var(--hm-color-text-muted);
        --hm-faint: var(--hm-color-text-faint);
        --hm-border: var(--hm-color-border-subtle);
        --hm-border-soft: var(--hm-color-border-soft);
        --hm-border-strong: var(--hm-color-border-strong);
        --hm-brand: var(--hm-color-brand-primary);
        --hm-brand-text: var(--hm-color-brand-primary);
        --hm-on-brand: var(--hm-color-brand-onPrimary);
        --hm-success: var(--hm-color-status-success);
        --hm-warning: var(--hm-color-status-warning);
        --hm-danger: var(--hm-color-status-danger);
        --hm-info: var(--hm-color-status-info);
        --hm-brand-weak: var(--hm-color-brand-tint);
        --hm-success-w: var(--hm-color-status-successTint);
        --hm-warning-w: var(--hm-color-status-warningTint);
        --hm-danger-w: var(--hm-color-status-dangerTint);
        --hm-info-w: var(--hm-color-status-infoTint);
        --hm-rule: 1px solid var(--hm-border);
        color: var(--hm-text);
        font-variant-numeric: tabular-nums;
    }
    .hm-dash *{ box-sizing:border-box; }
    .hm-dash .hm-grid{ display:flex; flex-direction:column; gap:16px; }
    .hm-dash .hm-kpis{ display:grid; grid-template-columns:repeat(6,1fr); gap:12px; }
    /* A card: surface-1 inside the line, 22px radius, no shadow — elevation is the surface step. */
    .hm-dash .hm-card{ background:var(--hm-surface); border:1px solid var(--hm-border); border-radius:22px; overflow:hidden; }
    /* The six KPI cards (handoff): 20px radius, padding 18, uppercase micro-label, 30px figure. */
    .hm-dash .hm-kpi{ padding:18px; border-radius:20px; display:grid; grid-template-columns:1fr auto; gap:6px 12px; align-items:start; }
    .hm-dash .hm-kpi .hm-label{ grid-column:1; font-size:11.5px; color:var(--hm-muted); font-weight:700; letter-spacing:.06em; text-transform:uppercase; }
    .hm-dash .hm-kpi .hm-value{ grid-column:1; font-size:clamp(20px, 1.7vw, 30px); font-weight:800; letter-spacing:-.035em; line-height:1.05; white-space:nowrap; }
    .hm-dash .hm-kpi .hm-value .hm-unit{ font-size:12px; color:var(--hm-muted); font-weight:600; margin-left:4px; letter-spacing:0; }
    .hm-dash .hm-kpi .hm-spark{ grid-column:2; grid-row:1 / span 3; align-self:center; }
    .hm-dash .hm-delta{ grid-column:1; font-size:12px; font-weight:600; display:flex; align-items:center; gap:4px; }
    .hm-dash .hm-delta.up{ color:var(--hm-brand);} .hm-dash .hm-delta.down{ color:var(--hm-danger);} .hm-dash .hm-delta.flat{ color:var(--hm-muted);}
    /* The one tinted card: escrow — the liability the whole product exists to hold. */
    .hm-dash .hm-kpi.hm-escrow{ background:color-mix(in srgb, var(--hm-brand) 9%, transparent); border-color:color-mix(in srgb, var(--hm-brand) 26%, transparent); }
    .hm-dash .hm-kpi.hm-escrow .hm-label{ color:var(--hm-brand); }
    /* Exceptions open: danger-tinted, label and caption in the danger colour. */
    .hm-dash .hm-kpi.hm-attention{ background:color-mix(in srgb, var(--hm-danger) 8%, transparent); border-color:color-mix(in srgb, var(--hm-danger) 28%, transparent); }
    .hm-dash .hm-kpi.hm-attention .hm-label{ color:var(--hm-danger); }
    /* `min-width:0` so the 1fr / 380px ratio actually holds and the table scrolls inside its card. */
    .hm-dash .hm-cols{ display:grid; grid-template-columns:minmax(0,1fr) 380px; gap:16px; align-items:start; }
    .hm-dash .hm-cols > *{ min-width:0; }
    .hm-dash .hm-phead{ display:flex; align-items:center; justify-content:space-between; gap:12px; padding:18px 22px; border-bottom:var(--hm-rule); }
    .hm-dash .hm-phead h2{ margin:0; font-size:16px; letter-spacing:-.01em; font-weight:700; }
    .hm-dash .hm-phead .hm-sub{ display:block; margin-top:2px; font-weight:400; }
    .hm-dash .hm-phead a{ font-size:12.5px; color:var(--hm-text); font-weight:700; text-decoration:none; padding:8px 12px; border:1px solid var(--hm-border-strong); border-radius:12px; white-space:nowrap; }
    .hm-dash .hm-phead a:hover{ background:var(--hm-sunken); }
    .hm-dash table{ width:100%; min-width:640px; border-collapse:collapse; font-size:13.5px; }
    /* Column headers: the rail fill, micro-labels in the faint ink (the one place it is allowed). */
    .hm-dash thead th{ text-align:left; font-size:10.5px; letter-spacing:.09em; text-transform:uppercase; color:var(--hm-faint); font-weight:800; padding:11px 22px; background:var(--hm-rail); border-bottom:var(--hm-rule); }
    .hm-dash tbody td{ padding:16px 22px; border-bottom:1px solid var(--hm-border-soft); vertical-align:middle; }
    .hm-dash tbody tr:last-child td{ border-bottom:0; }
    .hm-dash tbody tr:hover td{ background:var(--hm-sunken); }
    /* A money figure must never wrap: "800 000" broken across two lines reads as two numbers. */
    .hm-dash .hm-num{ text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap; font-weight:700; }
    /* A reference is ONE token, and a hyphen is a licence to break that has to be revoked. */
    .hm-dash .hm-ref{ font-weight:700; white-space:nowrap; font-size:14px; }
    .hm-dash .hm-sub{ color:var(--hm-muted); font-size:12px; }
    .hm-dash tbody td:first-child{ min-width:170px; }
    .hm-dash tbody td:nth-child(2){ min-width:150px; }
    .hm-dash .hm-prov{ display:flex; align-items:center; gap:9px; }
    .hm-dash .hm-prov > div{ min-width:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    /* Monograms: on surface-2 in a table (the handoff's table avatars), the accent when they lead. */
    .hm-dash .hm-pa{ width:28px; height:28px; border-radius:9px; flex:none; display:grid; place-items:center; font-weight:800; font-size:11px; color:var(--hm-text); background:var(--hm-sunken); }
    .hm-dash .hm-pa-lg{ width:42px; height:42px; font-size:15px; border-radius:13px; color:var(--hm-on-brand); background:var(--hm-brand); }
    .hm-dash .hm-pa.accent-info, .hm-dash .hm-pa.accent-warning, .hm-dash .hm-pa.accent-muted{ background:var(--hm-sunken); color:var(--hm-text); }
    /* Status pills: ink on a 14% tint of itself, 999px. */
    .hm-dash .hm-pill{ display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; font-size:11.5px; font-weight:700; white-space:nowrap; }
    .hm-dash .hm-engaged{ color:var(--hm-info); background:var(--hm-info-w); }
    .hm-dash .hm-progress{ color:var(--hm-warning); background:var(--hm-warning-w); }
    .hm-dash .hm-completed{ color:var(--hm-success); background:var(--hm-success-w); }
    .hm-dash .hm-danger{ color:var(--hm-danger); background:var(--hm-danger-w); }
    .hm-dash .hm-neutral{ color:var(--hm-muted); background:var(--hm-sunken); }
    /* Milestone segments: 16×5, 3px radius, accent done / surface-3 pending. */
    .hm-dash .hm-mstones{ display:flex; align-items:center; gap:3px; }
    .hm-dash .hm-mstones i{ width:16px; height:5px; border-radius:3px; background:var(--hm-step); }
    .hm-dash .hm-mstones i.hm-done{ background:var(--hm-brand); }
    .hm-dash .hm-mstones .hm-count{ margin-left:6px; font-size:12px; color:var(--hm-muted); }
    .hm-dash .hm-stack{ display:flex; flex-direction:column; gap:16px; }
    /* Long-form prose — the one place in the admin where a paragraph is read rather than scanned. */
    .hm-dash .hm-body{ margin:0; padding:16px 22px; font-size:13.5px; line-height:1.65; color:var(--hm-text); max-width:64ch; white-space:pre-wrap; overflow-wrap:anywhere; }
    .hm-dash .hm-note{ margin:0; padding:16px 22px; font-size:12.5px; line-height:1.6; color:var(--hm-muted); }
    .hm-dash .hm-linkcard{ display:flex; align-items:center; justify-content:space-between; gap:12px;
        padding:16px 22px; background:var(--hm-surface); border:1px solid var(--hm-border); border-radius:22px; color:var(--hm-brand-text); font-size:13px; font-weight:700; text-decoration:none; }
    .hm-dash .hm-linkcard:hover{ background:var(--hm-sunken); }
    /* The exceptions card: danger-tinted inside a danger line, rows divided by the soft line. */
    .hm-dash .hm-card.hm-exceptions{ background:color-mix(in srgb, var(--hm-danger) 6%, transparent); border-color:color-mix(in srgb, var(--hm-danger) 26%, transparent); }
    .hm-dash .hm-card.hm-exceptions .hm-phead{ border-bottom-color:color-mix(in srgb, var(--hm-danger) 18%, transparent); }
    .hm-dash .hm-card.hm-exceptions .hm-phead h2{ color:var(--hm-danger); display:flex; align-items:center; gap:8px; }
    .hm-dash .hm-badge{ display:inline-grid; place-items:center; min-width:22px; height:22px; padding:0 7px; border-radius:999px; font-size:11.5px; font-weight:800; }
    .hm-dash .hm-badge.hm-crit{ background:var(--hm-danger); color:var(--hm-on-brand); }
    .hm-dash .hm-exc{ display:flex; gap:12px; padding:14px 22px; border-bottom:1px solid var(--hm-border-soft); }
    .hm-dash .hm-exc:last-child{ border-bottom:0; }
    .hm-dash .hm-exc .hm-sev{ display:none; }
    .hm-dash .hm-exc .hm-body{ flex:1; min-width:0; max-width:none; padding:0; white-space:normal; overflow-wrap:break-word; font-size:inherit; line-height:1.45; }
    .hm-dash .hm-exc .hm-body b{ font-size:13.5px; font-weight:700; }
    .hm-dash .hm-exc .hm-body p{ margin:2px 0 0; color:var(--hm-muted); font-size:12px; }
    .hm-dash .hm-exc .hm-amt{ font-weight:700; font-size:13px; flex:none; white-space:nowrap; }
    .hm-dash .hm-exc.hm-crit .hm-amt{ color:var(--hm-danger); }
    .hm-dash .hm-chip{ display:inline-block; margin-top:7px; font-size:10.5px; font-weight:800; letter-spacing:.06em; text-transform:uppercase; padding:3px 8px; border-radius:999px; white-space:nowrap; }
    .hm-dash .hm-chip.hm-crit{ color:var(--hm-danger); background:var(--hm-danger-w); }
    .hm-dash .hm-chip.hm-warn{ color:var(--hm-warning); background:var(--hm-warning-w); }
    .hm-dash .hm-chip.hm-ok{ color:var(--hm-success); background:var(--hm-success-w); }
    .hm-dash .hm-empty{ padding:26px 22px; text-align:center; color:var(--hm-muted); font-size:13px; }
    /* Where the money sits: a 10px stacked bar on the surface-2 track, then rows with swatches. */
    .hm-dash .hm-ledger{ padding:18px 22px 20px; display:flex; flex-direction:column; gap:12px; }
    .hm-dash .hm-lbar{ height:10px; overflow:hidden; display:flex; border-radius:999px; background:var(--hm-sunken); margin-bottom:6px; }
    .hm-dash .hm-lbar span{ height:100%; }
    .hm-dash .hm-lrow{ display:flex; align-items:center; justify-content:space-between; gap:10px; padding:6px 0; border-bottom:1px solid var(--hm-border-soft); }
    .hm-dash .hm-lrow:last-child{ border-bottom:0; }
    .hm-dash .hm-lrow .hm-k{ display:flex; align-items:center; gap:9px; color:var(--hm-text); font-size:13.5px; font-weight:500; }
    .hm-dash .hm-lrow .hm-k i{ width:9px; height:9px; border-radius:3px; }
    .hm-dash .hm-lrow .hm-v{ font-weight:800; letter-spacing:-.01em; }
    .hm-dash .hm-tot{ border-top:var(--hm-rule); padding-top:12px; }
    .hm-dash .hm-foot{ color:var(--hm-muted); font-size:12px; text-align:center; padding-top:2px; }

    /* Detail-view specifics */
    .hm-dash .hm-head{ display:flex; align-items:center; gap:14px; padding:20px 22px; flex-wrap:wrap; }
    .hm-dash .hm-head .hm-title{ font-size:22px; font-weight:800; letter-spacing:-.028em; text-wrap:balance; }
    .hm-dash .hm-head .hm-title small{ color:var(--hm-muted); font-weight:500; font-size:13px; margin-left:8px; }
    .hm-dash .hm-two{ display:grid; grid-template-columns:1fr 1fr; gap:16px; align-items:start; }
    .hm-dash .hm-metric{ padding:16px 20px; }
    .hm-dash .hm-metric .hm-mk{ font-size:11px; color:var(--hm-muted); font-weight:800; text-transform:uppercase; letter-spacing:.1em; }
    .hm-dash .hm-metric .hm-mv{ font-size:24px; font-weight:800; letter-spacing:-.035em; margin-top:4px; }
    .hm-dash .hm-metric .hm-mv small{ font-size:12px; color:var(--hm-muted); font-weight:600; letter-spacing:0; }
    .hm-dash .hm-metric .hm-mv small.hm-under{ display:block; margin-top:2px; }
    .hm-dash .hm-mgrid{ display:grid; grid-template-columns:repeat(4,1fr); gap:12px; }
    .hm-dash .hm-tl{ display:flex; flex-direction:column; }
    .hm-dash .hm-mile{ display:flex; align-items:center; gap:14px; padding:12px 22px; border-bottom:1px solid var(--hm-border-soft); }
    .hm-dash .hm-mile:last-child{ border-bottom:0; }
    .hm-dash .hm-dot{ width:26px; height:26px; border-radius:50%; flex:none; display:grid; place-items:center; font-size:12px; font-weight:800; border:2px solid var(--hm-border-strong); color:var(--hm-muted); }
    .hm-dash .hm-dot.hm-paid{ background:var(--hm-brand); border-color:var(--hm-brand); color:var(--hm-on-brand); }
    .hm-dash .hm-mile .hm-mt{ flex:1; }
    .hm-dash .hm-mile .hm-mt b{ font-size:13.5px; }
    .hm-dash .hm-kv{ display:flex; flex-direction:column; gap:12px; padding:16px 22px; }
    .hm-dash .hm-kv .r{ display:flex; align-items:center; justify-content:space-between; gap:12px; }
    .hm-dash .hm-kv .r .l{ color:var(--hm-muted); font-size:12.5px; }
    .hm-dash .hm-kv .r .val{ font-weight:600; font-size:13px; }

    /* Responsive: fluid phone → desktop; nothing overflows the viewport. */
    @media (max-width:1400px){ .hm-dash .hm-kpis{ grid-template-columns:repeat(3,1fr); } }
    @media (max-width:1180px){ .hm-dash .hm-cols{ grid-template-columns:1fr; } }
    @media (max-width:1024px){ .hm-dash .hm-kpis{ grid-template-columns:repeat(2,1fr);} .hm-dash .hm-mgrid{ grid-template-columns:repeat(2,1fr);} }
    @media (max-width:820px){ .hm-dash .hm-two{ grid-template-columns:1fr; } }
    @media (max-width:560px){
        .hm-dash .hm-kpis{ grid-template-columns:1fr; }
        .hm-dash .hm-mgrid{ grid-template-columns:1fr; }
        .hm-dash .hm-kpi .hm-value{ font-size:24px; }
        .hm-dash .hm-metric .hm-mv{ font-size:19px; }
        .hm-dash .hm-head{ gap:10px; padding:14px; }
        .hm-dash .hm-head .hm-title{ font-size:17px; }
        .hm-dash .hm-phead{ padding:12px 14px; }
        .hm-dash tbody td, .hm-dash thead th{ padding:10px 12px; }
        .hm-dash .hm-mile, .hm-dash .hm-exc, .hm-dash .hm-metric, .hm-dash .hm-ledger, .hm-dash .hm-kv,
        .hm-dash .hm-note, .hm-dash .hm-body{ padding-left:14px; padding-right:14px; }
    }
    @media (max-width:380px){
        .hm-dash .hm-grid{ gap:12px; }
        .hm-dash .hm-two, .hm-dash .hm-stack{ gap:12px; }
        .hm-dash .hm-kpi .hm-value{ font-size:20px; }
    }
</style>
