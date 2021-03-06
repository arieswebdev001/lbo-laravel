<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Config;
use Validator;
use Storage;
use Artisan;

class ConfigController extends Controller{
    public function getTerms(){
        $queryTerms = Config::find(3);
        return $queryTerms->config_value;
    }
     public function getConsent(){
        $queryConsent = Config::find(29);
        return $queryConsent->config_value;
    }

    public function getConfigs(){
        return response()->json(Config::get()->toArray());
    }

    function getBackups(){
        $files = Storage::files('backups');
        $array = array();
        foreach($files as $key=>$file){
            $array[] = [
                "filename"=>str_replace('backups/','',$file),
                "size"=>number_format(Storage::size($file)/102400, 2) .'Mb',
                "created"=>date('m/d/Y h:i A',Storage::lastModified($file))
            ];
        }
        rsort($array);
        return response()->json($array);
    }
    function runBackup(){
        Artisan::queue('backup:mysql-dump');
        return response()->json(['result'=>'success', 'message'=>'Backup Success.']);
    }
    function restoreBackup(Request $request){
        if(Storage::exists('backups/' . $request->input('filename'))){
            $code = Artisan::call('backup:mysql-restore', ['--filename' => $request->input('filename'), '--yes'=>true]);
            if($code ===0)
                return response()->json(['result'=>'success', 'message'=>"Database successfully restored."]);
        }
        return  response()->json(['result'=>'failed', 'errors'=>'DB restore Failed to execute.'], 400);
    }
    function deleteBackup(Request $request){
        if(Storage::exists('backups/' . $request->input('filename'))){
            Storage::delete('backups/' . $request->input('filename'));
            return response()->json(['result'=>'success', 'message'=>"Backup successfully deleted."]);
        }
        return response()->json(['result'=>'failed', 'errors'=>"Failed to delete backup file."],400);
    }

}
