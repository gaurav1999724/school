<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        
        $this->middleware('guest')->except('logout');

    }

    public function username()
    {
        $loginValue = request('email');
        $this->username = filter_var($loginValue ,FILTER_VALIDATE_EMAIL) ? 'email' : 'mobile';
        request()->merge([$this->username => $loginValue]);
        return $this->username == 'mobile' ? 'mobile' : 'email';
    }

    public function login(Request $request)
    {
        // Validate the login request
        $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
            'code' => 'nullable|string',
        ]);

        $loginField = $this->username();

        if ($request->code) {
            // Retrieve the school's database connection info
            $school = School::on('mysql')->where('code', $request->code)->first();

            if (!$school) {
                return back()->withErrors(['code' => 'Invalid school identifier.']);
            }

            // Set the dynamic database connection
            Config::set('database.connections.school.database', $school->database_name);
            DB::purge('school');
            DB::connection('school')->reconnect();
            DB::setDefaultConnection('school');

            \Log::info('Switched to database: ' . DB::connection('school')->getDatabaseName());
            // Attempt login using the user's credentials within the school's database
            if (Auth::guard('web')->attempt([
                $loginField => $request->email,
                'password' => $request->password,
            ])) {
                \Log::info('User authenticated successfully.', [
                    'user_id' => Auth::guard('web')->id(),
                    'email' => $request->email,
                ]);

                // Optionally, log in the user explicitly
                Auth::loginUsingId(Auth::guard('web')->id());
                $user = Auth::guard('web')->user();
            
                // Set custom session data
                session(['user_id' => $user->id]);
                session(['user_email' => $user->email]);
                
                session()->save();

                Auth::login($user);
                
                Session::put('school_database_name', $school->database_name);

                
                return redirect()->intended('/dashboard');
            } else {
                \Log::error('Login attempt failed in school database. Email: ' . $request->email);
            }
        } else {
            // Attempt login on the main connection
            DB::setDefaultConnection('mysql');
            Session::forget('school_database_name');
            Session::flush();
            Session::put('school_database_name', null);
            if (Auth::guard('web')->attempt([
                $loginField => $request->email,
                'password' => $request->password,
            ])) {

                if (Auth::user()->school) {
                    Auth::logout();
                    $request->session()->flush();
                    $request->session()->regenerate();
                    session()->forget('school_database_name');
                    Session::forget('school_database_name');
                    return back()->withErrors(['email' => 'The provided credentials do not match our records.']);
                }

                session(['db_connection_name' => 'mysql']);
                return redirect()->intended('/home');
            } else {
                \Log::error('Login attempt failed in main database. Email: ' . $request->email);
            }
        }

        // Login failed, redirect back with an error message
        return back()->withErrors(['email' => 'The provided credentials do not match our records.']);
    }
    
}
