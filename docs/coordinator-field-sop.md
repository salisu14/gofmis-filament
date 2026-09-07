# GOF MIS — Coordinator Field Operations SOP

## 1. Overview of Coordinator Role & Field Portal

The **Field Coordinator** plays a vital operational role in GOF MIS, serving as the primary liaison between local communities and the Garko Orphans Foundation. Field Coordinators operate through a dedicated, mobile-responsive portal at `/coordinator`.

### Core Responsibilities
1. Intaking and registering Deceased household heads, Widows, and Orphans within assigned geographic zones.
2. Managing beneficiary family zone transfers when households relocate.
3. Submitting education and welfare support requests for eligible beneficiaries.
4. Enrolling beneficiary biometrics (fingerprint templates) using the web-based biometric bridge.
5. Verifying beneficiary identity during ID card verification and package distribution.

---

## 2. Geographic Zone Isolation & Scope Restrictions

Field Coordinators are strictly bound to their assigned geographic zone (e.g. *Kano Central Zone*, *Garko Zone A*):
- **Zone Scope Enforcement**: Coordinators can view, create, and update records belonging strictly to their assigned zone.
- **Cross-Zone Protection**: Direct URL manipulation or API requests attempting to access beneficiaries in another zone are automatically rejected with an HTTP 403 Forbidden error.
- **Global Overview Access**: Only Admins and Super Admins possess multi-zone visibility.

---

## 3. Beneficiary Registration Workflows

### 3.1 Registering Deceased Household Head
1. Navigate to **Beneficiaries > Deceaseds** in the Coordinator Panel.
2. Click **New Deceased**.
3. Fill in required demographics (Full Name, Date of Death, Town/LGA, Primary Cause of Death, Contact Person).
4. Upload Death Certificate document (PDF or image).
5. Save record. System automatically generates a unique registration number (e.g. `DEC-KCZ-001`).

### 3.2 Registering Widows
1. Open the parent **Deceased** record or navigate to **Beneficiaries > Widows**.
2. Complete Widow details (Full Name, NIN, Phone, Date of Birth, Employment/Skill Status).
3. Specify marital status (`Single/Widowed`, `Remarried`).
4. Save record. System links widow to deceased household.

### 3.3 Registering Orphans
1. Open the parent **Deceased** record or navigate to **Beneficiaries > Orphans**.
2. Complete Orphan details (Full Name, Gender, Date of Birth, Current School/Class, Vulnerability Index).
3. Upload Orphan profile photo (JPEG/PNG).
4. Save record. System verifies eligibility based on age (< 18 years).

---

## 4. Family Zone Transfer Procedure

When a beneficiary household physically relocates to another geographic zone:
1. Open the target **Deceased** record in the Coordinator Panel.
2. Select the **Transfer Zone** action button.
3. Search and select the destination Zone from the real-time dropdown control.
4. Enter transfer reason and confirmation notes.
5. Submit transfer.
6. System updates zone assignment for the Deceased parent, associated Widows, and Orphans, while recording an immutable audit trail in `ZoneTransferHistory`.

---

## 5. Education & Welfare Support Requests

### 5.1 Submitting Education Assistance Requests
1. Navigate to **Interventions > Education Requests**.
2. Select eligible Orphan and destination school.
3. Enter requested fee amount and academic session.
4. Submit request. Request routes to `education-verifier` for invoice verification and disbursement.

### 5.2 Submitting Welfare Package Nominations
1. Navigate to **Interventions > Welfare Requests**.
2. Select active Welfare Package (e.g. *Ramadan Grain Pack*).
3. Select eligible beneficiary household.
4. Submit nomination. System reserves package items in inventory upon Admin approval.

---

## 6. Biometric Enrollment SOP

### Local Fingerprint Capture
1. Ensure the local workstation biometric bridge service (`http://127.0.0.1:8787`) is running.
2. Open the beneficiary profile (Widow or Orphan).
3. Click **Enroll Fingerprints** in the Biometrics section.
4. Instruct beneficiary to place finger on scanner when prompted.
5. Capture template. The system encrypts the raw template payload using AES-256-GCM before storing in database.
6. Verify successful enrollment status badge.

---

## 7. Data Privacy & Compliance Safeguards
- **Certificate Access**: Death and birth certificates contain sensitive personal data. Coordinators can only preview/download certificates for beneficiaries within their assigned zone.
- **Photo Integrity**: Profile photos must accurately represent the beneficiary to ensure ID card authenticity.
- **Zero Local Plaintext Storage**: Never store downloaded certificates or biometric templates on public/unsecured devices.
