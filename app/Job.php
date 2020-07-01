<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Application;
use App\Ats;
use App\Config;
use App\Parser;
use Session;
use Auth;
use DB;
use App\Integrations\ICIMSIntegration;

class Job extends Model
{

    public $timestamps = false;

    protected $fillable = array('client_id', 'record_id', 'title', 'description', 'experience', 'location', 'country', 'latitude', 'longitude', 'active', 'live', 'department_id', 'first_job_stage_id', 'first_job_stage_name', 'filter_logic', 'approval_email_sent_at', 'updated_at', 'created_at');


    public function __construct()
    {
        //$this->_candidate = new Candidate;
    }

    /**
     * This Job belongs to a Client.
     */
    public function client()
    {
        return $this->belongsTo('App\Client');
    }

    /**
     * This Job belongs to a Category.
     */
    public function category()
    {
        return $this->belongsTo('App\Category');
    }

    /**
     * The skills that belong to the job.
     */
    public function skills()
    {
        return $this->belongsToMany('App\Skill')->withPivot('type');
    }

    /**
     * The job titles that belong to the job.
     */
    public function job_titles()
    {
        return $this->belongsToMany('App\JobTitle', 'job_job_title');
    }

    /**
     * The companies that belong to the job.
     */
    public function companies()
    {
        return $this->belongsToMany('App\Company');
    }

    public function company()
    {
        return $this->belongsToMany('App\Company', 'job_company');
    }

    /**
     * The universities that belong to the job.
     */
    public function universities()
    {
        return $this->belongsToMany('App\University', 'job_university');
    }    

    /**
     * The skills that belong to the job.
     */
    public function arkiive()
    {
        return $this->hasOne('App\Arkiive');
    }

    /**
     * The recruiters that belong to the job.
     */
    public function recruiters()
    {
        //return $this->belongsToMany('App\Recruiter', 'job_recruiter', 'job_id', 'recruiter_id');
        return $this->belongsToMany('App\Recruiter');
    }


    /**
     * TODO
     * @param $client
     * @param int $page
     * @param string $service
     */
    public function syncICIMSJobs($client, $page = 1, $service = 'FiiLTER')
    {

        $icims = new ICIMSIntegration();
        $icims->handle();

    }

    /**
     * TODO
     */
    public function storeICIMSJobs()
    {
        // todo
    }


    /**
     * Sync Greenhouse Jobs
     * ---
     * @param $client
     * @param int $page
     * @param string $service
     * @return array|bool
     */
    public function syncGreenhouseJob($client, $page = 1, $service = 'FiiLTER') {

      $_ats = new Ats;
             
       
		  $num_results_per_page = 100; // per page for pagination

        // If user_id of advancer is not set, do not sync or process anything
        if(!$client->user_id_of_advancer){
            return ['error' => 'Set a user/recruiter as the "Advancer"'];
        }
        // Sync Jobs
        $params = [
            'page' => $page,
            'per_page' => $num_results_per_page,
            'active' => true
        ];

       
        if(isset($client->service) && $client->service == 3){

            $timeframe =  DB::table("client_service")
                            ->select("created_at","updated_at")
                            ->where("client_id",$client->id)
                            ->where("service_id", 3)
                            ->get();

             if(!$timeframe->isEmpty())                
                $params['created_after'] = $timeframe[0]->created_at;

        } elseif ($service == 'FiiLTER' AND !in_array($client->id,[5,6])) {
          //elseif ($service == 'FiiLTER' AND !in_array($client->id,[6])) {

            $timeframe =  DB::table("client_service")
                            ->select("created_at","updated_at")
                            ->where("client_id",$client->id)
                            ->where("service_id", 1)
                            ->get();
           // dd($timeframe);
            if(!$timeframe->isEmpty()){

               $params['created_after'] = $timeframe[0]->created_at;

            } else {

            $timeframe =  DB::table("client_service")
                            ->select("created_at","updated_at")
                            ->where("client_id",$client->id)
                            ->where("service_id", 3)
                            ->get();
             if(!$timeframe->isEmpty())                
                $params['created_after'] = $timeframe[0]->created_at;

            }
        }        

        $job_posts = $_ats->callGreenhouse($client, "job_posts", $params);
        //dd($job_posts);
        if($job_posts){

            $_job = new Job;
            $_job->storeGreenhouseJob($job_posts, $client);

            return $this->syncGreenhouseJob($client, ($page + 1), 'FiiLTER');
        }

        if(isset($client->service))
            unset($client->service);
            
        return true;

        // if(!isset($job_posts['message'])){
        //     $_job = new Job;

        //     return $_job->storeGreenhouseJob($job_posts, $client);
        // }
        // else{
        //     return false;
        // }
    }

