<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LiveRequestTeacher extends Model
{
    use SoftDeletes;

    protected $table = 'live_request_teachers';
    
    protected $fillable = [
        'request_id',
        'teacher_id'
    ];

    public function liveRequest()
    {
        return $this->belongsTo(LiveRequest::class, 'request_id');
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }
}
