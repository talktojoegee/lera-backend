<div class="page-break">
    <h1>11. Module 6 — Payment Collection &amp; Digital Channels</h1>
    <p>Revenue realization depends on making payment as easy as possible while maintaining full traceability. LERA supports multiple collection channels suited to Edo State's diverse taxpayer base:</p>
    <h3>11.1 Online Payment Gateway (RoutePay)</h3>
    <ul>
        <li>Hosted checkout initiated from the admin portal or taxpayer-facing payment links.</li>
        <li>Per-agency OAuth credentials and payment gateway configuration.</li>
        <li>Webhook-based settlement confirmation with signature verification and idempotent payment logging.</li>
        <li>Automatic bill status update (paid / partly paid) upon confirmed payment.</li>
    </ul>
    <h3>11.2 QR Code Payment on Bills</h3>
    <ul>
        <li>Each printed demand notice embeds a QR code encoding the agency payment URL.</li>
        <li>Taxpayers scan with any smartphone to reach the payment page instantly.</li>
        <li>Configurable URL templates supporting assessment number, building code, amount, and year parameters.</li>
    </ul>
    <h3>11.3 Bank / Switch Integration (eTranzact)</h3>
    <ul>
        <li>Pre-payment validation endpoint returning taxpayer name, amount due (including arrears), and agency details.</li>
        <li>Payment notification webhook with IP whitelist security for bank-posted transactions.</li>
        <li>Balance brought forward automatically included in validation totals.</li>
    </ul>
    <h3>11.4 Manual Receipt Workflow</h3>
    <ul>
        <li>Counter payments recorded by revenue officers with pending approval status.</li>
        <li>Supervisor approve/discard workflow before payment posts to bill.</li>
        <li>Digital receipt generation upon approval.</li>
    </ul>
    <h3>11.5 Self-Service Payment</h3>
    <ul>
        <li>Public bill lookup by assessment number.</li>
        <li>Payment completion page for online transactions.</li>
        <li>Automated receipt email with PDF attachment after successful payment.</li>
    </ul>
    <table>
        <tr><th>Channel</th><th>Taxpayer Experience</th><th>Government Benefit</th></tr>
        <tr><td>Online (RoutePay/Odoo)</td><td>Pay anytime from phone or computer</td><td>Instant posting, digital audit trail</td></tr>
        <tr><td>QR on bill</td><td>Scan and pay in seconds</td><td>Higher conversion from bill to payment</td></tr>
        <tr><td>Bank/USSD (eTranzact)</td><td>Pay at bank or via mobile</td><td>Reaches unbanked/underbanked segments</td></tr>
        <tr><td>Counter (manual receipt)</td><td>Pay at EIRS/LGA office</td><td>Controlled cash handling with approval</td></tr>
    </table>

    <h1>12. Module 7 — Receipts, Reconciliation &amp; Settlement</h1>
    <h3>12.1 Digital Receipts</h3>
    <ul>
        <li>Branded PDF receipts with payment details, revenue code, assessment reference, and verification QR code.</li>
        <li>Public receipt verification via receipt number lookup.</li>
        <li>Downloadable proof of payment for taxpayers.</li>
    </ul>
    <h3>12.2 Bank Reconciliation</h3>
    <ul>
        <li>Upload bank/payment provider settlement files (Excel format).</li>
        <li>Automatic matching against payment logs with exception reporting.</li>
        <li>Reconciliation history with UUID tracking and confirmation/lock for closed periods.</li>
        <li>Unreconciled transaction reports for investigation.</li>
        <li>Re-query and manual reconciliation tools for disputed items.</li>
    </ul>
    <h3>12.3 Settlement Reporting</h3>
    <ul>
        <li>Configurable revenue split percentages (state agency, LGA, partner shares).</li>
        <li>Settlement reports by agency and date range for treasury disbursement.</li>
        <li>Exportable settlement data for accounting systems.</li>
    </ul>
    <p><strong>Fiscal integrity:</strong> Reconciliation ensures that every Naira collected is accounted for before it reaches the treasury — a critical safeguard for Edo State's public financial management framework.</p>

    <h1>13. Module 8 — Dashboard, Analytics &amp; Executive Monitoring</h1>
    <p>LERA transforms raw transaction data into actionable intelligence for policymakers and revenue managers.</p>
    <h3>13.1 Statewide Executive Dashboard</h3>
    <ul>
        <li>Total properties registered statewide</li>
        <li>Approved, pending, and objected bill counts</li>
        <li>Total amount billed vs. total amount collected (₦)</li>
        <li>Bills printed and delivered counts</li>
        <li><strong>Per-agency breakdown tab:</strong> Same KPIs for each LGA/agency — enabling comparative performance review</li>
    </ul>
    <h3>13.2 Visual Analytics</h3>
    <ul>
        <li>Monthly payment trend charts (area/line)</li>
        <li>Bill distribution by geographic zone (donut/bar)</li>
        <li>Bill and payment comparison by LGA</li>
        <li>Property distribution maps by zone and LGA</li>
        <li>Year-over-year filtering for trend analysis</li>
    </ul>
    <h3>13.3 LGA Chair Dashboard</h3>
    <ul>
        <li>Dedicated view scoped to the chairperson's LGA</li>
        <li>Outstanding bills list and payment history for local oversight</li>
        <li>Localized charts without exposure to other LGAs' data</li>
    </ul>
    <div class="highlight">
        <p><strong>Governor's Office use case:</strong> Weekly review of collection rate by LGA, identification of underperforming zones, and data-driven deployment of enforcement and sensitization resources.</p>
    </div>
