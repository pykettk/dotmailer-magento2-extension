<?php

namespace Dotdigitalgroup\Email\Model;

use Dotdigitalgroup\Email\Helper\Data;
use Dotdigitalgroup\Email\Model\Sync\Integration\DotdigitalConfig;
use Dotdigitalgroup\Email\Model\Sync\Integration\IntegrationInsightData;
use Dotdigitalgroup\Email\Model\Connector\Module;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class IntegrationInsightDataUnitTest extends TestCase
{
    const PLATFORM = 'Magento';
    const EDITION = 'Community';
    const VERSION = '2.3';
    const CONNECTOR_VERSION = '3.4.0';

    /**
     * @var IntegrationInsightData
     */
    private $integrationInsightData;

    /**
     * @var Data
     */
    private $helperMock;

    /**
     * @var ProductMetadataInterface
     */
    private $productMetadataMock;

    /**
     * @var \Dotdigitalgroup\Email\Model\Sync\Integration\DotdigitalConfig|\PHPUnit\Framework\MockObject\MockObject
     */
    private $dotdigitalConfigMock;

    /**
     * @var StoreManagerInterface|mixed|\PHPUnit\Framework\MockObject\MockObject
     */
    private $storeManagerInterfaceMock;

    /**
     * @var Module|Module&\PHPUnit\Framework\MockObject\MockObject|\PHPUnit\Framework\MockObject\MockObject
     */
    private $moduleManagerMock;

    public function setUp() :void
    {
        $this->helperMock = $this->createMock(Data::class);
        $this->productMetadataMock = $this->createMock(ProductMetadataInterface::class);
        $this->dotdigitalConfigMock = $this->createMock(DotdigitalConfig::class);
        $this->storeManagerInterfaceMock = $this->createMock(StoreManagerInterface::class);
        $this->moduleManagerMock = $this->createMock(Module::class);

        // set up metadata
        $this->productMetadataMock
            ->expects($this->once())
            ->method('getName')
            ->willReturn(self::PLATFORM);
        $this->productMetadataMock
            ->expects($this->once())
            ->method('getEdition')
            ->willReturn(self::EDITION);
        $this->productMetadataMock
            ->expects($this->once())
            ->method('getVersion')
            ->willReturn(self::VERSION);
        $this->moduleManagerMock
            ->expects($this->once())
            ->method('getModuleVersion')
            ->with('Dotdigitalgroup_Email')
            ->willReturn([
                'setup_version' => self::CONNECTOR_VERSION,
            ]);

        $this->storeManagerInterfaceMock->expects($this->once())
            ->method('getStores')
            ->willReturn([
                $this->getTestStore(1, 'Default', 'https://www.chaz-kangaroo.com', true),
                $this->getTestStore(2, 'Typos', 'https://www.chaz-kangaroo.com/cauals', true),
                $this->getTestStore(3, 'Bye Bye Man', 'https://www.bye-bye-man.com', false),
            ]);

        $this->integrationInsightData = new IntegrationInsightData(
            $this->helperMock,
            $this->productMetadataMock,
            $this->dotdigitalConfigMock,
            $this->storeManagerInterfaceMock,
            $this->moduleManagerMock
        );
    }

    /**
     * Test expected integration records are returned
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function testIntegrationData()
    {
        // set one website to have API disabled
        $enabledCheck = 0;
        $this->helperMock
            ->expects($this->any())
            ->method('isEnabled')
            ->will($this->returnCallback(function () use (&$enabledCheck) {
                if ($enabledCheck++ === 1) {
                    return false;
                }
                return true;
            }));

        $data = $this->integrationInsightData->getIntegrationInsightData();

        // assert 2 records were returned, with separate integration IDs based on the API hash
        $this->assertCount(2, $data);
        $this->assertEquals('www.chaz-kangaroo.com', reset($data)['recordId']);
        $this->assertEquals('www.bye-bye-man.com', end($data)['recordId']);
    }

    /**
     * Test expected metadata is returned
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function testMetadataReturned()
    {
        $this->helperMock
            ->expects($this->any())
            ->method('isEnabled')
            ->willReturn(true);

        $data = $this->integrationInsightData->getIntegrationInsightData();
        $toAssert = reset($data);

        $this->assertArrayHasKey('platform', $toAssert);
        $this->assertArrayHasKey('edition', $toAssert);
        $this->assertArrayHasKey('version', $toAssert);
        $this->assertArrayHasKey('connectorVersion', $toAssert);

        $this->assertArrayHasKey('phpVersion', $toAssert);
        $this->assertArrayHasKey('configuration', $toAssert);
    }

    /**
     * @param int $websiteId
     * @param string $websiteName
     * @param string $baseUrl
     * @param bool $isCurrentlySecure
     * @return object
     */
    private function getTestStore(int $websiteId, string $websiteName, string $baseUrl, bool $isCurrentlySecure)
    {
        return new class($websiteId, $websiteName, $baseUrl, $isCurrentlySecure) {
            private $websiteId;
            private $websiteName;
            private $baseUrl;
            private $isCurrentlySecure;

            public function __construct($websiteId, $websiteName, $baseUrl, $isCurrentlySecure)
            {
                $this->websiteId = $websiteId;
                $this->websiteName = $websiteName;
                $this->baseUrl = $baseUrl;
                $this->isCurrentlySecure = $isCurrentlySecure;
            }

            public function getWebsiteId()
            {
                return $this->websiteId;
            }

            public function getBaseUrl()
            {
                return $this->baseUrl;
            }

            public function isCurrentlySecure()
            {
                return $this->isCurrentlySecure;
            }

            public function getWebsite()
            {
                return new class($this->websiteName) {
                    private $websiteName;

                    public function __construct($websiteName)
                    {
                        $this->websiteName = $websiteName;
                    }

                    public function getName()
                    {
                        return $this->websiteName;
                    }
                };
            }
        };
    }
}
