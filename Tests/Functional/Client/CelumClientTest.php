<?php
namespace Brix\CelumFal\Tests\Functional\Client;

use Brix\CelumFal\Tests\Functional\SiteBasedTestTrait;
use TYPO3\CMS\Core\TypoScript\TemplateService;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\Internal\TypoScriptInstruction;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class CelumClientTest extends FunctionalTestCase
{

    public function setUp(): void
    {
        parent::setUp();

        $this->importDataSet(__DIR__ . '/../Fixtures/data.xml');
    }

    /**
     * @test
     */
    public function checkStorageRecord(): void
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('sys_file_storage');
        $queryBuilder->getRestrictions()->removeAll();
        $count = $queryBuilder->count('uid')
            ->from('sys_file_storage')
            ->execute()
            ->fetchColumn(0);
        var_dump($count);
        $this->assertTrue(false);
    }
}