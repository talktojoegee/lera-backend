<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    public static function LogActivity($title = 'No title', $narration = '', $user = 1){
        $log = new ActivityLog();
        $log->title = $title ;
        $log->narration = $narration;
        $log->user_id = $user;
        $log->save();
    }
}
