<?php

namespace App\Services;

use App\Enums\AcademicProgressionDecision;
use App\Enums\InstitutionType;
use App\Models\Institution;
use App\Models\OrphanClass;
use App\Models\OrphanEducation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class WesternEducationProgressionService
{
    /**
     * Standard canonical Western education class sequence.
     */
    protected const CANONICAL_SEQUENCE = [
        'Primary 1' => 'Primary 2',
        'Primary 2' => 'Primary 3',
        'Primary 3' => 'Primary 4',
        'Primary 4' => 'Primary 5',
        'Primary 5' => 'Primary 6',
        'Primary 6' => 'JSS I',
        'JSS I' => 'JSS II',
        'JSS 1' => 'JSS II',
        'JSS II' => 'JSS III',
        'JSS 2' => 'JSS III',
        'JSS III' => 'SS I',
        'JSS 3' => 'SS I',
        'SS I' => 'SS II',
        'SS 1' => 'SS II',
        'SS II' => 'SS III',
        'SS 2' => 'SS III',
        'SS III' => null, // Final class in secondary sequence
        'SS 3' => null,
    ];

    /**
     * Check whether an OrphanEducation record belongs to a Western education institution.
     */
    public function isWesternEducation(OrphanEducation $record): bool
    {
        $type = $record->institution?->type;

        if ($type instanceof InstitutionType) {
            return $type === InstitutionType::WESTERN;
        }

        return (string) $type === 'western';
    }

    /**
     * Check whether an OrphanEducation record is eligible for academic progression.
     */
    public function canProgress(OrphanEducation $record): bool
    {
        if (! $record->is_current) {
            return false;
        }

        if (! $this->isWesternEducation($record)) {
            return false;
        }

        if ($record->orphan && ! $record->orphan->isEligibleForSupport()) {
            return false;
        }

        return true;
    }

    /**
     * Compute the next logical Western class for a given current class.
     */
    public function getNextLogicalClass(?OrphanClass $currentClass): ?OrphanClass
    {
        if (! $currentClass) {
            return null;
        }

        $className = trim($currentClass->name);
        $nextName = self::CANONICAL_SEQUENCE[$className] ?? null;

        if (! $nextName) {
            return null;
        }

        return OrphanClass::where('name', $nextName)->first()
            ?? OrphanClass::where('name', 'like', $nextName)->first();
    }

    /**
     * Compute the previous logical Western class for a given current class.
     */
    public function getPreviousLogicalClass(?OrphanClass $currentClass): ?OrphanClass
    {
        if (! $currentClass) {
            return null;
        }

        $className = trim($currentClass->name);

        foreach (self::CANONICAL_SEQUENCE as $prev => $next) {
            if ($next === $className) {
                return OrphanClass::where('name', $prev)->first();
            }
        }

        return null;
    }

    /**
     * Validate canonical academic session string format: YYYY/YYYY (second year = first year + 1).
     *
     * @throws \DomainException
     */
    public function validateAcademicSessionFormat(string $session): void
    {
        $session = trim($session);

        if (! preg_match('/^(\d{4})\/(\d{4})$/', $session, $matches)) {
            throw new \DomainException("Academic session '{$session}' must be in canonical format YYYY/YYYY (e.g. 2025/2026).");
        }

        $startYear = (int) $matches[1];
        $endYear = (int) $matches[2];

        if ($endYear !== $startYear + 1) {
            throw new \DomainException("Academic session '{$session}' is invalid. The end year must be exactly one year after the start year (e.g. {$startYear}/".($startYear + 1).').');
        }
    }

    /**
     * Compute the next sequential academic session string given a current session or start date.
     * e.g. "2025/2026" -> "2026/2027"
     */
    public function getNextSequentialSession(?string $currentSession, ?Carbon $startDate = null): string
    {
        if ($currentSession && preg_match('/^(\d{4})\/(\d{4})$/', trim($currentSession), $matches)) {
            $startYear = (int) $matches[1];
            $endYear = (int) $matches[2];

            return ($startYear + 1).'/'.($endYear + 1);
        }

        $baseYear = $startDate ? $startDate->year : now()->year;

        return $baseYear.'/'.($baseYear + 1);
    }

    /**
     * Check whether an actor has privileged override authorization.
     */
    public function canActorOverride(?User $actor): bool
    {
        if (! $actor) {
            return false;
        }

        if ($actor->hasRole('super_admin')) {
            return true;
        }

        return $actor->can('orphan_education.override_academic_progression')
            || $actor->hasRole('admin');
    }

    /**
     * Execute an academic progression decision safely, strictly, and idempotently.
     *
     * @param  array{
     *     academic_session?: string,
     *     effective_date?: string,
     *     new_class_id?: string,
     *     new_institution_id?: string,
     *     reason?: string,
     *     is_session_override?: bool,
     *     session_override_reason?: string,
     *     is_class_override?: bool
     * }  $data
     *
     * @throws \DomainException
     */
    public function progress(
        OrphanEducation $record,
        AcademicProgressionDecision|string $decision,
        array $data = [],
        ?User $actor = null
    ): OrphanEducation {
        $decisionEnum = $decision instanceof AcademicProgressionDecision
            ? $decision
            : AcademicProgressionDecision::from((string) $decision);

        // 1. Guard non-Western institutions
        if (! $this->isWesternEducation($record)) {
            throw new \DomainException('Academic progression rules only apply to Western/formal education institutions.');
        }

        $expectedSession = $this->getNextSequentialSession($record->academic_session, $record->started_at);
        $targetSession = ! empty($data['academic_session']) ? trim($data['academic_session']) : $expectedSession;

        // 2. Validate Session Format (Cannot be bypassed by override)
        $this->validateAcademicSessionFormat($targetSession);

        // 3. Validate Sequential Session Policy
        $isSessionOverride = (bool) ($data['is_session_override'] ?? false);
        $overrideReason = $data['session_override_reason'] ?? $data['reason'] ?? null;

        if ($targetSession !== $expectedSession) {
            if (! $isSessionOverride) {
                throw new \DomainException("Target academic session '{$targetSession}' is non-sequential. Expected next session is '{$expectedSession}'. Overriding requires privileged authorization and a mandatory reason.");
            }

            if (! $this->canActorOverride($actor)) {
                throw new \DomainException('You do not have permission to override the sequential academic session.');
            }

            if (empty($overrideReason)) {
                throw new \DomainException('A mandatory justification reason is required when overriding the sequential academic session.');
            }
        }

        // 4. Determine and Validate Target Class Invariants
        $expectedNextClass = $this->getNextLogicalClass($record->orphanClass);
        $expectedPrevClass = $this->getPreviousLogicalClass($record->orphanClass);
        $providedClassId = $data['new_class_id'] ?? null;
        $isClassOverride = (bool) ($data['is_class_override'] ?? false);

        $targetClassId = match ($decisionEnum) {
            AcademicProgressionDecision::PROMOTED => $this->resolvePromotedTargetClass($record, $expectedNextClass, $providedClassId, $isClassOverride, $actor, $data['reason'] ?? null),
            AcademicProgressionDecision::REPEATED => $this->resolveRepeatedTargetClass($record, $providedClassId),
            AcademicProgressionDecision::DEMOTED => $this->resolveDemotedTargetClass($record, $expectedPrevClass, $providedClassId, $data['reason'] ?? null),
            AcademicProgressionDecision::GRADUATED => null,
            AcademicProgressionDecision::TRANSFERRED => $this->resolveTransferredTargetClass($record, $providedClassId, $data['new_institution_id'] ?? null, $data['reason'] ?? null),
        };

        $targetInstitutionId = $decisionEnum === AcademicProgressionDecision::TRANSFERRED
            ? ($data['new_institution_id'] ?? $record->institution_id)
            : $record->institution_id;

        // 5. Idempotency Check: If an active record already exists for this orphan & target session, return it safely
        if ($decisionEnum !== AcademicProgressionDecision::GRADUATED) {
            $existingActive = OrphanEducation::where('orphan_id', $record->orphan_id)
                ->where('institution_id', $targetInstitutionId)
                ->where('is_current', true)
                ->where('academic_session', $targetSession)
                ->first();

            if ($existingActive && $existingActive->id !== $record->id) {
                return $existingActive;
            }
        }

        // 6. Guard inactive records
        if (! $record->is_current) {
            throw new \DomainException('Only active current enrollments can undergo academic progression.');
        }

        $effectiveDate = ! empty($data['effective_date'])
            ? Carbon::parse($data['effective_date'])->toDateString()
            : now()->toDateString();

        $reason = $data['reason'] ?? $overrideReason ?? null;
        $actorId = $actor?->id ?? auth()->id();

        return DB::transaction(function () use (
            $record,
            $decisionEnum,
            $targetClassId,
            $targetInstitutionId,
            $effectiveDate,
            $targetSession,
            $reason,
            $actorId
        ) {
            // Close current enrollment
            $endedAt = $decisionEnum === AcademicProgressionDecision::GRADUATED
                ? $effectiveDate
                : Carbon::parse($effectiveDate)->subDay()->toDateString();

            $record->update([
                'is_current' => false,
                'ended_at' => $endedAt,
                'progression_decision' => $decisionEnum,
                'progression_reason' => $reason,
                'recorded_by_id' => $actorId,
            ]);

            // For Graduation, no new active enrollment is created!
            if ($decisionEnum === AcademicProgressionDecision::GRADUATED) {
                return $record;
            }

            // Fetch target class model to properly sync class_level string
            $targetClass = $targetClassId ? OrphanClass::find($targetClassId) : null;

            // Create next enrollment
            $newRecord = $record->replicate([
                'id',
                'reference',
                'is_current',
                'started_at',
                'ended_at',
                'created_at',
                'updated_at',
            ]);

            $newRecord->orphan_class_id = $targetClassId;
            $newRecord->class_level = $targetClass?->name ?? $record->class_level;
            $newRecord->institution_id = $targetInstitutionId;
            $newRecord->academic_session = $targetSession;
            $newRecord->started_at = $effectiveDate;
            $newRecord->ended_at = null;
            $newRecord->is_current = true;
            $newRecord->progression_decision = null;
            $newRecord->progression_reason = null;
            $newRecord->recorded_by_id = $actorId;
            $newRecord->save();

            return $newRecord;
        });
    }

    /**
     * Resolve and validate PROMOTED target class.
     */
    protected function resolvePromotedTargetClass(
        OrphanEducation $record,
        ?OrphanClass $expectedNextClass,
        ?string $providedClassId,
        bool $isClassOverride,
        ?User $actor,
        ?string $reason
    ): string {
        $currentClassName = $record->orphanClass?->name ?? $record->class_level;

        if (! $expectedNextClass) {
            throw new \DomainException("Class '{$currentClassName}' is the final class in secondary education. Normal terminal progression is GRADUATED.");
        }

        if ($providedClassId && $providedClassId !== $expectedNextClass->id) {
            $providedClass = OrphanClass::find($providedClassId);
            $providedClassName = $providedClass?->name ?? 'selected class';

            if (! $isClassOverride) {
                throw new \DomainException("PROMOTED decision requires target class to be the exact next sequential class ('{$expectedNextClass->name}'). Provided class '{$providedClassName}' is invalid.");
            }

            if (! $this->canActorOverride($actor)) {
                throw new \DomainException('You do not have permission to override the sequential progression class.');
            }

            if (empty($reason)) {
                throw new \DomainException('A mandatory justification reason is required when overriding the sequential target class.');
            }

            return $providedClassId;
        }

        return $expectedNextClass->id;
    }

    /**
     * Resolve and validate REPEATED target class.
     */
    protected function resolveRepeatedTargetClass(OrphanEducation $record, ?string $providedClassId): string
    {
        $currentClassId = $record->orphan_class_id;

        if ($providedClassId && $providedClassId !== $currentClassId) {
            $currentClassName = $record->orphanClass?->name ?? $record->class_level;
            throw new \DomainException("REPEATED decision requires target class to match the current class ('{$currentClassName}').");
        }

        return $currentClassId;
    }

    /**
     * Resolve and validate DEMOTED target class.
     */
    protected function resolveDemotedTargetClass(
        OrphanEducation $record,
        ?OrphanClass $expectedPrevClass,
        ?string $providedClassId,
        ?string $reason
    ): string {
        $currentClassName = $record->orphanClass?->name ?? $record->class_level;

        if (! $expectedPrevClass) {
            throw new \DomainException("Student is in '{$currentClassName}' and cannot be demoted lower.");
        }

        if (empty($reason)) {
            throw new \DomainException('A mandatory justification reason is required for demoting a student.');
        }

        if ($providedClassId && $providedClassId !== $expectedPrevClass->id) {
            $providedClassName = OrphanClass::find($providedClassId)?->name ?? 'selected class';
            throw new \DomainException("DEMOTED decision requires target class to be the immediately previous class ('{$expectedPrevClass->name}'). Provided class '{$providedClassName}' is invalid.");
        }

        return $expectedPrevClass->id;
    }

    /**
     * Resolve and validate TRANSFERRED target class & institution.
     */
    protected function resolveTransferredTargetClass(
        OrphanEducation $record,
        ?string $providedClassId,
        ?string $providedInstitutionId,
        ?string $reason
    ): string {
        if (! $providedInstitutionId) {
            throw new \DomainException('A valid target institution must be selected for school transfer.');
        }

        if (! $providedClassId) {
            throw new \DomainException('A valid target class must be selected for school transfer.');
        }

        if (empty($reason)) {
            throw new \DomainException('A mandatory justification reason is required for transferring a student.');
        }

        return $providedClassId;
    }
}
