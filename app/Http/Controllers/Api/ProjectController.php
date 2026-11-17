<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

class ProjectController extends Controller
{
    public function index(): JsonResponse
    {
        $projects = Project::where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'location',
                'average_price',
                'construction_status',
                'url',
            ]);

        return response()->json([
            'projects' => $projects,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $project = Project::where('is_active', true)
            ->find($id);

        if (!$project) {
            return response()->json([
                'message' => 'Proyecto no encontrado.',
            ], 404);
        }

        return response()->json([
            'project' => $project,
        ]);
    }
}