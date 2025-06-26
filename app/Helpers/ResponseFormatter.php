<?php

namespace App\Helpers;

use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Format response.
 */
class ResponseFormatter
{
    /**
     * Give success response.
     */
    public static function success($data = null, $message = null, $meta = [])
    {
        $response = [
            'meta' => array_merge([
                'code' => 200,
                'status' => 'success',
                'message' => $message,
            ], $meta),
            'data' => $data,
        ];

        return response()->json($response, $response['meta']['code']);
    }

    /**
     * Give error response.
     */
    public static function error($errors = null, $message = null, $code = 400, $data = null)
    {
        $response = [
            'meta' => [
                'code' => $code,
                'status' => 'error',
                'message' => $message,
            ],
            'data' => $data,
        ];
        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    /**
     * Give paginated success response.
     */
    public static function paginated(LengthAwarePaginator $paginator, $message = null, $meta = [])
    {
        $response = [
            'meta' => array_merge([
                'code' => 200,
                'status' => 'success',
                'message' => $message,
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
            ], $meta),
            'data' => $paginator->items(),
        ];

        return response()->json($response, $response['meta']['code']);
    }

    /**
     * Give validation error response.
     */
    public static function validation($errors, $message = 'Data yang Anda masukkan tidak valid')
    {
        return self::error($errors, $message, 422);
    }

    /**
     * Give unauthorized response.
     */
    public static function unauthorized($message = 'Anda tidak memiliki akses untuk melakukan aksi ini')
    {
        return self::error(null, $message, 401);
    }

    /**
     * Give forbidden response.
     */
    public static function forbidden($message = 'Akses ditolak')
    {
        return self::error(null, $message, 403);
    }

    /**
     * Give not found response.
     */
    public static function notFound($message = 'Data tidak ditemukan')
    {
        return self::error(null, $message, 404);
    }

    /**
     * Give server error response.
     */
    public static function serverError($message = 'Terjadi kesalahan pada server')
    {
        return self::error(null, $message, 500);
    }
}
