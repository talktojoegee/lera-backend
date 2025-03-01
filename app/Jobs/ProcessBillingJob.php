<?php

namespace App\Jobs;

use App\Models\Billing;
use App\Models\ChargeRate;
use App\Models\Depreciation;
use App\Models\Lga;
use App\Models\MinimumLuc;
use App\Models\PropertyAssessmentValue;
use App\Models\PropertyList;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessBillingJob implements ShouldQueue
{
    use Queueable;
    public $lgaId;
    public $year;
    public $billedBy;

    /**
     * Create a new job instance.
     */
    public function __construct($lgaId, $year, $billedBy)
    {
        $this->lgaId = $lgaId;
        $this->year = $year;
        $this->billedBy = $billedBy;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->lgaId == 0) { //All locations/LGAs
            $propertyLists = PropertyList::orderBy('id', 'DESC')->get();
        } else {
            $propertyLists = PropertyList::where('lga_id', $this->lgaId)/*->take(10)*/ ->get();
        }
        foreach ($propertyLists as $list) {
            $existingBill = Billing::getBillByYearBuildingCode($this->year, $list->building_code);
            if(empty($existingBill)){ //If there is no existing bill
                // echo "Existing Bill ID:: ".$existingBill->id;
                $pavOptional = PropertyAssessmentValue::where("pav_code", $list->pav_code)->first();
                /*
                 * Tie Government = State government, religious = commercial, recreational = commercial
                 * Religious =
                 * Residential =
                 * Commercial =
                 * Tie Owner to = Owner occupied; tenant to = 3rd Party Only
                 */
                $lga = Lga::find($list->lga_id);
                $depreciation = Depreciation::find($list->dep_id);
                $chargeRate = ChargeRate::find($list->cr);
                if (!empty($pavOptional) && !empty($lga) && !empty($depreciation) && !empty($chargeRate)) {

                    $uniqueNumber = uniqid();
                    /*
                     * LA = from Property(Area of Land)
                        LR = from Billing Setup
                        BA% = from Billing Setup (BA%) * 0.01
                        BR = from Billing Setup
                        DR% = from Depreciation Table using age of property to match * 0.01
                        RR% = from Billing Setup * 0.01
                     */
                    //LUC = {(LA * LR) + (BA% x BR x DR)} * RR% * CR
                    $la = (int) $list->area ?? 1; //la
                    $lr = $pavOptional->lr ?? 1;
                    //Log::info('BA Value', ['data' => $pavOptional->ba]);
                    //Log::info('Building ID', ['data' => $list->building_code]);
                    $ba = ( $pavOptional->ba * 0.01) * $la;
                    $br = $pavOptional->br;
                    $dr = $depreciation->value * 0.01; //carry to billing table
                    $rr = $pavOptional->rr * 0.01;


                    $cr = ($chargeRate->rate * 0.01);// ($pavOptional->value_rate * 0.01) * ($la * $lr);

                    $luc = (($la * $lr) + ($ba * $br * $dr)) * ($rr * $cr);
                    $billAmount = $luc;

                    $minimumLUC = MinimumLuc::first();

                    $billing = new Billing();
                    $billing->building_code = $list->building_code ?? null;
                    $billing->assessment_no = $uniqueNumber;
                    $billing->assessed_value = (($la * $lr) + ($ba * $br * $dr)) * ($rr);
                    $billing->bill_amount =  $billAmount > $minimumLUC->amount ? number_format($billAmount,2, '.', '') : $minimumLUC->amount;
                    $billing->minimum_luc =  $billAmount < $minimumLUC->amount ? number_format($billAmount,2, '.', '') : 0;

                    $billing->year = $this->year;

                    $dateTime = new \DateTime('now');
                    $dateTime->setDate($this->year, $dateTime->format('m'), $dateTime->format('d'));
                    $billing->entry_date = $dateTime->format('Y-m-d H:i:s'); //now();
                    $billing->billed_by = $request->billedBy ?? 1;

                    $billing->rr = $pavOptional->rr ?? 0;
                    $billing->lr = $pavOptional->lr ?? 0;
                    $billing->ba = $pavOptional->ba ?? 0;
                    $billing->br = $pavOptional->br ?? 0;
                    $billing->dr = $depreciation->depreciation_rate ?? 0;

                    $billing->cr = $chargeRate->rate;
                    $billing->dr_value = $depreciation->depreciation_rate ?? 0; //rate actually


                    $billing->paid_amount = 0.00;
                    $billing->objection = 0;
                    $billing->lga_id = $list->lga_id;
                    $billing->property_id = $list->id;
                    $billing->bill_rate = $pavOptional->value_rate ?? 0;
                    $billing->pav_code = $pavOptional->pav_code;
                    $billing->zone_name = $list->sub_zone ?? '';
                    $billing->url = substr(sha1( (time()+rand(9,99999)) ), 29, 40);
                    //occupancy
                    $billing->class_id = $list->class_id;
                    $billing->property_use = $list->property_use ?? null;
                    $billing->occupancy = $list->cr;
                    $billing->la = $la;
                    $billing->save();
                }


            }

        }
    }
}
