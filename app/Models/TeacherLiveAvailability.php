<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherLiveAvailability extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'teacher_live_availability';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'teacher_id',
        'start_time',
        'end_time',
        'status',
        'fee',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    /**
     * Get the status options for the availability.
     *
     * @return array
     */
    public static function getStatusOptions(): array
    {
        return [
            'online' => 'Online',
            'in-call' => 'In Call',
            'offline' => 'Offline',
        ];
    }

    /**
     * Scope a query to only include active availabilities.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', '!=', 'offline')
                    ->where('start_time', '<=', now())
                    ->where('end_time', '>=', now());
    }

    /**
     * Get the teacher that owns the availability.
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
}