    /**
     * @param $job_posts
     * @param $client
     * @return bool
     */
    public function storeGreenhouseJob($job_posts, $client){

        $_parser = new Parser;
        $_application = new Application;
      
        $new_approval_emails = [];

        //$configs_db = \DB::connection('mysql2')->table('pre_normalisation')->select()->get();

       // dd($configs_db);        

        foreach ($job_posts as $d) {

          //dd($d['created_at']);

          if($client->id == 5){

            $timeframe =  DB::table("client_service")
                              ->select("created_at","updated_at")
                              ->where("client_id",$client->id)
                              ->where("service_id", 1)
                              ->get();

            $client_service_time = new \DateTime($timeframe[0]->created_at);
            $job_created_at = new \DateTime($d['created_at']);
           } 
         
            // Restrict Agoda to these JOB IDs
            if($client->name == "Agoda" && !in_array($d['job_id'], [152305,304898,960753,970884,978936,918126,1012290,939203,743409,803966,992511,361503,967169,913257,913252,859538,1058295,993798,1076492,859546,965590,1024404,912297,921007,1001427,945005,918043,928806,1002700,1047241,994923,1079210,816569,611868,948566,1048266,814422,902490,1004095,730310,945525,963253,435700,417640,466536,797517,294305,1047194,1103829,1102739,931033,1168846,891145,830022,841839,860826])) {
                //continue;
                if($client_service_time > $job_created_at) {                 
                  continue;
                }
            } 

            $job_record = Model::where([
                ['client_id', '=', $client->id],
               // commented for testing 
                ['job_id', '=', $d['job_id']]
                //['job_id', '=', 1084910]
            ])->first();

            
            if($job_record){ // record exists

                $record_updated = false;
                
                if($job_record->updated_at != \Carbon\Carbon::parse($d['updated_at'])->format('Y-m-d H:i:s')){
                    $job_record->live = (int)$d['live'];
                    $job_record->active = (int)$d['active'];
                    $job_record->updated_at = \Carbon\Carbon::parse($d['updated_at'])->format('Y-m-d H:i:s');

                    // If the record has not yet been approved, the recruiters for the
                    // job can change and re-sync
                    if(!$job_record->approved) {

                        $job_record->title = $d['title'];

                        $job_description = $this->cleanupJobDescription($d['content']);

                        $job_description = strip_tags($job_description);

                        $job_description = $this->cleanupJobDescription($job_description);

                        $job_filename = $d['job_id'].".txt";

                        $parsed_job_description = "";
                        if(!in_array($d['job_id'],[1047241,1001427])) {
                            $parsed_job_description = $_parser->parseJob($job_description, $job_filename);

                            if(!is_int($parsed_job_description) && $parsed_job_description != false){
                               
                                $job_description = $parsed_job_description['content'];

                            }
                        }

                        $job_skills = $_parser->getJobSkills($job_description);

                        $skills_data = Skill::checkAddSkills($job_skills);

                        $job_record->description = $job_description;

                        // Attach skills to job via pivot table
                        $job_record->skills()->sync( array_keys($skills_data) );


                        // ******************
                        // GET recruiters/hiring_team from 'Job' using 'job_id'
                        $recruiter_ids = [];
                        $_ats = new Ats;
                        $job = $_ats->callGreenhouse($client, "jobs/".$d['job_id']);
                        
                        foreach ($job['hiring_team'] as $hiring_team_key => $hiring_team) {
                            if(count($hiring_team) && in_array($hiring_team_key, array('hiring_managers', 'recruiters'))){
                                foreach ($hiring_team as $team_member) {
                                    if(!in_array($team_member['id'], $recruiter_ids)){
                                        $recruiter_ids[] = $team_member['id'];
                                    }
                                }
                            }
                        }

                        if(isset($job['departments']) && !empty($job['departments'])){
                            $job_record->department = $job['departments'][0]['name'];
                            $job_record->department_id = $job['departments'][0]['id'];
                        }

                        $recruiters = \App\Recruiter::where('client_id', $client->id)
                            ->whereIn('recruiter_id', $recruiter_ids)
                            ->get();

                        // Attach recruiters to job via pivot table
                        $job_record->recruiters()->sync($recruiters->pluck('id')->toArray());
                    }

                    $record_updated = true;
                }               
                
                if(($job_record->approved && $d['active'] && $d['live'] && !isset($client->service)) OR $d['job_id'] == 361503){
                    //unblock after test
                    $application_ids = $_application->processGreenhouseApplications($client, $job_record);
                    //unblock after test
                    if(!empty($application_ids)){
                        Filter::process($client, $job_record, $application_ids); // (job, application_ids)
                    }
                }

                // FIND RECRUITERS TO EMAIL
                /*if($job_record->approved == 0){
                    $diff = date_diff(date_create($job_record->approval_email_sent_at), date_create());
                    
                    if($diff->d >= 5){ // Number of days before sending another reminder email

                        foreach ($job_record->recruiters()->get()->toArray() as $recruiter) {

                            if(!isset($new_approval_emails[$recruiter['email']])){
                                $new_approval_emails[$recruiter['email']]['name'] = $recruiter['name'];
                                $new_approval_emails[$recruiter['email']]['link'] = "/client/auth/" . encrypt($client->auth_token.":".$recruiter['recruiter_id']);
                                $new_approval_emails[$recruiter['email']]['job_titles'] = [];
                            }

                            $new_approval_emails[$recruiter['email']]['job_titles'][] = $d['title'];
                        };

                        $job_record->approval_email_sent_at = \Carbon\Carbon::now()->format('Y-m-d H:i:s');
                        $record_updated = true;
                    }
                }*/

                // SAVE RECORD IF ANY CHANGES WERE DETECTED
                if($record_updated){
                    $job_record->save();
                }
            }
            else{ // Create record


                // ******************
                // GET recruiters/hiring_team from 'Job' using 'job_id'
                $recruiter_ids = [];
                $ats_skills = [];
                $_ats = new Ats;
                $job = $_ats->callGreenhouse($client, "jobs/".$d['job_id']);               
               
                if($job['status'] == "open"){                  

                    foreach ($job['hiring_team'] as $hiring_team_key => $hiring_team) {
                        if(count($hiring_team) && in_array($hiring_team_key, array('hiring_managers', 'recruiters'))){
                            foreach ($hiring_team as $team_member) {
                                if(!in_array($team_member['id'], $recruiter_ids)){
                                    $recruiter_ids[] = $team_member['id'];
                                }
                            }
                        }
                    }

                    $recruiters = \App\Recruiter::where('client_id', $client->id)
                        ->whereIn('recruiter_id', $recruiter_ids)
                        ->get();

                    // ******************
                    // GET first job stage id
                    $first_job_stage_id = 0;
                    $first_job_stage_name = "";

                    // we skip jobs not within date range for agoda
                    if($client->id == 5){

                      $timeframe =  DB::table("client_service")
                              ->select("created_at","updated_at")
                              ->where("client_id",$client->id)
                              ->where("service_id", 1)
                              ->get();

                      $client_service_time = new \DateTime($timeframe[0]->created_at);
                      $job_created_at = new \DateTime($d['created_at']);

                      if ($client_service_time > $job_created_at) {
                        continue;
                      } 
                    }                    

                    $job_stages = $_ats->callGreenhouse($client, "jobs/".$d['job_id']."/stages");

                    if(!empty($job_stages) && isset($job_stages[0], $job_stages[0]['id'])){
                        $first_job_stage_id = $job_stages[0]['id'];
                        $first_job_stage_name = $job_stages[0]['name'];
                    }
                   

                    $job_description = ($d['content'] ? $d['content'] : $d['internal_content']);                  

                    $job_description = strip_tags($job_description);

                    $job_description = $this->cleanupJobDescription($job_description);

                    $job_filename = $d['job_id'].".txt";

                    $parsed_job_description = $_parser->parseJob($job_description, $job_filename);

                    if(!is_int($parsed_job_description) or $parsed_job_description == false){
                        $job_description = $parsed_job_description['content'];
                    }

                    //$job_description = $this->cleanupJobDescription($job_description);
 
                    // Add skills that dont exist in the database
                    $job_skills = $_parser->getJobSkills($job_description);

                    if(isset($job['custom_fields']['essential_skills'])) {
                        $ats_skills = explode(",", $job['custom_fields']['essential_skills']);
                        $job_skills = array_merge($job_skills, $ats_skills);
                    }

                    $skills_data = Skill::checkAddSkills($job_skills);

                    $custom_field = null;

                    if(isset($job['keyed_custom_fields']) && 
                        isset($job['keyed_custom_fields']['essential_skills_job_1563960688.408168']) && 
                        !is_null($job['keyed_custom_fields']['essential_skills_job_1563960688.408168']['value']))
                    {
                        $custom_field = $job['keyed_custom_fields']['essential_skills_job_1563960688.408168']['value'];

                    }
                   
                    $model = new Job;
                    $model->client_id = $client->id;
                    $model->record_id = $d['id'];
                    $model->job_id = $d['job_id'];
                    $model->title = $d['title'];
                    $model->description = $job_description;
                    $model->custom_1 = $custom_field;
                    $model->location = (isset($d['location'], $d['location']['name']) ? $d['location']['name'] : implode("|", $d['location']));
                    $model->active = (int)$d['active'];
                    $model->live = (int)$d['live'];
                    $model->first_job_stage_id = $first_job_stage_id;
                    $model->first_job_stage_name = $first_job_stage_name;
                    $model->approval_email_sent_at = \Carbon\Carbon::now()->format('Y-m-d H:i:s');
                    $model->updated_at = \Carbon\Carbon::parse($d['updated_at'])->format('Y-m-d H:i:s');
                    $model->created_at = \Carbon\Carbon::parse($d['created_at'])->format('Y-m-d H:i:s');

                    // Hard coded department for Blenheim Chalcot
                    if($client->id == 4){
                        $model->department = "Blenheim Chalcot";
                        $model->department_id = 12345;
                    }

                    // If custom field is null we ignore the jobs for agoda
                    if(is_null($custom_field) && $client->id == 5)
                    {
                        $model->ignore=1;

                        if(env('APP_ENV') == "production"){                                     
                          // Send Slack message to CiiVHUB admin
                          \Slack::to('@adam')->send($client->name." IGNORE JOB - Job Created for Approval -".$d['title']);
                        }

                    } else {

                        if(env('APP_ENV') == "production"){                                     
                        // Send Slack message to CiiVHUB admin
                        \Slack::to('@adam')->send($client->name."NEEDS APPROVAL - Job Created for Approval -".$d['title']);
                        }
                    }

                    /*if($client->id == 5){

                        // Step 1 : check whether the job exists
                        $hiistory_configs_db_job_id = \DB::connection('mysql2')->table('pre_normalisation')->select()->where("job_id", $d['job_id'])->first();
                        
                        if (!is_null($hiistory_configs_db_job_id))
                        {
                           // grab the config ID
                           $_config_id = \DB::table('configs')->select('id')->where("job_title_id", $hiistory_configs_db_job_id->normalised_job_title_id)->first();
                           $model->config_id = $_config_id->id;
                           dd($hiistory_configs_db_job_id);
                        } // end of step 1
                        // Step 2 : check for similarity
                        elseif(is_null($hiistory_configs_db_job_id)) {

                          $hiistory_configs_db_job_titles = \DB::connection('mysql2')->table('pre_normalisation')->select()->get();
                          $percentage = 0;
                          $percentage_array = [];
                          foreach($hiistory_configs_db_job_titles as $h_config_db_job_titles)
                          {
                            similar_text(strtolower($d['title']), strtolower($h_config_db_job_titles->job_title), 
                                $percentage);
                            $percentage_array[] = $percentage;
                          }

                          dd($percentage_array);

                        }

                    }
                    exit;*/
                    //dd($model);
                    /*if((isset($job['departments']) && !empty($job['departments'])) or $client->id == 4){
                        
                        // Assigning configs to jobs before approval

                        // grabbing all department configs if they exists
                        if($client->id == 4){
                            $configs_exist = Config::where('dept_id', 12345)->where('parent', 0)->exists();
                        }else{

                            $model->department = $job['departments'][0]['name'];
                            $model->department_id = $job['departments'][0]['id'];
                            $configs_exist = Config::where('dept_id', $job['departments'][0]['id'])->where('parent', 0)->exists();
                        }

                        if($configs_exist)
                        {                            
                            $configs_data = Config::where('dept_id', $job['departments'][0]['id'])->where('parent', 0)->get();

                            $config_matching_array = [];
                            foreach($configs_data as $config_data)
                            {
                                $config_model =  Config::find($config_data->id);
                                $config_skills = array_map('strtolower', $config_model->skills->pluck('skill')->toArray());
                                $config_job_titles = array_map('strtolower', $config_model->job_titles->pluck('title')->toArray());
                                $config_location = $config_data->location;
                                //dd($skills_data);                                
                                $job_skills_data = array_map('strtolower', (array_values($skills_data)));

                                //Skills matching
                                $skills_intersect = array_intersect($config_skills, $job_skills_data);
                               
                                if(count($skills_intersect))
                                {
                                    $config_matching_array[$config_data->id]['skills'] = count($skills_intersect);
                                } else {
                                    $config_matching_array[$config_data->id]['skills'] = 0;
                                }
                                // End of skills matching
                                 //dd($config_matching_array);
                                // Job title matching                               
                                $config_matching_array[$config_data->id]['jobtitle'] = 0;

                                if(strlen($d['title']) > 3) {
                                    foreach($config_job_titles as $config_job_title)
                                    { 
                                        $job_title_matching = strtolower($d['title']);

                                        similar_text($config_job_title, $job_title_matching, $percentage);
                                        
                                        if($percentage > 50)
                                        {
                                           $config_matching_array[$config_data->id]['jobtitle'] = 1; 
                                        }
                                    }
                                }                                
                            }

                            if(count($config_matching_array)>0){
                                $keys = array_keys($config_matching_array);
                                array_multisort($config_matching_array,SORT_DESC);
                                $config_matching_array = array_combine($keys, $config_matching_array);
                                $keys_config = array_keys($config_matching_array);
                                //add config to job
                                $model->config_id = $keys_config[0];
                            }
                        
                        }                        
                        // End of assigning
                    }*/

                    $model->save();                    

                    // Attach skills to job via pivot table
                    $model->skills()->sync( array_keys($skills_data) );

                    // Attach critical skills if exists
                    if(is_array($ats_skills) && count($ats_skills)>0) {
                        foreach($ats_skills as $atsskills)
                        {
                            $skills_key = array_search($atsskills, $skills_data);
                            $model->skills()->updateExistingPivot($skills_key ,['type' => 'C']);                            
                        }
                    }

                    // Attach recruiters to job via pivot table
                    $model->recruiters()->sync($recruiters->pluck('id')->toArray());

                    // SEND EMAILS TO RECRUITERS
                    foreach ($recruiters as $recruiter) {

                        if(!isset($new_approval_emails[$recruiter->email])){
                            $new_approval_emails[$recruiter->email]['name'] = $recruiter->name;
                            $new_approval_emails[$recruiter->email]['link'] = "/client/auth/" . encrypt($client->auth_token.":".$recruiter->recruiter_id);
                            $new_approval_emails[$recruiter->email]['job_titles'] = [];
                        }

                        $new_approval_emails[$recruiter->email]['job_titles'][] = $d['title'];
                    };
                }
            }
        }

        if(env('APP_ENV') == "production" && !empty($new_approval_emails) && $client->approved_by_client){
           // $this->sendApprovalReminderEmail($new_approval_emails);
        }

        return true;
    }

