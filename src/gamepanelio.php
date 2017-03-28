<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

if (!class_exists("\\GamePanelio\\GamePanelio")) {
    require_once "vendor/autoload.php";
}

/**
 * Define module related meta data.
 *
 * Values returned here are used to determine module related abilities and
 * settings.
 *
 * @see http://docs.whmcs.com/Provisioning_Module_Meta_Data_Parameters
 *
 * @return array
 */
function gamepanelio_MetaData()
{
    return [
        'DisplayName' => 'GamePanel.io',
        'APIVersion' => '1.1',
        'RequiresServer' => true,
        'DefaultNonSSLPort' => '80',
        'DefaultSSLPort' => '443',
    ];
}

/**
 * Define product configuration options.
 *
 * The values you return here define the configuration options that are
 * presented to a user when configuring a product for use with the module. These
 * values are then made available in all module function calls with the key name
 * configoptionX - with X being the index number of the field from 1 to 24.
 *
 * You can specify up to 24 parameters, with field types:
 * * text
 * * password
 * * yesno
 * * dropdown
 * * radio
 * * textarea
 *
 * Examples of each and their possible configuration parameters are provided in
 * this sample function.
 *
 * @return array
 */
function gamepanelio_ConfigOptions()
{
    return [
        'gpio_plan' => [
            'FriendlyName' => 'Plan ID',
            'Type' => 'text',
            'Size' => '20',
        ],
        'gpio_allocation' => [
            'FriendlyName' => 'IP Allocation',
            'Type' => 'dropdown',
            'Options' => [
                'auto' => 'Auto',
                'dedicated' => 'Dedicated',
            ],
            'Default' => 'Auto',
        ],
        'gpio_usernamePrefix' => [
            'FriendlyName' => 'Username Prefix',
            'Type' => 'text',
            'Size' => '25',
        ],
        'gpio_game' => [
            'FriendlyName' => 'Game Type',
            'Type' => 'dropdown',
            'Options' => json_decode(file_get_contents(__DIR__ . '/games.json'), true),
        ],
    ];
}

/**
 * @param array $params
 * @return array
 */
function gamepanelio_getModuleOptions($params)
{
    $allocation = $params["configoption2"];
    if (array_key_exists('configoptions', $params) && array_key_exists('allocation', $params['configoptions'])) {
        if (in_array(strtolower($params['configoptions']['allocation']), ['dedicated', 'auto'])) {
            $allocation = $params['configoptions']['allocation'];
        }
    }

    return [
        'plan' => $params["configoption1"],
        'allocation' => strtolower($allocation),
        'usernamePrefix' => $params["configoption3"],
        'game' => $params["configoption4"]
    ];
}

/**
 * @param $params
 * @return \GamePanelio\GamePanelio
 */
function gamepanelio_getApiClient($params)
{
    $accessToken = new \GamePanelio\AccessToken\PersonalAccessToken($params['serveraccesshash']);

    return new \GamePanelio\GamePanelio($params['serverhostname'], $accessToken);
}

/**
 * Setup the required database column to store the server ID in
 */
function gamepanelio_checkUpdateDatabase()
{
    // Use full_query() for maximum compatibility
    $tableExists = full_query("SHOW COLUMNS FROM `tblhosting` LIKE 'gamepanelioid'");

    if (mysql_num_rows($tableExists) == 0) {
        full_query("ALTER TABLE `tblhosting` ADD `gamepanelioid` VARCHAR(21) NOT NULL;");
    }
}

/**
 * @param string|int $serviceId
 * @return string
 */
function gamepanelio_findServerIdForService($serviceId)
{
    $result = select_query(
        "tblhosting",
        "gamepanelioid",
        [
            "id" => $serviceId
        ]
    );

    if (mysql_num_rows($result) > 0) {
        $result = mysql_fetch_array($result);

        return $result['gamepanelioid'];
    }

    return null;
}

/**
 * @param string|int $serviceId
 * @return string
 */
function gamepanelio_setServerIdForService($serviceId, $newServerId)
{
    update_query(
        "tblhosting",
        [
            "gamepanelioid" => $newServerId
        ],
        [
            "id" => $serviceId
        ]
    );
}

