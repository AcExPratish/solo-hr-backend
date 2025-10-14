<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait FileUploadTrait
{
    protected $storage;

    public function __construct()
    {
        $this->storage = config('filesystems.default');
    }

    public function uploadOrUpdateFile($file, string $folder): string|null
    {
        if (!($file instanceof UploadedFile)) {
            return null;
        }

        $extension = $file->getClientOriginalExtension();
        $newName = time() . '_' . Str::uuid() . '.' . $extension;
        $path = $folder . '/' . $newName;

        $result = Storage::disk($this->storage)->put($path, $file->get());
        if (!$result) {
            return "false";
        }

        return $path;
    }
}
