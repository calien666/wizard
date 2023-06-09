<?php

/*
 * This file is part of the TYPO3 project.
 *
 * @author Frank Berger <fberger@sudhaus7.de>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace SUDHAUS7\Sudhaus7Wizard\Events;

use SUDHAUS7\Sudhaus7Wizard\CreateProcess;
use SUDHAUS7\Sudhaus7Wizard\Interfaces\WizardEventInterface;
use SUDHAUS7\Sudhaus7Wizard\Interfaces\WizardEventWriteableRecordInterface;
use SUDHAUS7\Sudhaus7Wizard\Traits\EventTrait;
use SUDHAUS7\Sudhaus7Wizard\Traits\EventWriteableRecordTrait;

class CleanContentEvent implements WizardEventInterface, WizardEventWriteableRecordInterface
{
    use EventTrait;
    use EventWriteableRecordTrait;
    protected string $table;
    protected array $record;
    public function __construct(string $table, array $record, CreateProcess $create_process)
    {
        $this->create_process = $create_process;
        $this->record = $record;
        $this->table = $table;
    }
}
