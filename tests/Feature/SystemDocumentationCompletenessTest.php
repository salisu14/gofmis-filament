<?php

namespace Tests\Feature;

use Tests\TestCase;

class SystemDocumentationCompletenessTest extends TestCase
{
    public function test_all_mandatory_user_manuals_and_sop_documents_exist(): void
    {
        $docsDir = base_path('docs');

        $requiredDocs = [
            'administrator-manual.md' => [
                '# GOF MIS — Administrator Manual & Operational Guide',
                '## 1. System Overview & Architecture',
                '## 2. Role Hierarchy & RBAC Permissions',
                '## 3. Security & Multi-Factor Authentication (MFA)',
                '## 4. Beneficiary Administration',
                '## 5. Financial Supervision & Treasury Accounts',
                '## 6. System Maintenance Commands Reference',
                '## 7. Audit Trail & Sensitive Action Security',
            ],
            'coordinator-field-sop.md' => [
                '# GOF MIS — Coordinator Field Operations SOP',
                '## 1. Overview of Coordinator Role & Field Portal',
                '## 2. Geographic Zone Isolation & Scope Restrictions',
                '## 3. Beneficiary Registration Workflows',
                '## 4. Family Zone Transfer Procedure',
                '## 5. Education & Welfare Support Requests',
                '## 6. Biometric Enrollment SOP',
                '## 7. Data Privacy & Compliance Safeguards',
            ],
            'finance-and-banking-sop.md' => [
                '# GOF MIS — Finance & Banking Standard Operating Procedure',
                '## 1. Overview of Financial Architecture & General Ledger',
                '## 2. Bank Account Hierarchy & Fund Reservation',
                '## 3. Out-Of-Pocket (OOP) Expense Processing',
                '## 4. Education & Welfare Support Disbursements',
                '## 5. Double-Entry Journal Lines & Accounting Verification',
                '## 6. Financial Audit & Repair Commands',
            ],
            'widow-loan-operating-guide.md' => [
                '# GOF MIS — Widow Revolving Loan (WRL) Operating Guide',
                '## 1. Overview & Program Governance',
                '## 2. Loan Application, Approval Flow & Schedule Generation',
                '## 3. Loan Repayments & Installment Matching',
                '## 4. Delinquency & Days Past Due (DPD) Evaluation',
                '## 5. Hardship Relief Windows & Promise to Pay',
                '## 6. Counter-Funding Ledger Treatment',
                '## 7. Administrative Write-Off Policies & Procedures',
            ],
            'phase-c-go-live-readiness.md' => [
                '# GOF MIS — Phase C Go-Live Readiness Roadmap',
                '## Phase C Objective',
                '## Phase C Work Packages & Status',
            ],
            'go-live-runbook.md' => [
                '# GOF MIS — Production Deployment & Go-Live Runbook',
                '## 1. Pre-Deployment Readiness & Approvals',
                '## 2. Environment Pre-Flight Audit',
                '## 3. Step-by-Step Deployment Execution Sequence',
                '## 4. Post-Deployment Verification & Smoke Tests',
                '## 5. Rollback & Disaster Recovery Strategy',
            ],
            'manual-qa-checklist.md' => [
                '# GOF MIS — Manual Functional QA Checklist',
            ],
            'production-readiness.md' => [
                '# GOF MIS — Production Readiness Guide & Checklist',
            ],
        ];

        foreach ($requiredDocs as $filename => $expectedHeadings) {
            $filePath = $docsDir.'/'.$filename;

            $this->assertFileExists($filePath, "Documentation file docs/{$filename} must exist.");

            $content = file_get_contents($filePath);
            $this->assertNotEmpty($content, "Documentation file docs/{$filename} must not be empty.");

            foreach ($expectedHeadings as $heading) {
                $this->assertStringContainsString(
                    $heading,
                    $content,
                    "Documentation file docs/{$filename} must contain heading '{$heading}'."
                );
            }
        }
    }
}
