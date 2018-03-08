<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\User;
use App\Advertisment;
use App\ServiceType;
use App\Service;
use App\Product;
use App\ProductGroup;
use App\Branch;
use App\BranchSchedule;
use App\Config;
use App\ServicePackage;
use App\PlcReviewRequest;
use App\PremierLoyaltyCard;
use App\Transaction;
use App\TransactionItem;
use App\Technician;
use App\TechnicianSchedule;
use DateTime;
use Validator;
use Hash;
use DB;
use Mail;
use JWTAuth;

class MobileApiController extends Controller{

	public function LoadData(Request $request){

		$response             = array();
		$date_today           = date('Y-m-d H:i:s');
        $version_banner       = (double)$request->segment(4);
        $version_commercial   = (double)$request->segment(5);
        $version_services     = (double)$request->segment(6);
        $version_packages     = (double)$request->segment(7);
        $version_products     = (double)$request->segment(8);
        $version_branches     = (double)$request->segment(9);

        $arrayService         = array();
        $arrayProduct         = array();
        $arrayPackage         = array();
        $arrayBanner          = array();
        $arrayCommercial      = array();
        $arrayBranch            = array();


        $api = $this->authenticateAPI();
        if($api['result'] === 'success'){
            $ifValidToken = false;
        }

        if( (double)$this->getBannerVersion() > (double)$version_banner) {

            $version_banner = (double)$this->getBannerVersion();
            $arrayBanner    = Config::where("config_name","=","APP_BANNER")
                                ->orderBy('created_at')
                                ->select('config_value')
                                ->get()
                                ->first();
            $arrayBanner   = json_decode($arrayBanner['config_value'],true);
        }
        if( (double)$this->getDataVersions("APP_BRANCH_VERSION") > (double)$version_branches) {
           
            $version_branches = (double)$this->getDataVersions("APP_BRANCH_VERSION");
            $arrayBranch = Branch::leftJoin('branch_clusters','branches.cluster_id','=','branch_clusters.id')
                ->where('branches.is_active', 1)
                ->select('branches.id as id','branch_name','rooms_count','cluster_data','branch_address','branch_data','services','products', 'branch_contact','map_coordinates','branch_pictures','branch_email','branch_contact','branch_contact_person','rooms_count','payment_methods','welcome_message')
                ->orderBy('branch_name', 'asc')
                ->get()->toArray();

            foreach($arrayBranch as $key => $value){

                $arrayBranch[$key]['cluster_data']     = json_decode($value['cluster_data']);
                $arrayBranch[$key]['services']         = json_decode($value['services']);
                $arrayBranch[$key]['products']         = json_decode($value['products']);
                $arrayBranch[$key]['branch_data']      = json_decode($value['branch_data']);
                $arrayBranch[$key]['map_coordinates']  = json_decode($value['map_coordinates']);
                $query_schedule                        = BranchSchedule::where('branch_id', $value['id'])
                                                            ->select('date_start','date_end','schedule_data','schedule_type')
                                                            ->orderBy('schedule_type')
                                                            ->get()->toArray();
                $array_sched = array();                                    
                foreach($query_schedule as $k=>$v){
                    
                    $schedule_type  = $query_schedule[$k]['schedule_type'];
                    $date_start     = $query_schedule[$k]['date_start'];
                    $date_end       = $query_schedule[$k]['date_end'];
                    if($schedule_type == "regular"){
                        $query_schedule[$k]['schedule_data'] = json_decode($v['schedule_data']);
                        $array_sched[] = $query_schedule[$k];
                    }
                    else{
                         if(strtotime(date($date_today)) >= strtotime(date($date_start)) && strtotime(date($date_today)) <= strtotime(date($date_end))){
                            $query_schedule[$k]['schedule_data'] = json_decode($v['schedule_data']);
                            $array_sched[] = $query_schedule[$k];
                        }
                    }
                }
                $arrayBranch[$key]['schedules']  = $array_sched;
            }
        }
        if( (double)$this->getDataVersions("APP_SERVICE_VERSION") > (double)$version_services) {
    
            $version_services   = (double)$this->getDataVersions("APP_SERVICE_VERSION");
            $arrayService       = Service::leftJoin('service_types','services.service_type_id','=','service_types.id')
                                    ->leftJoin('service_packages','services.service_package_id','=','service_packages.id')
                                    ->select('services.*','service_name','package_name','service_description','service_picture','service_type_data')
                                    ->where('services.is_active', 1)
                                    ->where('services.service_type_id',"<>",0);
            $arrayService       = $arrayService->get()->toArray();
            foreach($arrayService as $key => $value){

                $service_type_id                            = $value["service_type_id"];
                $service_type_data                          = json_decode($value["service_type_data"]);
                if($value['service_type_id'] === 0){
                    $package_services = ServicePackage::find($value['service_package_id'])['package_services'];
                    $services[$key]['service_picture']              = ServiceType::whereIn('id', json_decode($package_services))->pluck('service_picture')->toArray();
                    $services[$key]['service_description']          = ServiceType::whereIn('id', json_decode($package_services))->pluck('service_name')->toArray();
                }
            }
        }
        if((double)$this->getDataVersions("APP_PACKAGE_VERSION") > (double)$version_packages) {
        
            //$arrayPackage[$key]['id'];
            $version_packages   = (double)$this->getDataVersions("APP_PACKAGE_VERSION");
            $arrayPackage       = Service::where('service_type_id','=',"0")
                                        ->where('service_package_id','>',"0")
                                        ->select('service_minutes','service_price','id','service_package_id','service_gender')
                                        ->get()
                                        ->toArray();
            foreach ($arrayPackage as $key => $value){

                $package_id     = $value["service_package_id"];
                $service_gender = $value["service_gender"];
                $packageQuery   = ServicePackage::where('is_active', 1)
                                    ->where("id",$package_id)
                                    ->select('package_name','package_services','package_image')
                                    ->get()->first();
                $arrayPackage[$key]["package_name"]     = $packageQuery['package_name']."(".ucfirst($service_gender).")";                   
                $arrayPackage[$key]['package_desc']     = "Package is composed of the following services: \n".implode(', ',ServiceType::whereIn('id', json_decode($packageQuery['package_services']))->pluck('service_name')->toArray());
                $arrayPackage[$key]['package_image']    = $packageQuery['package_image'];
                $arrayPackage[$key]['package_duration'] = $value['service_minutes'];
                $arrayPackage[$key]['package_price']    = $value['service_price'];
                $arrayPackage[$key]['package_gender']   = $value['service_gender'];
                $arrayPackage[$key]['package_services'] = json_decode($packageQuery['package_services']);

            } 
        }
        if( (double)$this->getDataVersions("APP_PRODUCT_VERSION")  > (double)$version_products) {
            
            $version_products   = (double)$this->getDataVersions("APP_PRODUCT_VERSION");
            $arrayProduct       =  DB::table('products')
                    ->leftJoin('product_groups','products.product_group_id','=','product_groups.id')
                    ->select('products.*','product_group_name','product_picture', 'product_description')
                    ->where('products.is_active', 1)
                    ->where('products.product_price', '>',"0")
                    ->get()
                    ->toArray();
        }
        if( (double)$this->getDataVersions("APP_COMMERCIAL_VERSION")  > (double)$version_commercial) {
            $version_commercial   = (double)$this->getDataVersions("APP_COMMERCIAL_VERSION");
        }
     
        $response['arrayBanner']             = $arrayBanner;
        $response['arrayServices']           = $arrayService;
        $response['arrayPackage']            = $arrayPackage;
        $response['arrayProducts']           = $arrayProduct;
        $response['arrayBranch']             = $arrayBranch;
        $response['arrayCommercial']         = $arrayCommercial;
        $response['versions']                = array(
                            "version_banner"        => $version_banner,
                            "version_branches"      => $version_branches,
                            "version_commercial"    => $version_commercial,
                            "version_services"      => $version_services,
                            "version_packages"      => $version_packages,
                            "version_products"      => $version_products,
                                                );
        return response()->json($response);
	}

