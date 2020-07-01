<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Application;
use App\Category;
use App\Ciiv;
use App\Client;
use App\Country;
use App\Config;
use App\Degree;
use App\Job;
use App\JobTitle;
use App\Company;
use App\University;
use App\Recruiter;
use App\Skill;
use DB;
use Session;
use Auth;

class JobsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

	public function index(){

		Job::clearLocks();

		$client = Session::get('client');
		$recruiter = Session::get('recruiter');
		$client_services = Session::get('client_services');

		if($client && $recruiter && in_array("FiiLTER", $client_services)){

		    $jobs = Job::select("jobs.*",
				// \DB::raw("(SELECT recruiters.name FROM recruiters
				// 	WHERE recruiters.id = jobs.approved_by
				// 	AND recruiters.client_id = ".$client->id.")
				// 	as approver"));
				\DB::raw("(SELECT users.name FROM users
					WHERE users.id = jobs.approved_by)
					as approver"),
				\DB::raw("(SELECT users.name FROM users
				WHERE users.id = jobs.locked_by)
				as locked_by")
			);

		    if(!isset($_GET['show']) || (isset($_GET['show']) && $_GET['show'] != "all")){
			    $job = $jobs->whereHas('recruiters', function ($query) use ($recruiter) {
					$query->where('recruiters.id', '=', $recruiter->id);
				});
			}

			$jobs = $jobs->where('jobs.client_id', '=', $client->id)->get();

			return view('jobs.index', compact('jobs', 'client', 'recruiter'));
		}

		abort(404);		
	}



	public function view($id){

		$client = Session::get('client');

		$criteria = [];
		$criteria[] = ['id', '=', $id];

		if(Auth::check() && !Auth::user()->isAdmin()){
			$criteria[] = ['client_id', '=', $client->id];
		}

		$job = Job::where($criteria)->first();

		if($job){
			return view('jobs.view', compact('job'));
		}

		abort(404);
	}



	public function analysis($id){

		if(Auth::check() && Auth::user()->isAdmin()) {
			$client = Session::get('client');

			$criteria = [];
			$criteria[] = ['id', '=', $id];
			$criteria[] = ['client_id', '=', $client->id];

			$job = Job::where($criteria)->first();

			if($job){

				$applications = Application::where([
					['job_id', '=', $job->job_id],
					['client_id', '=', $client->id]
				]);

				return view('jobs.analysis', compact('job', 'applications'));

			}
		}

		abort(404);
	}


	public function approveForm($id) {

		$is_admin = ((Auth::check() && Auth::user()->isAdmin()) ? 1 : 0);
		$client = Session::get('client');
		$recruiter = Session::get('recruiter');

		if(($client && $client->approved_by_client) || $is_admin) {

			$criteria = [];
			$criteria[] = ['id', '=', $id];
			if(!$is_admin){
				$criteria[] = ['client_id', '=', $client->id];
			}
			$criteria[] = ['active', '=', 1];
			$criteria[] = ['approved', '=', 0];

			/*if((Auth::check() && Auth::user()->isAdmin()) || $recruiter){

			    $job = Job::whereHas('recruiters', function ($query) use ($recruiter) {
					$query->where('recruiters.id', '=', $recruiter->id);
				})->where($criteria)->first();

			}
			else{*/

				$job = Job::where($criteria)->first();

			//}

			if($job){

				if(!$job->locked_by || $job->locked_by == Auth::user()->id){
					$countries = Country::orderBy('position', 'desc')->orderBy('country', 'asc')->get();

					$categories = Category::orderBy('name', 'asc')->get();

					return view('jobs.approve', compact('job', 'recruiter', 'countries', 'categories'));
				}
				else{
					return response('This record is currently locked', 200);
				}

			}
		}

		abort(404);
	}

	public function approveNormalizedForm($id)
	{


		//$a = "1+3+[2||4||5||6]";
		//preg_match('#\[(.*?)\]#', $a, $match);
		//$match = explode("+", $a);
		//print_r($match);exit;

		$is_admin = ((Auth::check() && Auth::user()->isAdmin()) ? 1 : 0);
		$client = Session::get('client');
		$recruiter = Session::get('recruiter');

		if(($client && $client->approved_by_client) || $is_admin) {

			$criteria = [];
			$criteria[] = ['id', '=', $id];
			if(!$is_admin){
				$criteria[] = ['client_id', '=', $client->id];
			}
			$criteria[] = ['active', '=', 1];
			$criteria[] = ['approved', '=', 0];

			$job = Job::where($criteria)->first();

			//Grab the job client details
			$job_client = Client::find($job->client_id);			

			//$normalized_job_titles = Ciiv::all();	

			$normalized_job_titles = DB::table("configs")->where("client_id", $job->client_id)
										->join("ciiv_job_titles","ciiv_job_titles.id", "=", "configs.job_title_id")
										->select("ciiv_job_titles.*")
										->orderBy("ciiv_job_titles.job_title", 'asc')
										->get();			
			if($job){

				if(!$job->locked_by || $job->locked_by == Auth::user()->id){
					$countries = Country::orderBy('position', 'desc')->orderBy('country', 'asc')->get();

					$categories = Category::orderBy('name', 'asc')->get();

					$critical_skills = Skill::select('skill')
											->join('job_skill', 'job_skill.skill_id', '=', 'skills.id')
											->where('job_skill.type', '=', "C")
											->where('job_skill.job_id', '=', $job->id)
											->get();

					// grab config details
					if(!is_null($job->config_id)){
						$config = Config::where('id', $job->config_id)->first();
						$config_skills = $config->skills->pluck('skill')->toArray();	
						//dd($config_skills);
						$config_job_title = $config->job_titles->pluck('title')->toArray();
						$config_company = $config->company->pluck('name')->toArray();
						$config_university = $config->universities->pluck('university')->toArray();
						$config_degree = $config->degrees->pluck('degree')->toArray();
										
					} else {
						$config = "";
						$config_skills = "";						
						$config_job_title = "";
						$config_company = "";
						$config_university = "";
						$config_degree = "";
					}
					return view('jobs.normalized', compact('job', 'recruiter', 'countries', 'categories', 'normalized_job_titles', 'job_client', 'critical_skills', 'config', 'config_skills', 'config_job_title', 'config_company', 'config_university', 'config_degree'));
				}
				else{
					return response('This record is currently locked', 200);
				}

			}
		}
		abort(404);
	}

	public function getNormalizedFormData($id)
	{
		
		// Grab config
		$get_config = Config::Where('job_title_id', $id)->first();
		$config_id = $get_config->id;

		$json = [];

		// Grab Skills
		$get_skills = DB::table("config_skills")->where("config_id", $config_id)
							->join("skills","skills.id", "=", "config_skills.skill_id")
							->select("skills.*")
							->get()
							->toArray();
		$json['skills'] = $get_skills;
		// Grab universities
		$get_universities = DB::table("config_universities")->where("config_id", $config_id)
							->join("universities","universities.id", "=", "config_universities.university_id")
							->select("universities.*")
							->get()
							->toArray();
		$json['universities'] = $get_universities;
		// Grab companies
		$get_companies = DB::table("config_companies")->where("config_id", $config_id)
							->join("companies","companies.id", "=", "config_companies.companies_id")
							->select("companies.*")
							->get()
							->toArray(); 
		$json['companies'] = $get_companies;
		// Grab job titles
		$get_job_titles = DB::table("config_job_titles")->where("config_id", $config_id)
							->join("job_titles","job_titles.id", "=", "config_job_titles.job_titles_id")
							->select("job_titles.*")
							->get()
							->toArray(); 
		$json['jobtitles'] = $get_job_titles;

		// Grab degree
		$get_degree = DB::table("config_degrees")->where("config_id", $config_id)
							->join("degrees","degrees.id", "=", "config_degrees.degree_id")
							->select("degrees.*")
							->get()
							->toArray(); 
		$json['degree'] = $get_degree;

		// Grab the frequencys set
		$json['skill_count'] = $get_config->skill_count;
		$json['jobtitle_count'] = $get_config->jobtitle_count;
		$json['company_count'] = $get_config->company_count;
		$json['university_count'] = $get_config->university_count;
		$json['degree_count'] = $get_config->degree_count;
		$json['config_logic'] = $get_config->config_logic;
		$json['experience'] = $get_config->experience / 12;

		return json_encode($json);
		//print_r($json);

	}

	public function update(Request $request, $id)
	{ // Update/Approve Form

		//$a = "1+3+[2||4||5||6]";
		//preg_match('#\[(.*?)\]#', $a, $match);
		//$match = explode("+", $a);
		//print_r($match);exit;
		//dd($request);

		$is_admin = ((Auth::check() && Auth::user()->isAdmin()) ? 1 : 0);

		$client = Session::get('client');
		$recruiter = Session::get('recruiter');

		//dd($request);

		if(Auth::check() && Auth::user()->isAdmin()){
			$redirect_back = "/jobs-admin";
		}
		else{
			$redirect_back = "/jobs";
		}

		if($client || $is_admin){			

			try{

				if($is_admin){
					$recruiter_id = Auth::user()->id;
				}
				else if($recruiter){
					$recruiter_id = $recruiter->id;
				}

				$this->validate(request(), [
					'title' => 'required|string|min:3',
					'location' => 'required|string|min:3',
					'experience' => 'required|integer'
				]);

				$criteria = [];
				$criteria[] = ['id', '=', $id];
				if(!$is_admin){
					$criteria[] = ['client_id', '=', $client->id];
				}
				
				$job = Job::where($criteria)->first();

				if($job){
					//dd($request);
					// Add job titles tht doesn't exist
					$attach_job_titles = [];
						if(trim(request('added_job_titles'))){
							$added_job_titles = explode(",", trim(request('added_job_titles')));

							// To prevent attach() on skills that are already attached to job
							// otherwise it will cause an error
							$added_job_titles = array_diff($added_job_titles, $job->job_titles()->pluck('title')->toArray());

							$added_job_titles_db = JobTitle::checkAddJobTitles($added_job_titles); // added by user & not verified
							$added_job_titles_db = array_map('strtolower', $added_job_titles_db);


							
							foreach ($added_job_titles_db as $added_key => $added_value) {
								if(in_array($added_value, $added_job_titles)){
									$attach_job_titles[] = $added_key;
								}								
							}
						}

						if($attach_job_titles){
							$job->job_titles()->attach($attach_job_titles);
						}


					// Add skills that dont exist in the database
					$required_skills = [];
					$desirable_skills = [];
					$critical_skills = [];

					if(request('required_skills')  != "" ){
						$required_skills = explode(",", trim(request('required_skills')));
					}

					if(request('desirable_skills')  != "" ){
						$desirable_skills = explode(",", trim(request('desirable_skills')));
					}

					if(request('critical_skills')  != "" ){

						$critical_skills = explode(",", trim(request('critical_skills')));
						//$critical_skills = array_map('strtolower', $critical_skills);
						//$critical_added_skills_db = Skill::checkAddSkills($critical_skills, 'external', 1);
					}

					$delete_skills = explode(",", trim(request('delete_skills')));
					$delete_skill_ids = Skill::setDeleteSkills($delete_skills);

					//****************************
					// New skills added by recruiter
						$attach_skills = [];
						if(trim(request('added_skills'))){
							$added_skills = explode(",", trim(request('added_skills')));

							// To prevent attach() on skills that are already attached to job
							// otherwise it will cause an error
							$added_skills = array_diff($added_skills, $job->skills()->pluck('skill')->toArray());

							$added_skills_db = Skill::checkAddSkills($added_skills, 'user', 0); // added by user & not verified

							foreach ($added_skills_db as $added_key => $added_value) {
								if(in_array($added_value, $required_skills)){
									$attach_skills[$added_key] = ['type' => 'R'];
								}
								else if(in_array($added_value, $desirable_skills)){
									$attach_skills[$added_key] = ['type' => 'D'];
								}else if(in_array($added_value, $critical_skills)){
									$attach_skills[$added_key] = ['type' => 'C'];
								}
							};
						}

						

						if($attach_skills){
							$job->skills()->attach($attach_skills);
						}
					//****************************

					$job->title = trim(request('title'));
					$job->location = trim(request('location'));
					$job->country = request('country');
					$job->experience = request('experience');
					$job->approved_by = Auth::user()->id;
					$job->approved_at = \Carbon\Carbon::now()->format('Y-m-d H:i:s');
					if(request('filter_logic')){
						$job->filter_logic = request('filter_logic');
					}
					$job->approved = 1;
					
					$job->consider_current_employer = (int)request('consider_current_employer');
					$job->consider_work_experience = (int)request('consider_work_experience');

					$job->category_id = request('category');
					$job->location_condition = (request('locationstatus')!=""?request('locationstatus'):0);
					$job->experience_condition = (request('expstatus')!=""?request('expstatus'):0);
					$job->skill = (request('nskillstatus')!=""?request('nskillstatus'):0);
					$job->job_title = (request('jobtstatus')!=""?request('jobtstatus'):0);
					$job->companie = (request('compstatus')!=""?request('compstatus'):0);
					$job->university = (request('unistatus')!=""?request('unistatus'):0);
					$job->degree = (request('degreestatus')!=""?request('degreestatus'):0);

					/*$geocoder = geocode($job->location.", ".$job->country);
					if($geocoder){
						$geo_coordinates = $geocoder['geometry']['location'];
						$job->latitude = $geo_coordinates['lat'];
						$job->longitude = $geo_coordinates['lng'];
					}
					else{
						return back()->with('error','Invalid Location');
					}*/
					if(!empty(request('geocode'))){
						$geocode = explode(",", request('geocode'));
						$job->latitude = $geocode[0];
						$job->longitude = $geocode[1];
					}
					else{
						$job->latitude = null;
						$job->longitude = null;
					}

					$job->save();

					// Attach skills to job via pivot table
					if(request('required_skills') != ""){
						$required_skill_ids = Skill::getSkills($required_skills, 'id');	
						foreach ($required_skill_ids as $skill_key) {
							$job->skills()->updateExistingPivot($skill_key, ['type' => 'R']);
						}
					}
					if(request('desirable_skills') != ""){
						$desirable_skill_ids = Skill::getSkills($desirable_skills, 'id');
						foreach ($desirable_skill_ids as $skill_key) {
							$job->skills()->updateExistingPivot($skill_key, ['type' => 'D']);
						}
					}
					if(request('critical_skills') != ""){
						
						$critical_skill_ids = Skill::getSkills($critical_skills, 'id');
					
						foreach ($critical_skill_ids as $skill_key) {
							$job->skills()->updateExistingPivot($skill_key, ['type' => 'C']);
						}
					}
					
					// Attach job titles
					if(request('required_job_titles')) {

						$required_job_titles = explode(",", trim(request('required_job_titles')));
						$required_job_titles_ids = JobTitle::getJobTitles($required_job_titles, 'id');
						
						foreach ($required_job_titles_ids as $jobtitle_key)
						{
							//$job->job_titles()->updateExistingPivot($jobtitle_key);

						}

					}

					// Attach companies
					if(request('required_companies')) {

						$required_companies = explode(",", trim(request('required_companies')));
						$required_companies_ids = Company::getCompanies($required_companies, 'id');
						
						foreach ($required_companies_ids as $companies_key)
						{							
							\DB::table('job_company')->insert([
								'job_id' => $job->id,
								'company_id' => $companies_key]);
						}

					}

					// Attach universities
					if(request('required_universities')) {

						$required_universities = explode(",", trim(request('required_universities')));
						$required_universities_ids = University::getUniversities($required_universities, 'id');
						//dd($required_companies_ids);
						foreach ($required_universities_ids as $universities_key)
						{							
							$job->universities()->attach($universities_key);
						}

					}

					if(request('njobtitle'))
					{
						$normalized_job_ids = request('njobtitle');
						$getConfigs = \DB::table('configs')
										->where("job_title_id", $normalized_job_ids)
										->get();
						$configs = Config::find($getConfigs[0]->id);
						$job->config_id = $configs->id;
						$job->save();
					}
					
				// If save to config enabled
				if(request('save_configurations') !=0) {

					if(request('save_configurations') == 1){

						$normalized_job_id = request('njobtitle');
						$getConfig = \DB::table('configs')
										->where("job_title_id", $normalized_job_id)
										->get();
						$config = Config::find($getConfig[0]->id);


						// Detach as per supplied info

						// Attach job titles
						if(request('required_job_titles')) {

							$config->job_titles()->detach();
							$config->job_titles()->attach($required_job_titles_ids, ["frequency" => 1]);						
						}

						// Attach companies
						if(request('required_companies')) {

							$config->company()->detach();
							$config->company()->attach($required_companies_ids, ["frequency" => 1]);						
						}

						// Attach universities
						if(request('required_universities')) {

							$config->universities()->detach();
							$config->universities()->attach($required_universities_ids, ["frequency" => 1]);						
						}
						// Attach degrees
						if(request('required_degree')) {

							$config->degrees()->detach();
							$required_degree = explode(",", request('required_degree'));
							$required_degree_ids = Degree::getDegrees($required_degree, 'id');					
							$config->degrees()->attach($required_degree_ids, ["frequency" => 1]);				
						}

						// Attach skills
						$config->skills()->detach();
						if(request('required_skills') != ""){

							if(is_array($required_skill_ids)) {
								
								$config->skills()->attach($required_skill_ids, ["frequency" => 1, "type" => "R"]);
								
							}
						}

						if(request('desirable_skills') != ""){

							if(is_array($desirable_skill_ids)) {
								$config->skills()->attach($desirable_skill_ids, ["frequency" => 1, "type" => "D"]);
							}

						}
						if(request('critical_skills') != ""){
							if(is_array($critical_skill_ids)) {
								$config->skills()->attach($critical_skill_ids, ["frequency" => 1, "type" => "C"]);
							}
						}

						$config->skill_count = request('skill_count');
						$config->jobtitle_count = request('jobtitle_count');
						$config->company_count = request('company_count');
						$config->university_count = request('university_count');
						$config->degree_count = request('degree_count');
						$config->config_logic = request('config_logic');
						$config->parent = 0;
						$config->location = trim(request('location'));
						$config->dept_id = $job->department_id;
						$config->save();

					}else {

						//create the normalized job title
						$ciiv_job_titles = new Ciiv;
						$ciiv_job_titles->job_title = request('cnjobtitle');
						$ciiv_job_titles->verified = 1;
						$ciiv_job_titles->save();

						//Create the config
						$_config = new Config;
						$_config->client_id = $job->client_id;
						$_config->experience = $job->experience * 12;
						$_config->job_title_id = $ciiv_job_titles->id;
						$_config->skill_count = request('skill_count');
						$_config->jobtitle_count = request('jobtitle_count');
						$_config->company_count = request('company_count');
						$_config->university_count = request('university_count');
						$_config->config_logic = request('config_logic');
						$_config->parent = 0;
						$_config->dept_id = $job->department_id;
						$_config->location = trim(request('location'));
						$_config->save();

						// Attach job titles
						if(request('required_job_titles')) {

							$_config->job_titles()->detach();
							$_config->job_titles()->attach($required_job_titles_ids, ["frequency" => 1]);						
						}

						// Attach companies
						if(request('required_companies')) {

							$_config->company()->detach();
							$_config->company()->attach($required_companies_ids, ["frequency" => 1]);						
						}

						// Attach universities
						if(request('required_universities')) {

							$_config->universities()->detach();
							$_config->universities()->attach($required_universities_ids, ["frequency" => 1]);						
						}

						// Attach degrees
						if(request('required_degree')) {

							$_config->degrees()->detach();
							$required_degree = explode(",", request('required_degree'));
							$required_degree_ids = Degree::getDegrees($required_degree, 'id');					
							$_config->degrees()->attach($required_degree_ids, ["frequency" => 1]);				
						}

						// Attach skills
						$_config->skills()->detach();
						if(request('required_skills') != ""){

							if(is_array($required_skill_ids)) {
								
								$_config->skills()->attach($required_skill_ids, ["frequency" => 1, "type" => "R"]);
								
							}
						}

						if(request('desirable_skills') != ""){

							if(is_array($desirable_skill_ids)) {
								$_config->skills()->attach($desirable_skill_ids, ["frequency" => 1, "type" => "D"]);
							}

						}
						if(request('critical_skills') != ""){
							if(is_array($critical_skill_ids)) {
								$_config->skills()->attach($critical_skill_ids, ["frequency" => 1, "type" => "C"]);
							}
						}

						$job->config_id = $_config->id;
						$job->save();
					}

				}

					/*$job->skills()->detach($attach_skills);
					$job->skills()->attach($attach_skills);*/

					return redirect($redirect_back)->with('success','Job "'.$job->title.'" (ID: '.$id.') approved');
				}
				else{
					abort(404);
				}
			}
			catch (QueryException $e){
				$errorCode = $e->errorInfo;
				return redirect($redirect_back)->with('error', $errorCode[1].': '.$errorCode[2]);
			}
		}
	}


	public function ignore($id){ // Ignore Job

		$client = Session::get('client');

		if(Auth::check() && Auth::user()->isAdmin()){
			$redirect_back = "/jobs-admin";
		}
		else{
			$redirect_back = "/jobs";
		}

		if($client){
			try{

				/*if(!isset($client_session['recruiter_id'])){
					return back()->with('error','You cannot change the status of this job');
				}*/

				$job = Job::where('id', $id);
				if(Auth::check() && !Auth::user()->isAdmin()) {
					$job->where('client_id', $client->id);
				}
				$job->update(['ignore' => 1]);

				return redirect($redirect_back)->with('success','Job set to ignored');
			}
			catch (QueryException $e){
				$errorCode = $e->errorInfo;
				return redirect($redirect_back)->with('error', $errorCode[1].': '.$errorCode[2]);
			}
		}
	}

	public function unignore($id){ // Ignore Job

		$client = Session::get('client');

		if(Auth::check() && Auth::user()->isAdmin()){
			$redirect_back = "/jobs-admin";
		}
		else{
			$redirect_back = "/jobs";
		}

		if($client){
			try{

				$job = Job::where('id', $id);
				if(Auth::check() && !Auth::user()->isAdmin()) {
					$job->where('client_id', $client->id);
				}
				$job->update(['ignore' => 0]);

				return redirect($redirect_back)->with('success','Job unignored');
			}
			catch (QueryException $e){
				$errorCode = $e->errorInfo;
				return redirect($redirect_back)->with('error', $errorCode[1].': '.$errorCode[2]);
			}
		}
	}


	public function adminJobApproval(){

		if(Auth::check() && Auth::user()->isAdmin()) {

			Job::clearLocks('all');

			$jobs = Job::select("jobs.*",
				\DB::raw("(SELECT users.name FROM users
				WHERE users.id = jobs.locked_by)
				as locked_by")
			);

			$jobs = $jobs->where([
				['active', '=', 1],
				['approved', '=', 0]
			])->orderBy('updated_at', 'asc')->get();

			return view('jobs.admin', compact('jobs'));
		}

		abort(404);
	}


	public function lockRecord($id){
		\Debugbar::disable();

		$criteria = [];
		$criteria[] = ['id', '=', $id];

		if(Auth::check() && !Auth::user()->isAdmin()) {
			$client = Session::get('client');

			$criteria[] = ['client_id', '=', $client->id];
		}
		
		$record = Job::where($criteria)->first();

		$record->locked_at = date('Y-m-d H:i:s');
		$record->locked_by = Auth::user()->id;
		$record->save();

		return 1;
	}


    /**
     * Create a new job manually
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function createNewJob()
    {
        $is_admin = ((Auth::check() && Auth::user()->isAdmin()) ? 1 : 0);

        $client = Session::get('client');

        $recruiter = Session::get('recruiter');

        if (($client && $client->approved_by_client) || $is_admin) {

            $countries = Country::orderBy('position', 'desc')->orderBy('country', 'asc')->get();
            $categories = Category::orderBy('name', 'asc')->get();

            // Get all clients except the current client
            $clients = Client::where('id', '!=', $client->id)->get();

            return view('jobs.create',
                [
                    'recruiter' => $recruiter,
                    'countries' => $countries,
                    'categories' => $categories,
                    'client' => $client,
                    'clients' => $clients
                ]
            );
        }

        abort(404);
    }


    /**
     * Save manual entered jobs
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws \Throwable
     */
    public function saveNewJob(Request $request)
    {
        // Basic input validation
        $this->validate(request(), [
            'title' => 'required|string|min:3',
            'location' => 'required|string|min:3',
            'experience' => 'required|integer',
            'description' => 'required|string|min:3',
        ]);

        // Create the job record
        $job = new Job();
        $job->client_id = $request->client;
        $job->title = $request->title;
        $job->description = $request->description;
        $job->experience = $request->experience;
        $job->location = $request->location;
        $job->consider_current_employer = $request->consider_current_employer;
        $job->consider_work_experience = $request->consider_work_experience;
        $job->record_id = time();
        $job->job_id = time();
        $job->active = 1;
        $job->live = 0;
        $job->from_ats = 0; // signifies this was entered manually via the form
        $job->created_at = date('Y-m-d H:i:s');
		$job->updated_at = date('Y-m-d H:i:s');
		
		if(!empty($request->geocode)){
			$geocode = explode(",", $request->geocode);
			$job->latitude = $geocode[0];
			$job->longitude = $geocode[1];
		}
		else{
			$job->latitude = null;
			$job->longitude = null;
		}

        // Grab the required skills from the request
        $required_skills = explode(",", trim($request->required_skills));
        $desired_skills = explode(",", trim($request->desirable_skills));

        // Get the skills ids from the required skills array
        $required_skills_ids = Skill::getSkills($required_skills, 'id');
        $desired_skills_ids = Skill::getSkills($desired_skills, 'id');

        $skills = [];

        // Add the skill types to the skills
        foreach ($required_skills_ids as $key => $value){
            $skills[$value] = ['type' => 'R'];
        }

        foreach ($desired_skills_ids as $key => $value){
            $skills[$value] = ['type' => 'D'];
        }

        // Save the job
        $job->save();

        // Attach skills to job via pivot table
        $job->skills()->sync($skills);

        // Just return to the jobs page for now
        return redirect('/jobs');
    }


	public function getGeocode(Request $request){
		\Debugbar::disable();

		$geocoder = geocode($request->city.", ".$request->country);
		if($geocoder){
			$geo_coordinates = $geocoder['geometry']['location'];
			return $geo_coordinates['lat'].",".$geo_coordinates['lng'];
		}
		else{
			return "Error";
		}
	}


    public function getSkillsFromDescription(Request $request)
    {
		\Debugbar::disable();

        $db_skills = \App\Skill::where('verified', 1)->pluck('id','skill')->toArray();
        $db_skills = foreignCharKeyConv($db_skills);
        $existing_skills = skillsMatchCount($request->description, array_keys($db_skills));
        return $existing_skills;
	}
	
	
}