/**
 * Provision a new instance of a product/service.
 *
 * Attempt to provision a new instance of a given product/service. This is
 * called any time provisioning is requested inside of WHMCS. Depending upon the
 * configuration, this can be any of:
 * * When a new order is placed
 * * When an invoice for a new order is paid
 * * Upon manual request by an admin user
 *
 * @param array $params common module parameters
 *
 * @see http://docs.whmcs.com/Provisioning_Module_SDK_Parameters
 *
 * @return string "success" or an error message
 */
function gamepanelio_CreateAccount(array $params)
{
    gamepanelio_checkUpdateDatabase();

    try {
        $apiClient = gamepanelio_getApiClient($params);
        $moduleOptions = gamepanelio_getModuleOptions($params);

        $serviceId = $params['serviceid'];
        $clientDetails = $params['clientsdetails'];
        $serviceUsername = $moduleOptions['usernamePrefix'] . $clientDetails['firstname'] . $clientDetails['lastname'] . $clientDetails['id'];
        $servicePassword = $params['password'];
        $gpioUserId = null;

        try {
            $response = $apiClient->getUserByUsername($serviceUsername);
        } catch (\GamePanelio\Exception\ApiCommunicationException $e) {
            $response = $apiClient->createUser([
                'username' => $serviceUsername,
                'password' => $servicePassword,
                'email' => $clientDetails['email'],
                'fullName' => $clientDetails['fullname'],
            ]);
        }

        $gpioUserId = $response['id'];

        update_query(
            'tblhosting',
            [
                'username' => $serviceUsername
            ],
            [
                'id' => $serviceId
            ]
        );

        $serverName = $clientDetails['firstname'] . "'";
        if (substr($clientDetails['firstname'], -1) != "s") {
            $serverName .= "s";
        }
        $serverName .= " Game Server";

        $serverResponse = $apiClient->createServer([
            'name' => $serverName,
            'user' => $gpioUserId,
            'game' => $moduleOptions['game'],
            'plan' => $moduleOptions['plan'],
            'allocation' => $moduleOptions['allocation'],
        ]);

        gamepanelio_setServerIdForService($serviceId, $serverResponse['id']);
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'gamepanelio',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }

    return 'success';
}

/**
 * Suspend an instance of a product/service.
 *
 * Called when a suspension is requested. This is invoked automatically by WHMCS
 * when a product becomes overdue on payment or can be called manually by admin
 * user.
 *
 * @param array $params common module parameters
 *
 * @see http://docs.whmcs.com/Provisioning_Module_SDK_Parameters
 *
 * @return string "success" or an error message
 */
function gamepanelio_SuspendAccount(array $params)
{
    gamepanelio_checkUpdateDatabase();

    try {
        $serviceId = $params['serviceid'];
        $apiClient = gamepanelio_getApiClient($params);
        $serverId = gamepanelio_findServerIdForService($serviceId);

        $apiClient->updateServer(
            $serverId,
            [
                'suspended' => true,
            ]
        );
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'gamepanelio',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }

    return 'success';
}

/**
 * Un-suspend instance of a product/service.
 *
 * Called when an un-suspension is requested. This is invoked
 * automatically upon payment of an overdue invoice for a product, or
 * can be called manually by admin user.
 *
 * @param array $params common module parameters
 *
 * @see http://docs.whmcs.com/Provisioning_Module_SDK_Parameters
 *
 * @return string "success" or an error message
 */
function gamepanelio_UnsuspendAccount(array $params)
{
    gamepanelio_checkUpdateDatabase();

    try {
        $serviceId = $params['serviceid'];
        $apiClient = gamepanelio_getApiClient($params);
        $serverId = gamepanelio_findServerIdForService($serviceId);

        $apiClient->updateServer(
            $serverId,
            [
                'suspended' => false,
            ]
        );
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'gamepanelio',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }

    return 'success';
}

