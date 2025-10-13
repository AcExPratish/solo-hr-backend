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
            $imageOrFileType = "";
            $response = "false";
            $file = $request->file("file");
            $attribute = $request->input("attribute");

            if ($attribute === "avatar") {
                $imageOrFileType = "Avatar";
                $response =  $this->uploadOrUpdateFile($file, "/user/avatar");
            }

            if ($response === "false") {
                return $this->sendErrorOfUnprocessableEntity("Unable to upload file. Please try again later.");
            }

            return $this->sendSuccessResponse($imageOrFileType . " " . "changed successfully", $response);
        } catch (\Exception $e) {
            return $this->sendErrorOfInternalServer($e->getMessage());
        }
    }
}