    public function getAppVersion(Request $request){

        $api                        = $this->authenticateAPI();
        $isValidToken               = false;
        $app_version                = (double)$request->segment(3);
        $currentAppVersion          = $request->segment(4);
        $deviceType                 = $request->segment(6);
        $deviceName                 = $request->segment(7);
        $user_token                 = $_GET["token"];
        $response                   = array();
        $response['arrayProfile']   = array();
        $ifUpdated                  = false;
        if($api['result'] === 'success'){
            $client_id      = $api['user']['id'];
            $isValidToken   = true;
            $user           = User::find($client_id);
            $tokenResults   = json_decode($user->device_data, true);

            for($x = 0; $x < count($tokenResults); $x++){
                $fetchToken     = $tokenResults[$x]["token"];
                $dateRegistered = $tokenResults[$x]["registered"];
                if($fetchToken == $user_token){
                    $tokenResults[$x]["token"]           = $user_token;
                    $tokenResults[$x]["type"]            = $deviceType;
                    $tokenResults[$x]["last_activity"]   = date('Y-m-d H:i');
                    $tokenResults[$x]["device_info"]     = $deviceName;
                    $tokenResults[$x]["registered"]      = $dateRegistered;
                    $user->device_data               = json_encode($tokenResults);
                    $user->save();
                    break;
                }
                else{
                    continue;
                }
            }
            $response['arrayProfile']   = $api['user'];
        }

        $array           = Config::where("config_name","=","APP_ANDROID_VERSION")
                            ->orderBy('created_at')
                            ->select('config_value as version')
                            ->get()
                            ->first();
        $version                    = (double)$array['version']; 
        if($version > $app_version){
            $ifUpdated = true;
        }  
        $response['ifUpdated']      = $ifUpdated;
        $response['isValidToken']   = $isValidToken;
        return response()->json($response);
    }

