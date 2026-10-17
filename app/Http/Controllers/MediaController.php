<?php
namespace App\Http\Controllers;
use App\Services\Media;
class MediaController {
    public function show(int $id): \Symfony\Component\HttpFoundation\BinaryFileResponse {return Media::serve($id);}
}
