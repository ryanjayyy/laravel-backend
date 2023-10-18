<?php

namespace App\Http\Controllers\Api\Admin\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\BloodBag;
use App\Models\Deferral;
use App\Models\Setting;
use App\Models\UserDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{

 
    public function getDashboardStock()
    {
        $bloodBags = DB::table('user_details')
            ->leftJoin('blood_bags', 'user_details.user_id', '=', 'blood_bags.user_id')
            ->select('user_details.blood_type', 'blood_bags.serial_no', 'blood_bags.date_donated', 'bled_by')
            ->whereIn('user_details.blood_type', ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'])
            ->where('blood_bags.isStored', '=', 1)
            ->where('blood_bags.isExpired', '=', '0')
            ->where('blood_bags.status', '=', '0')
            ->get();
    
        $bloodTypes = ['A+', 'B+', 'O+', 'AB+', 'A-', 'B-', 'O-', 'AB-'];
    
        // $settings = Setting::where('setting_desc', 'quarter_quota')->first();
        // $quotaPerQuarter = $settings->setting_value;
    
        $result = [];
    
        foreach ($bloodTypes as $bloodType) {
            $bloodBagsCount = $bloodBags->where('blood_type', $bloodType)->count();
    
            $legend = '';
    
            if ($bloodBagsCount <= 0) {
                $legend = 'Empty';
            } elseif ($bloodBagsCount <= 11) {
                $legend = 'Critically low';
            } elseif ($bloodBagsCount <= 19) {
                $legend = 'Low';
            } elseif ($bloodBagsCount <= 99) {
                $legend = 'Normal';
            } else {
                $legend = 'High';
            }
    
            $totalBloodBagsCount = $bloodBags->count();
            $availabilityPercentage = ($bloodBagsCount / 100) * 100;
    
            $result[] = [
                'blood_type' => $bloodType,
                'status' => $bloodBagsCount > 0 ? 'Available' : 'Unavailable',
                'legend' => $legend,
                'count' => $bloodBagsCount,
                'percentage' => $availabilityPercentage,
            ];
        }
    
    
        return response()->json([
            'blood_bags' => $result,
        ]);
    }

    public function getQuota()
    {
        $bloodBags = DB::table('user_details')
            ->leftJoin('blood_bags', 'user_details.user_id', '=', 'blood_bags.user_id')
            ->select('user_details.blood_type', 'blood_bags.serial_no', 'blood_bags.date_donated', 'bled_by')
            ->whereIn('user_details.blood_type', ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'])
            ->where('blood_bags.isStored', '=', 1)
            ->where('blood_bags.isExpired', '=', '0')
            ->where('blood_bags.status', '=', '0')
            ->where('user_details.remarks', '=', '0')
            ->get();
    
        $bloodTypes = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
    
        $settingsPerQuarter = Setting::where('setting_desc', 'quarter_quota')->first();
        $settingsPerMonth = Setting::where('setting_desc', 'monthly_quota')->first();
        $settingsPerWeek = Setting::where('setting_desc', 'weekly_quota')->first();
        $settingsPerDay = Setting::where('setting_desc', 'daily_quota')->first();

        $quotaPerQuarter = $settingsPerQuarter->setting_value;
        $quotaPerMonth = $settingsPerMonth->setting_value;
        $quotaPerWeek = $settingsPerWeek->setting_value;
        $quotaPerDay = $settingsPerDay->setting_value;


        $result = [];
    
        foreach ($bloodTypes as $bloodType) {
            $bloodBagsCount = $bloodBags->where('blood_type', $bloodType)->count();
            $quotaQuarter = $quotaPerQuarter / count($bloodTypes);
            $quotaMonth = $quotaPerMonth / count($bloodTypes);
            $quotaWeek = $quotaPerWeek / count($bloodTypes);
            $quotaDay = $quotaPerDay / count($bloodTypes);
            
            $availabilityPercentageQuarter = ($bloodBagsCount / $quotaQuarter) * 100;
            $availabilityPercentageMonth = ($bloodBagsCount / $quotaMonth) * 100;
            $availabilityPercentageWeek = ($bloodBagsCount / $quotaWeek) * 100;
            $availabilityPercentageDay = ($bloodBagsCount / $quotaDay) * 100;
            
            $bloodBagsQuantity = $bloodBags
                ->where('blood_type', $bloodType)
                ->pluck('serial_no')
                ->count();
            
            $legend = '';
            
            if ($bloodBagsCount <= 0) {
                $legend = 'Empty';
            } else {
                if ($availabilityPercentageQuarter <= 10) {
                    $legend = 'Critically low';
                } elseif ($availabilityPercentageQuarter <= 50) {
                    $legend = 'Low';
                } else {
                    $legend = 'Normal';
                }
            }
            
            $result[] = [
                'blood_type' => $bloodType,
                'status' => $bloodBagsCount > 0 ? 'Available' : 'Unavailable',
                'legend' => $legend,
                'percentage_quarter' => $availabilityPercentageQuarter,
                'percentage_month' => $availabilityPercentageMonth,
                'percentage_week' => $availabilityPercentageWeek,
                'percentage_day' => $availabilityPercentageDay,
                'quantity' => $bloodBagsQuantity,
            ];
        }
    
        return response()->json([
            'status' => 'success',
            'blood_bags' => $result,
        ]);
    }

   public function countBloodBagPerMonth() {
       $currentYear = date('Y');
       $currentMonth = date('n');
       $monthCounts = [];
   
       for ($i = 1; $i <= $currentMonth; $i++) {
           $monthName = date('F', mktime(0, 0, 0, $i, 1));
           $startDate = date('Y-m-d', strtotime($currentYear.'-'.$i.'-01'));
           $endDate = date('Y-m-t', strtotime($currentYear.'-'.$i.'-01'));
           
           $bloodBags = DB::table('user_details')
               ->leftJoin('blood_bags', 'user_details.user_id', '=', 'blood_bags.user_id')
               ->select('user_details.blood_type', 'blood_bags.serial_no', 'blood_bags.date_donated', 'bled_by')
               ->whereIn('user_details.blood_type', ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'])
               ->where('blood_bags.isStored', '=', 1)
               ->where('blood_bags.isExpired', '=', '0')
               ->where('blood_bags.status', '=', '0')
               ->where('user_details.remarks', '=', '0')
               ->whereYear('date_donated', $currentYear)
               ->whereBetween('date_donated', [$startDate, $endDate])
               ->count();
   
           $monthCounts[$monthName] = $bloodBags;
       }
       
      // Retrieve the latest updated_at value
      $latestUpdatedAt = DB::table('user_details')
          ->leftJoin('blood_bags', 'user_details.user_id', '=', 'blood_bags.user_id')
          ->select(DB::raw("IFNULL(DATE_FORMAT(blood_bags.updated_at, '%Y-%m-%d %h:%i:%s %p'), '') AS latest_updated_at"))
          ->whereIn('user_details.blood_type', ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'])
          ->where('blood_bags.isStored', '=', 1)
          ->where('blood_bags.isExpired', '=', '0')
          ->where('blood_bags.status', '=', '0')
          ->where('user_details.remarks', '=', '0')
          ->whereYear('date_donated', $currentYear)
          ->whereBetween('date_donated', [$startDate, $endDate])
          ->orderBy('blood_bags.updated_at', 'desc')
          ->value('latest_updated_at');
      
      return response()->json([
          'status' => 'success',
          'month_counts' => array($monthCounts),
          'latest_date' => $latestUpdatedAt
      ]);
   }

   

    public function countDonorPerBarangay() {
        $donorsPerBarangay = DB::table('user_details')
            ->leftJoin('blood_bags', 'user_details.user_id', '=', 'blood_bags.user_id')
            ->select(
                'user_details.barangay',
                DB::raw('count(distinct user_details.user_id) as donor_count'),
                DB::raw('MAX(blood_bags.created_at) as latest_date_donated')
            )
            ->where('blood_bags.isCollected', '=', 1)
            ->where('user_details.municipality', '=', 'CITY OF VALENZUELA')
            ->groupBy('user_details.barangay')
            ->get();
    
        // Find the maximum date considering AM/PM
        $latestDate = $donorsPerBarangay->max(function ($donor) {
            return strtotime($donor->latest_date_donated);
        });
    
        // Format the maximum date to the desired 12-hour format
        $latestDate = date('Y-m-d h:i A', $latestDate);
    
        // Convert the latest_date_donated field in each result to 12-hour format
        $donorsPerBarangay->transform(function ($donor) {
            $donor->latest_date_donated = date('Y-m-d h:i A', strtotime($donor->latest_date_donated));
            return $donor;
        });
    
        return response()->json([
            'status' => 'success',
            'donors_per_barangay' => $donorsPerBarangay,
            'latest_date' => $latestDate,
        ]);
    }
    
    
    public function mbdQuickView(){
        $data = [];
        $totalDonors = UserDetail::join('users', 'user_details.user_id', '=', 'users.user_id')
        ->join('galloners', 'user_details.user_id', '=', 'galloners.user_id')
        ->where('user_details.remarks', 0)
        ->where('user_details.status', 0)
        ->where('galloners.donate_qty', '>', 0) 
        ->select('users.mobile', 'users.email', 'user_details.*', 'galloners.badge', 'galloners.donate_qty')
        ->count();

        $totalTempDeferral = UserDetail::where('remarks','1')->count();
        $totalPermaDeferral = UserDetail::where('remarks','2')->count();
        $totalDeferral = $totalTempDeferral + $totalPermaDeferral;

        $totalDispensed = BloodBag::where('isUsed','1')->count();
        $totalExpired = BloodBag::where('isExpired','1')->count();

        $data[] = [
            'total_donors' => $totalDonors,
            'total_deferrals' => $totalDeferral,
            'total_dispensed' => $totalDispensed,
            'total_expired' => $totalExpired
        ];
        
        return response()->json([
            'status' => 'success',
            'data'  => $data
        ]);
    }
    
    public function countAllDonors() {
        $now = Carbon::now();

        $deferralsToUpdate = Deferral::where('end_date', '<=', $now)
        ->where('status', '!=', 1)
        ->get();

        foreach ($deferralsToUpdate as $deferral) {
            $deferral->status = 1;
            $deferral->save();

            $user_detail = UserDetail::where('user_id', $deferral->user_id)->first();
            if ($user_detail) {
                $user_detail->remarks = 0;
                $user_detail->save();
            }
        }

        $donors = UserDetail::join('users', 'user_details.user_id', '=', 'users.user_id')
        ->join('galloners', 'user_details.user_id', '=', 'galloners.user_id')
        ->where('user_details.remarks', 0)
        ->where('user_details.status', 0)
        ->where('galloners.donate_qty', '>', 0) 
        ->select('users.mobile', 'users.email', 'user_details.*', 'galloners.badge', 'galloners.donate_qty')
        ->count();
    
        return response()->json([
            'status' => 'success',
            'donorCount' => $donors
        ]);
    }
    
    
    
    
    
    
}
