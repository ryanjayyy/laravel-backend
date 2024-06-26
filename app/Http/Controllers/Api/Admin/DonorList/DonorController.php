<?php

namespace App\Http\Controllers\Api\Admin\DonorList;

use App\Http\Controllers\Controller;
use App\Models\AuditTrail;
use App\Models\BloodBag;
use App\Models\PatientReceiver;
use App\Models\UserDetail;
use Dompdf\Dompdf;
use Faker\Core\Blood;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;

class DonorController extends Controller
{

    public function donorList(Request $request)
    {
        $bloodType = $request->input('blood_type');
        $donorType = $request->input('donor_type');
        $donorList = UserDetail::join('users', 'user_details.user_id', '=', 'users.user_id')
            ->join('galloners', 'user_details.user_id', '=', 'galloners.user_id')
            ->join('donor_types', 'user_details.donor_types_id', '=', 'donor_types.donor_types_id') // Join the donor_types table
            ->join('region', 'user_details.region', '=', 'region.regCode')
            ->join('province', 'user_details.province', '=', 'province.provCode')
            ->join('municipality', 'user_details.municipality', '=', 'municipality.citymunCode')
            ->join('barangay', 'user_details.barangay', '=', 'barangay.brgyCode')
            ->where('user_details.remarks', 0)
            ->where('user_details.status', 0)
            ->where('galloners.donate_qty', '>', 0)
            ->select('user_details.user_id', 'users.mobile', 'users.email', 'user_details.*', 'galloners.badge', 'galloners.updated_at', 'galloners.donate_qty', 'donor_types.donor_type_desc', 'region.regDesc', 'province.provDesc', 'municipality.citymunDesc', 'barangay.brgyDesc',)
            ->distinct();
        if ($bloodType !== 'All') {
            $donorList->where('user_details.blood_type', $bloodType);
        }
        if ($donorType !== 'All') {
            $donorList->where('donor_types.donor_type_desc', $donorType);
        }

        $donorList = $donorList->orderBy('galloners.updated_at', 'desc')->get();

        // Transform the results to include a list of blood bags for each user
        $donorList->transform(function ($donor) {
            $bloodBags = BloodBag::where('user_id', $donor->user_id)
                ->select('serial_no', 'date_donated')
                ->orderBy('date_donated', 'asc')
                ->get();

            $donor->blood_bags_received = PatientReceiver::join('blood_bags', 'patient_receivers.patient_receivers_id', '=', 'blood_bags.patient_receivers_id')
                ->select('blood_bags.serial_no', 'patient_receivers.created_at')
                ->where('patient_receivers.user_id', '=', $donor->user_id)
                ->orderBy('patient_receivers.created_at', 'asc')
                ->get();

            $donor->blood_bags = $bloodBags;

            // Retrieve the last date_donated
            $lastDonated = $bloodBags->last()->date_donated ?? null;

            $donor->last_donated = $lastDonated;

            return $donor;
        });

        return response()->json([
            'status' => 'success',
            'data' => $donorList
        ]);
    }


