<?php

declare(strict_types=1);

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

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use SUDHAUS7\Sudhaus7Wizard\CreateProcess;

class CreateBackendUserEvent implements LoggerAwareInterface
{
    use LoggerAwareTrait;
    protected array $record;
    protected CreateProcess $create_process;
    public function __construct(array $record, CreateProcess $create_process)
    {
        $this->create_process = $create_process;
        $this->record         = $record;
        $this->logger = $create_process->getLogger();
    }

    /**
     * @return array
     */
    public function getRecord(): array
    {
        return $this->record;
    }

    /**
     * @param array $record
     */
    public function setRecord(array $record): void
    {
        $this->record = $record;
    }

    /**
     * @return CreateProcess
     */
    public function getCreateProcess(): CreateProcess
    {
        return $this->create_process;
    }
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }
}
