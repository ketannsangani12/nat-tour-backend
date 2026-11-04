<?php

namespace App\Traits;

use App\Constants\Constants;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;

trait ReleaseTrait
{
    public function getFillable()
    {
        return Schema::getColumnListing($this->getTable());
    }

    /**
     * Set the body attribute and ensure proper HTML encoding
     */
    public function setBodyAttribute($value)
    {
        // Store the HTML content as-is from the editor
        $this->attributes['body'] = $value;
    }

    /**
     * Get the body attribute with proper HTML rendering
     */
    public function getBodyAttribute($value)
    {
        // Return the HTML content as-is for proper rendering
        return $value;
    }
}