/**
 * Terminate instance of a product/service.
 *
 * Called when a termination is requested. This can be invoked automatically for
 * overdue products if enabled, or requested manually by an admin user.
 *
 * @param array $params common module parameters
 *
 * @see http://docs.whmcs.com/Provisioning_Module_SDK_Parameters
 *
 * @return string "success" or an error message
 */
function gamepanelio_TerminateAccount(array $params)
{
    gamepanelio_checkUpdateDatabase();

    try {
        $serviceId = $params['serviceid'];
        $apiClient = gamepanelio_getApiClient($params);
        $serverId = gamepanelio_findServerIdForService($serviceId);

        $apiClient->deleteServer($serverId);
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'gamepanelio',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }

    return 'success';
}

/**
 * Change the password for an instance of a product/service.
 *
 * Called when a password change is requested. This can occur either due to a
 * client requesting it via the client area or an admin requesting it from the
 * admin side.
 *
 * This option is only available to client end users when the product is in an
 * active status.
 *
 * @param array $params common module parameters
 *
 * @see http://docs.whmcs.com/Provisioning_Module_SDK_Parameters
 *
 * @return string "success" or an error message
 */
function gamepanelio_ChangePassword(array $params)
{
    gamepanelio_checkUpdateDatabase();

    try {
        $apiClient = gamepanelio_getApiClient($params);

        $response = $apiClient->getUserByUsername($params['username']);
        $gpioUserId = $response['id'];

        $apiClient->updateUser(
            $gpioUserId,
            [
                'password' => $params['password'],
            ]
        );
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'gamepanelio',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }

    return 'success';
}

/**
 * Upgrade or downgrade an instance of a product/service.
 *
 * Called to apply any change in product assignment or parameters. It
 * is called to provision upgrade or downgrade orders, as well as being
 * able to be invoked manually by an admin user.
 *
 * This same function is called for upgrades and downgrades of both
 * products and configurable options.
 *
 * @param array $params common module parameters
 *
 * @see http://docs.whmcs.com/Provisioning_Module_SDK_Parameters
 *
 * @return string "success" or an error message
 */
function gamepanelio_ChangePackage(array $params)
{
    gamepanelio_checkUpdateDatabase();

    try {
        $serviceId = $params['serviceid'];
        $apiClient = gamepanelio_getApiClient($params);
        $moduleOptions = gamepanelio_getModuleOptions($params);
        $serverId = gamepanelio_findServerIdForService($serviceId);

        $apiClient->updateServer(
            $serverId,
            [
                'game' => $moduleOptions['game'],
                'plan' => $moduleOptions['plan'],
            ]
        );
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'gamepanelio',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }

    return 'success';
}

/**
 * Test connection with the given server parameters.
 *
 * Allows an admin user to verify that an API connection can be
 * successfully made with the given configuration parameters for a
 * server.
 *
 * When defined in a module, a Test Connection button will appear
 * alongside the Server Type dropdown when adding or editing an
 * existing server.
 *
 * @param array $params common module parameters
 *
 * @see http://docs.whmcs.com/Provisioning_Module_SDK_Parameters
 *
 * @return array
 */
function gamepanelio_TestConnection(array $params)
{
    gamepanelio_checkUpdateDatabase();

    try {
        $apiClient = gamepanelio_getApiClient($params);
        $apiClient->getUser("me");

        $success = true;
        $errorMsg = '';
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'gamepanelio',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        $success = false;
        $errorMsg = $e->getMessage();
    }

    return [
        'success' => $success,
        'error' => $errorMsg,
    ];
}

/**
 * Admin services tab additional fields.
 *
 * Define additional rows and fields to be displayed in the admin area service
 * information and management page within the clients profile.
 *
 * Supports an unlimited number of additional field labels and content of any
 * type to output.
 *
 * @param array $params common module parameters
 *
 * @see http://docs.whmcs.com/Provisioning_Module_SDK_Parameters
 * @see gamepanelio_AdminServicesTabFieldsSave()
 *
 * @return array
 */
