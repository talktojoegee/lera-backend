<div class="page-break">
    <h1>5. System Architecture Overview</h1>
    <p>LERA follows a modern three-tier architecture optimized for security, maintainability, and integration:</p>
    <table>
        <tr><th>Layer</th><th>Technology</th><th>Responsibility</th></tr>
        <tr><td>Presentation</td><td>Angular SPA, PrimeNG, ApexCharts</td><td>User interface, dashboards, role-based navigation, exports download</td></tr>
        <tr><td>Application</td><td>Laravel 11 REST API, JWT Auth</td><td>Business rules, workflows, permissions, job queues, PDF/Excel generation</td></tr>
        <tr><td>Data</td><td>MySQL / PostgreSQL</td><td>Transactional data, property registry, bills, payments, audit logs</td></tr>
        <tr><td>Integration</td><td>HTTP webhooks, OAuth, GIS sync</td><td>Payment gateways, enumeration databases, notification services</td></tr>
    </table>
    <h3>5.1 Core Design Principles</h3>
    <ul>
        <li><strong>API-first:</strong> All functionality exposed via documented REST endpoints, enabling future mobile apps and citizen portals.</li>
        <li><strong>Asynchronous processing:</strong> Heavy tasks (annual billing, batch PDFs, bulk exports) run in background queues without blocking users.</li>
        <li><strong>Sector multi-tenancy:</strong> Users are scoped to authorized LGAs/agencies; data queries automatically enforce boundaries.</li>
        <li><strong>Auditability:</strong> Critical actions (deletions, payment posting, workflow transitions) are logged.</li>
        <li><strong>Configurable rules:</strong> Charge rates, depreciation, minimum LUC, relief settings, and agency payment credentials are administratively configurable.</li>
    </ul>

    <h1>6. Module 1 — Property &amp; Taxpayer Registry</h1>
    <p>The foundation of any property tax system is an accurate, searchable register of land parcels, buildings, owners, and occupancy characteristics. LERA's property module provides:</p>
    <h3>6.1 Owner Management</h3>
    <ul>
        <li>Registration and maintenance of property owner records with KGTIN (tax identification), contact details, and correspondence addresses.</li>
        <li>Search and lookup by identification number, name, or linked properties.</li>
        <li>Validation of contact data for billing and notification purposes.</li>
    </ul>
    <h3>6.2 Property Registry</h3>
    <ul>
        <li>Comprehensive property profiles: building code, address, ward, zone, LGA, property use/type, classification, assessed value indicators, coordinates, and imagery.</li>
        <li>Paginated statewide listing with sector-based filtering for LGA officers.</li>
        <li>Advanced keyword search across property attributes.</li>
        <li>Controlled editing of property fields with business rule enforcement (e.g., deletion blocked when paid bills exist).</li>
    </ul>
    <h3>6.3 Exempted Properties</h3>
    <ul>
        <li>Dedicated register for properties granted full or partial exemption (government facilities, places of worship where applicable by policy, etc.).</li>
        <li>Dashboard visibility of exempt inventory for policy review.</li>
    </ul>
    <h3>6.4 Property Exceptions</h3>
    <ul>
        <li>Queue of properties failing validation or sync rules (missing classification, incomplete enumeration data, duplicate building codes).</li>
        <li>Exportable exception reports for field remediation campaigns.</li>
    </ul>
    <p><strong>Revenue impact:</strong> Every bill issued in LERA is anchored to a verified property record, eliminating "ghost bills" and ensuring collection efforts target real assets.</p>

    <h1>7. Module 2 — GIS Synchronization &amp; Data Quality</h1>
    <p>Edo State's property enumeration initiatives and GIS investments produce valuable data that must flow into the revenue system automatically rather than through manual re-entry.</p>
    <ul>
        <li><strong>LGA-level synchronization:</strong> Pull building records from enumeration databases or remote GIS APIs into LERA's property registry.</li>
        <li><strong>Sync reporting:</strong> Historical logs of synchronization jobs with success/failure metrics.</li>
        <li><strong>Force sync:</strong> Re-pull individual properties when field corrections are made at source.</li>
        <li><strong>Bulk re-synchronization:</strong> Queue large remediation batches after enumeration drives.</li>
        <li><strong>Street name propagation:</strong> Updates street addressing on properties and linked bills when source data changes.</li>
    </ul>
    <p>This module ensures the revenue database stays current as Edo State's built environment evolves — critical for capturing new developments in the tax base promptly.</p>

    <h1>8. Module 3 — Billing Engine &amp; Assessment Rules</h1>
    <p>LERA's billing engine automates the computationally intensive process of annual (or periodic) Land Use Charge assessment across thousands or millions of properties.</p>
    <h3>8.1 Master Data Configuration</h3>
    <p>Before billing runs, administrators configure the rule framework:</p>
    <ul>
        <li>Zones, wards, and geographic hierarchies</li>
        <li>Property classifications (residential, commercial, industrial, mixed-use, etc.)</li>
        <li>Property Assessment Values (PAV) by type and sector</li>
        <li>Charge rates and occupancy multipliers</li>
        <li>Depreciation schedules</li>
        <li>Minimum LUC floors</li>
        <li>Relief and incentive settings</li>
        <li>Area offices and distribution codes</li>
    </ul>
    <h3>8.2 Bill Processing</h3>
    <ul>
        <li><strong>Preview:</strong> Officers preview eligible properties for a billing year before execution.</li>
        <li><strong>Async processing:</strong> Billing runs execute as background jobs, computing charges from PAV × rate × depreciation with minimum LUC enforcement.</li>
        <li><strong>Duplicate prevention:</strong> System skips properties already billed for the target year.</li>
        <li><strong>Exception logging:</strong> Properties without matching PAV codes are flagged rather than silently skipped.</li>
        <li><strong>Rollback:</strong> Authorized rollback of unapproved bills for a year when policy corrections require re-processing.</li>
    </ul>
    <h3>8.3 Bill Lifecycle States</h3>
    <table>
        <tr><th>Status</th><th>Stage</th><th>Description</th></tr>
        <tr><td>0</td><td>Pending</td><td>Newly generated, awaiting review</td></tr>
        <tr><td>1</td><td>Reviewed</td><td>Initial quality check completed</td></tr>
        <tr><td>2</td><td>Verified</td><td>Technical/financial verification</td></tr>
        <tr><td>3</td><td>Authorized</td><td>Management authorization</td></tr>
        <tr><td>4</td><td>Approved</td><td>Final approval — eligible for printing and payment</td></tr>
        <tr><td>5</td><td>Returned</td><td>Sent back for correction with documented reason</td></tr>
    </table>
