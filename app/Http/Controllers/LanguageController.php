<?php

namespace App\Http\Controllers;

use App\Models\Language;
use App\Models\LanguageLevel;
use Illuminate\Http\Request;

class LanguageController extends Controller
{
    public function index()
    {
        $languages = Language::orderBy('name')->get();
        $levels = LanguageLevel::orderBy('id')->get();
        return view('languages.index', compact('languages', 'levels'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:languages,name',
        ]);

        Language::create($request->only('name'));

        return back()->with('success', 'Language added successfully.');
    }

    public function destroy(Language $language)
    {
        if ($language->candidateProfiles()->count() > 0) {
            return back()->with('error', 'Cannot delete language used by candidates.');
        }

        $language->delete();
        return back()->with('success', 'Language deleted.');
    }

    public function storeLevel(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:language_levels,name',
        ]);

        LanguageLevel::create($request->only('name'));

        return back()->with('success', 'Language level added.');
    }

    public function destroyLevel(LanguageLevel $level)
    {
        if ($level->candidateLanguages()->count() > 0) {
            return back()->with('error', 'Cannot delete level used by candidates.');
        }

        $level->delete();
        return back()->with('success', 'Language level deleted.');
    }
}
