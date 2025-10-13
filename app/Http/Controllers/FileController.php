<?php

namespace App\Http\Controllers;

use App\Traits\FileUploadTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FileController extends Controller
{
    use FileUploadTrait;

    public function upload(Request $request): JsonResponse
    {
        try {
            $response = "false";
            $file = $request->file("file");
            $attribute = $request->input("attribute");

            if ($attribute === "avatar") {
                $response =  $this->uploadOrUpdateFile($file, "/user/avatar");
            }

            if ($response === "false") {
                return $this->sendErrorOfUnprocessableEntity("Unable to upload file. Please try again later.");
            }

            return $this->sendSuccessResponse("File/Image changed successfully", $response);
        } catch (\Exception $e) {
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }
}
