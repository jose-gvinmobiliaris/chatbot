<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
        ]);

        $faqs = Faq::where('is_active', true)
            ->where(function ($query) use ($request) {
                $query->whereNull('project_id');

                if ($request->filled('project_id')) {
                    $query->orWhere(
                        'project_id',
                        $request->project_id
                    );
                }
            })
            ->orderBy('sort_order')
            ->get([
                'id',
                'project_id',
                'question',
                'answer',
            ]);

        return response()->json([
            'faqs' => $faqs,
        ]);
    }
}