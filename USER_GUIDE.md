# Prativa Inventory & Auditing System — User Guide
**Internal Stock Auditing, Tiered Procurement & Financial Integrity Platform**

---

## 1. System Overview & Key Principles

This platform manages the complete lifecycle of school equipment, physical assets, procurement approvals, invoice matching, and petty cash disbursements.

### Core Integrity Rules (Enforced by Database)
1. **Separation of Duties (Two-Person Rule):**
   - The person who **places a purchase order** can *never* be the one who **verifies and receives the goods**.
   - The person who **issues a petty cash token** can *never* be the one who **releases payment** in Accounts.
2. **No Self-Approval:**
   - Any staff member who raises a demand form is automatically blocked from signing off or approving it, even if they hold an approver role.
3. **Immutable History & Stock Ledger:**
   - Stock entries and audit logs cannot be updated or deleted. Correcting a stock count writes a new ledger entry recording previous vs. new counts.
4. **Three-Way Invoice Matching:**
   - Bills are automatically checked against the original approved demand form and purchase order. Any excess requires explicit written sign-off by Accounts.

---

## 2. Roles and Default Logins

### Default Passwords:
- **Platform Owner:** `admin123`
- **School Staff Accounts:** `Prativa@2026`

### Role Directory:

| Role / Designation | Sample Account | Responsibilities & Capabilities |
| :--- | :--- | :--- |
| **Platform Super Admin** | `admin@gmail.com` | Manages school tenants at `/platform`, full global oversight, school setup. |
| **Managing Director** | `md@prativa.edu.np` | School Super Admin, tier 3 approver (up to Rs. 2,00,000), user management. |
| **Chairman** | `chairman@prativa.edu.np` | Tier 4 approver (Rs. 2,00,001+ with minute reference). |
| **Administrative Officer**| `admin.officer@prativa.edu.np` | Tier 2 approver (Rs. 15,001 to Rs. 50,000), operational reviews. |
| **HOD — Science** | `hod.science@prativa.edu.np` | Tier 1 approver (Rs. 100 to Rs. 15,000), department demands. |
| **Purchase Officer** | `purchase@prativa.edu.np` | Converts approved demands into Purchase Orders, selects vendors. |
| **Store Keeper (Receiving)**| `store@prativa.edu.np` | Inspects physical deliveries, counts units, signs Goods Receipts. |
| **Accounts Officer** | `accounts@prativa.edu.np` | Registers bills, 3-way match, issues petty cash tokens. |
| **Accounts Assistant** | `accounts2@prativa.edu.np` | Verifies and disburses petty cash payments. |
| **Stock Auditor** | `auditor@prativa.edu.np` | Conducts physical stock counts across assigned blocks/rooms. |
| **Teacher / Initiator** | `p.karki@prativa.edu.np` | Raises demand forms for classroom supplies and repairs. |

---

## 3. End-to-End Procurement Workflow

```
[ Teacher / Initiator ]
       │
       ▼
 Raises Demand Form (Items, Qty, Estimated Rate)
       │
       ▼
[ Approval Ladder (Tiers 1 → 4) ]
   • Tier 1: HOD (up to Rs. 15,000)
   • Tier 2: Admin Officer (up to Rs. 50,000)
   • Tier 3: Managing Director (up to Rs. 2,00,000)
   • Tier 4: Chairman (Rs. 2,00,001+ with Minute Reference)
       │
       ▼
[ Purchase Officer ]
   • Selects Vendor (PAN/VAT, terms)
   • Issues Purchase Order (PO)
       │
       ▼
[ Store Keeper ] ◄── (Must NOT be the Purchase Officer)
   • Goods physically delivered
   • Inspects condition and counts quantity
   • Creates Goods Received Note (GRN)
       │
       ▼
[ Accounts Department ]
   • Receives Vendor Tax Invoice / Bill
   • Three-Way Match: Demand Approval vs PO vs Bill Amount
   • Clears any variance with mandatory written remarks
   • Marks ready for final payment
```

---

## 4. Module Guide & Step-by-Step Instructions

### A. Demands & Requisitions (`/demands`)
1. **Create Demand:**
   - Navigate to **Demands** > click **"Raise a demand"**.
   - Select items from the catalogue or enter custom descriptions.
   - Specify quantity, estimated rate, and justification/purpose.
   - Click **"Submit for approval"**.