</div>

<div class="page-break">
    <h1>14. Module 9 — Reporting &amp; Regulatory Compliance</h1>
    <p>LERA includes a comprehensive reporting suite for operational, financial, and compliance purposes:</p>
    <table>
        <tr><th>Report</th><th>Purpose</th><th>Export</th></tr>
        <tr><td>Customer Statement</td><td>Payment history by property/building code</td><td>PDF, Excel</td></tr>
        <tr><td>Payment Report</td><td>Collections by LGA, zone, ward, date range</td><td>Excel</td></tr>
        <tr><td>Workflow Report</td><td>Bill pipeline metrics by approval stage</td><td>Excel</td></tr>
        <tr><td>Performance Report</td><td>Staff productivity and processing volumes</td><td>Excel</td></tr>
        <tr><td>Delivery Report</td><td>Bill serving status by area</td><td>Excel</td></tr>
        <tr><td>Print Report</td><td>Printed bill batches and volumes</td><td>Excel</td></tr>
        <tr><td>Settlement Report</td><td>Revenue distribution by agency</td><td>Excel</td></tr>
        <tr><td>Reconciliation Report</td><td>Matched vs. unmatched transactions</td><td>Excel</td></tr>
        <tr><td>Property Exception Export</td><td>Data quality remediation list</td><td>Excel</td></tr>
        <tr><td>Bill Exports (async)</td><td>Outstanding, paid, pending, returned, partly-paid</td><td>Excel (queued)</td></tr>
    </table>
    <p>Large exports run asynchronously via background jobs with status polling and download links — ensuring the system remains responsive even when generating reports spanning millions of records.</p>

    <h1>15. Module 10 — Objections, Appeals &amp; Taxpayer Rights</h1>
    <p>A fair revenue system must provide structured mechanisms for taxpayers to challenge assessments. LERA's objection module supports:</p>
    <ul>
        <li>Formal objection submission with reason, relief selections, and document attachments.</li>
        <li>Multi-stage review: Request → Verification → Authorization (revised LUC determination) → Approval.</li>
        <li>Upon approval: archival of original bill and issuance of revised assessment.</li>
        <li>Email notifications at status transitions.</li>
        <li>Searchable objection queues and dedicated objected-bills views.</li>
    </ul>
    <p>This protects Edo State from arbitrary assessment practices while giving EIRS a defensible, documented process for handling disputes — reducing litigation risk and building taxpayer confidence.</p>

    <h1>16. Module 11 — Security, RBAC &amp; Audit</h1>
    <h3>16.1 Authentication</h3>
    <ul>
        <li>JWT-based API authentication with token expiration and logout.</li>
        <li>Forced password change for default credentials.</li>
        <li>Typed user login supporting role-specific portals.</li>
    </ul>
    <h3>16.2 Role-Based Access Control</h3>
    <ul>
        <li>Granular permission codes (50+ permissions) controlling menu visibility and API access.</li>
        <li>Roles: Administrator, Biller, Reviewer, Verifier, Authorizer, Approver, LGA Chair, and custom roles.</li>
        <li>Permission assignment interface for security administrators.</li>
    </ul>
    <h3>16.3 Sector Scoping</h3>
    <ul>
        <li>Users assigned to one or more LGAs/agencies via sector field.</li>
        <li>Automatic query filtering — officers cannot access data outside their sector.</li>
        <li>403 enforcement on unauthorized LGA access attempts.</li>
    </ul>
    <h3>16.4 Audit Trail</h3>
    <ul>
        <li>Activity logging for bill deletions, property deletions, receipt posting, and critical state changes.</li>
        <li>Webhook idempotency and timestamp tolerance for payment integrity.</li>
    </ul>
</div>

<div class="page-break">
    <h1>17. Module 12 — Multi-Agency &amp; LGA Configuration</h1>
    <p>Edo State comprises multiple LGAs and potentially delegated revenue agencies. LERA supports this structure through:</p>
    <ul>
        <li><strong>Agency registry:</strong> Create and manage LGAs with contact details and operational parameters.</li>
        <li><strong>Per-agency payment gateway:</strong> Independent RoutePay credentials, return URLs, and webhook endpoints.</li>
        <li><strong>Agency-branded documents:</strong> Custom PDF backgrounds and logos per agency.</li>
        <li><strong>Sector assignment:</strong> Map users to agencies for scoped operations.</li>
        <li><strong>Distribution codes &amp; zones:</strong> Geographic subdivisions within agencies for targeted operations.</li>
    </ul>
    <p>This architecture allows Edo State to implement a "single platform, multiple operators" model — the gold standard for statewide revenue systems.</p>
</div>
