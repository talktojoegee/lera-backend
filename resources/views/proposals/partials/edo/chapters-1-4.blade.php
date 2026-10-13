<div class="page-break">
    <h1>1. Introduction &amp; Policy Context</h1>
    <p>Edo State Government has demonstrated consistent commitment to economic development, infrastructure investment, and public service delivery. Sustainable funding for these priorities depends fundamentally on the state's capacity to mobilize domestic revenue efficiently, fairly, and transparently. Federal allocations, while important, are insufficient and unpredictable as the sole basis for developmental expenditure. Internally Generated Revenue (IGR) — particularly property-linked charges rooted in land use, occupancy, and development — must therefore occupy a central place in Edo State's fiscal strategy.</p>
    <p>The Land Use Act (1978) and subsequent state-level legislation vest land administration and related revenue powers in state and local authorities. In practice, effective collection requires more than legal authority: it requires accurate property data, defensible valuations, timely billing, accessible payment channels, robust enforcement mechanisms, and executive dashboards that allow the Governor, Commissioner for Finance, and EIRS leadership to monitor performance in real time.</p>
    <p>LERA addresses this requirement holistically. Developed as a full-stack enterprise platform comprising a secure REST API backend and a modern web-based administrative portal, LERA has been designed for deployment across multiple agencies and LGAs within a single state ecosystem. The platform supports the complete revenue chain from property discovery to payment confirmation, receipt issuance, bank reconciliation, and inter-agency settlement.</p>

    <h2>2. Background: Revenue Administration in Sub-National Governments</h2>
    <p>Across Nigeria, sub-national revenue authorities face recurring operational constraints that limit IGR performance:</p>
    <ul>
        <li><strong>Data fragmentation:</strong> Property records may exist in GIS databases, paper field enumeration forms, LGA registers, and legacy spreadsheets — often without a golden source of truth.</li>
        <li><strong>Manual assessment:</strong> Valuation and charge computation depend on individual officer judgment, producing inconsistency and disputes.</li>
        <li><strong>Weak workflow controls:</strong> Bills may be issued without adequate review, authorization, or segregation of duties — creating fiscal and reputational risk.</li>
        <li><strong>Limited payment options:</strong> Taxpayers face inconvenience when only cash or in-person payment is available, depressing compliance.</li>
        <li><strong>Reconciliation gaps:</strong> Bank deposits, payment gateway settlements, and bill records frequently fail to align, obscuring true collection performance.</li>
        <li><strong>Delayed reporting:</strong> Executives receive revenue data weeks or months after transactions occur, preventing agile policy intervention.</li>
    </ul>
    <p>Digital revenue platforms have reversed these trends in jurisdictions that have invested in integrated systems. Automation reduces leakage. Workflow enforcement reduces fraud. Online payment increases compliance. Dashboards enable target-setting and accountability at LGA and ward levels. LERA embodies these principles in a solution already architected for Nigerian revenue administration practice.</p>

    <h2>3. Problem Statement &amp; Gap Analysis</h2>
    <h3>3.1 Revenue Leakage &amp; Under-Assessment</h3>
    <p>Without a comprehensive property register linked to automated assessment rules, significant portions of the taxable base remain outside the billing net. New developments, change of use, and occupancy modifications are often detected late — if at all. Minimum charge floors, depreciation schedules, and property classification rules are inconsistently applied.</p>
    <h3>3.2 Collection Inefficiency</h3>
    <p>Physical bill distribution is costly and slow. Taxpayers who are willing to pay may be unable to do so conveniently. Arrears accumulate not solely from unwillingness to pay but from friction in the payment process and uncertainty about amounts due.</p>
    <h3>3.3 Weak Executive Oversight</h3>
    <p>State leadership requires answers to straightforward questions: How much was billed this year? How much was collected? Which LGA is underperforming? What is the arrears position by zone? Without integrated data, these questions require manual compilation — often producing conflicting figures from different departments.</p>
    <h3>3.4 Compliance &amp; Trust Deficit</h3>
    <p>Taxpayers who cannot verify their assessments, obtain receipts promptly, or lodge formal objections through a transparent process are less likely to comply voluntarily. A fair, auditable system strengthens the social contract between government and citizens.</p>

    <h2>4. Proposed Solution: The LERA Platform</h2>
    <p>LERA is proposed as Edo State's statewide integrated platform for property-based revenue administration. The solution comprises:</p>
    <ul>
        <li><strong>LERA Backend API</strong> — Laravel-based enterprise service handling business logic, integrations, queues, PDF generation, exports, and webhooks.</li>
        <li><strong>LERA Admin Portal</strong> — Angular-based web application for revenue officers, approvers, LGA administrators, and executives.</li>
        <li><strong>Integration Layer</strong> — Connections to payment gateways (RoutePay, eTranzact, Odoo payment portal), GIS enumeration databases, and email notification services.</li>
        <li><strong>Document Services</strong> — Automated generation of demand notices, batch bill PDFs with payment QR codes, payment receipts, and regulatory reports.</li>
    </ul>
    <div class="highlight">
        <p><strong>Deployment model:</strong> A single statewide instance with sector-scoped access ensures Edo State maintains unified data standards and executive visibility, while LGAs and delegated agencies operate within their authorized geographic and functional boundaries.</p>
    </div>
    <p>The platform is cloud-ready, queue-driven for heavy operations (billing runs, batch PDF generation, large exports), and designed for horizontal scaling as transaction volumes grow.</p>
</div>
