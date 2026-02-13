<?php

namespace Dinara\EmailMarketing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailClick extends Model
{
    use HasFactory;

    protected $fillable = [
        'email_send_id',
        'url',
        'ip',
        'user_agent',
    ];

    public function emailSend()
    {
        return $this->belongsTo(EmailSend::class);
    }
}