function gamepanelio_AdminServicesTabFields(array $params)
{
    gamepanelio_checkUpdateDatabase();

    try {
        $serviceId = $params["serviceid"];
        $serverId = gamepanelio_findServerIdForService($serviceId);

        return [
            'Server ID' => '<input type="hidden" name="gamepanelio_serverId_original" '
                . 'value="' . htmlspecialchars($serverId) . '" />'
                . '<input type="text" name="gamepanelio_serverId" '
                . 'value="' . htmlspecialchars($serverId) . '" size="25" />',
        ];
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'gamepanelio',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        // In an error condition, simply return no additional fields to display.
        return [];
    }
}

/**
 * Execute actions upon save of an instance of a product/service.
 *
 * Use to perform any required actions upon the submission of the admin area
 * product management form.
 *
 * It can also be used in conjunction with the AdminServicesTabFields function
 * to handle values submitted in any custom fields which is demonstrated here.
 *
 * @param array $params common module parameters
 *
 * @see http://docs.whmcs.com/Provisioning_Module_SDK_Parameters
 * @see gamepanelio_AdminServicesTabFields()
 */
function gamepanelio_AdminServicesTabFieldsSave(array $params)
{
    gamepanelio_checkUpdateDatabase();

    // Fetch form submission variables.
    $originalFieldValue = isset($_REQUEST['gamepanelio_serverId_original'])
        ? $_REQUEST['gamepanelio_serverId_original']
        : '';

    $newFieldValue = isset($_REQUEST['gamepanelio_serverId'])
        ? $_REQUEST['gamepanelio_serverId']
        : '';

    // Look for a change in value to avoid making unnecessary service calls.
    if ($originalFieldValue != $newFieldValue) {
        try {
            gamepanelio_setServerIdForService($params["serviceid"], $newFieldValue);
        } catch (Exception $e) {
            // Record the error in WHMCS's module log.
            logModuleCall(
                'gamepanelio',
                __FUNCTION__,
                $params,
                $e->getMessage(),
                $e->getTraceAsString()
            );

            // Error conditions are not supported in this operation.
        }
    }
}

/**
 * Client area output logic handling.
 *
 * This function is used to define module specific client area output. It should
 * return an array consisting of a template file and optional additional
 * template variables to make available to that template.
 *
 * The template file you return can be one of two types:
 *
 * * tabOverviewModuleOutputTemplate - The output of the template provided here
 *   will be displayed as part of the default product/service client area
 *   product overview page.
 *
 * * tabOverviewReplacementTemplate - Alternatively using this option allows you
 *   to entirely take control of the product/service overview page within the
 *   client area.
 *
 * Whichever option you choose, extra template variables are defined in the same
 * way. This demonstrates the use of the full replacement.
 *
 * Please Note: Using tabOverviewReplacementTemplate means you should display
 * the standard information such as pricing and billing details in your custom
 * template or they will not be visible to the end user.
 *
 * @param array $params common module parameters
 *
 * @see http://docs.whmcs.com/Provisioning_Module_SDK_Parameters
 *
 * @return array
 */
function gamepanelio_ClientArea(array $params)
{
    gamepanelio_checkUpdateDatabase();

    // TODO: Make a front-end for the users
    // Determine the requested action and set service call parameters based on
    // the action.
    $requestedAction = isset($_REQUEST['customAction']) ? $_REQUEST['customAction'] : '';

    if ($requestedAction == 'manage') {
        $serviceAction = 'get_usage';
        $templateFile = 'templates/manage.tpl';
    } else {
        $serviceAction = 'get_stats';
        $templateFile = 'templates/overview.tpl';
    }

    try {
        // Call the service's function based on the request action, using the
        // values provided by WHMCS in `$params`.
        $response = array();

        $extraVariable1 = 'abc';
        $extraVariable2 = '123';

        return array(
            'tabOverviewReplacementTemplate' => $templateFile,
            'templateVariables' => array(
                'extraVariable1' => $extraVariable1,
                'extraVariable2' => $extraVariable2,
            ),
        );
    } catch (Exception $e) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'gamepanelio',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        // In an error condition, display an error page.
        return array(
            'tabOverviewReplacementTemplate' => 'error.tpl',
            'templateVariables' => array(
                'usefulErrorHelper' => $e->getMessage(),
            ),
        );
    }
}
