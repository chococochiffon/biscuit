<?php

namespace App\Installer;

use RuntimeException;

/**
 * インストーラーの段の処理の失敗。何に失敗したか(stage)と、画面に出す文言を持つ(秘密の値は入れない)。
 */
class InstallerStepException extends RuntimeException
{
    public function __construct(public readonly string $stage, string $message)
    {
        parent::__construct($message);
    }
}
