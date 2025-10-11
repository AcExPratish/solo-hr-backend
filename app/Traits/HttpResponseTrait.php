<?php

namespace App\Traits;

trait HttpResponseTrait
{
    public function sendSuccessOfCreateResponse($message, $data = null): \Illuminate\Http\JsonResponse
    {
        return $this->sendSuccessResponse($message, $data, 201);
    }

    public function sendSuccessOfOkResponse($message, $data = null): \Illuminate\Http\JsonResponse
    {
        return $this->sendSuccessResponse($message, $data, 200);
    }

    public function sendErrorOfBadResponse($message): \Illuminate\Http\JsonResponse
    {
        return $this->sendErrorResponse($message, 400);
    }

    public function sendErrorOfUnauthorized($message = null): \Illuminate\Http\JsonResponse
    {
        return $this->sendErrorResponse($message, 401);
    }

    public function sendErrorOfForbidden($message = null): \Illuminate\Http\JsonResponse
    {
        return $this->sendErrorResponse($message, 403);
    }

    public function sendErrorOfNotFound404($message = null): \Illuminate\Http\JsonResponse
    {
        return $this->sendErrorResponse($message, 404);
    }

    public function sendErrorOfUnprocessableEntity($message = null, $errors = null): \Illuminate\Http\JsonResponse
    {
        return $this->sendErrorResponse($message, 422, $errors);
    }

    public function sendErrorOfInternalServer($message = null): \Illuminate\Http\JsonResponse
    {
        return $this->sendErrorResponse($message, 500);
    }

    public function sendPaginateResponse(string $message, int $page, int $limit, int $count, $rows): \Illuminate\Http\JsonResponse
    {
        return $this->sendSuccessResponse(
            $message,
            [
                'meta' => [
                    'page' => (int)$page,
                    'limit' => (int)$limit,
                    'total_rows' => (int)$count
                ],
                'rows' => $rows
            ]
        );
    }

    public function sendSuccessResponse(string $message, $data = null, $code = 200): \Illuminate\Http\JsonResponse
    {
        $resp = [
            'success' => true,
            'message' => $message,
            'code' => $code,
        ];
        if ($data) {
            $resp['data'] = $data;
        }
        return response()->json($resp, $code);
    }

    public function sendErrorResponse($message, $code = 500, $errors = null): \Illuminate\Http\JsonResponse
    {
        $resp = [
            'success' => false,
            'message' => $message,
            'code' => $code,
        ];
        if ($errors) {
            $resp['errors'] = $errors;
        }
        return response()->json($resp, $code);
    }

    public function sendFileContentResponse($content): \Illuminate\Http\JsonResponse
    {
        return response()->json($content);
    }

    public function sendValidationErrors($validator): \Illuminate\Http\JsonResponse
    {
        return response()->json(
            [
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ],
            422
        );
    }
}