	public function getUser(){
        
        $api = $this->authenticateAPI();
        $response = array();

        if($api['result'] === 'success'){
            $user_data = json_decode($api['user']['user_data'],true);
            if($api['user']['is_client'] == 1){
                $branch = Branch::find($user_data['home_branch']);
                if(isset($branch->id)){
                    $branch = $branch->branch_name;
                }
                else{
                    $branch = 'N/A';
                }
                $api['user']['branch'] = ["value"=>$user_data['home_branch'], "label"=> $branch];
            }
            else{
            	//respond a non client 
            	return response()->json(["result"=>"failed","error"=>"You are not allowed to use the Mobile app"],400); 
            }
           
          	$rowUsers = $api['user'];
          	$bday     = new DateTime($rowUsers->birth_date);
            $email 	  = $rowUsers->email;
       		
       		$total_discount  	= 5550;
            // file_get_contents("http://boss.lay-bare.com/laybare-online/client_discounts.php?email=".$email);	
			$total_transaction 	= 230;
            // file_get_contents("http://boss.lay-bare.com/laybare-online/new_trans.php?email=".$email);
			// $total_discount		= "190";
			// $total_transaction  = "5520";
            $response = $api['user'];
            return response()->json($response,$api["status_code"]);
        }
        return response()->json($api, $api["status_code"]);
    }

    public function updateHomeBranch(Request $request){
    	$api = $this->authenticateAPI();
        if($api['result'] === 'success'){
           $validator = Validator::make($request->all(), [
                'branch' => 'required_if:is_client,1',
            ]);
            if ($validator->fails()) {
                return response()->json(['result'=>'failed','error'=>$validator->errors()->all()], 400);
            }

            $clientID  = $request->input('edit_client_id');
            $branch_id = $request->input('edit_home_branch_id');	

            $client 				= User::find($clientID);
            $data 					= json_decode($client->user_data);
            $data->home_branch 		= $branch_id;
            $client->user_data 		= json_encode($data);
            $client->save();
            return response()->json(["result"=>"success"]);
        }
        return response()->json($api, $api["status_code"]);
    }

