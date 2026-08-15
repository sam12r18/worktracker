<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $projects = Project::query()->whereBelongsTo($request->user())->with('rules')->orderBy('name')->get();
        return response()->json(['data'=>$projects]);
    }

    public function store(Request $request): JsonResponse
    {
        $data=$request->validate([
            'id'=>['nullable','uuid'],'name'=>['required','string','max:180'],'code'=>['nullable','string','max:80'],
            'parent_id'=>['nullable','string','max:36',Rule::exists('projects','id')->where('user_id',$request->user()->id)],
            'status'=>['nullable','string','max:30'],'color'=>['nullable','string','max:20']
        ]);
        $project=new Project($data);$project->id=$data['id']??(string)Str::uuid();$project->version=1;$project->user()->associate($request->user());$project->save();
        DB::table('project_multiplier_history')->insert(['project_id'=>$project->id,'customer_id'=>$project->customer_id,'multiplier'=>$project->rate_multiplier ?? 1,'is_billable_default'=>$project->is_billable_default ?? true,'effective_from'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['data'=>$project],201);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        abort_unless((string)$project->user_id===(string)$request->user()->id,404);
        return response()->json(['data'=>$project->load('rules')]);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        abort_unless((string)$project->user_id===(string)$request->user()->id,404);
        $project->fill($request->validate([
            'name'=>['sometimes','string','max:180'],'code'=>['nullable','string','max:80'],'status'=>['sometimes','string','max:30'],
            'color'=>['nullable','string','max:20'],'is_archived'=>['sometimes','boolean']
        ]));
        if ($project->isDirty()) $project->version = ((int)$project->version) + 1;
        $project->save();
        return response()->json(['data'=>$project]);
    }

    public function destroy(Request $request, Project $project): JsonResponse
    {
        abort_unless((string)$project->user_id===(string)$request->user()->id,404);
        $project->is_archived=true;$project->status='archived';$project->version=((int)$project->version)+1;$project->save();
        return response()->json([],204);
    }
}