    public function searchDonor(Request $request)
    {
        try {
            $request->validate([
                'searchInput' => 'required',
            ]);

            $searchInput = str_replace(' ', '', $request->input('searchInput'));

            $userDetails =  UserDetail::join('users', 'user_details.user_id', '=', 'users.user_id')
                ->join('galloners', 'user_details.user_id', '=', 'galloners.user_id')
                ->join('donor_types', 'user_details.donor_types_id', '=', 'donor_types.donor_types_id') // Join the donor_types table
                ->where('user_details.remarks', 0)
                ->where('user_details.status', 0)
                ->where('galloners.donate_qty', '>', 0)
                ->select('user_details.user_id', 'users.mobile', 'users.email', 'user_details.*', 'galloners.badge', 'galloners.donate_qty', 'galloners.updated_at', 'donor_types.donor_type_desc')
                ->distinct()
                ->orderBy('galloners.updated_at', 'desc')
                ->where(function ($query) use ($searchInput) {
                    $query->where('users.mobile', 'LIKE', '%' . $searchInput . '%')
                        ->orWhere('users.email', 'LIKE', '%' . $searchInput . '%')
                        ->orWhere('user_details.donor_no', 'LIKE', '%' . $searchInput . '%')
                        ->orWhere('user_details.first_name', 'LIKE', '%' . $searchInput . '%')
                        ->orWhere('user_details.middle_name', 'LIKE', '%' . $searchInput . '%')
                        ->orWhere('user_details.last_name', 'LIKE', '%' . $searchInput . '%')
                        ->orWhere('user_details.blood_type', 'LIKE', '%' . $searchInput . '%')
                        ->orWhere('user_details.dob', 'LIKE', '%' . $searchInput . '%')
                        ->orWhere('galloners.badge', 'LIKE', '%' . $searchInput . '%')
                        ->orWhere('galloners.donate_qty', 'LIKE', '%' . $searchInput . '%');
                })
                ->paginate(8);

            // Transform the results to include a list of blood bags for each user
            $userDetails->transform(function ($donor) {
                $bloodBags = BloodBag::where('user_id', $donor->user_id)
                    ->select('serial_no', 'date_donated')
                    ->orderBy('date_donated', 'asc')
                    ->get();

                $donor->blood_bags_received = PatientReceiver::join('blood_bags', 'patient_receivers.patient_receivers_id', '=', 'blood_bags.patient_receivers_id')
                    ->select('blood_bags.serial_no', 'patient_receivers.created_at')
                    ->where('patient_receivers.user_id', '=', $donor->user_id)
                    ->orderBy('patient_receivers.created_at', 'asc')
                    ->get();

                $donor->blood_bags = $bloodBags;

                // Retrieve the last date_donated
                $lastDonated = $bloodBags->last()->date_donated ?? null;

                $donor->last_donated = $lastDonated;

                return $donor;
            });

            if ($userDetails->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No donor found.'
                ], 200);
            } else {
                return response()->json([
                    'status' => 'success',
                    'data' => $userDetails
                ], 200);
            }
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->validator->errors(),
            ], 400);
        }
    }

    public function exportDonorListAsPdf(Request $request)
    {
        $bloodType = $request->input('bloodType');
        $donorType = $request->input('donorType');
        $user = Auth::user();
        $userId = $user->user_id;

        $donorList = UserDetail::join('users', 'user_details.user_id', '=', 'users.user_id')
            ->join('galloners', 'user_details.user_id', '=', 'galloners.user_id')
            ->join('donor_types', 'user_details.donor_types_id', '=', 'donor_types.donor_types_id')
            ->join('region', 'user_details.region', '=', 'region.regCode')
            ->join('province', 'user_details.province', '=', 'province.provCode')
            ->join('municipality', 'user_details.municipality', '=', 'municipality.citymunCode')
            ->join('barangay', 'user_details.barangay', '=', 'barangay.brgyCode')
            ->where('user_details.remarks', 0)
            ->where('user_details.status', 0)
            ->where('galloners.donate_qty', '>', 0);

        if ($bloodType !== 'All') {
            $donorList->where('user_details.blood_type', $bloodType);
        }

        if ($donorType !== 'All') {
            $donorList->where('donor_types.donor_type_desc', $donorType);
        }

        $donorList = $donorList->select('user_details.user_id', 'users.mobile', 'users.email', 'user_details.*', 'galloners.badge', 'galloners.donate_qty', 'galloners.updated_at', 'donor_types.donor_type_desc', 'region.regDesc', 'province.provDesc', 'municipality.citymunDesc', 'barangay.brgyDesc')
            ->distinct()
            ->orderBy('galloners.updated_at', 'desc')
            ->get();

        $donorsPerBarangay = DB::table('user_details')
            ->join('galloners', 'user_details.user_id', '=', 'galloners.user_id')
            ->join('donor_types', 'user_details.donor_types_id', '=', 'donor_types.donor_types_id')
            ->join('region', 'user_details.region', '=', 'region.regCode')
            ->join('province', 'user_details.province', '=', 'province.provCode')
            ->join('municipality', 'user_details.municipality', '=', 'municipality.citymunCode')
            ->join('barangay', 'user_details.barangay', '=', 'barangay.brgyCode')
            ->where('user_details.remarks', 0)
            ->where('user_details.status', 0)
            ->where('galloners.donate_qty', '>', 0)
            ->select(
                'barangay.brgyDesc',
                DB::raw('count(distinct user_details.user_id) as donor_count'),
                DB::raw('sum(case when user_details.sex = "Male" then 1 else 0 end) as male_count'),
                DB::raw('sum(case when user_details.sex = "Female" then 1 else 0 end) as female_count')
            );

        if ($bloodType !== 'All') {
            $donorsPerBarangay->where('user_details.blood_type', '=', $bloodType);
        }

        if ($donorType !== 'All') {
            $donorsPerBarangay->where('donor_types.donor_type_desc', '=', $donorType);
        }

        $donorsPerBarangay = $donorsPerBarangay->groupBy('barangay.brgyDesc')
            ->orderByDesc('donor_count') // Sort by donor count in descending order
            ->get();

        $maleCount = $donorsPerBarangay->sum('male_count');
        $femaleCount = $donorsPerBarangay->sum('female_count');

        $ip = file_get_contents('https://api.ipify.org');
        $ch = curl_init('http://ipwho.is/' . $ip);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);

        $ipwhois = json_decode(curl_exec($ch), true);

        curl_close($ch);

        AuditTrail::create([
            'user_id'    => $userId,
            'module'     => 'Donor List',
            'action'     => 'Export Donor List as PDF',
            'status'     => 'success',
            'ip_address' => $ipwhois['ip'],
            'region'     => $ipwhois['region'],
            'city'       => $ipwhois['city'],
            'postal'     => $ipwhois['postal'],
            'latitude'   => $ipwhois['latitude'],
            'longitude'  => $ipwhois['longitude'],
        ]);

        $totalDonorDetails = $donorList->count();
        $dateNow = new \DateTime();
        $formattedDate = $dateNow->format('F j, Y g:i A');

        $pdf = new Dompdf();
        $html = view('donor-details', ['maleCount' => $maleCount, 'femaleCount' => $femaleCount, 'bloodType' => $bloodType, 'donorType' => $donorType, 'donorDetails' => $donorList, 'donorsPerBarangay' => $donorsPerBarangay, 'totalDonors' => $totalDonorDetails, 'dateNow' => $formattedDate])->render();
        $pdf->loadHtml($html);
        $pdf->render();

        // Return the PDF as a response
        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="donor-list.pdf"');
    }
}
