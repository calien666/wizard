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

namespace SUDHAUS7\Sudhaus7Wizard\Sources;

use Psr\Log\LoggerAwareTrait;
use SUDHAUS7\Sudhaus7Wizard\Domain\Model\Creator;
use SUDHAUS7\Sudhaus7Wizard\Services\RestWizardRequest;
use SUDHAUS7\Sudhaus7Wizard\Traits\DbTrait;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

abstract class RestWizardServerSource implements SourceInterface
{
    use DbTrait;
    use LoggerAwareTrait;

    protected array $remoteTables = [];
    protected ?Creator $creator = null;
    private array $tree = [];
    public array $siteconfig = [
        'base'          => 'domainname',
        'baseVariants'  => [],
        'errorHandling' => [],
        'languages'     =>
            [
                0 =>
                    [
                        'title'           => 'Default',
                        'enabled'         => true,
                        'base'            => '/',
                        'typo3Language'   => 'en',
                        'locale'          => 'enUS.UTF-8',
                        'iso-639-1'       => 'en',
                        'navigationTitle' => 'English',
                        'hreflang'        => 'en-US',
                        'direction'       => 'ltr',
                        'flag'            => 'en',
                        'languageId'      => '0',
                    ],
            ],
        'rootPageId'    => 0,
        'routes'        =>
            [
                0 =>
                    [
                        'route'   => 'robots.txt',
                        'type'    => 'staticText',
                        'content' => 'User-agent: *
Disallow: /typo3/
Disallow: /typo3_src/
Allow: /typo3/sysext/frontend/Resources/Public/*
',
                    ],
            ],
        'imports'=>[

        ],
    ];
    public function setCreator(Creator $creator): void
    {
        // modify username
        // fetch original user
        $this->creator = $creator;
    }

    public function getCreator(): ?Creator
    {
        return $this->creator;
    }

    /**
     * @inheritDoc
     */
    public function getSiteConfig(mixed $id): array
    {
        // something differen? domain?

        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);

        try {
            $site = $siteFinder->getSiteByPageId((int)$id);
            return $site->getConfiguration();
        } catch (SiteNotFoundException $e) {
            // no harm done
            $x = 1;
        } catch (\Exception $e) {
            $x =1;
        }
        return $this->siteconfig;
    }

    /**
     * @inheritDoc
     */
    public function getRow($table, $where = [])
    {
        if (!empty($this->remoteTables) && !\in_array($table, $this->remoteTables)) {
            return [];
        }

        if ($table === 'pages') {
            $endpoint = sprintf('page/%d', $where['uid']);
        } else {
            $endpoint = sprintf('content/%s/uid/%d', $table, $where['uid']);
        }
        $this->logger->debug('getRow ' . $endpoint);

        $content = $this->getAPI()->request($endpoint);
        if ($table === 'pages') {
            return $content;
        }
        return $content[0] ?? [];
    }

    /**
     * @inheritDoc
     */
    public function getRows($table, $where = [])
    {
        if (!empty($this->remoteTables) && !\in_array($table, $this->remoteTables)) {
            return [];
        }
        $fields = array_keys($where);
        $values = array_values($where);
        $endpoint = sprintf('content/%s/%s/%d', $table, $fields[0], $values[0]);
        $this->logger->debug('getRows ' . $endpoint);
        $content = $this->getAPI()->request($endpoint);
        return $content;
    }

    /**
     * @inheritDoc
     */
    public function getTree($start)
    {
        $endpoint = sprintf('tree/%d', $start);
        $this->logger->debug('getTree ' . $endpoint);
        $content = $this->getAPI()->request($endpoint);
        return $content;
    }

    /**
     * @inheritDoc
     */
    public function ping()
    {
        // TODO: Implement ping() method.
    }

    /**
     * @inheritDoc
     */
    public function getIrre($table, $uid, $pid, array $oldrow, array $columnconfig, $pidlist = [])
    {
        if (!empty($this->remoteTables) && !\in_array($table, $this->remoteTables)) {
            return [];
        }
        $where = [
            $columnconfig['config']['foreign_field']=>$uid,
        ];
        if (isset($columnconfig['config']['foreign_table_field'])) {
            $where[$columnconfig['config']['foreign_table_field']] = $table;
        }

        if (isset($columnconfig['config']['foreign_match_fields']) && !empty($columnconfig['config']['foreign_match_fields'])) {
            foreach ($columnconfig['config']['foreign_match_fields'] as $ff => $vv) {
                $where[$ff]=$vv;
            }
        }

        $endpoint = sprintf('content/%s', $columnconfig['config']['foreign_table']);

        $this->logger->debug('getIRRE ' . $endpoint . ' ' . \json_encode($where));
        $content = $this->getAPI()->post($endpoint, $where);
        return $content;
    }

    /**
     * @inheritDoc
     */
    public function handleFile(array $sys_file, $newidentifier)
    {
        $this->logger->debug('handleFile ' . $newidentifier . ' START');
        $this->logger->debug('fetching ' . $this->getAPI()->getAPIHOST() . 'fileadmin/' . trim($sys_file['identifier'], '/'));
        $buf = @\file_get_contents($this->getAPI()->getAPIHOST() . 'fileadmin' . $sys_file['identifier']);
        if (!$buf) {
            $this->logger->error('fetch failed' . $this->getAPI()->getAPIHOST() . 'fileadmin/' . trim($sys_file['identifier'], '/'));
            return ['uid'=>0];
        }
        \file_put_contents(Environment::getPublicPath() . '/fileadmin' . $newidentifier, $buf);

        $this->logger->debug('wrote file ' . Environment::getPublicPath() . '/fileadmin' . $newidentifier);

        $olduid = $sys_file['uid'];
        unset($sys_file['uid']);

        $sys_file['identifier'] = $newidentifier;
        $sys_file['identifier_hash'] = sha1((string)$sys_file['identifier']);
        $sys_file['folder_hash'] = sha1(dirname((string)$sys_file['identifier']));

        [$affected,$uid] = self::insertRecord('sys_file', $sys_file);

        try {
            $endpoint = sprintf('content/%s/file/%d', 'sys_file_metadata', $olduid);
            $this->logger->debug('FILE metadata fetching ' . $endpoint);
            $content  = $this->getAPI()->request($endpoint);
            if (\is_array($content) && !empty($content) && !empty($content[0])) {
                $sys_file_metadata = $content[0];
                unset($sys_file_metadata['uid']);
                $sys_file_metadata['file'] = $uid;
                self::insertRecord('sys_file_metadata', $sys_file_metadata);
            }
        } catch (\Exception $e) {
            $this->logger->error('FILE fetching ' . $endpoint . ' : ' . $e->getMessage());
        }
        $sys_file['uid'] = $uid;
        $this->logger->debug('handleFile ' . $newidentifier . ' END');
        return $sys_file;
    }

    /**
     * @inheritDoc
     */
    public function getMM($mmtable, $uid, $tablename)
    {
        if (!empty($this->remoteTables) && !\in_array($mmtable, $this->remoteTables)) {
            return [];
        }
        $endpoint = sprintf('content/%s/uid_local/%d', $mmtable, $uid);
        $this->logger->debug('getMM ' . $endpoint);
        $content  = $this->getAPI()->request($endpoint);
        if (\is_array($content)) {
            return $content;
        }
        return [];
    }

    /**
     * @inheritDoc
     */
    public function sourcePid()
    {
        return $this->creator->getSourcepid();
    }

    /**
     * @inheritDoc
     */
    public function getTables()
    {
        $this->logger->debug('getTables');
        if (empty($this->remoteTables)) {
            $this->remoteTables = $this->getAPI()->request('tables');
        }
        return \array_intersect(array_keys($GLOBALS['TCA']), $this->remoteTables);
    }

    public function getSites()
    {
        $endpoint = 'content/pages/is_siteroot/1';
        $this->logger->debug('getSites ' . $endpoint);
        $content = $this->getAPI()->request($endpoint);
        return $content;
    }

    public function getAPI(): RestWizardRequest
    {
        throw new \Exception('implement the getAPI method first', 1696870054);
    }
}