    /**
     * @param $client
     * @return bool
     */
    public function syncSuccessFactorsJob($client){

        $_ats = new Ats;

        // If user_id of advancer is not set, do not sync or process anything
        /*if(!$client->user_id_of_advancer){
            return ['error' => 'Set a user/recruiter at the "Advancer"'];
        }*/

        /*$applications = $_ats->callSuccessFactors($client, "JobApplication", [
            '%24format'=>'json',
            '%24filter'=>'jobReqId eq 1021',
            '%24top'=>2,
        ]);
        dd($applications);*/

        /*$application_status = $_ats->callSuccessFactors($client, "JobApplicationStatusLabel", [
            '%24format'=>'json',
            '%24expand'=>'appStatus'
        ]);
        dd($application_status);
        die();*/


        // Sync Jobs
        // job list
        //$jobs_array = [59644, 59426, 59537, 59443, 59642, 59641];
        $jobs_array = [46228, 45612, 45352, 47553, 58262, 52311, 54671, 57479, 57781, 57773, 65288, 63505, 59272, 58259, 55263, 51226, 51719, 51419, 46472, 54665,67310,50620,65565, 66732, 66952, 66641, 64896, 59092, 59054, 63262, 61922, 65178, 65382, 65503, 65546, 65591, 65694, 57983, 44188, 51321, 57944, 58888, 52867, 65039, 68232];
        //$jobs_array = [ 45612, 45352, 58262, 52311, 57479, 57781, 57773, 58259, 51226, 51719, 51419, 46472, 54665];

        foreach($jobs_array as $job_array)
        {
            $job_posts = $_ats->callDellBoomiSuccessFactors($client, "Get_Job_Req&jobReqId=".$job_array, ['jobReqId' => $job_array]);

            if(isset($job_posts[0]['job_id']) && !empty($job_posts[0]['job_id']))
            {
                $_job = new Job; 

                $recruiter_ids = [];
                $location_data['city'] = trim($job_posts[0]['city']);
                $location_data['country'] = trim($job_posts[0]['country']);
                $location_data['full_location'] = $location_data['city'] . (!empty($location_data['country']) ? ", ".$location_data['country'] : "");
                $job_data = [
                        'id' => $job_posts[0]['job_id'],
                        'info' => $job_posts,
                        'postings' => (isset($job_posts[0]['status'])?$job_posts[0]['status']:""),
                        'created_at' => substr(preg_replace("/[^0-9\.]/", '', $job_posts[0]['created_at']), 0, 10),
                        'updated_at' => substr(preg_replace("/[^0-9\.]/", '', $job_posts[0]['updated_at']), 0, 10)
                    ];

                $_job->storeDellBoomiSuccessFactorsJob($client, $job_data, $recruiter_ids, $location_data);

            }

        }
        // Sync Jobs - method of direct sync to successfctors removed for time being (27-09-2019)
        /*$job_posts = $_ats->callSuccessFactors($client, "JobRequisition", [ //(1021)
            '%24format'=>'json',
            '%24select'=>'hiringManager,hiringManagerTeam,recruiterTeam,jobReqLocale,jobReqPostings,jobReqId,city,country,location,createdDateTime,lastModifiedDateTime',
            '%24expand'=>'hiringManager,hiringManagerTeam,recruiterTeam,jobReqLocale,jobReqPostings',
            '%24count'=>'true',
            // '%24top'=>5,
            // '%24skip'=>100
        ]);*/

       /* if(isset($job_posts['d'], $job_posts['d']['results']) && !empty($job_posts['d']['results'])){
            foreach($job_posts['d']['results'] as $job_post){

                $_job = new Job;

                // ONLY PROCESS IF RECRUITERS/HIRING MANAGER ARE ATTACHED TO THE JOB
                $recruiters = array_merge($job_post['hiringManager']['results'], $job_post['recruiterTeam']['results']);

                if($recruiters){
                    $_recruiter = new Recruiter;
                    $recruiter_ids = $_recruiter->storeSuccessFactorsRecruiter($recruiters, $client);

                    $location_data = [];
                    if(!empty($job_post['location'])){

                        $location = explode(' (', $job_post['location']);
                        $location = trim($location[0]);

                        $location_data['city'] = $location;
                        $location_data['country'] = trim($job_post['country']);
                        $location_data['full_location'] = $location_data['city'] . (!empty($location_data['country']) ? ", ".$location_data['country'] : "");

                        $_office = new Office;
                        if(!$_office->storeSuccessFactorsOffice($location_data, $client)) return false;
                    }

                    $job_data = [
                        'id' => $job_post['jobReqId'],
                        'info' => $job_post['jobReqLocale']['results'],
                        'postings' => $job_post['jobReqPostings']['results'],
                        'created_at' => substr(preg_replace("/[^0-9\.]/", '', $job_post['createdDateTime']), 0, 10),
                        'updated_at' => substr(preg_replace("/[^0-9\.]/", '', $job_post['lastModifiedDateTime']), 0, 10)
                    ];

                    $_job->storeSuccessFactorsJob($client, $job_data, $recruiter_ids, $location_data);
                }

            }
        }*/

        return false;
    }

