<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evacuee extends Model
{
    use HasFactory;

    protected $fillable = [
        'evacuation_center_id',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'contact_number',
        'barangay',
        'gender',
        'age',
        'logged_by',
    ];

    public function evacuationCenter()
    {
        return $this->belongsTo(EvacuationCenter::class);
    }

    public function loggedBy()
    {
        return $this->belongsTo(Responder::class, 'logged_by');
    }

    /**
     * "First Middle Last Suffix", skipping any empty parts — used
     * everywhere the log needs a single display name instead of
     * stitching the four name fields together inline each time.
     */
    public function getFullNameAttribute(): string
    {
        return collect([$this->first_name, $this->middle_name, $this->last_name, $this->suffix])
            ->filter()
            ->implode(' ');
    }
}