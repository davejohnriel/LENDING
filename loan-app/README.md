# MicroLend – Simple Pool Micro-Lending Platform

**Aligned with:** BRD-MICROLEND-2026-V1.0  
Student project · PHP + MySQL · XAMPP ready

This application implements the core business process defined in the Business Requirements Document: loan application → admin review → pool disbursement → flat-interest schedule → installment repayment → overdue/defaulted tracking.

---

## BRD ↔ Implementation mapping

| BRD concept | Implementation |
|-------------|----------------|
| Document ID | BRD-MICROLEND-2026-V1.0 |
| users | `users` table (roles: admin / borrower) |
| wallets | `wallets` table |
| lending_pool | `pool` table (single shared balance) |
| loans (lifecycle) | `loan_requests` (PENDING) + `loans` (ACTIVE / PAID / OVERDUE / DEFAULTED) |
| loan_repayments | `installments` table |
| transactions | `transactions` ledger |
| Flat interest (BRD §10) | `Interest = Principal × Rate`; total ÷ term = installment |
| Status model (BRD §11) | PENDING → ACTIVE \| REJECTED; ACTIVE → OVERDUE → DEFAULTED \| PAID |
| BR-005 / FR-007 | Pool balance checked before disbursement |
| BR-006 / FR-008 | Single DB transaction: pool ↓, wallet ↑, ledger row |
| BR-010 / BR-011 | Auto OVERDUE + DEFAULTED after grace days |
| NFR-002 / §17 | `password_hash` + role checks + prepared statements |
| NFR-003 / §13 | PDO transactions + `SELECT … FOR UPDATE` |

### Out of scope (as stated in BRD §5 & §16)
- Real KYC / government ID
- Real bank or payment gateway
- Automated credit scoring
- Automated collections
- Compounding / variable interest
- Enterprise reporting

---

## Features

- Borrower registration & login (role-based)
- Loan request (amount + term + optional purpose)
- Admin approve / reject with pool validation
- Disbursement: pool → borrower wallet + installment schedule
- Flat interest (configurable in `settings`)
- Installment repayment from wallet
- Automatic OVERDUE / DEFAULTED status
- Admin dashboards (pool, requests, all loans, KPIs)
- Full transaction ledger
- Demo wallet top-up (testing only)
- Bootstrap 5 UI

---

## Requirements

- XAMPP (or PHP 8+ + MySQL/MariaDB)
- PHP extension: `pdo_mysql`

---

## Installation

1. Copy the `loan-app` folder into XAMPP `htdocs`  
   → e.g. `C:\xampp\htdocs\loan-app`
2. Start **Apache** + **MySQL**
3. phpMyAdmin → Import `loan-app/sql/schema.sql`
4. Open: **http://localhost/loan-app/**

### Default admin
- Username: `admin`
- Password: `admin123`

---

## Recommended test flow (maps to BRD acceptance criteria §18)

1. Login as **admin**
2. Register a borrower (second browser / incognito)
3. Borrower → **Request Loan** (e.g. ₱5,000 / 3 months) → status **PENDING**
4. Admin → **Loan Requests** → **Approve**  
   - Pool decreases, wallet increases, schedule created, status **ACTIVE**
5. Borrower → use **Top-up** on dashboard (demo money)
6. Borrower → **My Loans** → **Pay** each installment
7. When all paid → loan status **PAID**
8. To test **OVERDUE / DEFAULTED**: set an installment `due_date` in the past in phpMyAdmin, then reload any page (status updater runs on load)

---

## Configurable settings (`settings` table)

| Key | Default | BRD relevance |
|-----|---------|---------------|
| interest_rate | 5.00 | Flat rate (BRD §10) |
| grace_days | 7 | Days after due → DEFAULTED (BR-011) |
| max_loan_amount | 50000.00 | Application limits |
| min_loan_amount | 1000.00 | Application limits |
| max_term_months | 12 | Application limits |
| min_term_months | 1 | Application limits |
| allow_multiple_active_loans | 0 | One active loan per borrower |

---

## File structure

```
loan-app/
├── admin/           Dashboard, requests, loans, pool
├── borrower/        Dashboard, request loan, my loans + pay
├── config/          database.php
├── includes/        auth, business logic, header/footer
├── sql/             schema.sql (BRD-aligned)
├── assets/          style.css
├── index.php        Login
├── register.php
└── logout.php
```

---

## Defense notes

Present this as an **academic functional simulation**, not a production fintech service.  
Every major requirement in BRD-MICROLEND-2026-V1.0 maps to a testable behavior in this codebase (see mapping table above).
