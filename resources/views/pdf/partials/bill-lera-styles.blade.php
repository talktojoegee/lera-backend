<style>
    /*
     | LERA demand notice overlay — background-2.jpeg (A4 portrait 210×297mm).
     | Tune :root values if alignment drifts vs. your JPEG export.
     */
    :root {
        /* Same line as header title “… NOTICE” — font matches title overlay on art */
        --lera-year-top: 23.5mm;
        --lera-year-left: 128mm;
        --lera-year-font: 17pt;
        --lera-year-color: #0f6b5c;
        --lera-prop-top: 93mm;
        --lera-prop-left: 10mm;
        --lera-prop-width: 92mm;
        --lera-qr-top: 98mm;
        --lera-qr-left: 112mm;
        --lera-qr-box: 48mm;
        --lera-qr-img-max: 38mm;
        --lera-assess-top: 169mm;
        --lera-assess-left: 10mm;
        --lera-assess-width: 190mm;
        --lera-total-num-top: 184mm;
        --lera-total-num-left: 126mm;
        --lera-total-num-width: 70mm;
        --lera-words-top: 191mm;
        --lera-words-left: 11mm;
        --lera-words-width: 118mm;
        --lera-pod-top: 255mm;
        --lera-pod-left: 10mm;
        --lera-pod-width: 96mm;
    }
    @page {
        margin: 0;
        size: A4 portrait;
    }
    * {
        box-sizing: border-box;
    }
    body {
        margin: 0;
        padding: 0;
        font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
        font-size: 9pt;
        color: #1a2b3c;
    }
    .lera-page {
        width: 210mm;
        height: 297mm;
        position: relative;
        page-break-after: always;
        page-break-inside: avoid;
        background-image: url('<?php echo e(asset('assets/images/background-22.png')); ?>');
        background-repeat: no-repeat;
        background-position: top center;
        background-size: 210mm 297mm;
    }
    .lera-page:last-child {
        page-break-after: auto;
    }
    .lera-overlay {
        position: relative;
        width: 210mm;
        height: 297mm;
    }
    .lera-val {
        font-weight: 600;
        word-wrap: break-word;
    }

    /* Year on same baseline as title block (after “NOTICE”) */
    .lera-year-suffix {
        position: absolute;
        top: var(--lera-year-top);
        left: var(--lera-year-left);
        font-size: var(--lera-year-font);
        font-weight: 700;
        color: var(--lera-year-color);
        line-height: 1.05;
        letter-spacing: 0.02em;
        white-space: nowrap;
        margin-top: -30px;
        margin-right: 5000px;
    }

    /* Label | value on one row (DomPDF-friendly two-column table) */
    .lera-kv-table {
        position: absolute;
        table-layout: fixed;
        width: 100%;
        border-collapse: collapse;
        border: none;
        margin-top: -30px;
    }
    /* POD uses its own row density; min-height here made slip rows too tall */
    .lera-kv-table:not(.lera-pod-kv) tr {
        min-height: 6mm;
    }
    .lera-kv-table td {
        border: none;
        vertical-align: top;
        padding: 0 0 4.2mm 0;
        font-size: 8.5pt;
        line-height: 1.25;
    }
    .lera-k {
        width: 40%;
        max-width: 40%;
        font-weight: 700;
        white-space: nowrap;
        padding-right: 1.5mm;
    }
    .lera-v {
        width: 60%;
        max-width: 60%;
        text-align: left;
        word-wrap: break-word;
    }
    .lera-prop-kv {
        top: var(--lera-prop-top);
        left: var(--lera-prop-left);
        width: var(--lera-prop-width);
    }
    .lera-pod-kv {
        top: var(--lera-pod-top);
        left: var(--lera-pod-left);
        width: var(--lera-pod-width);
        margin-top: 30px !important;
    }
    .lera-pod-kv tr.lera-pod-row {
        height: auto;
        min-height: 0 !important;
    }
    .lera-pod-kv tr.lera-pod-row td {
        padding: 0;
        line-height: 1;
        font-size: 7pt;
    }
    /* Space between rows (tr margin is ignored in tables; padding on td is reliable in DomPDF) */
    .lera-pod-kv tr.lera-pod-row:not(:last-child) td {
        padding-bottom: 1.8mm;
    }

    /* Payment QR — centered inside white card */
    .lera-qr-wrap {
        position: absolute;
        top: var(--lera-qr-top);
        left: var(--lera-qr-left);
        width: var(--lera-qr-box);
        height: var(--lera-qr-box);
    }
    .lera-qr-inner {
        width: 100%;
        height: 100%;
        border-collapse: collapse;
    }
    .lera-qr-inner td {
        text-align: center;
        vertical-align: middle;
        padding: 0;
        border: none;
    }
    .lera-qr-img {
        max-width: var(--lera-qr-img-max);
        max-height: var(--lera-qr-img-max);
        width: var(--lera-qr-img-max);
        height: var(--lera-qr-img-max);
        object-fit: contain;
        margin-top: -50px;
        margin-left: -100px;
    }

    /* Assessment: DATE | CATEGORY | AMOUNT */
    .lera-assess-row {
        position: absolute;
        top: var(--lera-assess-top);
        left: var(--lera-assess-left);
        width: var(--lera-assess-width);
        font-size: 8.5pt;
        border-collapse: collapse;
        border: none;
        table-layout: fixed;
        margin-top: -30px;
    }
    .lera-assess-row td {
        vertical-align: middle;
        padding: 0.8mm 3mm;
        border: none;
        text-align: center;
        width: 33.33%;
        white-space: nowrap;
    }
    .lera-assess-date {
        text-align: center;
    }
    .lera-assess-cat {
        text-align: center;
    }
    .lera-assess-amt {
        text-align: center;
        font-weight: 700;
    }

    .lera-total-num {
        position: absolute;
        top: var(--lera-total-num-top);
        left: var(--lera-total-num-left);
        width: var(--lera-total-num-width);
        text-align: right;
        font-size: 13pt;
        font-weight: bold;
        letter-spacing: 0.02em;
        margin-top: -50px;
    }

    .lera-total-words {
        position: absolute;
        top: var(--lera-words-top);
        left: var(--lera-words-left);
        width: var(--lera-words-width);
        text-align: right;
        font-size: 7.5pt;
        line-height: 1.2;
        margin-top: -40px;
    }
</style>
