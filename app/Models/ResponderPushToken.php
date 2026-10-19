<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The push token of one phone signed in to a responder account — see the
 * create_responder_push_tokens migration. A team's account is shared, so
 * it can have several.
 */
class ResponderPushToken extends Model
{
    protected $fillable = ['responder_id', 'token'];

    public function responder()
    {
        return $this->belongsTo(Responder::class);
    }
}