2. **Reviewing & Approving:**
   - Approvers see pending requests in **Demands** and on their **Dashboard**.
   - Open the demand to view the climb status.
   - Click **"Approve"** (or **"Reject"** with mandatory feedback).
   - If the value exceeds Tier 1, it automatically escalates to Tier 2, then Tier 3, and Tier 4.

---

### B. Purchase Orders (`/orders`)
1. **Generating Orders:**
   - Navigate to **Orders** > click **"Place an order"**.
   - Select an approved demand form.
   - Choose or create a vendor with PAN/VAT details.
   - Specify final agreed unit rates and delivery date.
   - Submit the order.

---

### C. Goods Receiving (`/receipts`)
1. **Receiving Deliveries:**
   - Navigate to **Goods Receipts** > click **"Receive items"**.
   - Select the active Purchase Order.
   - Verify the physical delivery against the order items.
   - Note any shortages or damaged items in the inspection remarks.
   - Sign off the delivery.
   *(Note: The system rejects receiving if attempted by the same user who created the PO).*

---

### D. Bills & Three-Way Matching (`/bills`)
1. **Registering Bills:**
   - Navigate to **Bills** > click **"Enter a bill"**.
   - Input supplier invoice number, date, total amount, and VAT.
   - Attach the Purchase Order.
2. **Three-Way Match Verification:**
   - **Matched:** Bill matches PO amount and approved demand budget exactly.
   - **Mismatch:** If the bill exceeds the PO or demand value:
     - Accounts must inspect the variance.
     - To proceed, an authorized Accounts Officer must type a written explanation (min. 10 characters) to formally accept the difference.

---

### E. Petty Cash (`/petty-cash`)
Used for urgent, small expenses without raising a full procurement ladder.

1. **Ceiling Limit:**
   - Defined in **Setup > Settings** (Default: Rs. 15,000 per bill).
   - Bills exceeding this limit are refused and must go through a regular demand form.
2. **Issuing a Token:**
   - Sighted bill must be in hand.
   - Enter Bill No, Vendor, Amount, Claimant Name, and Purpose.
   - Confirm the *"I have sighted the original bill"* checkbox.
   - Click **"Issue Token"**.
3. **Disbursing Payment:**
   - The claimant presents the token serial to Accounts.
   - A *different* Accounts team member opens the token and clicks **"Mark as Paid"**.
   - Sighted bill cannot be entered again or claimed through the main bill register (anti-double claim).

---

### F. Physical Stock Auditing (`/inventory`)
1. **Stock Register:**
   - View live balances grouped by Categories, Subcategories, and Blocks (A through F).
   - Every asset has a permanent identifier code (e.g., `CHAIR.S.1`, `LAPTOP.1`).
2. **Entering Stock Counts:**
   - Stock auditors navigate to their assigned blocks.
   - Select the item and input the counted physical quantity.
   - The system records the entry in an immutable ledger and highlights any discrepancy against expected system numbers.

---

### G. System Setup & Configuration (`/setup`)
*(Super Admin / Managing Director only)*

- **Blocks & Locations:** Setup physical buildings, blocks, labs, and classrooms.
- **Categories & Item Types:** Manage item classifications, prefixes, and lifespan classifications.
- **Approval Ladder:** Configure threshold amounts for Tier 1 to Tier 4 sign-offs.
- **Staff Accounts & Roles:** Add staff, assign designations, approval tiers, and specific roles.
- **Settings:** Adjust the school's display name and Petty Cash ceiling per bill.

---

### H. Reports and Excel Exports
Every major section includes an **"Export"** button that downloads an `.xlsx` file formatted with headers, audit references, and financial numbers:
- **Stock Register Export:** Full stock status with asset codes and block distribution.
- **Unit List Export:** Detailed listing of all serial-numbered physical assets.
- **Procurement & 3-Way Match Export:** Comprehensive pipeline from demand to billing.
- **Petty Cash Register:** Complete history of tokens, claimants, and disbursements.
- **Audit Trail Export:** Timestamped ledger of every system action and configuration change.

---

## 5. Security Best Practices for Administrators
1. **Unique Accounts:** Never share login credentials between staff members. Attribution in the audit log depends entirely on individual logins.
2. **Password Security:** Although the initial forced reset has been set to false for convenience, advise staff to change their default passwords via the user profile menu.
3. **Nightly Backups:** Run standard automated backups of the MariaDB database.
4. **Network Deployment:** Keep this application strictly inside your private school local network or behind a secured VPN.
