<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ApiService
{
    /**
     * Membuat response JSON sukses standar.
     *
     * @param mixed $data Data yang akan dikirim.
     * @param string $message Pesan status.
     * @param int $statusCode HTTP Status Code.
     * @return JsonResponse
     */
    public function success(string $message = 'Operation successful'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
        ], 200);
    }
    public function successWithDataMeta(mixed $data = [], mixed $meta = [], string $message = 'Operation successful', int $statusCode = Response::HTTP_OK): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
            'meta'    => $meta,
        ], $statusCode);
    }
    public function successWithData(mixed $data = [], string $message = 'Operation successful', int $statusCode = Response::HTTP_OK): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $statusCode);
    }

    /**
     * Membuat response JSON error standar.
     *
     * @param string $message Pesan error.
     * @param int $statusCode HTTP Status Code.
     * @param mixed $errors Detail error (opsional, misal dari validasi).
     * @return JsonResponse
     */
    public function error(string $message = 'An error occurred', int $statusCode = 500, mixed $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (!is_null($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }
    public function errorWithData(string $message = 'An error occurred', mixed $data = [], int $statusCode = 500, mixed $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
            'data' => $data,
        ];

        if (!is_null($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }
    public function errorResponse(string $message = 'An error occurred', int $statusCode = 500, mixed $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (!is_null($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }
    public function errorWithDataResponse(string $message = 'An error occurred', mixed $data = [], int $statusCode = 500, mixed $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
            'data' => $data,
        ];

        if (!is_null($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }
}
