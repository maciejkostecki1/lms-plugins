<?php
declare(strict_types=1);
final class NotifyReminderRunner
{
    public static function dispatch(bool $enabled,bool $run,callable $send): int
    {
        if(!$enabled){echo "Przypomnienie przed terminem wyłączone. Nic nie wysłano.\n";return 0;}
        if(!$run){echo "Przypomnienie przed terminem włączone; podgląd bez wysyłki.\n";return 0;}
        return $send();
    }
}
