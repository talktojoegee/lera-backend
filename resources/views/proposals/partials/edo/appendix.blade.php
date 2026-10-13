<div class="page-break">
    <h1>Appendix A — Technical Specifications</h1>
    <table>
        <tr><th>Component</th><th>Specification</th></tr>
        <tr><td>Backend Framework</td><td>Laravel 11 (PHP 8.2+)</td></tr>
        <tr><td>Frontend Framework</td><td>Angular (TypeScript), PrimeNG, Bootstrap</td></tr>
        <tr><td>Authentication</td><td>JWT (tymon/jwt-auth)</td></tr>
        <tr><td>Database</td><td>MySQL 8+ / PostgreSQL 14+</td></tr>
        <tr><td>PDF Generation</td><td>DomPDF (barryvdh/laravel-dompdf), FPDI merge</td></tr>
        <tr><td>Excel Export</td><td>Maatwebsite Excel 3.x</td></tr>
        <tr><td>Job Queue</td><td>Laravel Queue (database/Redis), Laravel Horizon</td></tr>
        <tr><td>Payment Integrations</td><td>RoutePay (OAuth + webhook), eTranzact, Odoo portal</td></tr>
        <tr><td>QR Generation</td><td>QuickChart.io API (embedded on PDF bills)</td></tr>
        <tr><td>Email</td><td>Laravel Mail (queued receipt notifications)</td></tr>
        <tr><td>API Style</td><td>RESTful JSON API</td></tr>
        <tr><td>Hosting</td><td>Linux server (Ubuntu), Nginx/Apache, SSL/TLS required</td></tr>
        <tr><td>Minimum Server (pilot)</td><td>2 vCPU, 4GB RAM, 70GB SSD</td></tr>
        <tr><td>Recommended (statewide)</td><td>4+ vCPU, 8GB+ RAM, managed database, Redis for queues</td></tr>
    </table>

    <h1>Appendix B — User Roles &amp; Permissions Matrix (Summary)</h1>
    <table>
        <tr><th>Role</th><th>Primary Functions</th><th>Key Permissions</th></tr>
        <tr><td>System Administrator</td><td>Platform configuration, user management, agency setup</td><td>All permissions</td></tr>
        <tr><td>Biller</td><td>Process bills, edit assessments</td><td>PROCESS_BILLING, bill queues</td></tr>
        <tr><td>Reviewer</td><td>First-level bill review</td><td>Workflow status 0→1</td></tr>
        <tr><td>Verifier</td><td>Technical verification</td><td>Workflow status 1→2</td></tr>
        <tr><td>Authorizer</td><td>Management authorization</td><td>Workflow status 2→3</td></tr>
        <tr><td>Approver</td><td>Final bill approval</td><td>Workflow status 3→4, PRINT_BILLS</td></tr>
        <tr><td>Cashier / Receipt Officer</td><td>Manual receipt creation</td><td>NEW_RECEIPT, MANAGE_RECEIPT</td></tr>
        <tr><td>Report Analyst</td><td>Generate and export reports</td><td>REPORT_* permissions</td></tr>
        <tr><td>Reconciliation Officer</td><td>Bank reconciliation processing</td><td>RECONCILIATION_*</td></tr>
        <tr><td>LGA Chair</td><td>LGA-scoped oversight dashboard</td><td>LGA_CHAIR_DASHBOARD</td></tr>
    </table>

    <h1>Appendix C — Payment Integration Overview</h1>
    <h3>C.1 RoutePay Hosted Checkout Flow</h3>
    <ol>
        <li>Officer or taxpayer initiates payment from LERA portal.</li>
        <li>System registers transaction with RoutePay and returns redirect URL.</li>
        <li>Taxpayer completes payment on RoutePay hosted page.</li>
        <li>RoutePay sends webhook notification to LERA API.</li>
        <li>LERA verifies signature, logs payment, updates bill status, queues receipt email.</li>
        <li>Taxpayer redirected to payment completion page.</li>
    </ol>
    <h3>C.2 Bill QR Payment Flow</h3>
    <ol>
        <li>Approved bill printed with QR code encoding payment URL.</li>
        <li>Taxpayer scans QR with smartphone camera.</li>
        <li>Browser opens agency payment portal (e.g., Odoo) with assessment number pre-filled.</li>
        <li>Payment completed on portal; confirmation flows back via webhook or manual reconciliation.</li>
    </ol>
    <h3>C.3 eTranzact Bank Channel Flow</h3>
    <ol>
        <li>Taxpayer presents assessment number at bank or via USSD.</li>
        <li>Bank calls LERA validation API — receives name, amount, agency.</li>
        <li>Upon payment, bank sends notification to LERA.</li>
        <li>LERA posts payment and generates receipt.</li>
    </ol>

    <h1>Appendix D — Glossary</h1>
    <table>
        <tr><th>Term</th><th>Definition</th></tr>
        <tr><td>LUC</td><td>Land Use Charge — property-based levy on land and buildings</td></tr>
        <tr><td>IGR</td><td>Internally Generated Revenue</td></tr>
        <tr><td>EIRS</td><td>Edo State Internal Revenue Service</td></tr>
        <tr><td>LGA</td><td>Local Government Area</td></tr>
        <tr><td>PAV</td><td>Property Assessment Value — unit valuation for charge computation</td></tr>
        <tr><td>BBF</td><td>Balance Brought Forward — arrears from prior years</td></tr>
        <tr><td>KGTIN</td><td>Kogi/State Tax Identification Number for taxpayers</td></tr>
        <tr><td>Assessment No.</td><td>Unique identifier for a bill instance</td></tr>
        <tr><td>Building Code</td><td>Unique identifier for a property in the registry</td></tr>
        <tr><td>LERA</td><td>Land Administration &amp; Revenue Automation Platform</td></tr>
    </table>

    <div class="footer-note">
        <p>This document is proprietary and prepared exclusively for the Edo State Government. Unauthorized reproduction or distribution is prohibited. &copy; {{ date('Y') }} LERA Platform.</p>
    </div>
</div>
