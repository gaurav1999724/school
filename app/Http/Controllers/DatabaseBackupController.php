<?php

namespace App\Http\Controllers;

use App\Models\CertificateTemplate;
use App\Models\ClassGroup;
use App\Models\ExpenseCategory;
use App\Models\Faq;
use App\Models\FeesType;
use App\Models\File as ModelsFile;
use App\Models\FormField;
use App\Models\Grade;
use App\Models\Holiday;
use App\Models\Mediums;
use App\Models\PaymentTransaction;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\Section;
use App\Models\Semester;
use App\Models\SessionYear;
use App\Models\Shift;
use App\Models\Slider;
use App\Models\Staff;
use App\Models\Stream;
use App\Models\Students;
use App\Models\Subscription;
use App\Models\User;
use App\Repositories\DatabaseBackup\DatabaseBackupInterface;
use App\Services\BootstrapTableService;
use App\Services\ResponseService;
use App\Services\SubscriptionService;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;
use ZipArchive;

class DatabaseBackupController extends Controller
{
    //

    private DatabaseBackupInterface $databaseBackup;
    private SubscriptionService $subscriptionService;

    public function __construct(DatabaseBackupInterface $databaseBackup, SubscriptionService $subscriptionService) {
        $this->databaseBackup = $databaseBackup;
        $this->subscriptionService = $subscriptionService;
        
    }

    public function index()
    {

        return view('database-backup.index');
    }

