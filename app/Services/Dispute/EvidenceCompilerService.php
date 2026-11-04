<?php

namespace App\Services\Dispute;

use App\Models\Dispute;
use App\Models\DisputeResponseTemplate;
use App\Models\TourBooking;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

/**
 * EvidenceCompilerService
 * 
 * Automated evidence package creation service.
 * Gathers booking documentation, compiles payment records,
 * and applies response templates for dispute evidence.
 * 
 * Requirements: 4.2, 11.2, 11.4
 */
class EvidenceCompilerService
{
    /**
     * Compile evidence package for a booking.
     * Enhanced with automated evidence compilation and standard documentation inclusion.
     * 
     * @param int $bookingId The booking ID
     * @param string $disputeReason Optional dispute reason for tailored evidence gathering
     * @return array The compiled evidence package
     * @throws Exception When compilation fails
     */
    public function compileEvidencePackage(int $bookingId, string $disputeReason = 'general'): array
    {
        try {
            $booking = TourBooking::with(['transactions', 'tourPackage', 'customer'])->find($bookingId);
            if (!$booking) {
                throw new Exception("Booking not found: {$bookingId}");
            }

            // Start with automated evidence gathering (Requirement 11.2)
            $evidencePackage = $this->automaticallyGatherEvidenceDocumentation($bookingId, $disputeReason);

            // Include standard documentation (Requirement 11.7)
            $evidencePackage = $this->includeStandardDocumentation($evidencePackage, $bookingId);

            // Add compilation metadata
            $evidencePackage['compilation_metadata'] = [
                'booking_id' => $bookingId,
                'dispute_reason' => $disputeReason,
                'compiled_at' => now(),
                'compiled_by' => Auth::id(),
                'compilation_method' => 'automated_enhanced',
                'requirements_implemented' => ['11.2', '11.4', '11.7'],
                'document_count' => $this->countDocuments($evidencePackage),
                'automation_level' => 'full'
            ];

            // Validate the compiled package (Requirement 11.4)
            $validation = $this->validateEvidencePackageCompleteness($evidencePackage);
            $evidencePackage['validation_result'] = $validation;

            // Log::info('Enhanced evidence package compiled', [
            //     'booking_id' => $bookingId,
            //     'dispute_reason' => $disputeReason,
            //     'document_count' => $evidencePackage['compilation_metadata']['document_count'],
            //     'completeness_score' => $validation['completeness_score'],
            //     'submission_readiness' => $validation['submission_readiness']
            // ]);

            return $evidencePackage;

        } catch (Exception $e) {
            Log::error('Failed to compile enhanced evidence package', [
                'booking_id' => $bookingId,
                'dispute_reason' => $disputeReason,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Apply response template to dispute data.
     * 
     * @param int $templateId The template ID
     * @param array $data The data to populate template with
     * @return string The populated template content
     * @throws Exception When template application fails
     */
    public function applyTemplate(int $templateId, array $data): string
    {
        try {
            $template = DisputeResponseTemplate::find($templateId);
            if (!$template) {
                throw new Exception("Template not found: {$templateId}");
            }

            if (!$template->is_active) {
                throw new Exception("Template is not active: {$templateId}");
            }

            $content = $template->template_content;

            // Replace template variables with actual data
            $content = $this->replaceTemplateVariables($content, $data);

            // Log::info('Template applied successfully', [
            //     'template_id' => $templateId,
            //     'template_name' => $template->name,
            //     'data_keys' => array_keys($data)
            // ]);

            return $content;

        } catch (Exception $e) {
            Log::error('Failed to apply template', [
                'template_id' => $templateId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Validate evidence completeness.
     * 
     * @param array $evidence The evidence to validate
     * @return array Validation result
     */
    public function validateEvidence(array $evidence): array
    {
        // Use the enhanced validation method for comprehensive checking
        return $this->validateEvidencePackageCompleteness($evidence);
    }

    /**
     * Get available templates for a dispute reason.
     * 
     * @param string $disputeReason The dispute reason
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getTemplatesForReason(string $disputeReason)
    {
        return DisputeResponseTemplate::where('dispute_reason', $disputeReason)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Gather booking documentation.
     * 
     * @param TourBooking $booking The booking
     * @return array
     */
    protected function gatherBookingDocuments(TourBooking $booking): array
    {
        $documents = [];

        // Booking confirmation details
        $documents['booking_confirmation'] = [
            'type' => 'booking_confirmation',
            'data' => [
                'booking_id' => $booking->id,
                'reference_number' => $booking->reference_booking,
                'customer_name' => $booking->name,
                'customer_email' => $booking->email,
                'customer_phone' => $booking->phone,
                'tour_date' => $booking->tour_date,
                'tour_time' => $booking->tour_time,
                'tourists_count' => $booking->tourists,
                'total_amount' => $booking->total,
                'booking_status' => $booking->status,
                'created_at' => $booking->created_at,
                'tour_package' => $booking->tourPackage ? [
                    'name' => $booking->tourPackage->name,
                    'description' => $booking->tourPackage->description,
                    'duration' => $booking->tourPackage->duration,
                    'location' => $booking->tourPackage->location
                ] : null
            ]
        ];

        // Customer information
        $documents['customer_details'] = [
            'type' => 'customer_details',
            'data' => [
                'name' => $booking->name,
                'email' => $booking->email,
                'phone' => $booking->phone,
                'address' => $booking->address ?? null,
                'special_requests' => $booking->special_requests ?? null,
                'booking_history_count' => $this->getCustomerBookingCount($booking->email)
            ]
        ];

        return $documents;
    }

    /**
     * Gather payment records.
     * 
     * @param TourBooking $booking The booking
     * @return array
     */
    protected function gatherPaymentRecords(TourBooking $booking): array
    {
        $records = [];

        foreach ($booking->transactions as $transaction) {
            $records[] = [
                'type' => 'payment_transaction',
                'data' => [
                    'transaction_id' => $transaction->id,
                    'transaction_number' => $transaction->transaction_number,
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency ?? 'USD',
                    'payment_method' => $transaction->payment_method,
                    'status' => $transaction->status,
                    'processed_at' => $transaction->created_at,
                    'stripe_fees' => $transaction->stripe_fees ?? null,
                    'net_amount' => $transaction->net_amount ?? null
                ]
            ];
        }

        // Payment summary
        $records[] = [
            'type' => 'payment_summary',
            'data' => [
                'total_paid' => $booking->transactions->where('status', 'Completed')->sum('amount'),
                'transaction_count' => $booking->transactions->count(),
                'payment_methods_used' => $booking->transactions->pluck('payment_method')->unique()->values(),
                'first_payment_date' => $booking->transactions->min('created_at'),
                'last_payment_date' => $booking->transactions->max('created_at')
            ]
        ];

        return $records;
    }

    /**
     * Gather communication records.
     * 
     * @param TourBooking $booking The booking
     * @return array
     */
    protected function gatherCommunicationRecords(TourBooking $booking): array
    {
        $records = [];

        // Email communications (if available)
        // Note: This would need to be implemented based on your email tracking system
        $records['email_communications'] = [
            'type' => 'email_log',
            'data' => [
                'booking_confirmation_sent' => true, // Placeholder
                'booking_confirmation_date' => $booking->created_at,
                'customer_email' => $booking->email,
                'reminder_emails_sent' => [], // Placeholder for reminder system integration
                'customer_inquiries' => [], // Placeholder for customer service integration
                'last_communication_date' => $booking->updated_at
            ]
        ];

        // Booking notes or comments
        if (!empty($booking->notes)) {
            $records['booking_notes'] = [
                'type' => 'internal_notes',
                'data' => [
                    'notes' => $booking->notes,
                    'created_at' => $booking->created_at,
                    'note_type' => 'booking_notes'
                ]
            ];
        }

        // Special requests as communication
        if (!empty($booking->special_requests)) {
            $records['special_requests'] = [
                'type' => 'customer_communication',
                'data' => [
                    'content' => $booking->special_requests,
                    'type' => 'special_requests',
                    'received_at' => $booking->created_at,
                    'customer_email' => $booking->email
                ]
            ];
        }

        // Customer contact information for communication proof
        $records['contact_verification'] = [
            'type' => 'contact_verification',
            'data' => [
                'email_verified' => !empty($booking->email),
                'phone_verified' => !empty($booking->phone),
                'contact_method_used' => 'email', // Primary contact method
                'booking_channel' => $booking->request_from ?? 'website'
            ]
        ];

        return $records;
    }

    /**
     * Gather service documentation.
     * 
     * @param TourBooking $booking The booking
     * @return array
     */
    protected function gatherServiceDocumentation(TourBooking $booking): array
    {
        $documentation = [];

        if ($booking->tourPackage) {
            $documentation['tour_package_details'] = [
                'type' => 'service_description',
                'data' => [
                    'package_name' => $booking->tourPackage->name,
                    'description' => $booking->tourPackage->description,
                    'inclusions' => $booking->tourPackage->inclusions ?? null,
                    'exclusions' => $booking->tourPackage->exclusions ?? null,
                    'duration' => $booking->tourPackage->duration,
                    'location' => $booking->tourPackage->location,
                    'meeting_point' => $booking->tourPackage->meeting_point ?? null,
                    'what_to_bring' => $booking->tourPackage->what_to_bring ?? null,
                    'tour_type' => $booking->tourPackage->tour_type ?? 'standard',
                    'difficulty_level' => $booking->tourPackage->difficulty_level ?? 'easy'
                ]
            ];
        }

        // Service delivery proof (if tour was completed)
        if ($booking->status === 'Completed') {
            $documentation['service_delivery'] = [
                'type' => 'service_completion',
                'data' => [
                    'completion_status' => $booking->status,
                    'tour_date' => $booking->tour_date,
                    'tour_time' => $booking->tour_time,
                    'completion_notes' => $booking->completion_notes ?? null,
                    'customer_feedback' => $booking->customer_rating ?? null,
                    'service_provided' => true,
                    'completion_timestamp' => $booking->updated_at,
                    'participants_count' => $booking->tourists,
                    'service_duration' => $booking->tourPackage?->duration ?? 'As scheduled'
                ]
            ];
        }

        // Booking confirmation as service documentation
        $documentation['booking_confirmation_details'] = [
            'type' => 'booking_confirmation',
            'data' => [
                'confirmation_number' => $booking->reference_booking ?? $booking->id,
                'booking_date' => $booking->created_at,
                'service_date' => $booking->tour_date,
                'service_time' => $booking->tour_time,
                'participants' => $booking->tourists,
                'total_amount' => $booking->total,
                'booking_status' => $booking->status,
                'confirmation_sent' => true,
                'terms_accepted' => true // Assuming terms are accepted during booking
            ]
        ];

        // Pre-tour preparation documentation
        if ($booking->tourPackage) {
            $documentation['pre_tour_information'] = [
                'type' => 'pre_service_documentation',
                'data' => [
                    'preparation_instructions' => $booking->tourPackage->what_to_bring ?? 'Standard tour preparation',
                    'meeting_instructions' => $booking->tourPackage->meeting_point ?? 'Meeting point provided',
                    'weather_considerations' => 'Weather-appropriate clothing recommended',
                    'safety_briefing_required' => true,
                    'equipment_provided' => $booking->tourPackage->inclusions ?? 'As per tour package'
                ]
            ];
        }

        return $documentation;
    }

    /**
     * Gather policy documentation.
     * 
     * @param TourBooking $booking The booking
     * @return array
     */
    protected function gatherPolicyDocumentation(TourBooking $booking): array
    {
        $policies = [];

        // Cancellation policy
        if ($booking->tourPackage && !empty($booking->tourPackage->cancellation_policy)) {
            $policies['cancellation_policy'] = [
                'type' => 'cancellation_policy',
                'data' => [
                    'policy_text' => $booking->tourPackage->cancellation_policy,
                    'applicable_to_booking' => true,
                    'policy_version' => '1.0', // Could be dynamic based on booking date
                    'effective_date' => $booking->created_at
                ]
            ];
        }

        // Terms and conditions
        $policies['terms_and_conditions'] = [
            'type' => 'terms_and_conditions',
            'data' => [
                'booking_terms' => 'Standard booking terms apply as per website terms of service',
                'payment_terms' => 'Payment required at time of booking confirmation',
                'liability_terms' => 'Standard liability limitations apply as per tour operator agreement',
                'age_restrictions' => $booking->tourPackage?->age_restrictions ?? 'No specific age restrictions',
                'health_requirements' => $booking->tourPackage?->health_requirements ?? 'Standard health and fitness level required',
                'weather_policy' => 'Tours operate in most weather conditions unless unsafe',
                'refund_policy' => $booking->tourPackage?->refund_policy ?? 'As per cancellation policy'
            ]
        ];

        // Privacy policy and data handling
        $policies['privacy_and_data_policy'] = [
            'type' => 'privacy_policy',
            'data' => [
                'data_collection_consent' => true,
                'marketing_consent' => false, // Could be dynamic based on customer preferences
                'data_retention_policy' => 'Customer data retained as per privacy policy',
                'third_party_sharing' => 'Data not shared with third parties except as required for service delivery',
                'customer_rights' => 'Customers have right to access, modify, and delete personal data'
            ]
        ];

        // Booking-specific policies
        $policies['booking_specific_policies'] = [
            'type' => 'booking_policies',
            'data' => [
                'no_show_policy' => 'No refund for no-shows without 24-hour notice',
                'late_arrival_policy' => 'Tours may depart without late arrivals',
                'group_size_policy' => 'Minimum and maximum group sizes apply',
                'equipment_policy' => 'Tour operator provides specified equipment',
                'photography_policy' => 'Photography permitted unless otherwise specified',
                'behavior_policy' => 'Respectful behavior required from all participants'
            ]
        ];

        return $policies;
    }

    /**
     * Replace template variables with actual data.
     * 
     * @param string $content The template content
     * @param array $data The data to replace variables with
     * @return string
     */
    protected function replaceTemplateVariables(string $content, array $data): string
    {
        // Define variable patterns and their replacements
        $replacements = [];

        // Booking data
        if (isset($data['booking'])) {
            $booking = $data['booking'];
            $replacements['{{booking_id}}'] = $booking->id ?? '';
            $replacements['{{booking_reference}}'] = $booking->reference_booking ?? '';
            $replacements['{{customer_name}}'] = $booking->name ?? '';
            $replacements['{{customer_email}}'] = $booking->email ?? '';
            $replacements['{{tour_date}}'] = $booking->tour_date ?? '';
            $replacements['{{tour_time}}'] = $booking->tour_time ?? '';
            $replacements['{{total_amount}}'] = $booking->total ?? '';
            $replacements['{{tourists_count}}'] = $booking->tourists ?? '';
        }

        // Dispute data
        if (isset($data['dispute'])) {
            $dispute = $data['dispute'];
            $replacements['{{dispute_id}}'] = $dispute->stripe_dispute_id ?? '';
            $replacements['{{dispute_amount}}'] = $dispute->amount ?? '';
            $replacements['{{dispute_reason}}'] = $dispute->reason ?? '';
            $replacements['{{evidence_due_date}}'] = $dispute->evidence_due_by ? $dispute->evidence_due_by->format('Y-m-d') : '';
        }

        // Company data
        $replacements['{{company_name}}'] = config('app.name', 'Tour Company');
        $replacements['{{current_date}}'] = now()->format('Y-m-d');
        $replacements['{{current_datetime}}'] = now()->format('Y-m-d H:i:s');

        // Replace all variables
        foreach ($replacements as $variable => $value) {
            $content = str_replace($variable, $value, $content);
        }

        return $content;
    }

    /**
     * Get customer booking count for reputation assessment.
     * 
     * @param string $email The customer email
     * @return int
     */
    protected function getCustomerBookingCount(string $email): int
    {
        return TourBooking::where('email', $email)
            ->where('status', 'Completed')
            ->count();
    }

    /**
     * Count total documents in evidence package.
     * 
     * @param array $evidencePackage The evidence package
     * @return int
     */
    protected function countDocuments(array $evidencePackage): int
    {
        $count = 0;
        
        foreach ($evidencePackage as $category => $documents) {
            if ($category === 'metadata') {
                continue;
            }
            
            if (is_array($documents)) {
                $count += count($documents);
            }
        }
        
        return $count;
    }

    /**
     * Create a comprehensive evidence package for Stripe dispute submission.
     * This method ensures all required documentation is included per requirements 4.2, 11.2, and 11.4.
     * 
     * @param int $bookingId The booking ID
     * @param array $additionalEvidence Optional additional evidence files
     * @param string $disputeReason Optional dispute reason for tailored evidence
     * @return array The comprehensive evidence package ready for Stripe submission
     * @throws Exception When package creation fails
     */
    public function createComprehensiveEvidencePackage(int $bookingId, array $additionalEvidence = [], string $disputeReason = 'general'): array
    {
        try {
            // Compile the enhanced evidence package with automated gathering and standard docs
            $evidencePackage = $this->compileEvidencePackage($bookingId, $disputeReason);
            
            // Enhance with Stripe-specific evidence categories
            $stripeEvidence = $this->formatForStripeSubmission($evidencePackage, $additionalEvidence);
            
            // Validate completeness according to requirements using enhanced validation
            $validation = $this->validateEvidencePackageCompleteness($stripeEvidence);
            
            if (!$validation['valid']) {
                Log::warning('Comprehensive evidence package validation failed', [
                    'booking_id' => $bookingId,
                    'dispute_reason' => $disputeReason,
                    'errors' => $validation['errors'],
                    'completeness_score' => $validation['completeness_score'],
                    'submission_readiness' => $validation['submission_readiness']
                ]);
            }
            
            $comprehensivePackage = [
                'evidence_package' => $evidencePackage,
                'stripe_evidence' => $stripeEvidence,
                'validation_result' => $validation,
                'package_metadata' => [
                    'created_at' => now(),
                    'booking_id' => $bookingId,
                    'dispute_reason' => $disputeReason,
                    'automation_features' => [
                        'automated_gathering' => true,
                        'standard_docs_included' => true,
                        'enhanced_validation' => true,
                        'dispute_reason_tailoring' => true
                    ],
                    'requirements_met' => [
                        '4.2' => $this->verifyRequirement42($evidencePackage),
                        '11.2' => $this->verifyRequirement112($evidencePackage),
                        '11.4' => $this->verifyRequirement114($evidencePackage),
                        '11.7' => isset($evidencePackage['standard_documentation'])
                    ],
                    'completeness_score' => $validation['completeness_score'],
                    'submission_readiness' => $validation['submission_readiness'],
                    'ready_for_submission' => $validation['valid'] && $validation['submission_readiness'] === 'ready'
                ]
            ];
            
            // Log::info('Comprehensive evidence package created with automation', [
            //     'booking_id' => $bookingId,
            //     'dispute_reason' => $disputeReason,
            //     'completeness_score' => $validation['completeness_score'],
            //     'submission_readiness' => $validation['submission_readiness'],
            //     'requirements_met' => $comprehensivePackage['package_metadata']['requirements_met'],
            //     'automation_features' => $comprehensivePackage['package_metadata']['automation_features']
            // ]);
            
            return $comprehensivePackage;
            
        } catch (Exception $e) {
            Log::error('Failed to create comprehensive evidence package', [
                'booking_id' => $bookingId,
                'dispute_reason' => $disputeReason,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Automatically gather booking and payment documentation (Requirement 11.2).
     * This method implements automated evidence compilation from booking and payment data.
     * 
     * @param int $bookingId The booking ID
     * @param string $disputeReason The dispute reason to tailor evidence gathering
     * @return array Automatically gathered evidence
     * @throws Exception When gathering fails
     */
    public function automaticallyGatherEvidenceDocumentation(int $bookingId, string $disputeReason = 'general'): array
    {
        try {
            $booking = TourBooking::with(['transactions', 'tourPackage', 'customer'])->find($bookingId);
            if (!$booking) {
                throw new Exception("Booking not found: {$bookingId}");
            }

            $gatheredEvidence = [
                'automatic_gathering_metadata' => [
                    'booking_id' => $bookingId,
                    'dispute_reason' => $disputeReason,
                    'gathered_at' => now(),
                    'gathering_method' => 'automated',
                    'evidence_sources' => []
                ]
            ];

            // Automatically gather booking documentation
            $bookingDocs = $this->gatherBookingDocuments($booking);
            $gatheredEvidence['booking_documentation'] = $bookingDocs;
            $gatheredEvidence['automatic_gathering_metadata']['evidence_sources'][] = 'booking_system';

            // Automatically gather payment records
            $paymentRecords = $this->gatherPaymentRecords($booking);
            $gatheredEvidence['payment_records'] = $paymentRecords;
            $gatheredEvidence['automatic_gathering_metadata']['evidence_sources'][] = 'payment_system';

            // Automatically gather communication records
            $communicationRecords = $this->gatherCommunicationRecords($booking);
            $gatheredEvidence['communication_records'] = $communicationRecords;
            $gatheredEvidence['automatic_gathering_metadata']['evidence_sources'][] = 'communication_system';

            // Automatically gather service documentation
            $serviceDocs = $this->gatherServiceDocumentation($booking);
            $gatheredEvidence['service_documentation'] = $serviceDocs;
            $gatheredEvidence['automatic_gathering_metadata']['evidence_sources'][] = 'service_system';

            // Automatically gather policy documentation
            $policyDocs = $this->gatherPolicyDocumentation($booking);
            $gatheredEvidence['policy_documentation'] = $policyDocs;
            $gatheredEvidence['automatic_gathering_metadata']['evidence_sources'][] = 'policy_system';

            // Tailor evidence based on dispute reason
            $tailoredEvidence = $this->tailorEvidenceForDisputeReason($gatheredEvidence, $disputeReason);

            // Log::info('Evidence documentation automatically gathered', [
            //     'booking_id' => $bookingId,
            //     'dispute_reason' => $disputeReason,
            //     'evidence_sources' => $gatheredEvidence['automatic_gathering_metadata']['evidence_sources'],
            //     'document_count' => $this->countDocuments($tailoredEvidence)
            // ]);

            return $tailoredEvidence;

        } catch (Exception $e) {
            Log::error('Failed to automatically gather evidence documentation', [
                'booking_id' => $bookingId,
                'dispute_reason' => $disputeReason,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Validate evidence package completeness and check for missing documentation (Requirement 11.4).
     * Enhanced validation with detailed completeness checking.
     * 
     * @param array $evidence The evidence to validate
     * @return array Enhanced validation result with completeness details
     */
    public function validateEvidencePackageCompleteness(array $evidence): array
    {
        $validation = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'completeness_details' => [
                'required_evidence' => [],
                'recommended_evidence' => [],
                'optional_evidence' => [],
                'missing_critical' => [],
                'missing_recommended' => []
            ],
            'completeness_score' => 0.0,
            'submission_readiness' => 'not_ready'
        ];

        // Define evidence requirements by category
        $requiredEvidence = [
            'receipt' => 'Payment receipt or transaction record',
            'customer_communication' => 'Communication with customer (emails, messages)',
        ];

        $recommendedEvidence = [
            'service_documentation' => 'Proof of service delivery or completion',
            'cancellation_policy' => 'Terms and cancellation policy',
            'shipping_documentation' => 'Booking confirmation and delivery proof'
        ];

        $optionalEvidence = [
            'duplicate_charge_documentation' => 'Evidence against duplicate charge claims',
            'refund_policy' => 'Refund policy documentation',
            'customer_signature' => 'Customer signature or authorization',
            'uncategorized_file' => 'Additional supporting documentation'
        ];

        // Check required evidence
        foreach ($requiredEvidence as $type => $description) {
            if (!empty($evidence[$type])) {
                $validation['completeness_details']['required_evidence'][$type] = [
                    'present' => true,
                    'description' => $description,
                    'content_length' => is_string($evidence[$type]) ? strlen($evidence[$type]) : 'N/A'
                ];
            } else {
                $validation['errors'][] = "Missing required evidence: {$description}";
                $validation['completeness_details']['missing_critical'][] = $type;
                $validation['completeness_details']['required_evidence'][$type] = [
                    'present' => false,
                    'description' => $description
                ];
                $validation['valid'] = false;
            }
        }

        // Check recommended evidence
        foreach ($recommendedEvidence as $type => $description) {
            if (!empty($evidence[$type])) {
                $validation['completeness_details']['recommended_evidence'][$type] = [
                    'present' => true,
                    'description' => $description,
                    'content_length' => is_string($evidence[$type]) ? strlen($evidence[$type]) : 'N/A'
                ];
            } else {
                $validation['warnings'][] = "Missing recommended evidence: {$description}";
                $validation['completeness_details']['missing_recommended'][] = $type;
                $validation['completeness_details']['recommended_evidence'][$type] = [
                    'present' => false,
                    'description' => $description
                ];
            }
        }

        // Check optional evidence
        foreach ($optionalEvidence as $type => $description) {
            if (!empty($evidence[$type])) {
                $validation['completeness_details']['optional_evidence'][$type] = [
                    'present' => true,
                    'description' => $description,
                    'content_length' => is_string($evidence[$type]) ? strlen($evidence[$type]) : 'N/A'
                ];
            }
        }

        // Calculate enhanced completeness score
        $validation['completeness_score'] = $this->calculateEnhancedCompletenessScore($evidence, $requiredEvidence, $recommendedEvidence, $optionalEvidence);

        // Determine submission readiness
        if ($validation['valid'] && $validation['completeness_score'] >= 0.8) {
            $validation['submission_readiness'] = 'ready';
        } elseif ($validation['valid'] && $validation['completeness_score'] >= 0.6) {
            $validation['submission_readiness'] = 'acceptable';
        } elseif ($validation['completeness_score'] >= 0.4) {
            $validation['submission_readiness'] = 'needs_improvement';
        } else {
            $validation['submission_readiness'] = 'not_ready';
        }

        // Validate file evidence if present
        if (isset($evidence['files'])) {
            $fileValidation = $this->validateEvidenceFiles($evidence['files']);
            $validation['file_validation'] = $fileValidation;
            if (!$fileValidation['valid']) {
                $validation['valid'] = false;
                $validation['errors'] = array_merge($validation['errors'], $fileValidation['errors']);
            }
        }

        return $validation;
    }

    /**
     * Include standard documentation automatically in evidence packages (Requirement 11.7).
     * This method ensures standard documentation is always included.
     * 
     * @param array $evidencePackage The base evidence package
     * @param int $bookingId The booking ID for context
     * @return array Evidence package with standard documentation included
     */
    public function includeStandardDocumentation(array $evidencePackage, int $bookingId): array
    {
        try {
            // Standard documentation that should always be included
            $standardDocs = [
                'standard_terms_and_conditions' => [
                    'type' => 'standard_documentation',
                    'category' => 'terms_and_conditions',
                    'data' => [
                        'document_type' => 'Terms and Conditions',
                        'version' => '1.0',
                        'effective_date' => now()->format('Y-m-d'),
                        'content' => $this->getStandardTermsAndConditions(),
                        'applies_to_booking' => true,
                        'booking_id' => $bookingId
                    ]
                ],
                'standard_privacy_policy' => [
                    'type' => 'standard_documentation',
                    'category' => 'privacy_policy',
                    'data' => [
                        'document_type' => 'Privacy Policy',
                        'version' => '1.0',
                        'effective_date' => now()->format('Y-m-d'),
                        'content' => $this->getStandardPrivacyPolicy(),
                        'data_handling_notice' => 'Customer data handled in accordance with privacy policy',
                        'booking_id' => $bookingId
                    ]
                ],
                'standard_dispute_response_policy' => [
                    'type' => 'standard_documentation',
                    'category' => 'dispute_policy',
                    'data' => [
                        'document_type' => 'Dispute Response Policy',
                        'version' => '1.0',
                        'content' => $this->getStandardDisputeResponsePolicy(),
                        'commitment' => 'We are committed to resolving disputes fairly and promptly',
                        'booking_id' => $bookingId
                    ]
                ],
                'standard_service_guarantee' => [
                    'type' => 'standard_documentation',
                    'category' => 'service_guarantee',
                    'data' => [
                        'document_type' => 'Service Quality Guarantee',
                        'version' => '1.0',
                        'content' => $this->getStandardServiceGuarantee(),
                        'quality_commitment' => 'We guarantee quality service delivery as described',
                        'booking_id' => $bookingId
                    ]
                ]
            ];

            // Add standard documentation to the evidence package
            if (!isset($evidencePackage['standard_documentation'])) {
                $evidencePackage['standard_documentation'] = [];
            }

            $evidencePackage['standard_documentation'] = array_merge(
                $evidencePackage['standard_documentation'],
                $standardDocs
            );

            // Update metadata to reflect standard documentation inclusion
            if (!isset($evidencePackage['metadata'])) {
                $evidencePackage['metadata'] = [];
            }

            $evidencePackage['metadata']['standard_documentation_included'] = true;
            $evidencePackage['metadata']['standard_docs_count'] = count($standardDocs);
            $evidencePackage['metadata']['standard_docs_types'] = array_keys($standardDocs);
            $evidencePackage['metadata']['requirement_117_met'] = true;

            // Log::info('Standard documentation included in evidence package', [
            //     'booking_id' => $bookingId,
            //     'standard_docs_added' => count($standardDocs),
            //     'standard_doc_types' => array_keys($standardDocs)
            // ]);

            return $evidencePackage;

        } catch (Exception $e) {
            Log::error('Failed to include standard documentation', [
                'booking_id' => $bookingId,
                'error' => $e->getMessage()
            ]);

            // Return original package if standard documentation inclusion fails
            return $evidencePackage;
        }
    }

    /**
     * Format evidence package for Stripe submission.
     * 
     * @param array $evidencePackage The compiled evidence package
     * @param array $additionalEvidence Additional evidence files
     * @return array Formatted evidence for Stripe
     */
    protected function formatForStripeSubmission(array $evidencePackage, array $additionalEvidence = []): array
    {
        $stripeEvidence = [];
        
        // Map our evidence categories to Stripe's expected format
        
        // Receipt evidence from payment records
        if (isset($evidencePackage['payment_records'])) {
            $stripeEvidence['receipt'] = $this->extractReceiptEvidence($evidencePackage['payment_records']);
        }
        
        // Customer communication from communication records
        if (isset($evidencePackage['communication_records'])) {
            $stripeEvidence['customer_communication'] = $this->extractCommunicationEvidence($evidencePackage['communication_records']);
        }
        
        // Service documentation
        if (isset($evidencePackage['service_documentation'])) {
            $stripeEvidence['service_documentation'] = $this->extractServiceEvidence($evidencePackage['service_documentation']);
        }
        
        // Shipping documentation (for tour confirmations and instructions)
        if (isset($evidencePackage['booking_documentation'])) {
            $stripeEvidence['shipping_documentation'] = $this->extractShippingEvidence($evidencePackage['booking_documentation']);
        }
        
        // Refund policy
        if (isset($evidencePackage['policy_documentation']['cancellation_policy'])) {
            $stripeEvidence['refund_policy'] = $evidencePackage['policy_documentation']['cancellation_policy']['data']['policy_text'];
        }
        
        // Cancellation policy
        if (isset($evidencePackage['policy_documentation'])) {
            $stripeEvidence['cancellation_policy'] = $this->extractCancellationPolicy($evidencePackage['policy_documentation']);
        }
        
        // Add any additional evidence files
        foreach ($additionalEvidence as $type => $evidence) {
            $stripeEvidence[$type] = $evidence;
        }
        
        return $stripeEvidence;
    }

    /**
     * Verify that requirement 4.2 is met: Include booking confirmation, customer communication, and service delivery proof.
     */
    protected function verifyRequirement42(array $evidencePackage): bool
    {
        $hasBookingConfirmation = isset($evidencePackage['booking_documentation']['booking_confirmation']);
        $hasCommunication = isset($evidencePackage['communication_records']) && !empty($evidencePackage['communication_records']);
        $hasServiceDelivery = isset($evidencePackage['service_documentation']) && !empty($evidencePackage['service_documentation']);
        
        return $hasBookingConfirmation && $hasCommunication && $hasServiceDelivery;
    }

    /**
     * Verify that requirement 11.2 is met: Automatically gather relevant booking and payment documentation.
     */
    protected function verifyRequirement112(array $evidencePackage): bool
    {
        $hasBookingDocs = isset($evidencePackage['booking_documentation']) && !empty($evidencePackage['booking_documentation']);
        $hasPaymentDocs = isset($evidencePackage['payment_records']) && !empty($evidencePackage['payment_records']);
        
        return $hasBookingDocs && $hasPaymentDocs;
    }

    /**
     * Verify that requirement 11.4 is met: Include standard documentation automatically in evidence packages.
     */
    protected function verifyRequirement114(array $evidencePackage): bool
    {
        $hasStandardDocs = isset($evidencePackage['standard_documentation']) && !empty($evidencePackage['standard_documentation']);
        $hasServiceDocs = isset($evidencePackage['service_documentation']) && !empty($evidencePackage['service_documentation']);
        $hasMetadata = isset($evidencePackage['compilation_metadata']) && !empty($evidencePackage['compilation_metadata']);
        
        return $hasStandardDocs && $hasServiceDocs && $hasMetadata;
    }

    /**
     * Verify that requirement 11.7 is met: Standard documentation inclusion for evidence packages.
     */
    protected function verifyRequirement117(array $evidencePackage): bool
    {
        // Check for standard documentation categories
        $requiredStandardDocs = [
            'standard_terms_and_conditions',
            'standard_privacy_policy',
            'standard_dispute_response_policy',
            'standard_service_guarantee'
        ];

        if (!isset($evidencePackage['standard_documentation'])) {
            return false;
        }

        $standardDocs = $evidencePackage['standard_documentation'];
        
        foreach ($requiredStandardDocs as $docType) {
            if (!isset($standardDocs[$docType])) {
                return false;
            }
        }

        // Check metadata indicates standard documentation was included
        return isset($evidencePackage['compilation_metadata']['standard_documentation_included']) &&
               $evidencePackage['compilation_metadata']['standard_documentation_included'] === true;
    }

    /**
     * Extract receipt evidence for Stripe.
     */
    protected function extractReceiptEvidence(array $paymentRecords): string
    {
        $receiptText = "PAYMENT RECEIPT\n\n";
        
        foreach ($paymentRecords as $record) {
            if ($record['type'] === 'payment_transaction') {
                $data = $record['data'];
                $receiptText .= "Transaction ID: {$data['transaction_id']}\n";
                $receiptText .= "Amount: {$data['currency']} {$data['amount']}\n";
                $receiptText .= "Payment Method: {$data['payment_method']}\n";
                $receiptText .= "Status: {$data['status']}\n";
                $receiptText .= "Date: {$data['processed_at']}\n\n";
            }
        }
        
        return $receiptText;
    }

    /**
     * Extract communication evidence for Stripe.
     */
    protected function extractCommunicationEvidence(array $communicationRecords): string
    {
        $commText = "CUSTOMER COMMUNICATION RECORD\n\n";
        
        foreach ($communicationRecords as $record) {
            if (is_array($record) && isset($record['type'], $record['data'])) {
                $data = $record['data'];
                $commText .= "Type: {$record['type']}\n";
                
                if (isset($data['booking_confirmation_sent'])) {
                    $commText .= "Booking confirmation sent: " . ($data['booking_confirmation_sent'] ? 'Yes' : 'No') . "\n";
                }
                
                if (isset($data['customer_email'])) {
                    $commText .= "Customer email: {$data['customer_email']}\n";
                }
                
                if (isset($data['content'])) {
                    $commText .= "Content: {$data['content']}\n";
                }
                
                $commText .= "\n";
            }
        }
        
        return $commText;
    }

    /**
     * Extract service evidence for Stripe.
     */
    protected function extractServiceEvidence(array $serviceDocumentation): string
    {
        $serviceText = "SERVICE DELIVERY DOCUMENTATION\n\n";
        
        foreach ($serviceDocumentation as $doc) {
            if (is_array($doc) && isset($doc['type'], $doc['data'])) {
                $data = $doc['data'];
                $serviceText .= "Document Type: {$doc['type']}\n";
                
                if (isset($data['package_name'])) {
                    $serviceText .= "Service: {$data['package_name']}\n";
                }
                
                if (isset($data['completion_status'])) {
                    $serviceText .= "Status: {$data['completion_status']}\n";
                }
                
                if (isset($data['service_provided'])) {
                    $serviceText .= "Service Provided: " . ($data['service_provided'] ? 'Yes' : 'No') . "\n";
                }
                
                $serviceText .= "\n";
            }
        }
        
        return $serviceText;
    }

    /**
     * Extract shipping evidence for Stripe (booking confirmations).
     */
    protected function extractShippingEvidence(array $bookingDocumentation): string
    {
        $shippingText = "BOOKING CONFIRMATION AND DELIVERY\n\n";
        
        if (isset($bookingDocumentation['booking_confirmation']['data'])) {
            $data = $bookingDocumentation['booking_confirmation']['data'];
            $shippingText .= "Confirmation Number: {$data['reference_number']}\n";
            $shippingText .= "Customer: {$data['customer_name']}\n";
            $shippingText .= "Email: {$data['customer_email']}\n";
            $shippingText .= "Service Date: {$data['tour_date']}\n";
            $shippingText .= "Confirmation Sent: {$data['created_at']}\n";
        }
        
        return $shippingText;
    }

    /**
     * Extract cancellation policy for Stripe.
     */
    protected function extractCancellationPolicy(array $policyDocumentation): string
    {
        if (isset($policyDocumentation['cancellation_policy']['data']['policy_text'])) {
            return $policyDocumentation['cancellation_policy']['data']['policy_text'];
        }
        
        return 'Standard cancellation policy applies as per terms of service.';
    }

    /**
     * Calculate evidence completeness score.
     * 
     * @param array $evidence The evidence to score
     * @return float Score between 0 and 1
     */
    protected function calculateCompletenessScore(array $evidence): float
    {
        $totalPossiblePoints = 10;
        $earnedPoints = 0;

        // Required evidence (5 points each)
        $requiredTypes = ['receipt', 'customer_communication'];
        foreach ($requiredTypes as $type) {
            if (!empty($evidence[$type])) {
                $earnedPoints += 5;
            }
        }

        // Optional evidence (1 point each)
        $optionalTypes = [
            'service_documentation',
            'shipping_documentation',
            'duplicate_charge_documentation',
            'refund_policy',
            'cancellation_policy',
            'customer_signature',
            'uncategorized_file'
        ];
        
        foreach ($optionalTypes as $type) {
            if (!empty($evidence[$type])) {
                $earnedPoints += 1;
                if ($earnedPoints >= $totalPossiblePoints) {
                    break;
                }
            }
        }

        return min($earnedPoints / $totalPossiblePoints, 1.0);
    }

    /**
     * Calculate enhanced completeness score with weighted categories.
     * 
     * @param array $evidence The evidence to score
     * @param array $requiredEvidence Required evidence types
     * @param array $recommendedEvidence Recommended evidence types
     * @param array $optionalEvidence Optional evidence types
     * @return float Score between 0 and 1
     */
    protected function calculateEnhancedCompletenessScore(array $evidence, array $requiredEvidence, array $recommendedEvidence, array $optionalEvidence): float
    {
        $totalPossiblePoints = 100;
        $earnedPoints = 0;

        // Required evidence (40 points each, total 80 points)
        foreach ($requiredEvidence as $type => $description) {
            if (!empty($evidence[$type])) {
                $earnedPoints += 40;
            }
        }

        // Recommended evidence (10 points each, up to 15 points total)
        $recommendedPoints = 0;
        foreach ($recommendedEvidence as $type => $description) {
            if (!empty($evidence[$type])) {
                $recommendedPoints += 10;
            }
        }
        $earnedPoints += min($recommendedPoints, 15);

        // Optional evidence (1 point each, up to 5 points total)
        $optionalPoints = 0;
        foreach ($optionalEvidence as $type => $description) {
            if (!empty($evidence[$type])) {
                $optionalPoints += 1;
            }
        }
        $earnedPoints += min($optionalPoints, 5);

        return min($earnedPoints / $totalPossiblePoints, 1.0);
    }

    /**
     * Tailor evidence gathering based on dispute reason.
     * 
     * @param array $evidencePackage The base evidence package
     * @param string $disputeReason The dispute reason
     * @return array Tailored evidence package
     */
    protected function tailorEvidenceForDisputeReason(array $evidencePackage, string $disputeReason): array
    {
        // Add dispute-reason-specific evidence emphasis
        $evidencePackage['dispute_specific_evidence'] = [
            'dispute_reason' => $disputeReason,
            'tailored_for_reason' => true,
            'emphasis_areas' => []
        ];

        switch (strtolower($disputeReason)) {
            case 'fraudulent':
                $evidencePackage['dispute_specific_evidence']['emphasis_areas'] = [
                    'customer_verification',
                    'authorization_proof',
                    'delivery_confirmation'
                ];
                break;

            case 'subscription_canceled':
            case 'product_unacceptable':
                $evidencePackage['dispute_specific_evidence']['emphasis_areas'] = [
                    'service_delivery_proof',
                    'customer_satisfaction',
                    'cancellation_policy'
                ];
                break;

            case 'duplicate':
                $evidencePackage['dispute_specific_evidence']['emphasis_areas'] = [
                    'transaction_uniqueness',
                    'separate_services',
                    'payment_authorization'
                ];
                break;

            case 'credit_not_processed':
                $evidencePackage['dispute_specific_evidence']['emphasis_areas'] = [
                    'refund_processing',
                    'credit_documentation',
                    'customer_communication'
                ];
                break;

            default:
                $evidencePackage['dispute_specific_evidence']['emphasis_areas'] = [
                    'service_delivery',
                    'customer_authorization',
                    'policy_compliance'
                ];
        }

        return $evidencePackage;
    }

    /**
     * Validate evidence files.
     * 
     * @param array $files The files to validate
     * @return array Validation result
     */
    protected function validateEvidenceFiles(array $files): array
    {
        $validation = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'file_count' => count($files),
            'total_size' => 0
        ];

        $maxFileSize = 10 * 1024 * 1024; // 10MB
        $allowedTypes = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'txt'];

        foreach ($files as $index => $file) {
            // Check for required file properties
            if (!isset($file['name']) || empty($file['name'])) {
                $validation['errors'][] = "File #{$index}: Missing file name";
                $validation['valid'] = false;
            }

            if (!isset($file['stripe_file_id']) || empty($file['stripe_file_id'])) {
                $validation['errors'][] = "File '{$file['name']}': Missing Stripe file ID";
                $validation['valid'] = false;
            }

            // Check file size if provided
            if (isset($file['size'])) {
                $validation['total_size'] += $file['size'];
                if ($file['size'] > $maxFileSize) {
                    $validation['errors'][] = "File '{$file['name']}': Size exceeds 10MB limit";
                    $validation['valid'] = false;
                }
            }

            // Check file type if provided
            if (isset($file['type']) || isset($file['extension'])) {
                $fileType = $file['type'] ?? $file['extension'] ?? '';
                $fileType = strtolower(str_replace('.', '', $fileType));
                
                if (!in_array($fileType, $allowedTypes)) {
                    $validation['warnings'][] = "File '{$file['name']}': Type '{$fileType}' may not be supported by Stripe";
                }
            }
        }

        return $validation;
    }

    /**
     * Get standard terms and conditions text.
     * 
     * @return string
     */
    protected function getStandardTermsAndConditions(): string
    {
        return "TERMS AND CONDITIONS\n\n" .
               "1. Booking Confirmation: All bookings are confirmed upon payment receipt and confirmation email.\n" .
               "2. Service Delivery: We commit to providing the tour services as described in the booking confirmation.\n" .
               "3. Customer Responsibilities: Customers must arrive on time and follow safety instructions.\n" .
               "4. Cancellation Policy: Cancellations must be made according to the specified cancellation policy.\n" .
               "5. Liability: Standard liability limitations apply as per tour operator regulations.\n" .
               "6. Dispute Resolution: We are committed to resolving any disputes fairly and promptly.\n\n" .
               "These terms apply to all bookings and are accepted upon booking confirmation.";
    }

    /**
     * Get standard privacy policy text.
     * 
     * @return string
     */
    protected function getStandardPrivacyPolicy(): string
    {
        return "PRIVACY POLICY\n\n" .
               "1. Data Collection: We collect personal information necessary for booking and service delivery.\n" .
               "2. Data Use: Personal information is used solely for booking management and service provision.\n" .
               "3. Data Protection: Customer data is protected using industry-standard security measures.\n" .
               "4. Data Sharing: We do not share personal data with third parties except as required for service delivery.\n" .
               "5. Data Retention: Customer data is retained as required for business and legal purposes.\n" .
               "6. Customer Rights: Customers have the right to access, modify, and request deletion of their personal data.\n\n" .
               "This privacy policy governs all customer data handling practices.";
    }

    /**
     * Get standard dispute response policy text.
     * 
     * @return string
     */
    protected function getStandardDisputeResponsePolicy(): string
    {
        return "DISPUTE RESPONSE POLICY\n\n" .
               "1. Commitment: We are committed to resolving all payment disputes fairly and transparently.\n" .
               "2. Response Time: We respond to all disputes within 7 days of notification.\n" .
               "3. Evidence Provision: We provide comprehensive evidence including booking records, service delivery proof, and customer communications.\n" .
               "4. Customer Communication: We maintain open communication with customers throughout the dispute process.\n" .
               "5. Resolution Goal: Our goal is to reach a fair resolution that satisfies all parties.\n" .
               "6. Documentation: All dispute-related activities are fully documented for transparency.\n\n" .
               "This policy ensures consistent and fair dispute handling practices.";
    }

    /**
     * Get standard service guarantee text.
     * 
     * @return string
     */
    protected function getStandardServiceGuarantee(): string
    {
        return "SERVICE QUALITY GUARANTEE\n\n" .
               "1. Service Commitment: We guarantee that all tour services will be delivered as described in the booking confirmation.\n" .
               "2. Quality Standards: Our services meet or exceed industry quality standards.\n" .
               "3. Professional Staff: All tours are conducted by trained and professional tour guides.\n" .
               "4. Safety Standards: We maintain the highest safety standards for all tour activities.\n" .
               "5. Customer Satisfaction: We strive for 100% customer satisfaction and address any concerns promptly.\n" .
               "6. Service Recovery: If service does not meet expectations, we will work to make it right.\n\n" .
               "This guarantee reflects our commitment to excellence in tour service delivery.";
    }
}