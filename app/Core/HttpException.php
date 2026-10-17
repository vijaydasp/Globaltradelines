<?php
namespace App\Core;
final class HttpException extends \Symfony\Component\HttpKernel\Exception\HttpException {
    public function __construct(public int $status,string $message){parent::__construct($status,$message);}
}
