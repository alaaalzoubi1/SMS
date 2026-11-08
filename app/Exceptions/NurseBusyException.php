<?php

namespace App\Exceptions;

/** The nurse already has a request in progress (pending or accepted instant visit). */
class NurseBusyException extends \RuntimeException
{
}