    /**
     * @param $client
     * @param $job_posts
     * @param $recruiter_ids
     * @param $location
     * @return bool
     */
    public function storeDellBoomiSuccessFactorsJob($client, $job_posts, $recruiter_ids, $location){

        $_parser = new Parser;
        $_application = new Application;

        $new_approval_emails = [];

        foreach ($job_posts['info'] as $d) {

            $live_status = 0;

            if($job_posts['postings'] == "ACTIVE"){
                $live_status = 1;
            }            

            $job_record = Model::where([
                ['client_id', '=', $client->id],
                ['record_id', '=', $job_posts['id']]
            ])->first();
            
            if($job_record){ // record exists

                $record_updated = false;

                if($job_record->live != $live_status){
                    $job_record->live = $live_status;
                    $job_record->updated_at = \Carbon\Carbon::now()->format('Y-m-d H:i:s');

                    $record_updated = true;
                }

                if($job_record->approved && $job_record->live){

                    // WORK ON GETTING APPLICATION FOR THE JOB_ID

                    $application_ids = $_application->processDellBoomiSuccessFactorsApplications($client, $job_record);

                    //dd($application_ids);

                    Filter::process($client, $job_record, $application_ids); // (job, application_ids)
                }

                // SAVE RECORD IF ANY CHANGES WERE DETECTED
                if($record_updated){
                    $job_record->save();
                }
            }
            else{ // Create record
                // ******************
                $_ats = new Ats;

                // ******************
                // GET first job stage id
                $first_job_stage_id = 1;
                $first_job_stage_name = "New Application";               

                $job_description = $this->cleanupJobDescription($d['job_description']);
                if(empty($job_description)){
                    $job_description = $this->cleanupJobDescription($d['job_description']);
                }

                // Add skills that dont exist in the database
                $job_skills = $_parser->getJobSkills($job_description);
                $skills_data = Skill::checkAddSkills($job_skills);

                $model = new Job;
                $model->client_id = $client->id;
                $model->record_id = $job_posts['id'];
                $model->job_id = $job_posts['id']; //$d['jobReqLocalId'];
                $model->title = $d['job_title'];
                $model->description = $job_description;
                $model->location = $location['city'];
                $model->country = $location['country'];
                $model->active = 1;
                $model->department= (isset($d['department'])?$d['department']:"");
                $model->live = $live_status;
                $model->first_job_stage_id = $first_job_stage_id;
                $model->first_job_stage_name = $first_job_stage_name;
                //$model->approval_email_sent_at = \Carbon\Carbon::now()->format('Y-m-d H:i:s');
                $model->updated_at = \Carbon\Carbon::createFromTimestamp($job_posts['created_at'])->format('Y-m-d H:i:s');
                $model->created_at = \Carbon\Carbon::createFromTimestamp($job_posts['updated_at'])->format('Y-m-d H:i:s');

                $model->save();
                // Attach skills to job via pivot table
                $model->skills()->sync( array_keys($skills_data) );
                if(env('APP_ENV') == "production"){                                     
                        // Send Slack message to CiiVHUB admin
                        \Slack::to('@adam')->send($client->name." Job Created for Approval -".$d['job_title']);
                }
                
            }
        }

        if(env('APP_ENV') == "production" && !empty($new_approval_emails) && $client->approved_by_client){
            //$this->sendApprovalReminderEmail($new_approval_emails);
        }

        return true;
    }






