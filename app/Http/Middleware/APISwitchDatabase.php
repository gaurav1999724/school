<?php

namespace App\Http\Middleware;

use App\Models\School;
use Auth;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class APISwitchDatabase
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $schoolCode = $request->header('school_code');
        if ($schoolCode) {
            $school = School::on('mysql')->where('code',$schoolCode)->first();

            if ($school) {
                DB::setDefaultConnection('school');
                Config::set('database.connections.school.database', $school->database_name);
                DB::purge('school');
                DB::connection('school')->reconnect();
                DB::setDefaultConnection('school');
                $token = $request->bearerToken();
                $user = PersonalAccessToken::findToken($token);
                
                if ($user) {
                    Auth::loginUsingId($user->tokenable_id);
                } else {
                    return response()->json(['message' => 'Unauthenticated.']);
                }

            } else {
                return response()->json(['message' => 'Invalid school code'], 400);
            }
        } else {
            return response()->json(['message' => 'Unauthenticated'], 400);
        }
        return $next($request);
    }
}
