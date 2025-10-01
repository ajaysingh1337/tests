<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Devices extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'user_type',
        'token',
        'device_id',
        'device_info',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'device_info' => 'array',
    ];

    /**
     * Get the parent user model (student, teacher, or academy).
     */
    public function user()
    {
        return $this->morphTo('user', 'user_type', 'user_id');
    }

    /**
     * Scope a query to only include tokens for a specific user type and ID.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $userType
     * @param  int  $userId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForUser($query, $userType, $userId)
    {
        return $query->where('user_type', $userType)
                    ->where('user_id', $userId);
    }

    /**
     * Scope a query to only include a specific device token.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $token
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithToken($query, $token)
    {
        return $query->where('token', $token);
    }

    /**
     * Get the device information.
     *
     * @param  mixed  $value
     * @return array|null
     */
    public function getDeviceInfoAttribute($value)
    {
        return $value ? json_decode($value, true) : null;
    }

    /**
     * Set the device information.
     *
     * @param  mixed  $value
     * @return void
     */
    public function setDeviceInfoAttribute($value)
    {
        $this->attributes['device_info'] = is_array($value) ? json_encode($value) : $value;
    }
}