    /**
     * @param $client
     * @param $job_posts
     * @param $recruiter_ids
     * @param $location
     * @return bool
     */
    public function storeSuccessFactorsJob($client, $job_posts, $recruiter_ids, $location){

        $_parser = new Parser;
        $_application = new Application;

        $new_approval_emails = [];


        $recruiters = \App\Recruiter::where('client_id', $client->id)
            ->whereIn('recruiter_id', $recruiter_ids)
            ->get();

        foreach ($job_posts['info'] as $d) {

            $live_status = 0;
            if(!empty($job_posts['postings'])){
                foreach ($job_posts['postings'] as $posting) {
                    if($posting['postingStatus'] == "Success" || $posting['postingStatus'] == "Updated"){
                        $live_status = 1;
                    }
                }
            }

            $job_record = Model::where([
                ['client_id', '=', $client->id],
                ['record_id', '=', $job_posts['id']]
            ])->first();

            if($job_record){ // record exists

                $record_updated = false;

                if($job_record->live != $live_status){
                    $job_record->live = $live_status;
                    $job_record->updated_at = \Carbon\Carbon::now()->format('Y-m-d H:i:s');

                    $record_updated = true;
                }

                if($job_record->approved && $job_record->live){

                    // WORK ON GETTING APPLICATION FOR THE JOB_ID

                    $application_ids = $_application->processSuccessFactorsApplications($client, $job_record);

                    Filter::process($client, $job_record, $application_ids); // (job, application_ids)
                }

                // FIND RECRUITERS TO EMAIL
                /*if($job_record->approved == 0){
                    $diff = date_diff(date_create($job_record->approval_email_sent_at), date_create());

                    if($diff->d >= 5){ // Number of days before sending another reminder email

                        foreach ($job_record->recruiters()->get()->toArray() as $recruiter) {

                            if(!isset($new_approval_emails[$recruiter['email']])){
                                $new_approval_emails[$recruiter['email']]['name'] = $recruiter['name'];
                                $new_approval_emails[$recruiter['email']]['link'] = "/client/auth/" . encrypt($client->auth_token.":".$recruiter['recruiter_id']);
                                $new_approval_emails[$recruiter['email']]['job_titles'] = [];
                            }

                            $new_approval_emails[$recruiter['email']]['job_titles'][] = $d['externalTitle'];
                        };

                        $job_record->approval_email_sent_at = \Carbon\Carbon::now();
                        $record_updated = true;
                    }
                }*/

                // SAVE RECORD IF ANY CHANGES WERE DETECTED
                if($record_updated){
                    $job_record->save();
                }
            }
            else{ // Create record
                // ******************
                $_ats = new Ats;

                // ******************
                // GET first job stage id
                $first_job_stage_id = 1;
                $first_job_stage_name = "New Application";
                /*$job_stages = $_ats->callTeamtailor($client, "jobs/".$d['id']."/stages");
                if(!empty($job_stages['data']) && isset($job_stages['data'], $job_stages['data'][0])){
                    $first_job_stage_id = $job_stages['data'][0]['id'];
                    $first_job_stage_name = $job_stages['data'][0]['attributes']['name'];
                }*/

                $job_description = $this->cleanupJobDescription($d['externalJobDescription']);
                if(empty($job_description)){
                    $job_description = $this->cleanupJobDescription($d['intJobDescHeader']);
                }

                // Add skills that dont exist in the database
                $job_skills = $_parser->getJobSkills($job_description);
                $skills_data = Skill::checkAddSkills($job_skills);

                $model = new Job;
                $model->client_id = $client->id;
                $model->record_id = $job_posts['id'];
                $model->job_id = $job_posts['id']; //$d['jobReqLocalId'];
                $model->title = $d['externalTitle'];
                $model->description = $job_description;
                $model->location = $location['city'];
                $model->country = $location['country'];
                $model->active = 1;
                $model->live = $live_status;
                $model->first_job_stage_id = $first_job_stage_id;
                $model->first_job_stage_name = $first_job_stage_name;
                $model->approval_email_sent_at = \Carbon\Carbon::now()->format('Y-m-d H:i:s');
                $model->updated_at = \Carbon\Carbon::createFromTimestamp($job_posts['created_at'])->format('Y-m-d H:i:s');
                $model->created_at = \Carbon\Carbon::createFromTimestamp($job_posts['updated_at'])->format('Y-m-d H:i:s');
                $model->save();

                // Attach skills to job via pivot table
                $model->skills()->sync( array_keys($skills_data) );

                // Attach recruiters to job via pivot table
                $model->recruiters()->sync($recruiters->pluck('id')->toArray());

                // SEND EMAILS TO RECRUITERS
                foreach ($model->recruiters()->get()->toArray() as $recruiter) {
                    if(!isset($new_approval_emails[$recruiter['email']])){
                        $new_approval_emails[$recruiter['email']]['name'] = $recruiter['name'];
                        $new_approval_emails[$recruiter['email']]['link'] = "/client/auth/" . encrypt($client->auth_token.":".$recruiter['recruiter_id']);
                        $new_approval_emails[$recruiter['email']]['job_titles'] = [];
                    }

                    $new_approval_emails[$recruiter['email']]['job_titles'][] = $d['externalTitle'];
                };
            }
        }

        if(env('APP_ENV') == "production" && !empty($new_approval_emails) && $client->approved_by_client){
            $this->sendApprovalReminderEmail($new_approval_emails);
        }

        return true;
    }

