<?php

enum HttpMethod: string
{
    case GET     = 'GET';
    case POST    = 'POST';
    case PUT     = 'PUT';
    case PATCH   = 'PATCH';
    case DELETE  = 'DELETE';
    case OPTIONS = 'OPTIONS';

    public static function fromRequest(): self
    {
        return self::from($_SERVER['REQUEST_METHOD']);
    }
}
