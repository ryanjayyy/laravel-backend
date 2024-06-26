<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Galloner extends Model
{
    use HasFactory;
    protected $primaryKey = 'galloners_id';
    protected $table = 'galloners';

    protected $fillable = [
        'user_id',
        'donate_qty',
        'badge'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    
}