    /**
     * @param $client
     * @return array|bool
     */
	public function syncTeamtailorJob($client){

		$_ats = new Ats;

		// If user_id of advancer is not set, do not sync or process anything
		if(!$client->user_id_of_advancer){
			return ['error' => 'Set a user/recruiter at the "Advancer"'];
		}

		// Sync Jobs
		$job_posts = $_ats->callTeamtailor($client, "jobs");

		if(isset($job_posts['data'])){
			$_job = new Job;

			return $_job->storeTeamtailorJob($job_posts['data'], $client);
		}
		else{
			return false;
		}
	}

    /**
     * @param $job_posts
     * @param $client
     * @return bool
     */
	public function storeTeamtailorJob($job_posts, $client){

		$_parser = new Parser;
		$_application = new Application;

		$new_approval_emails = [];

		foreach ($job_posts as $d) {

			$job_record = Model::where([
				['client_id', '=', $client->id],
				['record_id', '=', $d['id']]
			])->first();

			if($job_record){ // record exists
				$record_updated = false;

				$live_status = ($d['attributes']['human-status'] == "published" ? 1 : 0);

				if($job_record->live != $live_status){
					$job_record->live = $live_status;
					$job_record->updated_at = \Carbon\Carbon::now()->format('Y-m-d H:i:s');

					$record_updated = true;
				}

				if($job_record->approved && $job_record->live){

					// WORK ON GETTING APPLICATION FOR THE JOB_ID

					$application_ids = $_application->processTeamtailorApplications($client, $job_record);

					Filter::process($client, $job_record, $application_ids); // (job, application_ids)
				}

				// FIND RECRUITERS TO EMAIL
				/*if($job_record->approved == 0){
					$diff = date_diff(date_create($job_record->approval_email_sent_at), date_create());

					if($diff->d >= 5){ // Number of days before sending another reminder email

						foreach ($job_record->recruiters()->get()->toArray() as $recruiter) {

							if(!isset($new_approval_emails[$recruiter['email']])){
								$new_approval_emails[$recruiter['email']]['name'] = $recruiter['name'];
								$new_approval_emails[$recruiter['email']]['link'] = "/client/auth/" . encrypt($client->auth_token.":".$recruiter['recruiter_id']);
								$new_approval_emails[$recruiter['email']]['job_titles'] = [];
							}

							$new_approval_emails[$recruiter['email']]['job_titles'][] = $d['title'];
						};

						$job_record->approval_email_sent_at = \Carbon\Carbon::now()->format('Y-m-d H:i:s');
						$record_updated = true;
					}
				}*/

				// SAVE RECORD IF ANY CHANGES WERE DETECTED
				if($record_updated){
					$job_record->save();
				}
			}
			else{ // Create record

				if($d['attributes']['status'] == "open"){

					// ******************
					// GET recruiters/hiring_team
					$recruiter_ids = [];
					$_ats = new Ats;
					$recruiter = $_ats->callTeamtailor($client, "jobs/".$d['id']."/user");

					if($recruiter['data']){
						$recruiter_ids[] = $recruiter['data']['id'];
					}

					// ******************
					// GET first job stage id
					$first_job_stage_id = 0;
					$first_job_stage_name = "";
					$job_stages = $_ats->callTeamtailor($client, "jobs/".$d['id']."/stages");
					if(!empty($job_stages['data']) && isset($job_stages['data'], $job_stages['data'][0])){
						$first_job_stage_id = $job_stages['data'][0]['id'];
						$first_job_stage_name = $job_stages['data'][0]['attributes']['name'];
					}

					// ******************
					// GET job location
					$location_city = null;
					$location_country = null;
					$job_location = $_ats->callTeamtailor($client, "jobs/".$d['id']."/location");
					if(isset($job_location['data'], $job_location['data']['attributes'])){
						$job_location = $job_location['data']['attributes'];

						if($job_location['city']){
							$location_city = $job_location['city'];
						}
						if($job_location['country']){
							$location_country = $job_location['country'];
						}
					}

					$job_description = $this->cleanupJobDescription($d['attributes']['body']);

					// Add skills that dont exist in the database
					$job_skills = $_parser->getJobSkills($job_description);
					$skills_data = Skill::checkAddSkills($job_skills);

					$model = new Job;
					$model->client_id = $client->id;
					$model->record_id = $d['id'];
					$model->job_id = $d['id'];
					$model->title = $d['attributes']['title'];
					$model->description = $job_description;
					$model->location = $location_city;
					$model->country = $location_country;
					$model->active = 1;
					$model->live = ($d['attributes']['human-status'] == "published" ? 1 : 0);
					$model->first_job_stage_id = $first_job_stage_id;
					$model->first_job_stage_name = $first_job_stage_name;
					$model->approval_email_sent_at = \Carbon\Carbon::now()->format('Y-m-d H:i:s');
					$model->updated_at = \Carbon\Carbon::parse($d['attributes']['created-at'])->format('Y-m-d H:i:s');
					$model->created_at = \Carbon\Carbon::parse($d['attributes']['created-at'])->format('Y-m-d H:i:s');
					$model->save();

					// Attach skills to job via pivot table
					$model->skills()->sync( array_keys($skills_data) );

					// Attach recruiters to job via pivot table
					$model->recruiters()->sync($recruiter_ids);

					// SEND EMAILS TO RECRUITERS
					foreach ($model->recruiters()->get()->toArray() as $recruiter) {
						if(!isset($new_approval_emails[$recruiter['email']])){
							$new_approval_emails[$recruiter['email']]['name'] = $recruiter['name'];
							$new_approval_emails[$recruiter['email']]['link'] = "/client/auth/" . encrypt($client->auth_token.":".$recruiter['recruiter_id']);
							$new_approval_emails[$recruiter['email']]['job_titles'] = [];
						}

						$new_approval_emails[$recruiter['email']]['job_titles'][] = $d['attributes']['title'];
					};
				}
			}
		}

		if(env('APP_ENV') == "production" && !empty($new_approval_emails) && $client->approved_by_client){
			$this->sendApprovalReminderEmail($new_approval_emails);
		}

		return true;
    }

