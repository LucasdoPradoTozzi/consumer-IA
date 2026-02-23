<?php

namespace App\Http\Controllers;

use App\Models\Skill;
use App\Models\SkillType;
use Illuminate\Http\Request;

class SkillController extends Controller
{
    public function index()
    {
        $skillTypes = SkillType::with('skills')->orderBy('name')->get();
        return view('skills.index', compact('skillTypes'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:skills,name',
            'skill_type_id' => 'required|exists:skill_types,id',
        ]);

        Skill::create($request->only('name', 'skill_type_id'));

        return back()->with('success', 'Skill added successfully.');
    }

    public function destroy(Skill $skill)
    {
        $skill->delete();
        return back()->with('success', 'Skill deleted.');
    }

    public function storeType(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:skill_types,name',
        ]);

        SkillType::create($request->only('name'));

        return back()->with('success', 'Skill type added.');
    }

    public function destroyType(SkillType $type)
    {
        if ($type->skills()->count() > 0) {
            return back()->with('error', 'Cannot delete type with active skills.');
        }
        
        $type->delete();
        return back()->with('success', 'Skill type deleted.');
    }
}
