<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LiveRequest extends Model
{
    use SoftDeletes;

    protected $table = 'live_requests';
    
    protected $fillable = [
        'student_id',
        'category_id',
        'start_time',
        'end_time',
        'status'
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function category()
    {
        return $this->belongsTo(TeacherCategory::class, 'category_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'accepted');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * The teachers that belong to the live request.
     */
    public function teachers()
    {
        return $this->belongsToMany(Teacher::class, 'live_request_teachers', 'request_id', 'teacher_id')
            ->withTimestamps();
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }
}
