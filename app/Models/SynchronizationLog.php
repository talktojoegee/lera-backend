<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SynchronizationLog extends Model
{
    //

    public function getLGA(){
        return $this->belongsTo(Lga::class, 'lga_id');
    }


    public static function logSyncReport($gis, $labs, $lastSync, $lgaId){
        $log = new SynchronizationLog();
        $log->g_gis = $gis;
        $log->k_labs = $labs;
        $log->last_sync = $lastSync;
        $log->last_sync = $lastSync;
        $log->lga_id = $lgaId;
        $log->save();
    }


    public static function getSyncReport(){
        return SynchronizationLog::orderBy('id', 'DESC')->get();
    }
}
