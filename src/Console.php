<?php

declare(strict_types=1);

namespace Lkrms;

if (!class_alias("\Lkrms\Console\Console", "\Lkrms\Console"))
{
    /**
     * @ignore
     */
    class Console extends Console\Console
    {
    }
}