    public function store()
    {
        ResponseService::noPermissionThenSendJson('database-backup');
        // Database backup
        $schoolId = Auth::user()->school_id;
        $allTables = DB::select('SHOW TABLES');
        $tableNames = array_map('current', $allTables);

        $expectedTables = ['addons','attachments','categories','chats','failed_jobs','features','feature_sections','feature_section_lists','guidances','languages','messages','migrations','packages','package_features','password_resets','personal_access_tokens','staff_support_schools','system_settings','user_status_for_next_cycles','database_backups','schools'];

        $subscription = $this->subscriptionService->active_subscription(Auth::user()->school_id);

        $allTables = array_diff($tableNames, $expectedTables);
        $tableNames = array_values($allTables);

        $staff_ids = Staff::whereHas('user', function($q) use($schoolId) {
            $q->where('school_id', $schoolId);
        })->pluck('id')->toArray();

        $students = Students::where('school_id',$schoolId)->withTrashed()->get();
        $student_ids = $students->pluck('user_id')->toArray();
        $guardian_ids = $students->pluck('guardian_id')->toArray();

        $roles_id = Role::where('school_id',Auth::user()->school_id)->pluck('id')->toArray();

        $guardian_ids = array_values(array_unique($guardian_ids));
        // Tables requiring additional conditions
        $tablesWithAdditionalConditions = [
            'users' => function($query) use ($schoolId, $guardian_ids, $subscription) {
                $query->where(function($q) use($schoolId, $guardian_ids) {
                    $q->where('school_id', $schoolId)
                    ->orWhereIn('id', $guardian_ids);
                })
                ->where(function($q) use($subscription) {
                    $q->WhereNot('id', $subscription->school->admin_id)
                    ->where('id',Auth::user()->id);
                }); 
            },
            'fees_advance' => function($query) use ($guardian_ids) {
                $query->whereIn('parent_id',$guardian_ids);
            },
            'staffs' => function($query) use($staff_ids) {
                $query->whereIn('id', $staff_ids);
            },
            'staff_salaries' => function($query) use($staff_ids) {
                $query->whereIn('staff_id', $staff_ids);
            },
            'model_has_roles' => function($query) use($roles_id) {
                $query->whereIn('role_id', $roles_id);
            },

            'role_has_permissions' => function($query) use($roles_id) {
                $query->whereIn('role_id', $roles_id);
            },
            'subscription_features' => function($query) use($subscription) {
                $query->where('subscription_id', $subscription->id);
            },
            // Add more specific tables here
        ];

        $backupData = '';

        foreach ($tableNames as $table) {

            // Get table creation SQL
            // $createTableSQL = DB::select("SHOW CREATE TABLE `$table`");
            // $backupData .= $createTableSQL[0]->{'Create Table'} . ";\n\n";

            // ==========================================================
            $createTableSQL = DB::select("SHOW CREATE TABLE `$table`");
            $createTable = $createTableSQL[0]->{'Create Table'};

            // Replace 'CREATE TABLE' with 'CREATE TABLE IF NOT EXISTS'
            $createTableWithIfNotExists = str_replace('CREATE TABLE', 'CREATE TABLE IF NOT EXISTS', $createTable);

            // Add the modified SQL to the backup
            $backupData .= $createTableWithIfNotExists . ";\n\n";
            // ==========================================================

            // Create query builder
            $query = DB::table($table);

            // Check if 'school_id' column exists
            $hasSchoolIdColumn = Schema::hasColumn($table, 'school_id');

            // Apply conditions
            if ($hasSchoolIdColumn) {
                if (!array_key_exists($table, $tablesWithAdditionalConditions)) {
                    $query->where('school_id', $schoolId);
                } else {
                    // Apply specific conditions
                    $tablesWithAdditionalConditions[$table]($query);
                }
            } else {
                // Apply specific conditions if necessary
                if (array_key_exists($table, $tablesWithAdditionalConditions)) {
                    $tablesWithAdditionalConditions[$table]($query);
                }
            }

            // Fetch rows and build SQL for inserts
            $rows = $query->get();
            
            foreach ($rows as $row) {
                $values = array_map(function ($value) {
                    return is_null($value) ? 'NULL' : "'" . addslashes($value) . "'";
                }, (array) $row);

                $backupData .= "INSERT INTO `$table` VALUES (" . implode(', ', $values) . ");\n";
            }
            $backupData .= "\n";
        }

        // Define the path
        $path = 'public/database-backup/'.Auth::user()->school_id;
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $file_name = "database_backup_{$schoolId}_{$timestamp}";
        $filePath = $path . "/database_backup_{$schoolId}_{$timestamp}.sql";

        // Create the directory if it doesn't exist
        if (!Storage::exists($path)) {
            Storage::makeDirectory($path);
        }

        // Save the backup to the file
        Storage::put($filePath, $backupData);
        // End database backup
        // ==========================================================

        $zip = new ZipArchive;
        $zipFileName = storage_path('app/public/database-backup/'.Auth::user()->school_id.'/'.$file_name.'.zip');
        $mainFolder = Auth::user()->school_id; // Main folder name
        $mainFolderPath = storage_path('app/public/' . $mainFolder); // Path to the main folder

        if ($zip->open($zipFileName, ZipArchive::CREATE) === TRUE) {
            if (is_dir($mainFolderPath)) {
                // Folder exists
                // Add main folder to the zip
                $zip->addEmptyDir($mainFolder);

                // Get all subdirectories inside the main folder
                // dd(File::directories($mainFolderPath));
                $subfolders = File::directories($mainFolderPath);
                // $subfolders = File::allDirectories($mainFolderPath);
                foreach ($subfolders as $subfolderPath) {
                    // Get the relative path of the subfolder
                    $relativeSubfolder = str_replace(storage_path('app/public/'), '', $subfolderPath);

                    // Add the subfolder to the zip
                    $zip->addEmptyDir($relativeSubfolder);

                    // Get all files inside the current subfolder
                    $files = File::files($subfolderPath);

                    foreach ($files as $file) {
                        // Get the relative path of the file
                        $relativeFile = str_replace(storage_path('app/public/'), '', $file);

                        // Add the file to the zip with its relative path
                        $zip->addFile($file, $relativeFile);
                    }
                }

                // Add files in the main folder
                $mainFolderFiles = File::files($mainFolderPath);
                foreach ($mainFolderFiles as $file) {
                    // Get the relative path of the file
                    $relativeFile = str_replace(storage_path('app/public/'), '', $file);

                    // Add the file to the zip
                    $zip->addFile($file, $relativeFile);
                }

                // Close the archive
                $zip->close();    
            }
            
            $data = [
                'name' => $file_name
            ];
            $this->databaseBackup->create($data);
            ResponseService::successResponse('Backup completed successfully');
        } else {
            ResponseService::logErrorResponse("DatabaseBackup Controller -> Store Method");
            ResponseService::errorResponse();
        }
        
        
    }

    public function show()
    {
        ResponseService::noPermissionThenRedirect('database-backup');
        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'id');
        $order = request('order', 'DESC');
        $search = request('search');
        
