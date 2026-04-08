<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappMessage extends Model
{
    protected $fillable = ['phone', 'message', 'status', 'type', 'error'];
}
