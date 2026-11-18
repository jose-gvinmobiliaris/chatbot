<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WordPress\WordPressProjectService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProjectController extends Controller
{
    public function __construct(
        private WordPressProjectService $wordpressProjects
    ) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'property_type' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $projects = $this->wordpressProjects->getProjects($filters);

            return response()->json([
                'success' => true,
                'data' => $projects['data'] ?? [],
                'pagination' => $projects['pagination'] ?? null,
            ]);

        } catch (Throwable $e) {
            Log::error('Error consultando proyectos WordPress', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudieron consultar los proyectos.',
            ], 502);
        }
    }

    public function show(int $id)
    {
        try {
            $project = $this->wordpressProjects->getProject($id);

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Proyecto no encontrado.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $project,
            ]);

        } catch (Throwable $e) {
            Log::error('Error consultando proyecto WordPress', [
                'wordpress_project_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo consultar el proyecto.',
            ], 502);
        }
    }

    public function locations()
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->wordpressProjects->getLocations(),
            ]);

        } catch (Throwable $e) {
            Log::error('Error consultando ubicaciones WordPress', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudieron consultar las ubicaciones.',
            ], 502);
        }
    }
}