    public function updatePersonalInfo(Request $request){
    	$api = $this->authenticateAPI();

        if($api['result'] === 'success'){
           $validator = Validator::make($request->all(), [
                'edit_fname'    => 'required|max:255',
                'edit_mname'    => 'required|max:255',
                'edit_lname'    => 'required|max:255',
                'edit_contact'  => 'required|max:255',
                'edit_address'  => 'required|max:255',
                'edit_bday'     => 'required|date',
                'edit_gender'   => 'required|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json(['result'=>'failed','error'=>$validator->errors()->all()], 400);
            }
            
			$clientID     = $request->input('edit_client_id');
            $first_name   = $request->input('edit_fname');
            $middle_name  = $request->input('edit_mname');
            $last_name    = $request->input('edit_lname');
            $address   	  = $request->input('edit_address');
            $mobile       = $request->input('edit_contact');
            $bday   	  = new DateTime($request->input('edit_bday'));
            $birth_date   = $bday->format("Y-m-d H:i:s");
            $gender       = $request->input('edit_gender');

            $client 				= User::find($clientID);
            $client->first_name 	= $first_name;
            $client->middle_name 	= $middle_name;
            $client->last_name 		= $last_name;
            $client->user_address 	= $address;
            $client->user_mobile 	= $mobile;
            $client->birth_date 	= $birth_date;
            $client->gender 	    = $gender;
            $client->save();	

            return response()->json(["result"=>"success"]);
        }
        return response()->json($api, $api["status_code"]);
    }

 	public function updateAccount(Request $request){
    	$api = $this->authenticateAPI();

        if($api['result'] === 'success'){
        	
        	$clientID     		= $request->input('edit_client_id');
           	$email        		= $request->input('edit_email');
           	$old_password 		= $request->input('edit_old_password');
           	$new_password 		= $request->input('edit_new_password');
           	$confirm_password 	= $request->input('edit_confirm_password');
           	
        	if(!Hash::check($old_password, $api['user']['password'] ))
                return response()->json(["result"=>"failed","error"=>"Your old password is incorrect."], 400);

            $rule 	= [
	            		// required and has to match the password field
		            	'edit_email'    		 => 'required|max:255',
		                'edit_new_password'      => 'required|regex:/^.*(?=.*[a-zA-Z])(?=.*[0-9]).*$/|min:10',
            		];
            $message = [
            			'edit_email.required'	 		=> 'Please provide your Email address', 
            			'edit_new_password.required'	=> 'Please provide your new password', 
            			'edit_new_password.regex'		=> 'Your password must be atleast 10 alphanumeric.',
            		];

            $validator = Validator::make($request->all(),$rule,$message);
            if ($validator->fails()) {
                return response()->json(['result'=>'failed','error'=>$validator->errors()->all()], 400);
            }

            $client 				= User::find($clientID);
            $client->password       = bcrypt($new_password);
            $client->save();	
        
            return response()->json(["result"=>"success"]);
        }
        return response()->json($api, $api["status_code"]);
    }

    public function uploadUserImage(Request $request){

    	$api = $this->authenticateAPI();
        if($api['result'] === 'success') {

            if($request->input('upload_image') === null)
                return response()->json(["result"=>"failed","error"=>"Warning! No File to be uploaded."], 400);

            $clientID   		= $request->input('upload_client_id');
            $image 				= $request->input('upload_image');

            if($image === null)
                return response()->json(["result"=>"failed","error"=>"No ID to be uploaded."], 400);

            
            list($type, $image) = explode(';',$image);
            list(,$image)       = explode(',', $image);

            if($type == 'data:image/jpeg')
                $ext = 'jpg';
            elseif($type == 'data:image/png')
                $ext = 'png';
            else
                return response()->json(["result"=>"failed","error"=>"Invalid File Format."],400);

            $filename = $clientID . '_' . time().".".$ext;
            $image = base64_decode($image);
            file_put_contents(public_path('images/users/'). $filename, $image );
           
            $user = User::find($clientID);
            if($user->user_picture != 'no photo ' . $user->gender .'.jpg')
                if(file_exists(public_path('/images/users/'.$user->user_picture)))
                    unlink(public_path('/images/users/'.$user->user_picture));

            $user->user_picture = $filename;
            $user->save();

            return response()->json(["result"=>"success","imageDirectory" => $filename],200);
    	}
        return response()->json(["result"=>"failed","error"=>"No "], 400);
	}

	public function registerUser(Request $request){

	 	$rule 	= [
            		// required and has to match the password field
	            	'addLname'          => 'required|max:255',
	            	'addFname'          => 'required|max:255',
		            'addMobile'         => 'required|max:255',
		            'addAddress'        => 'required|max:255',
		            'addEmail'          => 'required|email|unique:users,email',
		            'addGender'         => 'required',
		            'addBranch'         => 'required|not_in:0',
		            'addPassword'       => 'required|regex:/^.*(?=.*[a-zA-Z])(?=.*[0-9]).*$/',
		            'addBday'        	=> 'required'
        		];
        $message = [
        			'addFname.required'				=> 'First name.', 
        			'addLname.required'	 			=> 'Last name', 
        			'addMobile.required'			=> 'Contact no.', 
        			'addAddress.required'			=> 'Complete address.', 
        			'addEmail.required'				=> 'Email address.', 
        			'addEmail.email'				=> 'Email is not valid', 
        			'addEmail.unique'				=> 'Email address is already taken', 
        			'addGender.required'			=> 'Gender.', 
        			'addBranch.required'			=> 'Home Branch.', 
        			'addPassword.regex'			    => 'Password must be atleast 10 alphanumeric.', 
        			'addBday.required'			    => 'Birth date'
        		];

        $validator = Validator::make($request->all(),$rule,$message);
        if ($validator->fails())
            return response()->json(['result'=>'failed','error'=>$validator->errors()->all()], 400);

        $bday 			    = new DateTime($request->input('addBday'));
        $birth_date         = $bday->format("Y-m-d H:i:s");
        $branch 		    = $request->input('addBranch');
        
        $gender 		    = lcfirst($request->input('addGender'));
        $device             = $request->input('addDevice');
        $device_name        = $request->input('addDeviceName');
        $facebook_id        = $request->input('addFBID');

        $user 			    = new User;
        $user->first_name 	= $request->input('addFname');
        $user->middle_name 	= $request->input('addMname');
        $user->last_name 	= $request->input('addLname');
        $user->username     = $request->input('addFname').' '.$request->input('addLname');
        $user->user_mobile 	= "0".$request->input('addMobile');
        $user->username 	= $request->input('addFname')." ".$request->input('addLname');
        $user->email 		= $request->input('addEmail');
        $user->password 	= bcrypt($request->input('addPassword'));
        $user->gender 		= $gender;
        $user->birth_date 	= $birth_date;
        $user->user_address = $request->input('addAddress');
        $user->level 		= 0;
        $user->is_client 	= 1;
        $user->is_active 	= 0;
        $user->is_confirmed = 0;
        $user->is_agreed 	= 1;
        $user->user_picture = "";
        if($facebook_id == "" || $facebook_id == null){
            $user->user_data    = json_encode(array(
                                        "home_branch"    =>  $branch,
                                        "premier_status" => 0
                                        ));
            $user->device_data  = '[]';
            $user->save();
            return response()->json([
                                "result"        =>"success", 
                                "isFacebook"    =>  false
                            ]);
        }
        else{

            $user->device_data  = '[]';
            $user->user_data    = json_encode(array(
                                        "home_branch"    => $branch,
                                        "premier_status" => 0,
                                        "facebook_id"    => $facebook_id,
                                        ));

            $user->save();
            $clientID = $user->id;
            $filename = $clientID.'_'.time().'.jpg';
            $data     = file_get_contents('https://graph.facebook.com/'.$facebook_id.'/picture?type=large');
            file_put_contents(public_path('images/users/').$filename, $data);

            $token    = JWTAuth::fromUser($user);
            $this->registerToken($clientID, $token,$device,$device_name);
            
            $user     = User::find($clientID);
            $user->user_picture = $filename;   
            $user->save();

            $array_response         = array( 
                            "result"        =>  "success",
                            "isFacebook"    =>  true,
                            "image"         =>  $filename,
                            "token"         =>  $token,
                            "client_id"     =>  $clientID,
                            "client_data"   =>  $user->toArray()
                                );
            
            
            return response()->json($array_response);
        }
    }

    public function verifyMyPassword(Request $request){

        $rule  = [
                    // required and has to match the password field
                    'verify_email'      => 'required|max:255',
                    'verify_birth_date' => 'required|max:255'
                ];
        $message = [
                    'verify_email.required'             => 'Email is empty', 
                    'verify_birth_date.required'        => 'Birth date is empty'
                ];

        $validator = Validator::make($request->all(),$rule,$message);
        if ($validator->fails()) {
            return response()->json(['result'=>'failed','error'=>$validator->errors()->all()], 400);
        }

        $email      = $request->input('verify_email');
        $bday       = new DateTime($request->input('verify_birth_date'));
        $birth_date = $bday->format("Y-m-d H:i:s");
        $user       = User::where('email', $email)
                            ->where('birth_date', 'LIKE',$birth_date.'%')
                            ->get()->first();
        if(!isset($user['id'])) {
            if($result = $this->selfMigrateClient($email,null, $birth_date) ){
                $user = User::where('id', $result['id'])->get()->first();
            }
        }
        if(isset($user['id'])) {
            $generated = md5(rand(1,600));
            $user_data = json_decode($user['user_data'],true);
            $user_data['reset_password_key'] = $generated;
            $user_data['reset_password_expiration'] = time() + 300;
            User::where('id', $user['id'])
                        ->update(['user_data'=> json_encode($user_data)]);

            Mail::send('email.reset_password', ["user"=>$user, "generated"=>$generated], function ($message) use($user) {
                $message->from('notification@system.lay-bare.com', 'Lay Bare Online - Mobile Application');
                $message->subject('Password Reset');
                $message->to($user['email'], $user['first_name']);
            });
            return response()->json(["result"=>"success"]);
        }
        return response()->json([
                            "result" => "failed",
                            "error"  => "No email address or birthdate exist"

                        ],400);
    }

    public function FacebookLogin(Request $request){
        

        $rule  = [
                    'fb_email'      => 'required|max:255'
                ];
        $message = [
                    'fb_email.required'             => 'Email must not empty.'
                ];

        $validator = Validator::make($request->all(),$rule,$message);

        if ($validator->fails()) {
            return response()->json(['result'=>'failed','error'=>$validator->errors()->all()], 400);
        }

        $facebook_id        = $request->input("fb_id");
        $email              = $request->input("fb_email");
        $bday               = $request->input("fb_bday");
        $fname              = $request->input("fb_fname");
        $lname              = $request->input("fb_lname");
        $gender             = $request->input("fb_gender");
        $image              = $request->input("fb_image");
        $device             = $request->input("device");
        $device_name        = $request->input("device_info");
        $branch_id          = "";  
        $branch_name        = ""; 

        $user_fb_login_query = User::where('user_data','LIKE', '%"facebook_id":"'.$facebook_id.'"%')
                                    ->where('email','=',$email)
                                    ->get()
                                    ->first();
        if($email != ""){
            $user_fb_login_query  = User::where('email','=',$email)
                                  ->get()
                                  ->first();
        }
        if(count($user_fb_login_query)){                        
                          
            $clientID = $user_fb_login_query['id']; 
            $token                  = JWTAuth::fromUser($user_fb_login_query);
            $this->registerToken($clientID, $token,$device,$device_name);
            $client                 = User::find($clientID);
            $data                   = json_decode($client->user_data,true);
            $data['facebook_id']    = $facebook_id;
            $branch_id              = $data['home_branch'];
            $client->user_data      = json_encode($data);
            $client->last_activity  = date('Y-m-d H:i:s');
            $client->last_login     = date('Y-m-d H:i:s');
            $client->is_confirmed   = 1;
            $client->save();
            $query_branch           = DB::table('branches')
                                        ->where('id','=',$branch_id)
                                        ->get()  
                                        ->first();                                     
            $branch_name    = $query_branch->branch_name;
        
             return response()->json([
                        "result"        =>"success",
                        "isAlready"     => true,
                        "token"         => $token,
                        "objResult"     => $user_fb_login_query
                    ]);
        }
        else{
            return response()->json([
                            "result"    =>" success",
                            "isAlready" => false, 
                            "objResult" => $user_fb_login_query,
                            "error"     => "Email not found. Redirecting.."]);
        }
        return response()->json(['result'=>'failed', "error" => "Cannot proceed to login to facebook"],400);
    }

    //update terms and conditions applied
    public function updateTerms(Request $request){
        $api      = $this->authenticateAPI();
        

        if($api['result'] === 'success'){
            $clientID = $request->input('client_id');
            $query                   = User::find($clientID);
            $query->is_agreed        = "1";
            $query->save();
            return response()->json(['result'=>'success',"clientID" => $clientID]);
        }
        else{
             return response()->json($api, $api["status_code"]);
        }
    }

    public function getPackageWithDescription(Request $request){

       if($request->segment(4)=='active')
            $data = ServicePackage::where('is_active', 1)->get()->toArray();
        else{
            $data = ServicePackage::get()->toArray();
        }
        foreach ($data as $key=>$value){
            $query  = Service::where('service_package_id','=',$data[$key]['id'])
                            ->select('service_minutes','service_price','id as service_id')
                            ->get()
                            ->first();

            $data[$key]['service_desc'] = implode(', ',ServiceType::whereIn('id', json_decode($value['package_services']))->pluck('service_name')->toArray());
            $data[$key]['service_duration'] = $query['service_minutes'];
            $data[$key]['service_price']    = $query['service_price'];
            $data[$key]['service_id']       = $query['service_id'];
        } 
        return response()->json($data);
    }

    public function getServices(Request $request){
        
        if($request->segment(4)!= ""){
            $gender = $request->segment(4);

            $query  =  DB::table('services as a')
                        ->leftJoin('service_types as b','a.service_type_id','=','b.id')
                        ->where('a.service_type_id', '<>',0)
                        ->where('a.is_active', 1)
                        ->where('a.service_gender', $gender)
                        ->select('a.id','a.service_gender','a.service_minutes','a.service_price','b.service_name','b.service_description','b.service_picture','b.id as service_type_id')
                        ->get();
        }
        else{
              $query  =  DB::table('services as a')
                        ->leftJoin('service_types as b','a.service_type_id','=','b.id')
                        ->where('a.service_type_id', '<>',0)
                        ->where('a.is_active', 1)
                        ->select('a.id','a.service_gender','a.service_minutes','a.service_price','b.service_name','b.service_description','b.service_picture','b.id as service_type_id')
                        ->get();
        }
        return response()->json($query);
    }

    public function getPLCAllLogs(Request $request){
        
        $array_response = array();
        $api            = $this->authenticateAPI();
        if($api['result'] === 'success'){
            //request Logs
            $client_id = $api['user']['id'];
            $data = PlcReviewRequest::where('client_id', $api['user']['id'])->get()->toArray();
            foreach($data as $key=>$value){
                $client = User::find($value['client_id']);
                $data[$key]['name'] = ($client->id?$client->username:'');
                $data[$key]['status_html'] = '<span class="badge '.($value['status']=='pending'?'badge-info':'badge-success').'">'. $value['status'] .'</span>';
                $user = User::find($value['updated_by_id']);
                $data[$key]['updated_by'] =  (isset($user->id)?$user->username:'');
                $data[$key]['processed_date_formatted'] =  isset($value['processed_date'])?date('m/d/Y',strtotime($value['processed_date'])):'';
            }

            $premiers = PremierLoyaltyCard::where('client_id','=', $client_id);
            $premiers = $premiers->get()->toArray();
            foreach($premiers as $key=>$value){
                $branch = Branch::find($value['branch_id']);
                $branch_name = isset($branch->id)?$branch->branch_name:'N/A';
                $premiers[$key]['branch_name']  = $branch_name;
                $premiers[$key]['client']  = User::where('id', $value['client_id'])
                                                    ->select('birth_date', 'user_mobile', 'first_name', 'last_name', 'middle_name', 'username',
                                                                'email', 'gender','user_address')->get()->first();
                $premiers[$key]['plc_data']     = json_decode($value['plc_data']);
                $premiers[$key]['date_applied'] = date('m/d/Y', strtotime($value['created_at']));
            }
            
            $array_plc_request_logs['request_logs']     = $data;
            $array_plc_request_logs['application_logs'] = $premiers;
            return response()->json($array_plc_request_logs);


        }
        return response()->json($api, $api["status_code"]);
    }

    public function getTotalTransactionAmount(Request $request){

        $object_response = array();
        $api             = $this->authenticateAPI();
        $premiers[] = array();
        if($api['result'] === 'success'){

            $client_id      = $api['user']['id'];
            $email          = $api['user']['email'];
            $transactions   = file_get_contents("https://boss.lay-bare.com/laybare-online/client-total.php?email=".$email);
            $minimum = Config::where('config_name', 'PLC_MINIMUM_TRANSACTIONS_AMOUNT')->get()->first();
            if(isset($minimum['id'])){
                $minimum = $minimum->config_value;
            }

            $premiers = PremierLoyaltyCard::where('client_id','=', $client_id)
                        ->select("id","reference_no","status","remarks","application_type")
                        ->orderBy('created_at', 'DESC')->get()->first();

            $object_result                      = json_decode($transactions,true);
            $object_result["minimum_amount"]    = (double)$minimum;
            if(count($premiers) > 0){
                $object_result["premier"]           = $premiers;
            }
            return response()->json($object_result);
        }
        return response()->json($api, $api["status_code"]);
    }


    //get Appointments & User Events(ex: Summer Vacation remind 3 days before the event)
    public function getAppointmentsByMonth(Request $request){

        $start_date     = $request->input('start_date');
        $end_date       = $request->input('end_date');
        $api            = $this->authenticateAPI();
        $items_array    = array();
        if($api['result'] === 'success') {
            $client_id   = $api['user']['id'];
            $appointments = Transaction::where('client_id', $client_id)
                                        ->whereBetween('transaction_datetime', array($start_date, $end_date))->get();
             foreach($appointments as $key => $value){
                $branch 								= Branch::find($value['branch_id']);
                $client 								= User::find($value['client_id']);
                $technician 							= Technician::find($value['technician_id']);
                $appointments[$key]['branch_name'] 		= isset($branch)?$branch->branch_name:'N/A';
                $appointments[$key]['client_name'] 		= $client->username;
                $appointments[$key]['client_contact'] 	= $client->user_mobile;
                $appointments[$key]['client_gender'] 	= $client->gender;
                $appointments[$key]['technician_name'] 	= isset($technician)?$technician->first_name .' '. $technician->last_name :'N/A';
                $appointments[$key]['items'] 			= $this->getAppointmentItems($value['id']);
                $appointments[$key]['transaction_date_formatted'] = date('m/d/Y', strtotime($value['transaction_datetime']));
                $appointments[$key]['transaction_time_formatted'] = date('h:i A', strtotime($value['transaction_datetime']));
                $appointments[$key]['transaction_added_formatted']= date('m/d/Y h:i A', strtotime($value['created_at']));
                $appointments[$key]['transaction_data'] = json_decode($value['transaction_data']);
                // $appointments[$key]['status_formatted'] = $this->formatStatus($value['transaction_status']);
                $appointments[$key]['waiver_data'] = null;
            }
            return response()->json($appointments);
        }
        else{
            return response()->json($api, $api["status_code"]);
        }
    }

    public function getBranchSchedules(Request $request){

        $branch_id              = $request->segment(4);
        $app_reserved           = $request->segment(5);
        $response               = array();
        $arrayBranch            = array();
        $arrayTransaction       = array();
        $queryBranchSchedule    = BranchSchedule::where("branch_id",$branch_id)
                                ->orderBy('created_at','desc')->get()->toArray();                            
        $technicians         = $this->getScheduledTechnicians($branch_id, $app_reserved);
         foreach ($queryBranchSchedule as $key => $value) {
            $date_start     = $value["date_start"];
            $date_end       = $value["date_end"];
            $schedule_type  = $value["schedule_type"];  
            if($schedule_type == "regular"){
                $arrayBranch[] = $value;
            }
            if(strtotime($app_reserved) >= strtotime($date_start) && strtotime($app_reserved) <= strtotime($date_start) && $schedule_type != "regular"){
                $arrayBranch[] = $value;
            }
        }   
        $arrayQueuing        = Transaction::where("transaction_datetime","LIKE",$app_reserved.'%')
                                ->where("transaction_status","reserved")
                                ->where("branch_id",$branch_id)
                                ->select("transaction_datetime","id","technician_id")
                                ->get()->toArray();
        foreach ($arrayQueuing as $key => $value) {
            $transaction_id         = $value["id"]; 
            $transaction_datetime   = $value["transaction_datetime"];
            $technician_id          = $value["technician_id"];
            $queryItems             = TransactionItem::leftJoin("services","transaction_items.item_id","=","services.id")
                                        ->where("transaction_items.transaction_id",$transaction_id)
                                        ->where("transaction_items.item_status","reserved")
                                        ->select("services.service_minutes")
                                        ->get()->toArray();
            $duration = 0;                    
            foreach ($queryItems as $key1 => $value1) {
                $duration+=$value1["service_minutes"];
            }
            $arrayTransaction[] = array(
                                "transaction_datetime" => $transaction_datetime,
                                "duration"             => $duration,
                                "technician_id"        => $technician_id
                                    );
        }

        $response["branch"]         =   $arrayBranch;
        $response["technician"]     =   $technicians;
        $response["transactions"]   =   $arrayTransaction;
        return response()->json($response);
        // if($queryBranchSchedule){

        // }
        // else{
        //     return response()->json(["result"=>"failed","error"=>"Failed to load"],400);
        // }
    }


    //get Technician Schedule    
    function getScheduledTechnicians($branch, $date){
        $technicians = array();

        $find = TechnicianSchedule::where('branch_id', $branch)
                                    ->where('date_start','<=', $date)
                                    ->where('date_end','>=', $date .' 23:59:59')
                                    ->get()->toArray();

        foreach($find as $key=>$value){
            if($e = $this->compareExtract($technicians, $value, idate('w', strtotime($date)))){
                $tech               = Technician::find($value['technician_id']);
                $name               = $tech->first_name .' ' . $tech->last_name;
                $tech_id            = $value['technician_id'];
                $transaction_status = "reserved";
                $array_reserved_sched     = array();

                $getAppointment = DB::table("transaction_items as a")
                                        ->leftJoin("transactions as b","a.transaction_id","=","b.id")
                                        ->where("b.technician_id","=",$tech_id)
                                        ->where("b.transaction_datetime",'<=',$date.' 23:59:59')
                                        ->where("b.transaction_status","=",$transaction_status)
                                        ->where("a.item_status","=",$transaction_status)
                                        ->where("a.item_type","=","service")
                                        ->select("a.book_start_time","a.book_end_time")
                                        ->get();

               foreach ($getAppointment as $key) {
                    $converted_start = new DateTime($key->book_start_time);
                    $converted_end   = new DateTime($key->book_end_time);
                    
                    $array_reserved_sched[] = array(
                                            "sched_appointment_start"    => $converted_start->format("H:i"),
                                            "sched_appointment_end"      => $converted_end->format("H:i")
                                                );
               }                         
                                        
                if($e['schedule'] != '00:00') {
                    $object = array(
                        "employee_id" => $tech['employee_id'],
                        "id" => $tech_id,
                        "schedule" =>
                            array("start" => $e['schedule'],
                                "end" => date("H:i", strtotime(date('Y-m-d ') . ' ' . $e['schedule']) + 32400),
                            ),
                        "name" => $name,
                        "type" => $e['type'],
                        "appointment" => $array_reserved_sched
                    );

                    $found_key = $this->findRangeSchedule($technicians, $tech_id, $e['type']);

                    if ($found_key === false)
                        $technicians[] = $object;
                    else
                        $technicians[$found_key] = $object;
                }

            }
        }

        return $technicians;
    }

    function findRangeSchedule($array, $tech_id, $type){
        foreach($array as $key=>$value){
            if($tech_id === $value['id']){
                if($value['type']==='RANGE' && $type === 'SINGLE')
                    return $key;
                elseif($value['type']==='RANGE')
                    return $key;
            }
        }
        return false;
    }

    function compareExtract($list, $data, $i){
        foreach($list as $key=>$value ) {
            if ($value['id'] == $data['technician_id']) {
                if ($value['type'] == 'SINGLE')
                    return false;
                else
                    if ($data['schedule_type'] == 'RANGE')
                        return false;
            }
        }

        $schedule = json_decode($data['schedule_data']);

        return is_array($schedule)?array("schedule"=>$schedule[$i],"type"=>"RANGE"): array("schedule"=>$schedule,"type"=>"SINGLE");
    }





     //get appointment Items
    function getAppointmentItems($id){
        
        $items = TransactionItem::where('transaction_id', $id)->get()->toArray();
        foreach($items as $key=>$value){
            $items[$key]['item_data'] = json_decode($value['item_data']);
            if($value['item_type'] === 'service'){
                $service = Service::find($value['item_id']);
                $service_name = $service->service_type_id !== 0 ? ServiceType::find($service->service_type_id)->service_name:ServicePackage::find($service->service_package_id)->package_name;

                $service_image = "";
                if($service->service_type_id !== 0){
                    $service_image = ServiceType::find($service->service_type_id)->service_picture;
                }
                else{
                    $service_image  = ServicePackage::find($service->service_package_id)->package_image;
                }

                $items[$key]['item_name']       = $service_name;
                $items[$key]['item_image']      = $service_image;
                $items[$key]['item_duration']   = $service->service_minutes;
                $items[$key]['item_info']['gender'] = $service->service_gender;
            }
            
            else{
                $product       = Product::find($value['item_id']);
                $product_name  = ProductGroup::find($product->product_group_id)->product_group_name;
                $product_image = ProductGroup::find($product->product_group_id)->product_picture;
                $items[$key]['item_name']            = $product_name;
                $items[$key]['item_info']['size']    = $product->product_size;
                $items[$key]['item_info']['variant'] = $product->product_variant;
                $items[$key]['item_image']           = $product_image;
            }
        }
        return $items;
    }

    public function getBannerVersion(){

        $query  = Config::where("config_name","=","APP_BANNER")
                          ->orderBy('created_at')
                          ->select('config_description')
                          ->get()
                          ->first();
        return (double)$query['config_description'];
    }

    public function getDataVersions($type_name){

        $query  = Config::where("config_name","=",$type_name)
                          ->select('config_value')
                          ->get()
                          ->first();
        return (double)$query['config_value'];
    }





}
