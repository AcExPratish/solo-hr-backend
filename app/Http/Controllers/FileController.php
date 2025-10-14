<?php

namespace App\Http\Controllers;

use App\Traits\FileUploadTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
                $validator = Validator::make($request->all(), $this->rules("image"));
                if ($validator->fails()) {
                    return $this->sendValidationErrors($validator);
                }

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

    private function rules(string $type): array
    {
        if ($type === "image") {
            return [
                "file" => "nullable|image|mimes:jpeg,png,jpg|max:5222",
                "attribute" => "required|string|in:avatar"
            ];
        } else {
            return [];
        }
    }
}
