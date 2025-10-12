<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $page  = request('page') ? (int) request('page') : 1;
            $limit = request('limit') ? (int) request('limit') : 10;

            $query = Permission::query()->orderByDesc('created_at');

            $total = (clone $query)->count();
            $permissions  = $query->skip(value: ($page - 1) * $limit)
                ->take($limit)
                ->get();

            return $this->sendPaginateResponse(
                'Fetch all permissions',
                $page,
                $limit,
                $total,
                $permissions
            );
        } catch (\Exception $e) {
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }
}