</div>

<div class="page-break">
    <h1>9. Module 4 — Workflow, Approvals &amp; Governance Controls</h1>
    <p>Fiscal governance requires that no bill reaches a taxpayer without passing through defined checkpoints. LERA enforces segregation of duties through a multi-stage workflow:</p>
    <ul>
        <li><strong>Single bill actions:</strong> Review, verify, authorize, approve, or return individual bills with audit trail.</li>
        <li><strong>Bulk actions:</strong> Process batches of bills at each workflow stage for operational efficiency.</li>
        <li><strong>Bill editing:</strong> Authorized officers may adjust assessed values, rates, and amounts prior to final approval.</li>
        <li><strong>Special interest bills:</strong> Parallel workflow track for high-priority or sensitive assessments requiring enhanced scrutiny.</li>
        <li><strong>Returned bills queue:</strong> Dedicated views for bills sent back for correction, with search and filtering.</li>
        <li><strong>Deletion controls:</strong> Bills with recorded payments cannot be deleted; deletions are activity-logged.</li>
    </ul>
    <p>For Edo State, this workflow maps directly to EIRS internal control standards and can be aligned with state audit requirements. Each role — Biller, Reviewer, Verifier, Authorizer, Approver — receives only the permissions necessary for their function.</p>

    <h1>10. Module 5 — Bill Printing, Delivery &amp; Citizen Engagement</h1>
    <h3>10.1 Print Selection &amp; Batch Generation</h3>
    <ul>
        <li>Filter approved bills by LGA, zone, ward, distribution code, street name, and property class.</li>
        <li>Initiate print batches that mark bills as printed and assign batch codes.</li>
        <li>Background generation of consolidated batch PDFs with agency-branded demand notice templates.</li>
        <li>Regeneration capability when templates or QR payment links are updated.</li>
    </ul>
    <h3>10.2 Demand Notice Features</h3>
    <ul>
        <li>Professional PDF layout with property details, assessment number, charge breakdown, balance brought forward, and total payable.</li>
        <li>Payment QR codes linking to online payment portals (configurable per agency).</li>
        <li>Proof of delivery (POD) section for field collection agents.</li>
        <li>Agency-specific branding (background templates per LGA/agency).</li>
    </ul>
    <h3>10.3 Delivery Tracking</h3>
    <ul>
        <li>Update delivery status when bills are physically served to taxpayers.</li>
        <li>Delivery metrics on executive dashboard for monitoring field operations.</li>
    </ul>
    <p><strong>Citizen benefit:</strong> Clear, standardized demand notices with scannable QR codes reduce confusion and enable immediate payment — a proven driver of compliance.</p>
</div>