    /**
     * Clear job record locks older than 10 seconds
     */
	public static function clearLocks($type = ''){
		$criteria = [];
		$criteria[] = ['locked_at', '<', \Carbon\Carbon::now()->subSeconds(10)];

		if(Auth::check() && !Auth::user()->isAdmin() && $type != 'all') {
			$client = Session::get('client');

			$criteria[] = ['client_id', '=', $client->id];
		}
		
		$record = Job::where($criteria)->get();

        if($record){
            foreach($record as $rec){
                $rec->locked_at = null;
                $rec->locked_by = null;
                $rec->save();
            }
        }

		return true;
	}

    /**
     * Send email to recruiters with a list of jobs pending approval
     */
    public function sendApprovalReminderEmail($new_approval_emails){
        foreach ($new_approval_emails as $email_address => $email_data) {
            \Mail::send('emails.approvalreminder', ['email_data' => $email_data], function ($m) use ($email_address, $email_data) {
                $number_of_jobs = count($email_data['job_titles']);
                $m->from('info@ciivhub.com', 'CiiVSOFT');
                $m->to($email_address, $email_data['name'])->subject($number_of_jobs.' job'.($number_of_jobs>1 ? 's' : '') . ' awaiting approval');
            });
        }
    }

    /**
     * Clean Up Job Description
     * --
     * @param $job_description
     * @return mixed|string|string[]|null
     */
    private function cleanupJobDescription($job_description){
    	$job_description = preg_replace('#<br\s*/?>#i', "\n", $job_description);
		$job_description = str_replace("</p>", "\n\n", $job_description);
		$job_description = str_replace("&rsquo;", "'", $job_description);
		$job_description = str_replace("&nbsp;", " ", $job_description);
		$job_description = preg_replace('#\R+#', "\n", $job_description);
		$job_description = strip_tags($job_description);
		$job_description = html_entity_decode(htmlspecialchars_decode($job_description));
		$job_description = trim($job_description);

        $job_description = preg_replace('/[\x00-\x08\x10\x0B\x0C\x0E-\x19\x7F]'.
 '|[\x00-\x7F][\x80-\xBF]+'.
 '|([\xC0\xC1]|[\xF0-\xFF])[\x80-\xBF]*'.
 '|[\xC2-\xDF]((?![\x80-\xBF])|[\x80-\xBF]{2,})'.
 '|[\xE0-\xEF](([\x80-\xBF](?![\x80-\xBF]))|(?![\x80-\xBF]{2})|[\x80-\xBF]{3,})/S',
 '--', $job_description );

//reject overly long 3 byte sequences and UTF-16 surrogates and replace with --
$job_description = preg_replace('/\xE0[\x80-\x9F][\x80-\xBF]'.'|\xED[\xA0-\xBF][\x80-\xBF]/S','--', $job_description );

		return $job_description;
    }
}
