<?php

class gamepanelioTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        require_once __DIR__ . "/../src/gamepanelio.php";
    }

    protected function buildMockApiClient()
    {
        global $mockApiClient;

        return $mockApiClient = $this->createMock(\GamePanelio\GamePanelio::class);
    }

    protected function buildModuleOptions()
    {
        return [
            'serviceid' => 820,
            'configoption1' => 19,
            'configoption2' => "auto",
            'configoption3' => '',
            'configoption4' => 'minecraft',
            'clientsdetails' => [
                'id' => 1,
                'firstname' => 'John',
                'lastname' => 'Johnson',
            ],
            'password' => 'abcd', // Service Password
            'serversecure' => 1,
            'serverhostname' => 'abcd.domain.dev',
        ];
    }

    public function testMetaData()
    {
        $this->assertInternalType('array', gamepanelio_MetaData());
    }

    public function testConfigOptions()
    {
        $out = gamepanelio_ConfigOptions();

        $this->assertInternalType('array', $out);

        foreach ($out as $item) {
            $this->assertInternalType('array', $item);

            $this->assertArrayHasKey('FriendlyName', $item);
            $this->assertArrayHasKey('Type', $item);
        }
    }

    public function testGetApiClient()
    {
        global $mockApiClient;
        $mockApiClient = false;

        $out = gamepanelio_getApiClient([
            'serverhostname' => 'abcd.test.dev',
            'serveraccesshash' => '12345',
        ]);

        $this->assertInstanceOf('\\GamePanelio\\GamePanelio', $out);
    }

    public function testCreateAccount()
    {
        $apiClient = $this->buildMockApiClient();

        $apiClient
            ->expects($this->once())
            ->method('createServer')
            ->withAnyParameters();

        $params = $this->buildModuleOptions();

        $this->assertEquals('success', gamepanelio_CreateAccount($params));
    }

    private function updateServerSetup()
    {
        $apiClient = $this->buildMockApiClient();

        $apiClient
            ->expects($this->once())
            ->method('updateServer')
            ->withAnyParameters();
    }

    public function testSuspendAccount()
    {
        $this->updateServerSetup();
        $params = $this->buildModuleOptions();

        $this->assertEquals('success', gamepanelio_SuspendAccount($params));
    }

    public function testUnsuspendAccount()
    {
        $this->updateServerSetup();
        $params = $this->buildModuleOptions();

        $this->assertEquals('success', gamepanelio_UnsuspendAccount($params));
    }

    public function testTerminateAccount()
    {
        $apiClient = $this->buildMockApiClient();

        $apiClient
            ->expects($this->once())
            ->method('deleteServer')
            ->withAnyParameters();

        $params = $this->buildModuleOptions();

        $this->assertEquals('success', gamepanelio_TerminateAccount($params));
    }

    public function testChangePassword()
    {
        $apiClient = $this->buildMockApiClient();

        $apiClient
            ->expects($this->once())
            ->method('updateUser')
            ->withAnyParameters();

        $params = $this->buildModuleOptions();
        $params['username'] = 'JohnJohnson1';

        $this->assertEquals('success', gamepanelio_ChangePassword($params));
    }

    public function testChangePackage()
    {
        $this->updateServerSetup();
        $params = $this->buildModuleOptions();

        $this->assertEquals('success', gamepanelio_ChangePackage($params));
    }

    public function testAdminServicesTabFields()
    {
        $params = $this->buildModuleOptions();

        $this->assertInternalType('array', gamepanelio_AdminServicesTabFields($params));
    }

    public function testClientArea()
    {
        $params = $this->buildModuleOptions();

        $out = gamepanelio_ClientArea($params);

        $this->assertInternalType('array', $out);
        $this->assertArrayHasKey('templateVariables', $out);
        $this->assertInternalType('array', $out['templateVariables']);
    }
}
