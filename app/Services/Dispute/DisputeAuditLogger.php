<?php

namespace App\Services\Dispute;

use App\Models\Admin;
use App\Models\Dispute;
use App\Models\DisputeTimeline;
use App\Models\Staff;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Carbon\Carbon;

/**
 * DisputeAuditLogger
 * 
 * Comprehensive audit logging service for dispute management activities.
 * Implements activity logging with user identification, PCI compliance measures,
 * and audit trail maintenance for compliance reporting.
 * 
 * Requirements: 7.7, 9.3, 9.7
 */
class DisputeAuditLogger
{
    /**
     * Sensitive data fields that should be masked in logs.
     */
    protected array $sensitiveFields = [
        'card_number',
        'cvv',
        'ssn',
        'tax_id',
        'bank_account',
        'routing_number',
        'api_key',
        'secret_key',
        'password',
        'token'
    ];

    /**
     * PCI DSS compliance log levels.
     */
    protected array $pciLogLevels = [
        'access' => 'info',
        'modification' => 'warning',
        'deletion' => 'error',
        'security_event' => 'alert',
        'authentication_failure' => 'critical'
    ];

    /**
     * Log dispute access activity.
     * 
     * @param Dispute $dispute The dispute being accessed
     * @param string $action The action being performed
     * @param array $metadata Additional metadata
     * @param Admin|Staff|null $user The user performing the action
     */
    public function logDisputeAccess(Dispute $dispute, string $action, array $metadata = [], Admin|Staff|null $user = null): void
    {
        $user = $this->resolveCurrentUser($user);
        $request = request();

        $logData = [
            'event_type' => 'dispute_access',
            'action' => $action,
            'dispute_id' => $dispute->id,
            'stripe_dispute_id' => $dispute->stripe_dispute_id,
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'System',
            'user_email' => $user?->email,
            'user_role' => $user?->getRoleNames()?->first() ?? 'Unknown',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'session_id' => session()->getId(),
            'tenant_id' => session('tenant_id'),
            'timestamp' => now()->toISOString(),
            'metadata' => $this->sanitizeMetadata($metadata)
        ];

        // Log to dispute timeline
        DisputeTimeline::create([
            'dispute_id' => $dispute->id,
            'event_type' => 'access_logged',
            'event_data' => $logData,
            'user_id' => $user?->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        // Log to application logs with appropriate level
        $logLevel = $this->pciLogLevels['access'];
        // Log::log($logLevel, 'Dispute access logged', $logData);

        // Store in audit trail for compliance
        $this->storeAuditTrail('dispute_access', $logData);
    }

    /**
     * Log list-level access (no specific dispute — used for index/analytics views).
     * Avoids creating a timeline entry since there is no associated dispute record.
     */
    public function logListAccess(string $action, array $metadata = []): void
    {
        $user = $this->resolveCurrentUser();
        $request = request();

        $logData = [
            'event_type' => 'dispute_list_access',
            'action' => $action,
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'System',
            'user_email' => $user?->email,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'tenant_id' => session('tenant_id'),
            'timestamp' => now()->toISOString(),
            'metadata' => $this->sanitizeMetadata($metadata),
        ];

        // Log::log($this->pciLogLevels['access'], 'Dispute list access logged', $logData);
        $this->storeAuditTrail('dispute_list_access', $logData);
    }

    /**
     * Log dispute modification activity.
     * 
     * @param Dispute $dispute The dispute being modified
     * @param string $action The modification action
     * @param array $changes The changes being made
     * @param array $metadata Additional metadata
     * @param Admin|Staff|null $user The user performing the action
     */
    public function logDisputeModification(
        Dispute $dispute, 
        string $action, 
        array $changes, 
        array $metadata = [], 
        Admin|Staff|null $user = null
    ): void {
        $user = $this->resolveCurrentUser($user);
        $request = request();

        $logData = [
            'event_type' => 'dispute_modification',
            'action' => $action,
            'dispute_id' => $dispute->id,
            'stripe_dispute_id' => $dispute->stripe_dispute_id,
            'changes' => $this->sanitizeChanges($changes),
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'System',
            'user_email' => $user?->email,
            'user_role' => $user?->getRoleNames()?->first() ?? 'Unknown',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'session_id' => session()->getId(),
            'tenant_id' => session('tenant_id'),
            'timestamp' => now()->toISOString(),
            'metadata' => $this->sanitizeMetadata($metadata),
            'compliance_flags' => $this->generateComplianceFlags($action, $changes)
        ];

        // Log to dispute timeline
        DisputeTimeline::create([
            'dispute_id' => $dispute->id,
            'event_type' => 'modification_logged',
            'event_data' => $logData,
            'user_id' => $user?->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        // Log to application logs with appropriate level
        $logLevel = $this->pciLogLevels['modification'];
        Log::log($logLevel, 'Dispute modification logged', $logData);

        // Store in audit trail for compliance
        $this->storeAuditTrail('dispute_modification', $logData);

        // Check for high-risk modifications
        if ($this->isHighRiskModification($action, $changes)) {
            $this->logSecurityEvent($dispute, 'high_risk_modification', $logData, $user);
        }
    }

    /**
     * Log evidence submission activity.
     * 
     * @param Dispute $dispute The dispute
     * @param array $evidenceData The evidence being submitted
     * @param array $files The files being uploaded
     * @param Admin|Staff|null $user The user submitting evidence
     */
    public function logEvidenceSubmission(
        Dispute $dispute, 
        array $evidenceData, 
        array $files = [], 
        Admin|Staff|null $user = null
    ): void {
        $user = $this->resolveCurrentUser($user);
        $request = request();

        $logData = [
            'event_type' => 'evidence_submission',
            'action' => 'submit_evidence',
            'dispute_id' => $dispute->id,
            'stripe_dispute_id' => $dispute->stripe_dispute_id,
            'evidence_types' => array_keys($evidenceData),
            'file_count' => count($files),
            'file_details' => $this->sanitizeFileDetails($files),
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'System',
            'user_email' => $user?->email,
            'user_role' => $user?->getRoleNames()?->first() ?? 'Unknown',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'session_id' => session()->getId(),
            'tenant_id' => session('tenant_id'),
            'timestamp' => now()->toISOString(),
            'pci_compliance_check' => $this->performPciComplianceCheck($evidenceData, $files)
        ];

        // Log to dispute timeline
        DisputeTimeline::create([
            'dispute_id' => $dispute->id,
            'event_type' => 'evidence_submission_logged',
            'event_data' => $logData,
            'user_id' => $user?->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        // Log to application logs
        // Log::info('Evidence submission logged', $logData);

        // Store in audit trail for compliance
        $this->storeAuditTrail('evidence_submission', $logData);
    }

    /**
     * Log security events.
     * 
     * @param Dispute $dispute The dispute
     * @param string $eventType The security event type
     * @param array $eventData The event data
     * @param Admin|Staff|null $user The user involved
     */
    public function logSecurityEvent(
        Dispute $dispute, 
        string $eventType, 
        array $eventData, 
        Admin|Staff|null $user = null
    ): void {
        $user = $this->resolveCurrentUser($user);
        $request = request();

        $logData = [
            'event_type' => 'security_event',
            'security_event_type' => $eventType,
            'dispute_id' => $dispute->id,
            'stripe_dispute_id' => $dispute->stripe_dispute_id,
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'System',
            'user_email' => $user?->email,
            'user_role' => $user?->getRoleNames()?->first() ?? 'Unknown',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'session_id' => session()->getId(),
            'tenant_id' => session('tenant_id'),
            'timestamp' => now()->toISOString(),
            'event_data' => $this->sanitizeMetadata($eventData),
            'severity' => $this->getSecurityEventSeverity($eventType),
            'requires_investigation' => $this->requiresInvestigation($eventType)
        ];

        // Log to dispute timeline
        DisputeTimeline::create([
            'dispute_id' => $dispute->id,
            'event_type' => 'security_event_logged',
            'event_data' => $logData,
            'user_id' => $user?->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        // Log to application logs with appropriate level
        $logLevel = $this->pciLogLevels['security_event'];
        Log::log($logLevel, 'Security event logged', $logData);

        // Store in audit trail for compliance
        $this->storeAuditTrail('security_event', $logData);

        // Alert on critical security events
        if ($logData['severity'] === 'critical') {
            $this->alertSecurityTeam($logData);
        }
    }

    /**
     * Log authentication failures.
     * 
     * @param string $attemptedAction The action that was attempted
     * @param array $context Additional context
     * @param Admin|Staff|null $user The user involved
     */
    public function logAuthenticationFailure(
        string $attemptedAction, 
        array $context = [], 
        Admin|Staff|null $user = null
    ): void {
        $request = request();

        $logData = [
            'event_type' => 'authentication_failure',
            'attempted_action' => $attemptedAction,
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'Unknown',
            'user_email' => $user?->email ?? 'Unknown',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'session_id' => session()->getId(),
            'tenant_id' => session('tenant_id'),
            'timestamp' => now()->toISOString(),
            'context' => $this->sanitizeMetadata($context),
            'security_risk_level' => $this->assessSecurityRisk($context)
        ];

        // Log to application logs
        $logLevel = $this->pciLogLevels['authentication_failure'];
        Log::log($logLevel, 'Authentication failure logged', $logData);

        // Store in audit trail for compliance
        $this->storeAuditTrail('authentication_failure', $logData);

        // Track repeated failures
        $this->trackRepeatedFailures($request->ip(), $user?->id);
    }

    /**
     * Generate compliance report for audit purposes.
     * 
     * @param Carbon $startDate Start date for the report
     * @param Carbon $endDate End date for the report
     * @param array $filters Additional filters
     * @return array
     */
    public function generateComplianceReport(Carbon $startDate, Carbon $endDate, array $filters = []): array
    {
        $auditTrail = DB::table('dispute_audit_trail')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when(isset($filters['tenant_id']), function ($query) use ($filters) {
                return $query->where('tenant_id', $filters['tenant_id']);
            })
            ->when(isset($filters['event_type']), function ($query) use ($filters) {
                return $query->where('event_type', $filters['event_type']);
            })
            ->when(isset($filters['user_id']), function ($query) use ($filters) {
                return $query->where('user_id', $filters['user_id']);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $summary = [
            'report_period' => [
                'start_date' => $startDate->toISOString(),
                'end_date' => $endDate->toISOString()
            ],
            'total_events' => $auditTrail->count(),
            'event_breakdown' => $auditTrail->groupBy('event_type')->map->count(),
            'user_activity' => $auditTrail->groupBy('user_id')->map->count(),
            'security_events' => $auditTrail->where('event_type', 'security_event')->count(),
            'authentication_failures' => $auditTrail->where('event_type', 'authentication_failure')->count(),
            'high_risk_activities' => $auditTrail->where('risk_level', 'high')->count(),
            'pci_compliance_flags' => $auditTrail->whereNotNull('pci_flags')->count(),
            'generated_at' => now()->toISOString(),
            'generated_by' => $this->resolveCurrentUser()?->name ?? 'System'
        ];

        // Log report generation
        // Log::info('Compliance report generated', [
        //     'report_period' => $summary['report_period'],
        //     'total_events' => $summary['total_events'],
        //     'generated_by' => $summary['generated_by'],
        //     'filters' => $filters
        // ]);

        return [
            'summary' => $summary,
            'detailed_events' => $auditTrail->toArray()
        ];
    }

    /**
     * Store audit trail record for compliance.
     * 
     * @param string $eventType The event type
     * @param array $logData The log data
     */
    protected function storeAuditTrail(string $eventType, array $logData): void
    {
        try {
            DB::table('dispute_audit_trail')->insert([
                'event_type' => $eventType,
                'event_data' => json_encode($logData),
                'user_id' => $logData['user_id'] ?? null,
                'dispute_id' => $logData['dispute_id'] ?? null,
                'ip_address' => $logData['ip_address'] ?? null,
                'tenant_id' => $logData['tenant_id'] ?? null,
                'risk_level' => $this->assessRiskLevel($eventType, $logData),
                'pci_flags' => json_encode($logData['compliance_flags'] ?? []),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to store audit trail record', [
                'event_type' => $eventType,
                'error' => $e->getMessage(),
                'log_data_keys' => array_keys($logData)
            ]);
        }
    }

    /**
     * Sanitize metadata to remove sensitive information.
     * 
     * @param array $metadata The metadata to sanitize
     * @return array
     */
    protected function sanitizeMetadata(array $metadata): array
    {
        $sanitized = $metadata;

        foreach ($this->sensitiveFields as $field) {
            if (isset($sanitized[$field])) {
                $sanitized[$field] = $this->maskSensitiveData($sanitized[$field]);
            }
        }

        // Recursively sanitize nested arrays
        foreach ($sanitized as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeMetadata($value);
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize changes data to remove sensitive information.
     * 
     * @param array $changes The changes to sanitize
     * @return array
     */
    protected function sanitizeChanges(array $changes): array
    {
        $sanitized = [];

        foreach ($changes as $field => $change) {
            if (in_array($field, $this->sensitiveFields)) {
                $sanitized[$field] = [
                    'from' => '[REDACTED]',
                    'to' => '[REDACTED]',
                    'changed' => true
                ];
            } else {
                $sanitized[$field] = $change;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize file details for logging.
     * 
     * @param array $files The files to sanitize
     * @return array
     */
    protected function sanitizeFileDetails(array $files): array
    {
        $sanitized = [];

        foreach ($files as $key => $file) {
            if (is_object($file) && method_exists($file, 'getClientOriginalName')) {
                $sanitized[$key] = [
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'type' => $file->getMimeType(),
                    'extension' => $file->getClientOriginalExtension()
                ];
            } else {
                $sanitized[$key] = '[FILE_DETAILS_UNAVAILABLE]';
            }
        }

        return $sanitized;
    }

    /**
     * Mask sensitive data for logging.
     * 
     * @param string $data The data to mask
     * @return string
     */
    protected function maskSensitiveData(string $data): string
    {
        if (strlen($data) <= 4) {
            return str_repeat('*', strlen($data));
        }

        return substr($data, 0, 2) . str_repeat('*', strlen($data) - 4) . substr($data, -2);
    }

    /**
     * Generate compliance flags for the action and changes.
     * 
     * @param string $action The action being performed
     * @param array $changes The changes being made
     * @return array
     */
    protected function generateComplianceFlags(string $action, array $changes): array
    {
        $flags = [];

        // PCI DSS compliance flags
        if ($this->involvesSensitiveData($changes)) {
            $flags[] = 'pci_sensitive_data_modified';
        }

        if ($this->involvesFinancialData($changes)) {
            $flags[] = 'financial_data_modified';
        }

        // SOX compliance flags
        if ($this->involvesFinancialReporting($action, $changes)) {
            $flags[] = 'sox_financial_reporting';
        }

        // GDPR compliance flags
        if ($this->involvesPersonalData($changes)) {
            $flags[] = 'gdpr_personal_data_processed';
        }

        return $flags;
    }

    /**
     * Perform PCI compliance check on evidence data.
     * 
     * @param array $evidenceData The evidence data
     * @param array $files The files being uploaded
     * @return array
     */
    protected function performPciComplianceCheck(array $evidenceData, array $files): array
    {
        $checks = [
            'contains_card_data' => false,
            'contains_sensitive_auth_data' => false,
            'encryption_required' => false,
            'access_logged' => true,
            'compliance_status' => 'compliant'
        ];

        // Check evidence data for sensitive information
        foreach ($evidenceData as $key => $value) {
            if (is_string($value)) {
                if ($this->containsCardData($value)) {
                    $checks['contains_card_data'] = true;
                    $checks['encryption_required'] = true;
                    $checks['compliance_status'] = 'requires_review';
                }

                if ($this->containsSensitiveAuthData($value)) {
                    $checks['contains_sensitive_auth_data'] = true;
                    $checks['compliance_status'] = 'non_compliant';
                }
            }
        }

        return $checks;
    }

    /**
     * Check if data contains card information.
     * 
     * @param string $data The data to check
     * @return bool
     */
    protected function containsCardData(string $data): bool
    {
        // Simple regex patterns for card numbers (this is a basic check)
        $cardPatterns = [
            '/\b4[0-9]{12}(?:[0-9]{3})?\b/', // Visa
            '/\b5[1-5][0-9]{14}\b/',         // MasterCard
            '/\b3[47][0-9]{13}\b/',          // American Express
            '/\b6(?:011|5[0-9]{2})[0-9]{12}\b/' // Discover
        ];

        foreach ($cardPatterns as $pattern) {
            if (preg_match($pattern, $data)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if data contains sensitive authentication data.
     * 
     * @param string $data The data to check
     * @return bool
     */
    protected function containsSensitiveAuthData(string $data): bool
    {
        $sensitivePatterns = [
            '/\bcvv\s*:?\s*[0-9]{3,4}\b/i',
            '/\bcvc\s*:?\s*[0-9]{3,4}\b/i',
            '/\bpin\s*:?\s*[0-9]{4,6}\b/i'
        ];

        foreach ($sensitivePatterns as $pattern) {
            if (preg_match($pattern, $data)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if modification is high risk.
     * 
     * @param string $action The action being performed
     * @param array $changes The changes being made
     * @return bool
     */
    protected function isHighRiskModification(string $action, array $changes): bool
    {
        $highRiskActions = [
            'delete_dispute',
            'modify_amount',
            'change_status_to_won',
            'bulk_update'
        ];

        if (in_array($action, $highRiskActions)) {
            return true;
        }

        // Check for high-risk field changes
        $highRiskFields = ['amount', 'status', 'stripe_dispute_id'];
        
        foreach ($highRiskFields as $field) {
            if (isset($changes[$field])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if changes involve sensitive data.
     * 
     * @param array $changes The changes being made
     * @return bool
     */
    protected function involvesSensitiveData(array $changes): bool
    {
        foreach ($this->sensitiveFields as $field) {
            if (isset($changes[$field])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if changes involve financial data.
     * 
     * @param array $changes The changes being made
     * @return bool
     */
    protected function involvesFinancialData(array $changes): bool
    {
        $financialFields = ['amount', 'currency', 'fee', 'refund_amount'];
        
        foreach ($financialFields as $field) {
            if (isset($changes[$field])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if action involves financial reporting.
     * 
     * @param string $action The action being performed
     * @param array $changes The changes being made
     * @return bool
     */
    protected function involvesFinancialReporting(string $action, array $changes): bool
    {
        $reportingActions = ['generate_report', 'export_financial_data', 'modify_financial_record'];
        
        return in_array($action, $reportingActions) || $this->involvesFinancialData($changes);
    }

    /**
     * Check if changes involve personal data.
     * 
     * @param array $changes The changes being made
     * @return bool
     */
    protected function involvesPersonalData(array $changes): bool
    {
        $personalDataFields = ['customer_name', 'email', 'phone', 'address'];
        
        foreach ($personalDataFields as $field) {
            if (isset($changes[$field])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get security event severity.
     * 
     * @param string $eventType The event type
     * @return string
     */
    protected function getSecurityEventSeverity(string $eventType): string
    {
        $severityMap = [
            'unauthorized_access' => 'critical',
            'data_breach' => 'critical',
            'high_risk_modification' => 'high',
            'suspicious_activity' => 'medium',
            'failed_authentication' => 'low'
        ];

        return $severityMap[$eventType] ?? 'medium';
    }

    /**
     * Check if security event requires investigation.
     * 
     * @param string $eventType The event type
     * @return bool
     */
    protected function requiresInvestigation(string $eventType): bool
    {
        $investigationRequired = [
            'unauthorized_access',
            'data_breach',
            'high_risk_modification',
            'repeated_failures'
        ];

        return in_array($eventType, $investigationRequired);
    }

    /**
     * Assess risk level for audit trail.
     * 
     * @param string $eventType The event type
     * @param array $logData The log data
     * @return string
     */
    protected function assessRiskLevel(string $eventType, array $logData): string
    {
        if ($eventType === 'security_event') {
            return $logData['severity'] ?? 'medium';
        }

        if ($eventType === 'authentication_failure') {
            return 'high';
        }

        if (isset($logData['compliance_flags']) && !empty($logData['compliance_flags'])) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Assess security risk from context.
     * 
     * @param array $context The context data
     * @return string
     */
    protected function assessSecurityRisk(array $context): string
    {
        // Implement risk assessment logic based on context
        $riskFactors = 0;

        if (isset($context['repeated_attempts']) && $context['repeated_attempts'] > 3) {
            $riskFactors += 2;
        }

        if (isset($context['unusual_ip']) && $context['unusual_ip']) {
            $riskFactors += 1;
        }

        if (isset($context['off_hours']) && $context['off_hours']) {
            $riskFactors += 1;
        }

        if ($riskFactors >= 3) {
            return 'high';
        } elseif ($riskFactors >= 1) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Track repeated authentication failures.
     * 
     * @param string $ipAddress The IP address
     * @param int|null $userId The user ID
     */
    protected function trackRepeatedFailures(string $ipAddress, ?int $userId): void
    {
        $cacheKey = "auth_failures_{$ipAddress}_{$userId}";
        $failures = cache()->get($cacheKey, 0) + 1;
        
        cache()->put($cacheKey, $failures, 3600); // Store for 1 hour

        if ($failures >= 5) {
            Log::alert('Multiple authentication failures detected', [
                'ip_address' => $ipAddress,
                'user_id' => $userId,
                'failure_count' => $failures,
                'time_window' => '1 hour'
            ]);
        }
    }

    /**
     * Resolve the current authenticated user as Admin or Staff.
     * Auth::user() returns Authenticatable|null; this narrows it to Admin|Staff|null
     * so callers get proper type inference (including Spatie getRoleNames()).
     */
    protected function resolveCurrentUser(Admin|Staff|null $override = null): Admin|Staff|null
    {
        if ($override !== null) {
            return $override;
        }
        $authenticated = Auth::user();
        if ($authenticated instanceof Admin || $authenticated instanceof Staff) {
            return $authenticated;
        }
        return null;
    }

    /**
     * Alert security team of critical events.
     *
     * @param array $logData The log data
     */
    protected function alertSecurityTeam(array $logData): void
    {
        // In a real implementation, this would send alerts via email, Slack, etc.
        Log::alert('CRITICAL SECURITY EVENT - Security team notification required', $logData);
        
        // You could implement actual notification logic here:
        // - Send email to security team
        // - Post to Slack channel
        // - Create incident ticket
        // - Send SMS alerts
    }
}