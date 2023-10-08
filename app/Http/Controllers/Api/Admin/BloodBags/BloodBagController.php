<?php

namespace App\Http\Controllers\Api\Admin\BloodBags;

use App\Http\Controllers\Controller;
use App\Models\BloodBag;
use App\Models\AuditTrail;
use App\Models\Galloner;
use App\Models\User;
use App\Models\UserDetail;
use App\Rules\SerialNumberBloodBagUpdate;
use App\Rules\ValidateDateDonated;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail; 
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use App\Mail\ThankyouForDonationMail; // Import the Mailable class

class BloodBagController extends Controller
{
    
    public function store(Request $request)
    {
        $user = getAuthenticatedUserId();
        $userId = $user->user_id;
            try {
                
                $validatedData = $request->validate([
                    'user_id'       => 'required',
                    'serial_no'     => 'required|numeric|unique:blood_bags',
                    'date_donated'  => ['required', 'date', new ValidateDateDonated],
                    'venue'         => 'required|string',
                    'bled_by'       => 'required|string',
                ],[
                    'serial_no.unique' => 'The serial number is already used.',
                ]);               

                $ch = curl_init('http://ipwho.is/' );
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HEADER, false);
            
                $ipwhois = json_decode(curl_exec($ch), true);
            
                curl_close($ch);

                $EXPIRATION = 37;
                $expirationDate = Carbon::parse($validatedData['date_donated'])->addDays($EXPIRATION);
                $remainingDays = $expirationDate->diffInDays($validatedData['date_donated']);

                $today = Carbon::today();
                if ($expirationDate->lte($today)) {
                    
                    return response()->json([
                        'status' => 'error',
                        'message' => 'The blood is already expired.',
                    ], 400);
                } else {
                    
                    $lastRecord = BloodBag::where('user_id', $validatedData['user_id'])->latest('date_donated')->first();

                    if ($lastRecord) {
                        $lastDonationDate = Carbon::parse($lastRecord->date_donated);
                        $currentDonationDate = Carbon::parse($validatedData['date_donated']);
                        $minDonationInterval = clone $lastDonationDate;
                        $minDonationInterval->addMonths(3);
                    
                        if ($currentDonationDate <= $lastDonationDate) {
                            // AuditTrail::create([
                            //     'user_id'    => $userId,
                            //     'module'     => 'User List',
                            //     'action'     => 'Add Blood Bag | serial no: ' . $validatedData['serial_no'],
                            //     'status'     => 'failed',
                            //     'ip_address' => $ipwhois['ip'],
                            //     'region'     => $ipwhois['region'],
                            //     'city'       => $ipwhois['city'],
                            //     'postal'     => $ipwhois['postal'],
                            //     'latitude'   => $ipwhois['latitude'],
                            //     'longitude'  => $ipwhois['longitude'],
                            // ]);
                    
                            return response()->json([
                                'status'       => 'error',
                                'last_donated' => $lastRecord->date_donated,
                                'message'      => 'The minimum donation interval is 3 months. Please wait until ' . $minDonationInterval->toDateString() . ' before donating again.',
                            ], 400);

                        } elseif ($currentDonationDate < $minDonationInterval) {

                            // AuditTrail::create([
                            //     'user_id'    => $userId,
                            //     'module'     => 'User List',
                            //     'action'     => 'Add Blood Bag | serial no: ' . $validatedData['serial_no'],
                            //     'status'     => 'failed',
                            //     'ip_address' => $ipwhois['ip'],
                            //     'region'     => $ipwhois['region'],
                            //     'city'       => $ipwhois['city'],
                            //     'postal'     => $ipwhois['postal'],
                            //     'latitude'   => $ipwhois['latitude'],
                            //     'longitude'  => $ipwhois['longitude'],
                            // ]);

                            return response()->json([
                                'status'       => 'error',
                                'last_donated' => $lastRecord->date_donated,
                                'message'      => 'The minimum donation interval is 3 months. Please wait until ' . $minDonationInterval->toDateString() . ' before donating again.',
                            ], 400);

                        }else{

                            BloodBag::create([
                                'user_id'      => $validatedData['user_id'],
                                'serial_no'    => $validatedData['serial_no'],
                                'date_donated' => $validatedData['date_donated'],
                                'venue'        => ucwords(strtolower($validatedData['venue'])),
                                'bled_by'      => ucwords(strtolower($validatedData['bled_by'])),
                                'expiration_date' => $expirationDate,
                                'remaining_days'  => $remainingDays,
                                'isCollected'   => 1
                            ]);
                        
                            // AuditTrail::create([
                            //     'user_id'    => $userId,
                            //     'module'     => 'User List',
                            //     'action'     => 'Add Blood Bag | serial no: ' . $validatedData['serial_no'],
                            //     'status'     => 'success',
                            //     'ip_address' => $ipwhois['ip'],
                            //     'region'     => $ipwhois['region'],
                            //     'city'       => $ipwhois['city'],
                            //     'postal'     => $ipwhois['postal'],
                            //     'latitude'   => $ipwhois['latitude'],
                            //     'longitude'  => $ipwhois['longitude'],
                            // ]);

                            $donor = User::where('user_id', $validatedData['user_id'])->first();
                            $donorEmail = $donor->email;

                            Mail::to($donorEmail)->send(new ThankyouForDonationMail($donor));

                            $galloner = Galloner::where('user_id', $validatedData['user_id'])->first();
                            $galloner->donate_qty += 1;
                            $galloner->save();
                            
                            if($galloner->donate_qty == 4){
                                $galloner->badge = 'bronze';
                                $galloner->save();
                            }elseif($galloner->donate_qty == 8){
                                $galloner->badge = 'silver';
                                $galloner->save();
                            }elseif($galloner->donate_qty == 12){
                                $galloner->badge = 'gold';
                                $galloner->save();
                            }


                            return response()->json([
                                'status'  => 'success',
                                'message' => 'Blood bag added successfully',
                            ], 200);
                        }
                        

                    } else {
                      
                        BloodBag::create([
                            'user_id'      => $validatedData['user_id'],
                            'serial_no'    => $validatedData['serial_no'],
                            'date_donated' => $validatedData['date_donated'],
                            'venue'        => ucwords(strtolower($validatedData['venue'])),
                            'bled_by'      => ucwords(strtolower($validatedData['bled_by'])),
                            'expiration_date' => $expirationDate,
                            'remaining_days'  => $remainingDays,
                            'isCollected'   => 1
                        ]);
                    
                        // AuditTrail::create([
                        //     'user_id'    => $userId,
                        //     'module'     => 'User List',
                        //     'action'     => 'Add Blood Bag | serial no: ' . $validatedData['serial_no'],
                        //     'status'     => 'success',
                        //     'ip_address' => $ipwhois['ip'],
                        //     'region'     => $ipwhois['region'],
                        //     'city'       => $ipwhois['city'],
                        //     'postal'     => $ipwhois['postal'],
                        //     'latitude'   => $ipwhois['latitude'],
                        //     'longitude'  => $ipwhois['longitude'],
                        // ]);

                        $donor = User::where('user_id', $validatedData['user_id'])->first();
                        $donorEmail = $donor->email;

                        Mail::to($donorEmail)->send(new ThankyouForDonationMail($donor));
                        
                        $galloner = Galloner::where('user_id', $validatedData['user_id'])->first();
                        $galloner->donate_qty += 1;
                        $galloner->save();
                        
                        if($galloner->donate_qty == 4){
                            $galloner->badge = 'bronze';
                            $galloner->save();
                        }elseif($galloner->donate_qty == 8){
                            $galloner->badge = 'silver';
                            $galloner->save();
                        }elseif($galloner->donate_qty == 12){
                            $galloner->badge = 'gold';
                            $galloner->save();
                        }
                    
                        return response()->json([
                            'status'  => 'success',
                            'message' => 'Blood bag added successfully',
                        ], 200);
                    }

                } //here

                
            } catch (ValidationException $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $e->validator->errors(),
                ], 400);
            }
            
    }
    

   public function collectedBloodBag()
   {
       try {

           $bloodBags = UserDetail::join('blood_bags', 'user_details.user_id', '=', 'blood_bags.user_id')
               ->select('user_details.donor_no', 'user_details.first_name', 'user_details.last_name', 'user_details.blood_type', 'blood_bags.isExpired','blood_bags.blood_bags_id','blood_bags.serial_no', 'blood_bags.date_donated', 'blood_bags.expiration_date', 'blood_bags.created_at', 'bled_by', 'venue')
               ->where('user_details.remarks', '=', 0)
               ->where('blood_bags.status', '=', 0)
               ->where('blood_bags.isStored', '=', 0)
               ->where('blood_bags.isExpired', '=', 0)
               ->orderBy('blood_bags.date_donated', 'asc')
               ->paginate(8);
    
           $EXPIRATION = 37;
           $today = Carbon::today();
   
           // Calculate the countdown for each blood bag
           $currentTime = now(); // Current timestamp
           foreach ($bloodBags as $bloodBag) {
               $expirationDate = Carbon::parse($bloodBag->date_donated)->addDays($EXPIRATION);
               $remainingDays = $expirationDate->diffInDays($bloodBag->date_donated);
   
               if ($expirationDate->lte($today)) {
                   BloodBag::where('blood_bags_id', $bloodBag->blood_bags_id)
                       ->update(['isExpired' => 1]);
               }
   
               $createdAt = $bloodBag->created_at;
               $timeDifference = $createdAt->diffInDays($currentTime); // Calculate the difference in days
               $bloodBag->countdown = max(0, 3 - $timeDifference); // Calculate the countdown (minimum 0)
   
               // Check if the countdown is 0 and add a message
               if ($bloodBag->countdown === 0) {
                   $bloodBag->countdown_message = 'The removal period has ended';
               } else {
                   $bloodBag->countdown_message = 'Blood bag can be removed within ' . $bloodBag->countdown . ' day/s';
               }
            
           }
   
           // Return the paginated results with decrypted serial_no
           return response()->json([
               'status' => 'success',
               'data' => $bloodBags
           ]);
       } catch (\Exception $e) {
           return response()->json([
               'status' => 'error',
               'message' => 'An error occurred while fetching blood bags.',
               'error' => $e->getMessage(),
           ], 500);
       }
   }


    public function searchCollectedBloodBag(Request $request)
    {
        try {
            $request->validate([
                'searchInput' => 'required',
            ]);

            $searchInput = str_replace(' ', '', $request->input('searchInput')); 
            
            $bloodBags = DB::table('user_details')
                ->join('blood_bags', 'user_details.user_id', '=', 'blood_bags.user_id')
                ->where('blood_bags.status', '=', 0) 
                ->where('blood_bags.isStored', '=', 0)
                ->where(function ($query) use ($searchInput) {
                    $query->where('user_details.donor_no', 'LIKE', '%' . $searchInput . '%')
                        ->orWhere('user_details.first_name', 'LIKE', '%' . $searchInput . '%')
                        ->orWhere('user_details.last_name', 'LIKE', '%' . $searchInput . '%')
                        ->orWhere('user_details.blood_type', 'LIKE', '%' . $searchInput . '%')
                        ->orWhere('blood_bags.serial_no', 'LIKE', '%' . $searchInput . '%')
                        ->orWhere('blood_bags.date_donated', 'LIKE', '%' . $searchInput . '%')
                        ->orWhere('blood_bags.expiration_date', 'LIKE', '%' . $searchInput . '%')
                        ->orWhere('blood_bags.venue', 'LIKE', '%' . $searchInput . '%')
                        ->orWhere('blood_bags.bled_by', 'LIKE', '%' . $searchInput . '%');
                })
                ->select('user_details.donor_no','user_details.first_name', 'user_details.last_name', 'user_details.blood_type','blood_bags.serial_no', 'blood_bags.date_donated', 'blood_bags.expiration_date' ,'bled_by','venue')
                ->paginate(8);


            if($bloodBags->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No blood bag found.'
                ], 200);
            }else{
                return response()->json([
                    'status' => 'success',
                    'data' => $bloodBags
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

    public function exportBloodBagAsPdf(){
        $user = getAuthenticatedUserId();
        $userId = $user->user_id;

        $bloodBags = DB::table('user_details')
            ->join('blood_bags', 'user_details.user_id', '=', 'blood_bags.user_id')
            ->select('user_details.donor_no','user_details.first_name', 'user_details.last_name', 'user_details.blood_type','blood_bags.serial_no', 'blood_bags.date_donated', 'blood_bags.expiration_date' ,'bled_by','venue')
            ->where('blood_bags.status', '=', 0) 
            ->get();

            $ch = curl_init('http://ipwho.is/' );
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
        
            $ipwhois = json_decode(curl_exec($ch), true);
        
            curl_close($ch);

            // AuditTrail::create([
            //     'user_id'    => $userId,
            //     'module'     => 'Collected Blood Bags',
            //     'action'     => 'Export Blood Bags as PDF',
            //     'status'     => 'success',
            //     'ip_address' => $ipwhois['ip'],
            //     'region'     => $ipwhois['region'],
            //     'city'       => $ipwhois['city'],
            //     'postal'     => $ipwhois['postal'],
            //     'latitude'   => $ipwhois['latitude'],
            //     'longitude'  => $ipwhois['longitude'],
            // ]);

            $totalBloodBags = $bloodBags->count();
            $dateNow = new \DateTime();
            $formattedDate = $dateNow->format('F j, Y g:i A');

            $pdf = new Dompdf();
            $html = view('blood-bag-details', ['bloodBags' => $bloodBags, 'totalBloodBags' => $totalBloodBags, 'dateNow' => $formattedDate])->render();
            $pdf->loadHtml($html);
            $pdf->render();
    
            // Return the PDF as a response
            return response($pdf->output(), 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="donor-list.pdf"');
    }


    function removeBlood(Request $request){

        $user = getAuthenticatedUserId();
        $userId = $user->user_id;

        try {
            
            $request->validate([
                'serial_no'     => 'required',
            ]);               

            $ch = curl_init('http://ipwho.is/' );
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            $ipwhois = json_decode(curl_exec($ch), true);
            curl_close($ch);

            $serialNumber = $request->input('serial_no');

            $bloodBag = BloodBag::where('serial_no', $serialNumber)->first();

            if (!$bloodBag) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Blood bag not found',
                ], 404);
            }else{

                $user_id = $bloodBag->user_id;  
                $createdAt = $bloodBag->created_at;
                $currentTimestamp = now(); 

                $hoursDifference = $createdAt->diffInHours($currentTimestamp);

                if ($hoursDifference > 72) { // 72 hours = 3 days
                    
                    // AuditTrail::create([
                    //     'user_id'    => $userId,
                    //     'module'     => 'Collected Blood Bags',
                    //     'action'     => 'Remove Blood Bag from Collected | serial no: ' . $serialNumber,
                    //     'status'     => 'Failed',
                    //     'ip_address' => $ipwhois['ip'],
                    //     'region'     => $ipwhois['region'],
                    //     'city'       => $ipwhois['city'],
                    //     'postal'     => $ipwhois['postal'],
                    //     'latitude'   => $ipwhois['latitude'],
                    //     'longitude'  => $ipwhois['longitude'],
                    // ]);
                    
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Blood bag can no longer be removed. The removal period has ended.',
                    ], 400);
                }else{

                    $galloners = Galloner::where('user_id', $user_id)->first();
                    $galloners->donate_qty -= 1;
                    $galloners->save();
                    $bloodBag->delete();

                    // AuditTrail::create([
                    //     'user_id'    => $userId,
                    //     'module'     => 'Collected Blood Bags',
                    //     'action'     => 'Remove Blood Bag from Collected | serial no: ' . $serialNumber,
                    //     'status'     => 'Success',
                    //     'ip_address' => $ipwhois['ip'],
                    //     'region'     => $ipwhois['region'],
                    //     'city'       => $ipwhois['city'],
                    //     'postal'     => $ipwhois['postal'],
                    //     'latitude'   => $ipwhois['latitude'],
                    //     'longitude'  => $ipwhois['longitude'],
                    // ]);

                    return response()->json([
                        'status' => 'success',
                        'message' => 'Blood bag removed successfully',
                    ]);

                }



                // $user_id = $bloodBag->user_id;  
                // $bloodBagDateSave = $bloodBag->created_at;
                
                // $galloners = Galloner::where('user_id', $user_id)->first();
                // $galloners->donate_qty -= 1;
                // $galloners->save();
                // $bloodBag->delete();

                // AuditTrail::create([
                //     'user_id'    => $userId,
                //     'module'     => 'Collected Blood Bag',
                //     'action'     => 'Remove Blood Bag from Collected | serial no: ' . $serialNumber,
                //     'status'     => 'success',
                //     'ip_address' => $ipwhois['ip'],
                //     'region'     => $ipwhois['region'],
                //     'city'       => $ipwhois['city'],
                //     'postal'     => $ipwhois['postal'],
                //     'latitude'   => $ipwhois['latitude'],
                //     'longitude'  => $ipwhois['longitude'],
                // ]);

                // return response()->json([
                //     'status' => 'success',
                //     'message' => 'Blood bag removed successfully',
                // ]);

            }
           
            
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->validator->errors(),
            ], 400);
        }

    }


    function editBloodBag(Request $request){

        $user = getAuthenticatedUserId();
        $userId = $user->user_id;

        try {
            $request->validate([
                'blood_bags_id' => 'required',
                'serial_no'     => ['required', new SerialNumberBloodBagUpdate],
                'bled_by'       => 'required',
                'venue'         => 'required',
                'date_donated'  => 'required'
            ]);

            $bloodBag = BloodBag::where('blood_bags_id', $request->input('blood_bags_id'))->first();
            $bloodBag->serial_no    = $request->input('serial_no');
            $bloodBag->bled_by      = ucwords(strtolower($request->input('bled_by')));
            $bloodBag->venue        = ucwords(strtolower($request->input('venue')));
            $bloodBag->date_donated = $request->input('date_donated');
            $bloodBag->save();
            

            $ch = curl_init('http://ipwho.is/' );
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            $ipwhois = json_decode(curl_exec($ch), true);
            curl_close($ch);

            // AuditTrail::create([
            //     'user_id'    => $userId,
            //     'module'     => 'Collected Blood Bags',
            //     'action'     => 'Edit Blood Bag | blood_bags_id: ' . $request->input('blood_bags_id'),
            //     'status'     => 'Success',
            //     'ip_address' => $ipwhois['ip'],
            //     'region'     => $ipwhois['region'],
            //     'city'       => $ipwhois['city'],
            //     'postal'     => $ipwhois['postal'],
            //     'latitude'   => $ipwhois['latitude'],
            //     'longitude'  => $ipwhois['longitude'],
            // ]);

            return response()->json([
                'status' => 'success',
            ]);
           
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->validator->errors(),
            ], 400);
        }

    }
}

