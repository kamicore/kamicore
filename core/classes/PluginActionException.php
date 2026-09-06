<?php

namespace Core;

if (!IN_KAMI) die();

final class PluginActionException extends \RuntimeException
{
    public const NOT_FOUND = 404;
    public const FORBIDDEN = 403;
}
