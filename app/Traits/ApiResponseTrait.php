<?php

namespace App\Traits;

trait ApiResponseTrait
{
    /**
     * Return a success response.
     *
     * @param mixed $result
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    public function successResponse($result = [], $lang = 'messages', $message = 'Success')
    {
        return response()->json([
            'success' => 1,
            'result' => $result,
            'message' => __($lang . '.' . $message),
        ]);
    }

    /**
     * Return an error response.
     *
     * @param string $message
     * @param int $statusCode
     * @param mixed $result
     * @return \Illuminate\Http\JsonResponse
     */
    public function errorResponse($message = 'Error', $lang = 'messages',  $statusCode = 400, $result = [])
    {
        return response()->json([
            'success' => 0,
            'result' => $result,
            'message' => __($lang . '.' . $message),
        ], $statusCode);
    }
}