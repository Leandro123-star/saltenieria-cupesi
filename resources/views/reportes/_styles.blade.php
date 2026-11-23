<style>
    .report-intro{margin:-4px 0 16px;color:var(--muted)}
    .metric{font-size:24px;font-weight:700;color:var(--pri);margin-top:5px}
    .metric-label{color:var(--muted);font-size:13px}
    .bar-row{display:grid;grid-template-columns:minmax(110px,1fr) 3fr 70px;align-items:center;gap:10px;margin:10px 0;font-size:13px}
    .bar-track{height:18px;background:#f2e6dc;border-radius:99px;overflow:hidden}.bar-fill{height:100%;background:var(--pri);border-radius:99px}
    .stock-low{background:#fff3cd;color:#936b00}.stock-empty{background:#fdecea;color:#b42318}
    @media(max-width:650px){.bar-row{grid-template-columns:90px 1fr 58px;font-size:12px}}
</style>
