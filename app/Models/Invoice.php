<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Ramsey\Uuid\Uuid;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditTrait;

class Invoice extends Model implements Auditable
{
    use SoftDeletes, HasFactory, AuditTrait;

    protected $table = 'invoices';

    protected $fillable = [
        'uuid',
        'tour_operator_id',
        'customer_id',
        'invoice_number',
        'reference_invoice',
        'from_date',
        'to_date',
        'amount',
        'due_date',
        'email_sent',
        'status',
        'external_status',
        'external_customer_id',
        'external_invoice_id',
        'external_payment_link',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $dates = [
        'from_date' => 'date:Y-m-d',
        'to_date' => 'date:Y-m-d',
        'due_date' => 'date:Y-m-d',
        'created_at',
        'uodated_at',
        'deleted_at'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'amount' => 'double',
    ];

    /**
     * Get the tour operator data using relationship.
     *
     * @return BelongsTo
     */
    public function tourOperator(): BelongsTo
    {
        return $this->belongsTo(TourOperator::class, 'tour_operator_id', 'id', BelongsTo::class);
    }

    /**
     * Get the affiliate data using relationship.
     *
     * @return BelongsTo
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id', BelongsTo::class);
    }

    /**
     * @param $query
     * @param $uuid
     * @return object
     */
    public function scopeUuid($query, $uuid): object
    {
        return $query->where('uuid', $uuid);
    }

    /**
     * @param $query
     * @return object
     */
    public function scopePaid($query): object
    {
        return $query->where('status', '=', 'paid');
    }

    /**
     * Get the next number of invoice
     *
     * @return string
     */
    public static function invoiceNumber(): string
    {
        $latest = self::latest()->withTrashed()->first();
        if (!$latest) {
            return 'NAT0000001';
        }

        $string = preg_replace("/[^0-9\.]/", '', $latest->invoice_number);
        return 'NAT' . sprintf('%07d', $string + 1);
    }

    /**
     * Use Laravel bootable traits.
     */
    protected static function booted()
    {
        static::creating(function ($model) {
            $model->invoice_number = self::invoiceNumber();
            $model->uuid = Uuid::uuid4()->toString();
            $model->created_by = Auth::id();
        });

        static::updating(function ($model) {
            $model->updated_by = Auth::id();
        });

        static::deleting(function ($model) {
            $model->deleted_by = Auth::id();
        });
    }
}
