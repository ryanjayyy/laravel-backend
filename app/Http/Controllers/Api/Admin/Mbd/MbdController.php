<?php

namespace App\Http\Controllers\Api\Admin\Mbd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;


use App\Models\BloodBag;

class MbdController extends Controller
{
    public function getMbdSummary(Request $request){
        $user = Auth::user();
        $userId = $user->user_id;

        try {
            $validatedData = $request->validate([
                'venue'     => ['required'],
                'startDate' => ['required'],
                'endDate'   => ['required'],
            ]);

            $venue = $validatedData['venue'];
            $startDate = $validatedData['startDate'];
            $endDate = $validatedData['endDate'];

            $expiredDate  = '';

            if ($startDate !== $endDate) {
                $expiredDate  = '';
            }else{
                $expiredDate = date('Y-m-d', strtotime($startDate . ' + 37 days'));
            }

            $manPowerCount = app(BloodBag::class)->getManPower($venue, $startDate, $endDate);
            $manPowerList = app(BloodBag::class)->ListOfManPower($venue, $startDate, $endDate);
            $bloodCollection = app(BloodBag::class)->bloodCollection($venue, $startDate, $endDate);
            $totalMaleAndFemale = app(BloodBag::class)->getTotalMaleandFemale($venue, $startDate, $endDate);
            $totalDonorTypes = app(BloodBag::class)->getDonorType($venue, $startDate, $endDate);
            $donateFrequency = app(BloodBag::class)->getDonateFrequency($venue, $startDate, $endDate);
            $getAgeDistributionLeft = app(BloodBag::class)->getAgeDistributionLeft($venue, $startDate, $endDate);
            $getAgeDistributionRight = app(BloodBag::class)->getAgeDistributionRight($venue, $startDate, $endDate);
            $getTempCategoriesDeferral = app(BloodBag::class)->getTempCategoriesDeferral($venue, $startDate, $endDate);
            $countDeferral = app(BloodBag::class)->countDeferral($venue, $startDate, $endDate);
            $numberOfUnitsCollected = app(BloodBag::class)->numberOfUnitsCollected($venue, $startDate, $endDate);
            $countDeferredDonors = app(BloodBag::class)->countDeferredDonors($venue, $startDate, $endDate);
            //PD
            $getTempCategoriesDeferralPD = app(BloodBag::class)->getTempCategoriesDeferralPD($venue, $startDate, $endDate);
            $countDeferralPD =app(BloodBag::class)->countDeferralPD($venue, $startDate, $endDate);
            $bloodCollectionPD =  app(BloodBag::class)->bloodCollectionPD($venue, $startDate, $endDate);

            //all
            $totalUnit = app(BloodBag::class)->totalUnit($venue, $startDate, $endDate);

            return response()->json([
                'status'    => 'success',
                'manPowerCount'      => $manPowerCount,
                'manPowerList'       => $manPowerList,
                'bloodCollection'    => $bloodCollection,
                'totalMaleAndFemale' => $totalMaleAndFemale,
                'totalDonorTypes'    => $totalDonorTypes,
                'donateFrequency'    => $donateFrequency,
                'getAgeDistributionLeft' => $getAgeDistributionLeft,
                'getAgeDistributionRight' => $getAgeDistributionRight,
                'getTempCategoriesDeferral' => $getTempCategoriesDeferral,
                'countDeferral'      => $countDeferral,
                'numberOfUnitsCollected' => $numberOfUnitsCollected,
                'countDeferredDonors' => $countDeferredDonors,
                'getTempCategoriesDeferralPD' => $getTempCategoriesDeferralPD,
                'countDeferralPD' => $countDeferralPD,
                'bloodCollectionPD' => $bloodCollectionPD,
                'totalUnit' => $totalUnit,
                'expiredDate' => $expiredDate,

            ]);

        } catch (ValidationException $e) {

            return response()->json([
                'status'        => 'error',
                'errors'        => $e->validator->errors(),
            ], 422);
        }
    }
}
