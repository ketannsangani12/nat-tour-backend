<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class TenantProcessor implements ProcessorInterface
{
    /**
     * Add tenant ID to all log records.
     *
     * @param LogRecord $record
     * @return LogRecord
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        $tenantId = $this->getTenantId();
        
        return $record->with(extra: array_merge($record->extra, [
            'tenant_id' => $tenantId,
        ]));
    }

    /**
     * Get the current tenant ID.
     *
     * @return string|null
     */
    private function getTenantId(): ?string
    {
        try {
            if (function_exists('tenancy') && tenancy()->initialized && tenancy()->tenant !== null) {
                return tenancy()->tenant->id;
            }
        } catch (\Exception $e) {
            // Silently fail if tenancy is not available
        }

        return null;
    }
}
