<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Virtualization extends Model
{

    protected $table = 'virtualization';
    protected $fillable = [
      'building_code',
      'assessment_code',
      'bill_approved',
      'bill_distributed',
      'bill_paid',
      'bill_approved_date',
      'bill_distributed_date',
      'bill_paid_date',
      'property_name',
      'property_address',
      'property_lga',
      'property_zone',
      'property_ward',
      'property_image',
      'bill_delivery_image',
      'property_longitude',
      'property_latitude',
      'created_at',
      'property_category',
    ];
}