        $sql = $this->databaseBackup->builder()
            ->where(function ($query) use ($search) {
                $query->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('id', 'LIKE', "%$search%")->orwhere('title', 'LIKE', "%$search%")->orwhere('description', 'LIKE', "%$search%")->orwhere('date', 'LIKE', "%$search%");
                });
                });
            });

        $total = $sql->count();

        $sql->orderBy($sort, $order)->skip($offset)->take($limit);
        $res = $sql->get();

        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $no = 1;
        foreach ($res as $row) {
            $operate = BootstrapTableService::button('fa fa-refresh', '#', ['restore-database', 'btn-gradient-info'], ['title' => trans("restore"), 'data-id' => $row->id]);
            $operate .= BootstrapTableService::deleteButton(url('database-backup', $row->id));
            
            $tempRow = $row->toArray();
            $tempRow['no'] = $no++;
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }
        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function destroy($id)
    {
        ResponseService::noPermissionThenSendJson('database-backup');
        try {
            $databaseBackup = $this->databaseBackup->findById($id);
            $sql_file = 'database-backup/'.Auth::user()->school_id.'/'.$databaseBackup->name.'.sql';
            $zip_file = 'database-backup/'.Auth::user()->school_id.'/'.$databaseBackup->name.'.zip';
            
            
            if (Storage::disk('public')->exists($sql_file)) {
                Storage::disk('public')->delete($sql_file);
            }
            if (Storage::disk('public')->exists($zip_file)) {
                Storage::disk('public')->delete($zip_file);
            }
            

            $this->databaseBackup->deleteById($id);
            ResponseService::successResponse('Data Deleted Successfully');
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e, "DatabaseBackup Controller -> Destroy Method");
            ResponseService::errorResponse();
        }
    }

    public function restore($id)
    {
        try {

            
            $database = $this->databaseBackup->findById($id);
            $subscription = $this->subscriptionService->active_subscription($database->school_id);

            if (!$subscription) {
                ResponseService::errorResponse('No active plan found Please subscribe to continue');
            }
            DB::beginTransaction();


            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            $sql_file = 'database-backup/'.$database->school_id.'/'.$database->name.'.sql';
            $zip_file = 'database-backup/'.$database->school_id.'/'.$database->name.'.zip';

            // Check if the file exists
            if (Storage::disk('public')->exists($sql_file)) {

                // Delete old records
                $this->deleteOldRecords($subscription);

                $path = storage_path('app/public/database-backup/'.$database->school_id.'/'.$database->name.'.sql');

                // Read the contents of the SQL file
                $sql = File::get($path);
                // Split SQL file into individual queries
                $queries = explode(';', $sql);
                // Loop through each query and skip 'CREATE TABLE' if the table exists
                foreach ($queries as $query) {
                    $query = trim($query);

                    if (!empty($query)) {
                        // Skip CREATE TABLE queries if the table already exists
                        if (stripos($query, 'CREATE TABLE') === 0) {
                            $tableName = $this->getTableNameFromQuery($query);
                            if (DB::getSchemaBuilder()->hasTable($tableName)) {
                                continue;
                            }
                        }
                        // Execute the query
                        DB::unprepared($query);
                    }
                }

                // Run the SQL queries
                DB::unprepared($sql);
            }
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            DB::commit();

            ResponseService::successResponse('Data Restore Successfully');
        } catch (\Throwable $th) {
            DB::rollBack();
            ResponseService::logErrorResponse($th, "DatabaseBackup Controller -> Restore Method");
            ResponseService::errorResponse();
        }
    }

    public function getTableNameFromQuery($query)
    {
        if (preg_match('/CREATE TABLE `?(\w+)`?/', $query, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function deleteOldRecords($subscription)
    {
        $schoolId = $subscription->school_id;
        if ($subscription) {

            $guardian_ids = Students::where('school_id',$schoolId)->pluck('guardian_id')->toArray();

            Mediums::where('school_id',$schoolId)->delete();
            User::where('school_id',$schoolId)->whereNot('id',Auth::user()->id)->whereNot('id',$subscription->school->admin_id)->delete();
            User::whereIn('id',$guardian_ids)->delete();
            CertificateTemplate::where('school_id',$schoolId)->delete();
            ClassGroup::where('school_id',$schoolId)->delete();
            ExpenseCategory::where('school_id',$schoolId)->delete();
            Faq::where('school_id',$schoolId)->delete();
            FeesType::where('school_id',$schoolId)->delete();
            ModelsFile::where('school_id',$schoolId)->delete();
            FormField::where('school_id',$schoolId)->delete();
            SessionYear::where('school_id',$schoolId)->delete();
            Grade::where('school_id',$schoolId)->delete();
            Holiday::where('school_id',$schoolId)->delete();
            SchoolSetting::where('school_id',$schoolId)->delete();
            Section::where('school_id',$schoolId)->delete();
            Semester::where('school_id',$schoolId)->delete();
            Shift::where('school_id',$schoolId)->delete();
            Slider::where('school_id',$schoolId)->delete();
            Stream::where('school_id',$schoolId)->delete();
            Subscription::where('school_id',$schoolId)->delete();
            PaymentTransaction::where('school_id',$schoolId)->delete();

        }
        
    }
}
