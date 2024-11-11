<?php

namespace App\Http\Controllers;

use App\Repositories\FeatureSection\FeatureSectionInterface;
use App\Repositories\FeatureSectionList\FeatureSectionListInterface;
use App\Repositories\SchoolSetting\SchoolSettingInterface;
use App\Repositories\SystemSetting\SystemSettingInterface;
use App\Services\BootstrapTableService;
use App\Services\CachingService;
use App\Services\ResponseService;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Storage;
use Throwable;

class WebSettingsController extends Controller
{
    private CachingService $cache;
    private SystemSettingInterface $systemSettings;
    private FeatureSectionInterface $featureSection;
    private FeatureSectionListInterface $featureSectionList;
    private SchoolSettingInterface $schoolSettings;

    public function __construct(CachingService $cache, SystemSettingInterface $systemSettings, FeatureSectionInterface $featureSection, FeatureSectionListInterface $featureSectionList, SchoolSettingInterface $schoolSettings) {
        $this->cache = $cache;
        $this->systemSettings = $systemSettings;
        $this->featureSection = $featureSection;
        $this->featureSectionList = $featureSectionList;
        $this->schoolSettings = $schoolSettings;
    }
    
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        ResponseService::noPermissionThenRedirect('web-settings');

        $settings = $this->cache->getSystemSettings();
        return view('web_settings.index',compact('settings'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        ResponseService::noPermissionThenRedirect('web-settings');

        $request->validate([
            'hero_title_1'         => 'required',
            'hero_title_2'         => 'required',
            'about_us_title'         => 'required',
            'about_us_heading'         => 'required',
            'about_us_description'         => 'required',
            'about_us_points'         => 'required',
            'custom_package_description'         => 'required_if:custom_package_status,1',
            'download_our_app_description'         => 'required',
            'short_description'         => 'required'

        ],[
            'custom_package_description.required_if' => 'The custom package description field is required when custom package status is enable.'
        ]);
        

        $settings = array(
            'home_image', 'hero_title_1', 'hero_title_2', 'hero_title_2_image', 'about_us_title', 'about_us_heading', 'about_us_description', 'about_us_points', 'about_us_image', 'custom_package_status', 'custom_package_description', 'download_our_app_image', 'download_our_app_description', 'facebook', 'instagram', 'linkedin', 'footer_text','short_description', 'theme_primary_color',  'theme_secondary_color', 'theme_secondary_color_1',  'theme_primary_background_color', 'theme_text_secondary_color', 'display_school_logos', 'display_counters'
        );

        try {
            $data = array();
            foreach ($settings as $row) {
                if ($row == 'home_image' || $row == 'hero_title_2_image' || $row == 'about_us_image' || $row == 'download_our_app_image') {
                    if ($request->hasFile($row)) {
                        // TODO : Remove the old files from server
                        $data[] = [
                            "name" => $row,
                            "data" => $request->file($row),
                            "type" => "file"
                        ];
                    }
                } else {
                    $data[] = [
                        "name" => $row,
                        "data" => $request->$row,
                        "type" => "text"
                    ];
                }
            }
            $this->systemSettings->upsert($data, ["name"], ["data"]);
            $this->cache->removeSystemCache(config('constants.CACHE.SYSTEM.SETTINGS'));
            ResponseService::successResponse('Data Updated Successfully');
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, "Web Settings Controller -> Store method");
            ResponseService::errorResponse();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        //
    }

    public function feature_section_index()
    {
        ResponseService::noPermissionThenRedirect('web-settings');

        return view('web_settings.feature_section');
    }

    public function feature_section_store(Request $request)
    {
        ResponseService::noPermissionThenRedirect('web-settings');
        try {
            DB::beginTransaction();
            $feature_section_data = [
                'title' => $request->title,
                'heading' => $request->heading,
                'rank' => 0
            ];
            $featureSection = $this->featureSection->create($feature_section_data);
            $data = array();
            foreach ($request->section_data as $key => $section) {
                $data[] = [
                    'feature_section_id' => $featureSection->id,
                    'feature' => $section['feature'],
                    'description' => $section['description'],
                    'image' => $section['image']->store('feature_section','public'),
                ];
            }
            $this->featureSectionList->createBulk($data);
            DB::commit();
            ResponseService::successResponse('Data Stored Successfully');
        } catch (\Throwable $th) {
            DB::rollBack();
            ResponseService::logErrorResponse($th);
            ResponseService::errorResponse();
        }
    }

    public function web_settings_show()
    {
        ResponseService::noPermissionThenRedirect('web-settings');
        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'rank');
        $order = request('order', 'ASC');
        $search = request('search');

        $sql = $this->featureSection->builder()
            ->where(function ($query) use ($search) {
                $query->when($search, function ($q) use ($search) {
                    $q->where('id', 'LIKE', "%$search%")
                        ->orwhere('title', 'LIKE', "%$search%")
                        ->orwhere('heading', 'LIKE', "%$search%");
                });
            });
        $total = $sql->count();
        $sql = $sql->orderBy($sort, $order)->skip($offset)->take($limit);
        $res = $sql->get();

        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $no = 1;

        foreach ($res as $row) {            
            // $operate = BootstrapTableService::editButton(route('web-settings-section.update', $row->id));
            $operate = BootstrapTableService::button('fa fa-edit', route('web-settings-section.edit',$row->id), ['btn-gradient-info'], ['title' => trans("edit")]);
            $operate .= BootstrapTableService::deleteButton(route('web-settings-section.destroy', $row->id));
            
            $tempRow = $row->toArray();
            $tempRow['no'] = $no++;
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function web_settings_edit($id)
    {
        ResponseService::noPermissionThenRedirect('web-settings');
        $featureSection = $this->featureSection->builder()->with('feature_section_list')->where('id',$id)->first();

        return view('web_settings.feature_section_edit',compact('featureSection'));
        
    }

    public function web_settings_update(Request $request, $id)
    {
        ResponseService::noPermissionThenRedirect('web-settings');

        try {
            DB::beginTransaction();
            $feature_section_data = [
                'title' => $request->title,
                'heading' => $request->heading,
                'rank' => 0
            ];
            $featureSection = $this->featureSection->update($id, $feature_section_data);
            $data_with_image = array(); // with adding image [ update / create ]
            $data_without_image = array(); // without image
            foreach ($request->section_data as $key => $section) {
                // To check image select or not
                if (isset($section['image'])) {
                    // Check already exits record or not
                    if (isset($section['id'])) {
                        // If image & id found then delete old image file from storage
                        $section_feature = $this->featureSectionList->findById($section['id']);
                        if ($section_feature) {
                            if ($section_feature->image && Storage::disk('public')->exists($section_feature->getRawOriginal('image'))) {
                                Storage::disk('public')->delete($section_feature->getRawOriginal('image'));
                            }
                        }
                    }
                    // Set data with image
                    $data_with_image[] = [
                        'id' => $section['id'],
                        'feature_section_id' => $featureSection->id,
                        'feature' => $section['feature'],
                        'description' => $section['description'],
                        'image' => $section['image']->store('feature_section','public'),
                    ];
                } else {
                    // Set data without image
                    $data_without_image[] = [
                        'id' => $section['id'],
                        'feature_section_id' => $featureSection->id,
                        'feature' => $section['feature'],
                        'description' => $section['description'],
                        // 'image' => $section['image']->store('feature_section','public'),
                    ];
                }
                
            }
            // With image
            $this->featureSectionList->upsert($data_with_image, ['id'],['feature_section_id', 'feature', 'description', 'image']);
            // Without image
            $this->featureSectionList->upsert($data_without_image, ['id'],['feature_section_id', 'feature', 'description']);
            DB::commit();
            ResponseService::successResponse('Data Updated Successfully');
        } catch (\Throwable $th) {
            DB::rollBack();
            ResponseService::logErrorResponse($th);
            ResponseService::errorResponse();
        }

    }

    public function feature_section_delete($id)
    {
        ResponseService::noPermissionThenRedirect('web-settings');
        try {
            $this->featureSection->deleteById($id);
            ResponseService::successResponse('Data Deleted Successfully');
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function feature_section_rank(Request $request)
    {
        ResponseService::noPermissionThenSendJson('web-settings');

        $validator = Validator::make($request->all(), [
            'ids' => 'required',
        ], [
            'ids' => trans('No Data Found'),
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            $ids = json_decode($request->ids, false, 512, JSON_THROW_ON_ERROR);
            $update = [];
            foreach ($ids as $key => $id) {
                $update[] = [
                    'id' => $id,
                    'rank' => ($key + 1)
                ];
            }
            $this->featureSection->upsert($update, ['id'], ['rank']);
            DB::commit();
            ResponseService::successResponse('Rank Updated Successfully');
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, 'WebSettings Controller -> Change Rank method');
            ResponseService::errorResponse();
        }
    }
    
    public function school_index()
    {
        // $systemSettings = $this->cache->getSystemSettings();
        // if (isset($systemSettings['school_website_feature']) && $systemSettings['school_website_feature'] == 0) {
        //     return redirect('/');
        // }
        ResponseService::noFeatureThenRedirect('Website Management');
        ResponseService::noPermissionThenSendJson('school-web-settings');

        try {
            $settings = $this->cache->getSchoolSettings();

            return view('school-settings.web-page.index',compact('settings'));
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, 'WebSettings Controller -> School index  method');
            ResponseService::errorResponse();
        }
    }

    public function school_store(Request $request)
    {
        $systemSettings = $this->cache->getSystemSettings();
        if (isset($systemSettings['school_website_feature']) && $systemSettings['school_website_feature'] == 0) {
            return redirect('/');
        }
        ResponseService::noFeatureThenRedirect('Website Management');
        ResponseService::noPermissionThenSendJson('school-web-settings');
        $settings = [
            "primary_color" => 'required',
            "secondary_color" => 'required',
            "primary_background_color" => 'required',
            "text_secondary_color" => 'required',
            "primary_hover_color" => 'required',

            "about_us_title" => 'required_if:about_us_status,1',
            "about_us_heading" => 'required_if:about_us_status,1',
            "about_us_description" => 'required_if:about_us_status,1',
            "about_us_image" => 'nullable',
            "about_us_status" => 'required',

            "education_program_title" => 'required_if:education_program_status,1',
            "education_program_heading" => 'required_if:education_program_status,1',
            "education_program_description" => 'required_if:education_program_status,1',
            "education_program_status" => 'required',

            "expert_teachers_title" => 'required_if:expert_teachers_status,1',
            "expert_teachers_heading" => 'required_if:expert_teachers_status,1',
            "expert_teachers_description" => 'required_if:expert_teachers_status,1',
            "expert_teachers_status" => 'required',

            "faqs_title" => 'required_if:faqs_status,1',
            "faqs_heading" => 'required_if:faqs_status,1',
            "faqs_description" => 'required_if:faqs_status,1',
            "faqs_status" => 'required',

            "counter_title" => 'required_if:counter_status,1',
            "counter_heading" => 'required_if:counter_status,1',
            "counter_description" => 'required_if:counter_status,1',
            "counter_teacher" => 'nullable',
            "counter_student" => 'nullable',
            "counter_class" => 'nullable',
            "counter_stream" => 'nullable',
            "counter_status" => 'required',

            "our_mission_title" => 'required_if:our_mission_status,1',
            "our_mission_heading" => 'required_if:our_mission_status,1',
            "our_mission_description" => 'required_if:our_mission_status,1',
            "our_mission_points" => 'required_if:our_mission_status,1',
            "our_mission_image" => 'nullable',
            "our_mission_status" => 'required',

            "announcement_title" => 'required_if:announcement_status,1',
            "announcement_heading" => 'required_if:announcement_status,1',
            "announcement_description" => 'required_if:announcement_status,1',
            "announcement_image" => 'nullable',
            "announcement_status" => 'required',

            "gallery_title" => 'required_if:gallery_status,1',
            "gallery_heading" => 'required_if:gallery_status,1',
            "gallery_description" => 'required_if:gallery_status,1',
            "gallery_status" => 'required',

            "contact_us_heading" => 'required_if:contact_us_status,1',
            "contact_us_description" => 'required_if:contact_us_status,1',
            "contact_us_status" => 'required',

            "online_registration_title" => 'required_if:online_registration_status,1',
            "online_registration_heading" => 'required_if:online_registration_status,1',
            "online_registration_description" => 'required_if:aonline_registration_status,1',
            "online_registration_image" => 'nullable',
            "online_registration_status" => 'required',

            "short_description" => 'nullable',
            "footer_text" => 'nullable',
            "footer_logo" => 'nullable',
            

            "facebook" => 'nullable',
            "instagram" => 'nullable',
            "linkedin" => 'nullable',
            "twitter" => 'nullable',
        ];

        $validator = Validator::make($request->all(), $settings);
        
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            $data = array();
            foreach ($settings as $key => $rule) {
                $images = ['about_us_image', 'counter_teacher','counter_student', 'counter_class', 'counter_stream', 'announcement_image','our_mission_image','footer_logo', 'online_registration_image'];
                if (in_array($key, $images)) {
                    if ($request->hasFile($key)) {
                        // TODO : Remove the old files from server
                        $data[] = [
                            "name" => $key,
                            "data" => $request->file($key),
                            "type" => "file"
                        ];
                    }
                } else {
                    if ($request->$key) {
                        $data[] = [
                            "name" => $key,
                            "data" => $request->$key,
                            "type" => "string"
                        ];    
                    } else {
                        $data[] = [
                            "name" => $key,
                            "data" => 0,
                            "type" => "string"
                        ];
                    }
                    
                }
            }            
            $this->schoolSettings->upsert($data, ["name"], ["data"]);
            $this->cache->removeSchoolCache(config('constants.CACHE.SCHOOL.SETTINGS'));

            DB::commit();
            ResponseService::successResponse('Data Updated Successfully');
        } catch (\Throwable $th) {
            ResponseService::logErrorResponse($th);
            ResponseService::errorResponse();
        }
        


    }
}
