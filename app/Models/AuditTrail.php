<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AuditTrail extends Model
{
    use HasFactory;
    protected $primaryKey = 'audit_trails_id';
    protected $table = 'audit_trails';

    protected $fillable = [
        'user_id',
        'module',
        'action',
        'status',
        'ip_address',
        'region',
        'city',
        'postal',
        'latitude',
        'longitude',
    ];

    public function getAuditTrail(){
        $sql = "SELECT at.*, ud.first_name, ud.middle_name, ud.last_name FROM audit_trails at
        JOIN user_details as ud on at.user_id = ud.user_id";

        $result = DB::connection('mysql')->select($sql);

        return $result;
    }
}
